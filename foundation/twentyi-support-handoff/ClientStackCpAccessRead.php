<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyISupportHandoff;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/ClientStackCpAccessTransport.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyIStackCpAccess\{StackCpAccessBinding,StackCpAccessJson};

/** Closed read failure; never retain provider text, a response body, or credentials. */
final class ClientStackCpAccessFailure extends \RuntimeException
{
    public function __construct(public readonly string $phase,public readonly string $reason)
    {
        if (!in_array($phase,['membership','grants'],true)
            || !in_array($reason,['dns','credentials','access_changed','transport','http_status','authentication',
                'rate_limited','upstream_unavailable','body_limit','malformed_body','shape_unsupported','credential_echo'],true)) {
            throw new \InvalidArgumentException('Invalid client StackCP access outcome.');
        }
        parent::__construct('Client StackCP access '.$phase.': '.$reason.'.');
    }
}

/** Truthful client read: no owner binding, owner session, or caller-supplied URL. */
final class ClientStackCpAccessRead
{
    public static function project(string $phase,int $packageId,string $identity,TransportResponse $response,int $at): array
    {
        ClientStackCpAccessTransport::url($phase,$packageId,$identity);
        if ($at<1 || $at>2147483647) throw new \InvalidArgumentException('Invalid access observation time.');
        $fail=static fn(string $reason)=>throw new ClientStackCpAccessFailure($phase,$reason);
        if ($response->status!==200) $fail(match (true) {
            in_array($response->status,[401,403],true)=>'authentication',
            $response->status===429=>'rate_limited',$response->status>=500=>'upstream_unavailable',default=>'http_status',
        });
        if (strlen($response->body)>StackCpAccessJson::MAX_BYTES) $fail('body_limit');
        try { $rows=StackCpAccessJson::decode($response->body); }
        catch (\InvalidArgumentException|\JsonException) { $fail('malformed_body'); }
        if (!is_array($rows)||!array_is_list($rows)||count($rows)>1000) $fail('shape_unsupported');
        $values=[];$seen=[];
        foreach ($rows as $row) {
            if ($phase==='membership') {
                if (!$row instanceof \stdClass || !isset($row->identity) || !is_string($row->identity)) $fail('shape_unsupported');
                $row=$row->identity;
            }
            try { $value=$phase==='membership'?StackCpAccessBinding::identity($row):StackCpAccessBinding::tuple($row); }
            catch (\InvalidArgumentException) { $fail('shape_unsupported'); }
            if (isset($seen[$value])) $fail('shape_unsupported');
            $seen[$value]=true;$values[]=$value;
        }
        if ($phase==='membership'?!in_array($identity,$values,true):$values!==['package:'.$packageId]) $fail('access_changed');
        return ['values'=>$values,'observed_epoch'=>$at];
    }

    /** Journal the started phase before calling. Internal factories are never HTTP inputs. */
    public static function read(string $phase,int $packageId,string $identity,callable $credentials,callable $authorize,
        ?callable $lookup=null,?callable $transportFactory=null): array
    {
        $url=ClientStackCpAccessTransport::url($phase,$packageId,$identity);
        $guard=static function()use($authorize,$phase):void {
            try {$authorize();} catch (\PDOException $e) {throw $e;}
            catch (\Throwable) {throw new ClientStackCpAccessFailure($phase,'access_changed');}
        };
        $guard();
        try {$address=ClientStackCpAccessTransport::resolve($lookup);}
        catch (\Throwable) {throw new ClientStackCpAccessFailure($phase,'dns');}
        $guard();
        $transport=$transportFactory===null?new ClientStackCpAccessTransport($phase,$packageId,$identity,$address,$guard):$transportFactory($phase,$address,$guard);
        if (!$transport instanceof HttpTransport) throw new \LogicException('Invalid client StackCP read transport.');
        $guard();
        try {$secret=$credentials('general-api-key');} catch (\PDOException $e) {throw $e;}
        catch (\Throwable) {throw new ClientStackCpAccessFailure($phase,'credentials');}
        if (!is_string($secret)||$secret===''||strlen($secret)>4096||preg_match('//u',$secret)!==1||preg_match('/[\x00-\x20\x7f]/',$secret)) throw new ClientStackCpAccessFailure($phase,'credentials');
        try {
            $guard();
            $request=new TransportRequest('GET',$url,['Authorization'=>'Bearer '.base64_encode($secret),'Accept'=>'application/json'],null,15);
            try {$response=$transport->send($request);}
            catch (\PDOException|ClientStackCpAccessFailure $e) {throw $e;}
            catch (\Throwable) {throw new ClientStackCpAccessFailure($phase,'transport');}
            $guard();
            // The full bounded body is checked, including ignored membership metadata.
            if (str_contains($response->body,$secret)||str_contains($response->body,base64_encode($secret))) throw new ClientStackCpAccessFailure($phase,'credential_echo');
            $observed=self::project($phase,$packageId,$identity,$response,time());
            // JSON escapes can hide an echo in the raw bytes. Never journal an
            // opaque key (or its base64 form) inside a normalized retained value.
            foreach ($observed['values'] as $value) if (str_contains($value,$secret)||str_contains($value,base64_encode($secret))) throw new ClientStackCpAccessFailure($phase,'credential_echo');
            return $observed;
        } finally {unset($secret,$request,$response);}
    }
}
