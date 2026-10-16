<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIDomainContracts;
if(PHP_SAPI!=='cli'&&!defined('CWP_BOOT')){http_response_code(404);exit;}
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyIInventory\InventoryDomain;
/** Retained20i SDK1.0.6 test-real/03-get-domain.php establishes this date-only read.
 * No response envelope, expiry, nameservers, pricing or write contract is inferred. */
final class DomainContract
{
    public const OPERATION='domain.contract';
    public const PREFIX='https://api.20i.com/domain/';
    public const MAX_BODY_BYTES=65536;
    public static function request(string $name):TransportRequest
    {
        InventoryDomain::fromItem((object)['id'=>1,'name'=>$name]);
        return new TransportRequest('GET',self::PREFIX.$name.'/contract',['Accept'=>'application/json'],null,15.0);
    }
    public static function acceptsUrl(string $url):bool
    {
        if(!str_starts_with($url,self::PREFIX)||!str_ends_with($url,'/contract'))return false;
        $name=substr($url,strlen(self::PREFIX),-strlen('/contract'));
        try{return self::request($name)->url===$url;}catch(\InvalidArgumentException){return false;}
    }
    public static function project(TransportResponse $response,DomainContractBinding $binding,int $at):DomainContractObservation
    {
        $fail=static fn(string $reason)=>new DomainContractObservation($binding,'unavailable',null,$reason,$at);
        if($response->status!==200)return $fail(match(true){in_array($response->status,[401,403],true)=>'authentication',$response->status===429=>'rate_limited',$response->status>=500=>'remote_error',default=>'unexpected_status'});
        if(strlen($response->body)>self::MAX_BODY_BYTES)return $fail('response_limit');
        try{$data=json_decode($response->body,false,8,JSON_THROW_ON_ERROR);}catch(\JsonException){return $fail('malformed_body');}
        if(!$data instanceof \stdClass)return $fail('unexpected_shape');
        if(!property_exists($data,'renewalDate'))return $fail('date_missing');
        if($data->renewalDate===null)return $fail('date_null');
        if(!is_string($data->renewalDate))return $fail('date_type');
        if(!DomainContractObservation::validDate($data->renewalDate))return $fail('date_invalid');
        return new DomainContractObservation($binding,'complete',$data->renewalDate,'',$at);
    }
    public static function read(HttpTransport $transport,#[\SensitiveParameter] ProviderCredential $credential,DomainContractBinding $binding,int $at):DomainContractObservation
    {
        if($credential->providerId!=='twenty-i'||$credential->accountIdentity!==$binding->data['profile_id']||$at<0)throw new \InvalidArgumentException('Invalid domain read account.');
        try{$secret=$credential->secret();}catch(\Throwable){throw new \InvalidArgumentException('Provider credentials are unavailable.');}
        if($secret===''||strlen($secret)>4096||preg_match('/[\x00-\x1f\x7f]/',$secret))throw new \InvalidArgumentException('Provider credentials are unavailable.');
        $descriptor=self::request($binding->data['domain']);$request=new TransportRequest('GET',$descriptor->url,['Authorization'=>'Bearer '.base64_encode($secret),'Accept'=>'application/json'],null,15.0);unset($descriptor);
        try{
            try{$response=$transport->send($request);}catch(TransportException $e){return new DomainContractObservation($binding,'unavailable',null,'transport_'.$e->kind,$at);}catch(\Throwable){return new DomainContractObservation($binding,'unavailable',null,'transport_unknown',$at);}
            $observation=self::project($response,$binding,$at);
            // Inspect decoded text while credentials remain local; an echo cannot become a saved date.
            if($observation->state==='complete'&&(str_contains($observation->renewalDate,$secret)||str_contains($observation->renewalDate,base64_encode($secret))))return new DomainContractObservation($binding,'unavailable',null,'date_invalid',$at);
            return $observation;
        }finally{unset($secret,$request);}
    }
}
