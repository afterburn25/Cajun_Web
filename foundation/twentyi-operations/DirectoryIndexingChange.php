<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyI\Operations;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{ProviderOperation,TransportRequest};
use InvalidArgumentException;

/** Owner reference34e713 L2522-2529: exact setter only; no response acknowledgement contract. */
final class DirectoryIndexingChange
{
    public const OPERATION = 'packages.directoryindexing';

    /** Supplied typed fields are a request contract, never current account authority. */
    public static function params(array $params): array
    {
        $keys=array_keys($params);sort($keys);
        if($keys!==['packageId','value'])throw new InvalidArgumentException('Directory listing change requires exactly a package and boolean value.');
        if(!is_int($params['packageId'])||$params['packageId']<1||$params['packageId']>999999999999999999)throw new InvalidArgumentException('Select an allocated hosting package.');
        if(!is_bool($params['value']))throw new InvalidArgumentException('Directory listing change requires a boolean value.');
        // Return detached scalar values, including false; do not retain caller references.
        return ['packageId'=>(int)$params['packageId'],'value'=>(bool)$params['value']];
    }

    public static function request(ProviderOperation $operation): TransportRequest
    {
        if($operation->name!==self::OPERATION)throw new InvalidArgumentException('Unsupported Directory listing operation.');
        $params=self::params($operation->params);
        return new TransportRequest('POST','https://api.20i.com/package/'.$params['packageId'].'/web/directoryIndexing',
            ['Content-Type'=>'application/json','Accept'=>'application/json'],
            json_encode(['value'=>$params['value']],JSON_THROW_ON_ERROR),15.0);
    }

    /** Only this exact route and canonical one-property JSON body may enter transport. */
    public static function wire(TransportRequest $request): void
    {
        if($request->method!=='POST'||preg_match('~\Ahttps://api\.20i\.com/package/([1-9][0-9]{0,17})/web/directoryIndexing\z~',$request->url,$match)!==1
            ||!is_string($request->body)||strlen($request->body)>32)throw new InvalidArgumentException('Unsupported Directory listing request.');
        try{$body=json_decode($request->body,true,4,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new InvalidArgumentException('Unsupported Directory listing body.');}
        if(!is_array($body)||array_keys($body)!==['value'])throw new InvalidArgumentException('Unsupported Directory listing body.');
        $params=self::params(['packageId'=>(int)$match[1],'value'=>$body['value']]);
        $expected=self::request(new ProviderOperation(self::OPERATION,'wire-validation',$params));
        if($request->body!==$expected->body)throw new InvalidArgumentException('Unsupported Directory listing body.');
    }
}
