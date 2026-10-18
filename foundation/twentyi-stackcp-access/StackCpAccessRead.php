<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIStackCpAccess;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};

/** Sensitive owner-only observation subset; never token/session creation or grant editing. */
final class StackCpAccessRead {
    public static function request(StackCpAccessBinding $binding):TransportRequest {
        $b=$binding->validated()->data;$path=$b['operation']===StackCpAccessBinding::MEMBERSHIP
            ?'/package/'.$b['package_id'].'/stackUserList':'/reseller/*/stackUser/'.substr($b['stack_identity'],11).'/grants';
        return new TransportRequest('GET','https://api.20i.com'.$path,['Accept'=>'application/json'],null,15.0);
    }
    public static function project(TransportResponse $response,StackCpAccessBinding $binding,int $at):StackCpAccessObservation {
        $binding=$binding->validated();$fail=static fn(string $reason)=>new StackCpAccessObservation($binding,$at,'unavailable',$reason,null);
        if ($response->status!==200) return $fail(match(true){in_array($response->status,[401,403],true)=>'authentication',$response->status===429=>'rate_limited',$response->status>=500=>'remote_error',default=>'unexpected_status'});
        if (strlen($response->body)>StackCpAccessJson::MAX_BYTES) return $fail('response_limit');
        try {$rows=StackCpAccessJson::decode($response->body);}catch(\JsonException|\InvalidArgumentException) {return $fail('malformed_body');}
        if (!is_array($rows)||!array_is_list($rows)) return $fail('unexpected_shape');$values=[];
        foreach ($rows as $row) {
            if ($binding->data['operation']===StackCpAccessBinding::MEMBERSHIP) {
                if (!$row instanceof \stdClass||!property_exists($row,'identity')||!is_string($row->identity)) return $fail('unexpected_shape');$values[]=$row->identity;
            } else {if (!is_string($row)) return $fail('unexpected_shape');$values[]=$row;}
        }
        try {return new StackCpAccessObservation($binding,$at,'complete','',$values);}catch(\InvalidArgumentException) {return $fail('invalid_value');}
    }
    public static function read(HttpTransport $transport,#[\SensitiveParameter] ProviderCredential $credential,StackCpAccessBinding $binding,int $at):StackCpAccessObservation {
        $binding=$binding->validated();if ($credential->providerId!=='twenty-i'||$credential->accountIdentity!==$binding->data['profile_id']||$at<0) throw new \InvalidArgumentException('StackCP observation account does not match.');
        try {$secret=$credential->secret();}catch(\Throwable) {throw new \InvalidArgumentException('Provider credentials are unavailable.');}
        if ($secret===''||strlen($secret)>4096||preg_match('//u',$secret)!==1||preg_match('/[\x00-\x1f\x7f]/',$secret)) throw new \InvalidArgumentException('Provider credentials are unavailable.');
        $request=self::request($binding);$request=new TransportRequest('GET',$request->url,['Authorization'=>'Bearer '.base64_encode($secret),'Accept'=>'application/json'],null,15.0);
        try {
            try {$response=$transport->send($request);}catch(TransportException $e) {$reason=in_array($e->kind,['dns','tls','connect','timeout','network','protocol'],true)?'transport_'.$e->kind:'transport_unknown';return new StackCpAccessObservation($binding,$at,'unavailable',$reason,null);}catch(\Throwable) {return new StackCpAccessObservation($binding,$at,'unavailable','transport_unknown',null);}
            $observation=self::project($response,$binding,$at);
            if ($observation->state==='complete') foreach ($observation->values as $value) if (str_contains($value,$secret)||str_contains($value,base64_encode($secret))) return new StackCpAccessObservation($binding,$at,'unavailable','invalid_value',null);
            return $observation;
        } finally {unset($secret,$request,$response);}
    }
}
