<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyI\Operations;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{ProviderOperation,TransportRequest};
use InvalidArgumentException;

/** Official Cloud Hosting API guide: create a website on an existing server. */
final class CloudWebsiteCreate
{
    public const OPERATION = 'cloudsites.create';

    public static function params(array $params): array
    {
        $keys=array_keys($params);sort($keys);
        if($keys!==['cloudServerId','domain_name','extra_domain_names'])throw new InvalidArgumentException('Cloud website fields are incomplete or unsupported.');
        if(!is_int($params['cloudServerId'])||$params['cloudServerId']<1||$params['cloudServerId']>999999999999999999)throw new InvalidArgumentException('Select an allocated cloud server.');
        $domain=self::domain($params['domain_name']);$extra=$params['extra_domain_names'];
        if(!is_array($extra)||!array_is_list($extra)||count($extra)>20)throw new InvalidArgumentException('Use at most 20 additional domain names.');
        $seen=[$domain=>true];
        foreach($extra as $name){self::domain($name);if(isset($seen[$name]))throw new InvalidArgumentException('Domain names must be distinct.');$seen[$name]=true;}
        return ['cloudServerId'=>$params['cloudServerId'],'domain_name'=>$domain,'extra_domain_names'=>$extra];
    }

    public static function domain(mixed $value): string
    {
        if(!is_string($value)||strlen($value)>253||$value!==strtolower($value)||filter_var($value,FILTER_VALIDATE_IP)||!str_contains($value,'.'))throw new InvalidArgumentException('Enter a lowercase domain name without a URL or trailing dot.');
        foreach(explode('.',$value) as $label)if(!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D',$label))throw new InvalidArgumentException('Enter a valid ASCII domain name.');
        if(preg_match('/^[0-9]+$/D',substr($value,strrpos($value,'.')+1)))throw new InvalidArgumentException('Enter a domain name, not an address.');
        return $value;
    }

    public static function request(ProviderOperation $operation): TransportRequest
    {
        $params=self::params($operation->params);
        $body=json_encode(['domain_name'=>$params['domain_name'],'extra_domain_names'=>$params['extra_domain_names']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        return new TransportRequest('POST','https://api.20i.com/cloud_server/'.$params['cloudServerId'].'/addWeb',['Content-Type'=>'application/json','Accept'=>'application/json'],$body,15.0);
    }

    public static function wire(TransportRequest $request): void
    {
        if($request->method!=='POST'||!preg_match('~^https://api\.20i\.com/cloud_server/([1-9][0-9]{0,17})/addWeb$~D',$request->url,$match)||!is_string($request->body)||strlen($request->body)>8192)throw new InvalidArgumentException('Unsupported cloud website request.');
        try{$body=json_decode($request->body,true,8,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new InvalidArgumentException('Unsupported cloud website body.');}
        if(!is_array($body))throw new InvalidArgumentException('Unsupported cloud website body.');
        $params=['cloudServerId'=>(int)$match[1]]+$body;
        $expected=self::request(new ProviderOperation(self::OPERATION,'wire-validation',$params));
        if($request->body!==$expected->body)throw new InvalidArgumentException('Unsupported cloud website body.');
    }
}
