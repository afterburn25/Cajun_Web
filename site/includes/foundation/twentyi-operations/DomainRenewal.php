<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyI\Operations;
if(PHP_SAPI!=='cli'&&!defined('CWP_BOOT')){http_response_code(404);exit;}
use CajunWebPro\Foundation\Provider\{ProviderOperation,TransportRequest,TransportResponse,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\InventoryDomain;

/** Owner reference34e713 L239–249; SDK696a REST184–200 directly decodes JSON. */
final class DomainRenewal {
    public const OPERATION='domains.renew';
    public const URL='https://api.20i.com/reseller/*/renewDomain';
    /** The 1–10 bound is local intake policy, never registry eligibility. */
    public static function params(array $p):array {
        $keys=array_keys($p);sort($keys);
        if($keys!==['name','renewPrivacy','resellerId','years']||$p['resellerId']!=='*'||!is_string($p['name'])||!is_int($p['years'])||$p['years']<1||$p['years']>10||!is_bool($p['renewPrivacy']))throw new \InvalidArgumentException('Review the exact renewal name, years and privacy choice.');
        $domain=InventoryDomain::fromItem((object)['id'=>1,'name'=>$p['name']]);
        return ['resellerId'=>'*','name'=>$domain->name,'years'=>(int)$p['years'],'renewPrivacy'=>(bool)$p['renewPrivacy']];
    }
    public static function request(ProviderOperation $operation):TransportRequest {
        if($operation->name!==self::OPERATION)throw new \InvalidArgumentException('Unsupported renewal operation.');
        $p=self::params($operation->params);
        return new TransportRequest('POST',self::URL,['Content-Type'=>'application/json','Accept'=>'application/json'],json_encode(['name'=>$p['name'],'years'=>$p['years'],'renewPrivacy'=>$p['renewPrivacy']],JSON_THROW_ON_ERROR),15.0);
    }
    public static function wire(TransportRequest $request):void {
        if($request->method!=='POST'||$request->url!==self::URL||!is_string($request->body)||strlen($request->body)>512)throw new \InvalidArgumentException('Unsupported renewal request.');
        try{$d=json_decode($request->body,true,4,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new \InvalidArgumentException('Unsupported renewal body.');}
        if(!is_array($d)||array_keys($d)!==['name','years','renewPrivacy'])throw new \InvalidArgumentException('Unsupported renewal body.');
        $expected=self::request(new ProviderOperation(self::OPERATION,'wire-review',['resellerId'=>'*']+$d));
        if($request->body!==$expected->body)throw new \InvalidArgumentException('Noncanonical renewal body.');
    }
    /** Only this operation has the documented boolean result. False never proves no debit. */
    public static function result(TransportResponse $response,#[\SensitiveParameter] string $credential):OperationResult {
        if($response->status!==200)return TwentyIResultClassifier::fromResponse(self::OPERATION,$response);
        if(strlen($response->body)>65536)return OperationResult::uncertain(self::OPERATION,'response_limit');
        try{$value=json_decode($response->body,false,4,JSON_THROW_ON_ERROR);}catch(\JsonException){return OperationResult::uncertain(self::OPERATION,'response_shape');}
        if(!is_bool($value))return OperationResult::uncertain(self::OPERATION,'response_unverified');
        $scalar=$value?'true':'false';
        if($credential!==''&&(str_contains($scalar,$credential)||str_contains($scalar,base64_encode($credential))))return OperationResult::uncertain(self::OPERATION,'response_unverified');
        return $value?OperationResult::succeeded(self::OPERATION,'20i reported the renewal successful.',null,'renewal_reported_success'):OperationResult::failed(self::OPERATION,'20i reported the renewal failed.','renewal_reported_failure');
    }
}
