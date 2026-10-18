<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIPackageWordpressInstalled;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};

/** Owner34e713 lines1741-1747 plus retained SDK direct JSON decoding.
 * True is documented as installed; false is retained only as the reported boolean.
 * Null/other shapes are not false. No absence, version, security or health inference. */
final class PackageWordpressInstalled
{
    public const OPERATION='package.wordpress-installed';
    public const URL_PREFIX='https://api.20i.com/package/';
    public const URL_SUFFIX='/web/wordpressIsInstalled';
    /** Defensive local size policy, not a documented provider capacity. */
    public const MAX_BODY_BYTES=4096;

    public static function request(PackageWordpressInstalledBinding $binding):TransportRequest
    {
        $binding=$binding->validated();
        return new TransportRequest('GET',self::URL_PREFIX.$binding->data['package_id'].self::URL_SUFFIX,['Accept'=>'application/json'],null,15.0);
    }

    public static function project(TransportResponse $response,PackageWordpressInstalledBinding $binding,int $at):PackageWordpressInstalledObservation
    {
        $binding=$binding->validated();
        if($at<0)throw new \InvalidArgumentException('Invalid WordPress installation observation date.');
        $fail=static fn(string $reason)=>new PackageWordpressInstalledObservation($binding,$at,'unavailable',$reason,null);
        if($response->status!==200)return $fail(match(true){in_array($response->status,[401,403],true)=>'authentication',$response->status===429=>'rate_limited',$response->status>=500=>'remote_error',default=>'unexpected_status'});
        if(strlen($response->body)>self::MAX_BODY_BYTES)return $fail('response_limit');
        try{$value=json_decode($response->body,false,4,JSON_THROW_ON_ERROR);}catch(\JsonException){return $fail('malformed_body');}
        if($value===null)return $fail('value_null');
        if(!is_bool($value))return $fail('unexpected_shape');
        return new PackageWordpressInstalledObservation($binding,$at,'complete','',$value);
    }

    /** Injected transport read; current application authority belongs to the shared executor. */
    public static function read(HttpTransport $transport,#[\SensitiveParameter] ProviderCredential $credential,PackageWordpressInstalledBinding $binding,int $at):PackageWordpressInstalledObservation
    {
        $binding=$binding->validated();
        if($credential->providerId!=='twenty-i'||$credential->accountIdentity!==$binding->data['profile_id']||$at<0)throw new \InvalidArgumentException('WordPress installation observation account does not match.');
        try{$secret=$credential->secret();}catch(\Throwable){throw new \InvalidArgumentException('Provider credentials are unavailable.');}
        if($secret===''||strlen($secret)>4096||preg_match('/[\x00-\x1f\x7f]/',$secret))throw new \InvalidArgumentException('Provider credentials are unavailable.');
        $descriptor=self::request($binding);
        $request=new TransportRequest('GET',$descriptor->url,['Authorization'=>'Bearer '.base64_encode($secret),'Accept'=>'application/json'],null,15.0);
        try{
            try{$response=$transport->send($request);}catch(TransportException $e){return new PackageWordpressInstalledObservation($binding,$at,'unavailable','transport_'.$e->kind,null);}catch(\Throwable){return new PackageWordpressInstalledObservation($binding,$at,'unavailable','transport_unknown',null);}
            $observation=self::project($response,$binding,$at);
            if($observation->state==='complete'){
                // Also handle locally accepted synthetic credentials exactly matching a boolean token.
                $token=$observation->value?'true':'false';
                if(hash_equals($token,$secret)||hash_equals($token,base64_encode($secret)))return new PackageWordpressInstalledObservation($binding,$at,'unavailable','invalid_value',null);
            }
            return $observation;
        }finally{unset($secret,$request);}
    }
}
