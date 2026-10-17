<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyI\Operations;
if(PHP_SAPI!=='cli'&&!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once __DIR__.'/MailboxCreate.php';
require_once __DIR__.'/../provider/TransportRequest.php';
use CajunWebPro\Foundation\Provider\TransportRequest;
use InvalidArgumentException;
/** Offline request contract only. No registered operation, transport admission or response classifier. */
final class ForwardingRequest {
    public static function parameters(array $p):array {
        $keys=array_keys($p);sort($keys);if($keys!==['domain','local','packageId','remote']||!is_int($p['packageId'])||$p['packageId']<1||$p['packageId']>4294967295)throw new InvalidArgumentException('Use one exact hosting package and standard forwarding address.');
        MailboxCreate::domain($p['domain']);MailboxCreate::local($p['local']);
        if(!is_string($p['remote'])||strlen($p['remote'])>254||substr_count($p['remote'],'@')!==1)throw new InvalidArgumentException('Enter one lowercase destination email address.');
        [$local,$domain]=explode('@',$p['remote']);MailboxCreate::local($local);MailboxCreate::domain($domain);
        if(strlen($p['local'].'@'.$p['domain'])>254||$p['remote']===$p['local'].'@'.$p['domain'])throw new InvalidArgumentException('Choose a destination different from the source address.');return $p;
    }
    /** Official article and both screenshots: docs.20i.com/api/20i-api-browser-console, inspected 2026-10-15. */
    public static function request(array $params):TransportRequest {
        $p=self::parameters($params);
        return new TransportRequest('POST','https://api.20i.com/package/'.$p['packageId'].'/email/'.$p['domain'],['Content-Type'=>'application/json','Accept'=>'application/json'],json_encode(['new'=>['forward'=>['local'=>$p['local'],'remote'=>$p['remote']]]],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),15.0);
    }
}
