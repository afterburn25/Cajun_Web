<?php
/** Fixed sandbox adapter. No production endpoint, retries, raw card storage or provider logging. */
declare(strict_types=1);
defined('CWP_BOOT') || exit;
function authorize_result(int $http,string $body): string {
    if($http!==200||strlen($body)>65536)return 'unavailable';
    try{$r=json_decode(preg_replace('/^\xEF\xBB\xBF/','',$body),true,16,JSON_THROW_ON_ERROR);}catch(JsonException){return 'unavailable';}
    $result=$r['messages']['resultCode']??null;$code=$r['messages']['message'][0]['code']??null;
    if($result==='Ok'&&$code==='I00001')return 'authenticated';
    if($result==='Error'&&is_string($code)&&preg_match('/^E[0-9]{5}$/D',$code))return 'rejected';
    return 'unavailable';
}
function authorize_authenticate(#[\SensitiveParameter] array $credentials,?callable $transport=null): string {
    $body=json_encode(['authenticateTestRequest'=>['merchantAuthentication'=>['name'=>$credentials['api_login_id'],'transactionKey'=>$credentials['transaction_key']]]],JSON_THROW_ON_ERROR);
    [$status,$response]=authorize_transport($body,$transport);return authorize_result($status,$response);
}
function authorize_transport(#[\SensitiveParameter] string $body,?callable $transport=null): array {
    if($transport!==null){if(PHP_SAPI!=='cli')throw new LogicException('Fixture transport is CLI-only.');return $transport($body);}
    if(!function_exists('curl_init'))return [0,''];
    $curl=curl_init('https://apitest.authorize.net/xml/v1/request.api');$response='';
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk) use(&$response): int {if(strlen($response)+strlen($chunk)>65536)return 0;$response.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($curl);$http=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
    return $ok===false?[0,'']:[$http,$response];
}
function authorize_sandbox_call(string $method,#[\SensitiveParameter] array $credentials,#[\SensitiveParameter] array $fields,?callable $transport): ?array {
    if(!in_array($method,['getMerchantDetailsRequest','createTransactionRequest','getTransactionDetailsRequest'],true))throw new LogicException('Unsupported sandbox operation.');
    $body=json_encode([$method=>['merchantAuthentication'=>['name'=>$credentials['api_login_id'],'transactionKey'=>$credentials['transaction_key']]]+$fields],JSON_THROW_ON_ERROR);
    try{
        [$http,$response]=authorize_transport($body,$transport);
        if($http!==200||strlen($response)>65536)return null;
        $r=json_decode(preg_replace('/^\xEF\xBB\xBF/','',$response),true,32,JSON_THROW_ON_ERROR);
        return is_array($r)?$r:null;
    }catch(Throwable){return null;} // An interrupted send may have reached the gateway; never retry here.
}
function authorize_sandbox_ok(?array $r): bool {return ($r['messages']['resultCode']??null)==='Ok'&&($r['messages']['message'][0]['code']??null)==='I00001';}
function authorize_sandbox_merchant(#[\SensitiveParameter] array $credentials,?callable $transport): bool {
    $r=authorize_sandbox_call('getMerchantDetailsRequest',$credentials,[],$transport);
    // Transaction details do not report currency. Bind to a USD-only merchant instead of guessing.
    return authorize_sandbox_ok($r)&&($r['isTestMode']??null)===false&&($r['currencies']??null)===['USD'];
}
function authorize_sandbox_charge(#[\SensitiveParameter] array $credentials,array $attempt,#[\SensitiveParameter] string $nonce,?callable $transport): ?string {
    $r=authorize_sandbox_call('createTransactionRequest',$credentials,['refId'=>$attempt['reference'],'transactionRequest'=>[
        'transactionType'=>'authCaptureTransaction','amount'=>number_format((int)$attempt['amount_cents']/100,2,'.',''),
        'payment'=>['opaqueData'=>['dataDescriptor'=>'COMMON.ACCEPT.INAPP.PAYMENT','dataValue'=>$nonce]],
        'order'=>['invoiceNumber'=>$attempt['reference'],'description'=>'CajunWebPro sandbox trial'],
        'transactionSettings'=>['setting'=>[['settingName'=>'emailCustomer','settingValue'=>'false'],['settingName'=>'allowPartialAuth','settingValue'=>'false'],['settingName'=>'duplicateWindow','settingValue'=>'28800']]],
    ]],$transport);
    $id=$r['transactionResponse']['transId']??null;
    // A create response is only a locator. No approval/decline is trusted without a details query.
    return ($r['refId']??null)===$attempt['reference']&&is_string($id)&&preg_match('/^[1-9][0-9]{0,19}$/D',$id)?$id:null;
}
function authorize_cents(mixed $amount): ?int {
    if(!is_int($amount)&&!is_float($amount)&&!is_string($amount))return null;
    $value=(string)$amount;if(!preg_match('/^(0|[1-9][0-9]{0,5})(?:\.([0-9]{1,2}))?$/D',$value,$m))return null;
    return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
}
function authorize_sandbox_details(#[\SensitiveParameter] array $credentials,array $attempt,string $id,?callable $transport): array {
    $unknown=['state'=>'unknown','transaction_id'=>$id];
    $r=authorize_sandbox_call('getTransactionDetailsRequest',$credentials,['transId'=>$id],$transport);$t=$r['transaction']??null;
    if(!authorize_sandbox_ok($r)||!is_array($t)||($t['transId']??null)!==$id||($t['transactionType']??null)!=='authCaptureTransaction'||($t['order']['invoiceNumber']??null)!==$attempt['reference']||authorize_cents($t['authAmount']??null)!==(int)$attempt['amount_cents']||!empty($t['splitTenderId'])||isset($t['currencyCode'])&&$t['currencyCode']!=='USD')return $unknown;
    $state=match($t['transactionStatus']??null){'capturedPendingSettlement'=>'captured','settledSuccessfully'=>'settled','declined'=>'declined','voided'=>'voided','expired'=>'expired','FDSPendingReview','FDSAuthorizedPendingReview'=>'held',default=>'unknown'};
    if(in_array($state,['captured','settled'],true)&&((string)($t['responseCode']??'')!=='1'||authorize_cents($t['settleAmount']??null)!==(int)$attempt['amount_cents']))return $unknown;
    return ['state'=>$state,'transaction_id'=>$id];
}
