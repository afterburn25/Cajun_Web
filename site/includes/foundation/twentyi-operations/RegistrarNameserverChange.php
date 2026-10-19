<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyI\Operations;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{ProviderOperation,TransportRequest};
require_once __DIR__.'/NameserverChange.php';

/** Retained SDK README: whole desired ns and whole observed old-ns for a /domain list ID.
 * Restricted to nonempty disjoint replacements. Package-route delta semantics do not apply.
 * Native admission must prove old-ns equals the complete current retained observation.
 */
final class RegistrarNameserverChange
{
    public const OPERATION='registrar.nameservers';
    public static function id(mixed $id): int
    {
        if (!is_int($id) || $id<1 || $id>999999999999999999) throw new \InvalidArgumentException('Select an allocated registrar domain ID.');
        return $id;
    }
    public static function params(array $params): array
    {
        $keys=array_keys($params);sort($keys);
        if ($keys!==['domainId','ns','old-ns']) throw new \InvalidArgumentException('Use the exact registrar nameserver fields.');
        $id=self::id($params['domainId']);
        $desired=NameserverChange::observed($params['ns']);
        $current=NameserverChange::observed($params['old-ns']);
        if ($desired===[] || $current===[] || array_intersect($desired,$current)) {
            throw new \InvalidArgumentException('Use complete nonempty disjoint registrar nameserver sets.');
        }
        return ['domainId'=>$id,'ns'=>$desired,'old-ns'=>$current];
    }
    public static function request(ProviderOperation $operation): TransportRequest
    {
        if ($operation->name!==self::OPERATION) throw new \InvalidArgumentException('Unsupported registrar nameserver operation.');
        $p=self::params($operation->params);
        $body=json_encode(['ns'=>$p['ns'],'old-ns'=>$p['old-ns']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        if (strlen($body)>8192) throw new \InvalidArgumentException('Nameserver request exceeds the local byte bound.');
        return new TransportRequest('POST','https://api.20i.com/domain/'.$p['domainId'].'/nameservers',
            ['Content-Type'=>'application/json','Accept'=>'application/json'],$body,15.0);
    }
    /** Exact canonical request; duplicate keys, aliases and widened routes are refused. */
    public static function wire(TransportRequest $request): void
    {
        if ($request->method!=='POST' || preg_match('~\Ahttps://api\.20i\.com/domain/([1-9][0-9]{0,17})/nameservers\z~',$request->url,$m)!==1
            || !is_string($request->body) || strlen($request->body)>8192) throw new \InvalidArgumentException('Unsupported registrar nameserver request.');
        try { $body=json_decode($request->body,true,4,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new \InvalidArgumentException('Unsupported registrar nameserver body.'); }
        if (!is_array($body) || array_keys($body)!==['ns','old-ns']) throw new \InvalidArgumentException('Unsupported registrar nameserver body.');
        $expected=self::request(new ProviderOperation(self::OPERATION,'wire-validation',['domainId'=>(int)$m[1]]+$body));
        if ($request->body!==$expected->body) throw new \InvalidArgumentException('Unsupported registrar nameserver body.');
    }
}
