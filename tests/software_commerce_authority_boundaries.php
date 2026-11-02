<?php
/** Native SQL authority regression: only gateway transport/configuration is synthetic. */
declare(strict_types=1);
namespace SoftwareClientAuthority;
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1')exit(2);

function software_anet_settings():array{return ['mode'=>'production']; /* Synthetic mode, never merchant configuration. */}
function software_anet_execute(string $method,array $request):array{
    $GLOBALS['swa_calls'][]=['method'=>$method,'transaction'=>\db()->inTransaction()];
    if(\db()->inTransaction())throw new \LogicException('Gateway boundary must follow the reservation commit.');
    if($GLOBALS['swa_after_reservation']??null){
        $callback=$GLOBALS['swa_after_reservation'];$GLOBALS['swa_after_reservation']=null;$callback();
    }
    if($method==='createTransactionRequest')return ['messages'=>['resultCode'=>'Ok'],'transactionResponse'=>['responseCode'=>'2']];
    if($method==='ARBCreateSubscriptionRequest'){
        $id=(string)++$GLOBALS['swa_gateway_id'];
        // Retain only the nonsecret contract. An opaque payment token is never evidence.
        $sub=$request['subscription'];unset($sub['payment']);$sub['status']='active';
        $GLOBALS['swa_provider'][$id]=$sub;
        return ['messages'=>['resultCode'=>'Ok'],'subscriptionId'=>$id];
    }
    $id=(string)($request['subscriptionId']??'');
    if(!isset($GLOBALS['swa_provider'][$id]))throw new \RuntimeException('Unknown synthetic subscription.');
    if($method==='ARBCancelSubscriptionRequest'){
        $GLOBALS['swa_provider'][$id]['status']='canceled';return ['messages'=>['resultCode'=>'Ok']];
    }
    if($method==='ARBGetSubscriptionRequest')return ['messages'=>['resultCode'=>'Ok'],'subscription'=>$GLOBALS['swa_provider'][$id]];
    throw new \RuntimeException('Unexpected synthetic gateway method.');
}

/** Token boundaries preserve the complete actual declaration and body, without replacement. */
function source_function(string $source,string $name):string{
    $tokens=token_get_all($source);$offset=0;$start=null;$named=false;$depth=0;$body=false;
    foreach($tokens as $token){
        $text=is_array($token)?$token[1]:$token;
        if(is_array($token)&&$token[0]===T_FUNCTION){$start=$offset;$named=false;$body=false;$depth=0;}
        elseif($start!==null&&!$body&&is_array($token)&&$token[0]===T_STRING){
            if(!$named){if($text!==$name){$start=null;}else $named=true;}
        }
        if($start!==null&&$named){
            if($text==='{'){$depth++;$body=true;}
            elseif($text==='}'&&$body&&--$depth===0)return substr($source,$start,$offset+1-$start);
        }
        $offset+=strlen($text);
    }
    throw new \RuntimeException('Missing actual source function '.$name);
}

foreach([
    'app/billing/software_charge.php'=>'software_charge_run',
    'app/billing/software_subscription_enroll.php'=>'software_subscription_enroll',
    'app/billing/software_subscription_cancel.php'=>'software_subscription_cancel',
] as $relative=>$name){
    $source=file_get_contents(CWP_CODE_ROOT.'/'.$relative);$body=source_function($source,$name);
    $GLOBALS['swa_source'][$relative]=['file_sha256'=>hash('sha256',$source),'function'=>$name,'function_sha256'=>hash('sha256',$body)];
    eval('namespace SoftwareClientAuthority; use \\RuntimeException; use \\InvalidArgumentException; use \\LogicException; use \\Throwable; use \\DateTimeImmutable; use \\DateTimeZone; use \\SensitiveParameter; '.$body);
}
