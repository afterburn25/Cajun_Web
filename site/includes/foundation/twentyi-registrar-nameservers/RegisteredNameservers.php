<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIRegistrarNameservers;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/RegisteredNameserverObservation.php';
require_once __DIR__.'/../twentyi-operations/RegistrarNameserverChange.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyI\Operations\{NameserverChange,RegistrarNameserverChange};

/** SDK1.0.6 README: GET /domain/{id}/nameservers -> result, used whole as old-ns.
 * Package-route delta semantics are not a contract for this registrar route.
 * This reader never substitutes a package ID, domain name or package-domain path.
 */
final class RegisteredNameservers
{
    public const OPERATION='registrar.nameservers.read';
    public const MAX_BODY_BYTES=65536;
    public static function request(mixed $domainId): TransportRequest
    {
        return new TransportRequest('GET','https://api.20i.com/domain/'.RegistrarNameserverChange::id($domainId).'/nameservers',['Accept'=>'application/json'],null,15.0);
    }
    public static function acceptsUrl(string $url): bool
    {
        return preg_match('~\Ahttps://api\.20i\.com/domain/[1-9][0-9]{0,17}/nameservers\z~',$url)===1;
    }
    public static function project(TransportResponse $response,RegisteredNameserverBinding $binding,int $at): RegisteredNameserverObservation
    {
        $fail=static fn(string $reason)=>new RegisteredNameserverObservation($binding,'unavailable',null,$reason,$at);
        if ($response->status!==200) return $fail(match(true){in_array($response->status,[401,403],true)=>'authentication',$response->status===429=>'rate_limited',$response->status>=500=>'remote_error',default=>'unexpected_status'});
        if (strlen($response->body)>self::MAX_BODY_BYTES) return $fail('response_limit');
        try { $data=json_decode($response->body,false,8,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return $fail('malformed_body'); }
        if (!$data instanceof \stdClass || array_keys(get_object_vars($data))!==['result'] || !is_array($data->result) || !array_is_list($data->result)) return $fail('unexpected_shape');
        // After JSON validation, remove string tokens and require one object key.
        // This rejects duplicate result keys without pretending json_decode detects them.
        $structure=preg_replace('/"(?:[^"\\\\]|\\\\.)*"/s','""',$response->body);
        if ($structure===null || substr_count($structure,':')!==1 || substr_count($structure,'{')!==1) return $fail('unexpected_shape');
        try { $names=NameserverChange::observed($data->result); }
        catch (\InvalidArgumentException) { return $fail('invalid_nameservers'); }
        return new RegisteredNameserverObservation($binding,'complete',$names,'',$at);
    }
    public static function read(HttpTransport $transport,#[\SensitiveParameter] ProviderCredential $credential,RegisteredNameserverBinding $binding,int $at): RegisteredNameserverObservation
    {
        if ($credential->providerId!=='twenty-i' || $credential->accountIdentity!==$binding->data['profile_id'] || $at<0) throw new \InvalidArgumentException('Invalid registrar nameserver account.');
        $secret=$credential->secret();
        if ($secret==='' || strlen($secret)>4096 || preg_match('/[\x00-\x1f\x7f]/',$secret)) throw new \InvalidArgumentException('Provider credentials are unavailable.');
        $descriptor=self::request($binding->data['domain_id']);
        $request=new TransportRequest('GET',$descriptor->url,['Authorization'=>'Bearer '.base64_encode($secret),'Accept'=>'application/json'],null,15.0);
        try {
            try { $response=$transport->send($request); }
            catch (TransportException $e) { return new RegisteredNameserverObservation($binding,'unavailable',null,'transport_'.$e->kind,$at); }
            $observed=self::project($response,$binding,$at);
            if ($observed->state==='complete') {
                // Inspect decoded raw values too: canonical lowercase/dot normalization
                // must not hide a literal or encoded credential echo.
                $raw=json_decode($response->body,false,8,JSON_THROW_ON_ERROR)->result;
                foreach (array_merge($raw,$observed->nameservers) as $name) {
                    if (str_contains($name,$secret) || str_contains($name,base64_encode($secret))) return new RegisteredNameserverObservation($binding,'unavailable',null,'invalid_nameservers',$at);
                }
            }
            return $observed;
        } finally { unset($secret,$request,$response,$raw); }
    }
}
