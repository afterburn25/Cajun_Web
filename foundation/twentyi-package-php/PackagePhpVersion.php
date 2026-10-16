<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIPackagePhp;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};

/** Owner reference2484-2490: the active virtual-host PHP version, not an available-version list. */
final class PackagePhpVersion
{
    public const OPERATION='package.php-version';
    public const URL_PREFIX='https://api.20i.com/package/';
    public const URL_SUFFIX='/web/phpVersion';
    public const MAX_BODY_BYTES=4096;
    public const MAX_VALUE_BYTES=128;

    /** Local defensive text bounds, not provider version or capacity guarantees. */
    public static function value(mixed $value): string
    {
        if(!is_string($value)||trim($value)===''||strlen($value)>self::MAX_VALUE_BYTES||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value))throw new \InvalidArgumentException('Unsupported PHP version value.');
        return $value;
    }

    public static function request(PackagePhpVersionBinding $binding): TransportRequest
    {
        return new TransportRequest('GET',self::URL_PREFIX.$binding->data['package_id'].self::URL_SUFFIX,['Accept'=>'application/json'],null,15.0);
    }

    public static function project(TransportResponse $response,PackagePhpVersionBinding $binding,int $at): PackagePhpVersionObservation
    {
        $unavailable=static fn(string $reason)=>new PackagePhpVersionObservation($binding,$at,'unavailable',$reason,null);
        if($response->status!==200)return $unavailable(match(true){in_array($response->status,[401,403],true)=>'authentication',$response->status===429=>'rate_limited',$response->status>=500=>'remote_error',default=>'unexpected_status'});
        if(strlen($response->body)>self::MAX_BODY_BYTES)return $unavailable('response_limit');
        try{$value=json_decode($response->body,false,4,JSON_THROW_ON_ERROR);}catch(\JsonException){return $unavailable('malformed_body');}
        if(!is_string($value))return $unavailable('unexpected_shape');
        try{self::value($value);}catch(\InvalidArgumentException){return $unavailable('invalid_value');}
        return new PackagePhpVersionObservation($binding,$at,'complete','',$value);
    }

    public static function read(HttpTransport $transport,#[\SensitiveParameter] ProviderCredential $credential,PackagePhpVersionBinding $binding,int $at): PackagePhpVersionObservation
    {
        if($credential->providerId!=='twenty-i'||$credential->accountIdentity!==$binding->data['profile_id']||$at<0)throw new \InvalidArgumentException('PHP observation account does not match.');
        $secret=$credential->secret();
        if($secret===''||strlen($secret)>4096||preg_match('/[\x00-\x1f\x7f]/',$secret))throw new \InvalidArgumentException('Provider credentials are unavailable.');
        $descriptor=self::request($binding);
        $request=new TransportRequest('GET',$descriptor->url,['Authorization'=>'Bearer '.base64_encode($secret),'Accept'=>'application/json'],null,15.0);
        try{
            $response=$transport->send($request);$observation=self::project($response,$binding,$at);
            // Check decoded text while credentials remain local; never persist an echoed secret.
            if($observation->state==='complete'&&(str_contains($observation->value,$secret)||str_contains($observation->value,base64_encode($secret))))return new PackagePhpVersionObservation($binding,$at,'unavailable','invalid_value',null);
            return $observation;
        }
        catch(TransportException $e){return new PackagePhpVersionObservation($binding,$at,'unavailable','transport_'.$e->kind,null);}
        finally{unset($secret,$request);}
    }
}
