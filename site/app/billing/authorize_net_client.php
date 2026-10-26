<?php
/** Authorize.net JSON API client, server secrets kept outside the repository. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function software_anet_settings():array{
    $cfg=config()['authorize_net']??[];
    $mode=(string)($cfg['mode']??getenv('CWP_AUTHNET_MODE')?:'disabled');
    if(!in_array($mode,['sandbox','production'],true))throw new RuntimeException('Online gateway is disabled.');
    $login=(string)($cfg['login']??getenv('CWP_AUTHNET_LOGIN')?:'');
    $key=(string)($cfg['transaction_key']??getenv('CWP_AUTHNET_TRANSACTION_KEY')?:'');
    if($login===''||$key==='')throw new RuntimeException('Authorize.net credentials are missing.');
    return ['mode'=>$mode,'login'=>$login,'key'=>$key,
       'client_key'=>(string)($cfg['public_client_key']??getenv('CWP_AUTHNET_PUBLIC_KEY')?:'')];
}
function software_anet_execute(string $method,array $request):array{
    $methods=['createTransactionRequest','getTransactionDetailsRequest','ARBCreateSubscriptionRequest',
        'ARBGetSubscriptionRequest','ARBCancelSubscriptionRequest','createCustomerProfileRequest'];
    if(!in_array($method,$methods,true))throw new InvalidArgumentException('Unsupported gateway method.');
    $cfg=software_anet_settings();
    if(!function_exists('curl_init'))throw new RuntimeException('cURL is required.');
    $url=$cfg['mode']==='sandbox'?'https://apitest.authorize.net/xml/v1/request.api':
        'https://api.authorize.net/xml/v1/request.api';
    $payload=json_encode([$method=>['merchantAuthentication'=>[
        'name'=>$cfg['login'],'transactionKey'=>$cfg['key']]]+$request],JSON_THROW_ON_ERROR);
    $ch=curl_init($url);
    if(!$ch)throw new RuntimeException('Gateway transport unavailable.');
    try{
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false]);
        $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        if($status!==200||!is_string($body)){
            throw new RuntimeException('Payment outcome unknown. Reconcile at the gateway before another attempt.');
        }
        $reply=json_decode(ltrim($body,"\xEF\xBB\xBF"),true,32,JSON_THROW_ON_ERROR);
        if(!is_array($reply))throw new RuntimeException('Unrecognized gateway response.');
        return $reply;
    }finally{curl_close($ch);}
}
