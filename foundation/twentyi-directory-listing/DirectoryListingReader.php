<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIDirectoryListing;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};

/** Owner34e713 lines2515-2521 documents whether Options +Indexes is enabled.
 * Bare JSON booleans are the accepted subset, not a complete wire-schema claim.
 * Null means the request could not complete; it is never a disabled setting. */
final class DirectoryListingReader
{
    public const OPERATION='package.directory-indexing';
    public const URL_PREFIX='https://api.20i.com/package/';
    public const URL_SUFFIX='/web/directoryIndexing';
    /** Defensive local size policy, not a documented provider capacity. */
    public const MAX_BODY_BYTES=128;

    public static function request(DirectoryListingBinding $binding):TransportRequest
    {
        $binding=$binding->validated();
        return new TransportRequest('GET',self::URL_PREFIX.$binding->data['package_id'].self::URL_SUFFIX,['Accept'=>'application/json'],null,15.0);
    }

    public static function project(TransportResponse $response,DirectoryListingBinding $binding,int $at):DirectoryListingObservation
    {
        $binding=$binding->validated();
        if($at<0)throw new \InvalidArgumentException('Invalid Directory listing observation date.');
        $fail=static fn(string $reason)=>new DirectoryListingObservation($binding,$at,'unavailable',$reason,null);
        if($response->status!==200)return $fail(match(true){in_array($response->status,[401,403],true)=>'authentication',$response->status===429=>'rate_limited',$response->status>=500=>'remote_error',default=>'unexpected_status'});
        if(strlen($response->body)>self::MAX_BODY_BYTES)return $fail('response_limit');
        try{$value=json_decode($response->body,false,4,JSON_THROW_ON_ERROR);}catch(\JsonException){return $fail('malformed_body');}
        if($value===null)return $fail('value_null');
        if(!is_bool($value))return $fail('unexpected_shape');
        return new DirectoryListingObservation($binding,$at,'complete','',$value);
    }

    /** Explicit injected read only; construction and projection never trigger network access. */
    public static function read(HttpTransport $transport,#[\SensitiveParameter] ProviderCredential $credential,DirectoryListingBinding $binding,int $at):DirectoryListingObservation
    {
        $binding=$binding->validated();
        if($credential->providerId!=='twenty-i'||$credential->accountIdentity!==$binding->data['profile_id']||$at<0)throw new \InvalidArgumentException('Directory listing observation account does not match.');
        try{$secret=$credential->secret();}catch(\Throwable){throw new \InvalidArgumentException('Provider credentials are unavailable.');}
        if($secret===''||strlen($secret)>4096||preg_match('/[\x00-\x1f\x7f]/',$secret))throw new \InvalidArgumentException('Provider credentials are unavailable.');
        $descriptor=self::request($binding);
        $request=new TransportRequest('GET',$descriptor->url,['Authorization'=>'Bearer '.base64_encode($secret),'Accept'=>'application/json'],null,15.0);
        try{
            try{$response=$transport->send($request);}catch(TransportException $e){return new DirectoryListingObservation($binding,$at,'unavailable','transport_'.$e->kind,null);}catch(\Throwable){return new DirectoryListingObservation($binding,$at,'unavailable','transport_unknown',null);}
            $observation=self::project($response,$binding,$at);
            if($observation->state==='complete'){
                // Also handle locally accepted synthetic credentials exactly matching a boolean token.
                $token=$observation->value?'true':'false';
                if(hash_equals($token,$secret)||hash_equals($token,base64_encode($secret)))return new DirectoryListingObservation($binding,$at,'unavailable','invalid_value',null);
            }
            return $observation;
        }finally{unset($secret,$request);}
    }
}
