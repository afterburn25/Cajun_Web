<?php
/** Fixed sandbox authentication adapter. No charge, refund or production endpoint. */
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
    if($transport!==null){if(PHP_SAPI!=='cli')throw new LogicException('Fixture transport is CLI-only.');[$status,$response]=$transport($body);return authorize_result($status,$response);}
    if(!function_exists('curl_init'))return 'unavailable';
    $curl=curl_init('https://apitest.authorize.net/xml/v1/request.api');$response='';
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk) use(&$response): int {if(strlen($response)+strlen($chunk)>65536)return 0;$response.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($curl);$http=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
    return $ok===false?'unavailable':authorize_result($http,$response);
}
