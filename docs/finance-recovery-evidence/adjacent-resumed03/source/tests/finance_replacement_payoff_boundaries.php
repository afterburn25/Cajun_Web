<?php
/** Only gateway transport/mode is synthetic; named source bodies are copied unchanged. */
declare(strict_types=1);
namespace FinancePayoffRetirement;
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
function license_finance_anet_settings():array{return ['mode'=>'production','login'=>'synthetic-finance-account','key'=>'synthetic-key','client_key'=>'synthetic-client-key','revision'=>1]; /* Test-only mode; no real activation. */}
function license_finance_anet_execute(string $method,array $request,?array $context=null):array{
    $id=(string)($request['subscriptionId']??'');
    $sub=$id!==''?\one('SELECT * FROM '.\table('license_finance_subscriptions').' WHERE gateway_subscription_id=?',[$id]):null;
    $GLOBALS['fepCalls'][]=['method'=>$method,'id'=>$id,'transaction'=>\db()->inTransaction(),'retained'=>(bool)$sub];
    if($method==='ARBCreateSubscriptionRequest'){
        if($GLOBALS['postReservation']??null){$work=$GLOBALS['postReservation'];$GLOBALS['postReservation']=null;$work();}
        $id=(string)++$GLOBALS['fepGatewayId'];$GLOBALS['provider'][$id]=$request['subscription'];$GLOBALS['provider'][$id]['status']='active';
        return ['messages'=>['resultCode'=>'Ok'],'subscriptionId'=>$id];
    }
    $scenario=$GLOBALS['providerScenario'];$candidate=($GLOBALS['oldGateway']??'')!==''&&$id!==$GLOBALS['oldGateway'];
    if($method==='ARBCancelSubscriptionRequest'){
        if($candidate&&$scenario==='cancel_timeout')throw new \RuntimeException('Synthetic candidate cancellation timeout');
        if($candidate&&$scenario==='cancel_error')return ['messages'=>['resultCode'=>'Error']];
        if(!$candidate||$scenario!=='still_active')$GLOBALS['provider'][$id]['status']='canceled';
        if($candidate&&$scenario==='lost_cancel_reply')throw new \RuntimeException('Synthetic lost candidate cancellation reply');
        return ['messages'=>['resultCode'=>'Ok']];
    }
    if($method!=='ARBGetSubscriptionRequest'||!isset($GLOBALS['provider'][$id]))throw new \RuntimeException('Unexpected synthetic provider operation');
    if(!$candidate&&$GLOBALS['provider'][$id]['status']==='canceled'&&($GLOBALS['afterOldRetirement']??null)){
        $work=$GLOBALS['afterOldRetirement'];$GLOBALS['afterOldRetirement']=null;$work();
    }
    if($candidate&&($GLOBALS['afterCandidateRead']??null)){$work=$GLOBALS['afterCandidateRead'];$GLOBALS['afterCandidateRead']=null;$work();}
    if($candidate&&$scenario==='readback_timeout')throw new \RuntimeException('Synthetic candidate readback timeout');
    $reply=$GLOBALS['provider'][$id];$reply['profile']=['customerProfileId'=>'1001','customerPaymentProfileId'=>'2001'];
    if($candidate&&$scenario==='wrong_readback')$reply['amount']='999.00';
    return ['messages'=>['resultCode'=>'Ok'],'subscription'=>$reply];
}
function report_error(\Throwable $error):string{
    if($error instanceof \PDOException){
        $trace=[];$queryHash=null;
        foreach($error->getTrace() as $frame){
            $trace[]=['function'=>$frame['function']??'', 'file'=>basename($frame['file']??''), 'line'=>$frame['line']??0];
            if(($frame['function']??'')==='query'&&is_string($frame['args'][0]??null))$queryHash=hash('sha256',$frame['args'][0]);
        }
        // Numeric driver diagnostics and source locations only. Never emit SQL
        // arguments, credentials, tokens, license keys or provider payloads.
        error_log('CWP finance native PDO '.json_encode(['time'=>gmdate('c'),'case'=>$GLOBALS['name']??null,
            'sqlstate'=>$error->errorInfo[0]??$error->getCode(),'driver_code'=>$error->errorInfo[1]??null,
            'query_sha256'=>$queryHash,'trace'=>$trace],JSON_THROW_ON_ERROR));
    }
    return \report_error($error);
}
$recovery=file_get_contents($root.'/site/app/billing/finance_recovery.php');
preg_match_all('/^function ([a-zA-Z0-9_]+)\(.*?(?=^function |\z)/ms',$recovery,$recoveryFunctions,PREG_SET_ORDER);
foreach($recoveryFunctions as $function){
    eval('namespace FinancePayoffRetirement; use \\RuntimeException; use \\InvalidArgumentException; use \\LogicException; use \\Throwable; use \\PDOException; '.$function[0]);
}
$groups=[
    'app/billing/finance_subscription.php'=>['license_finance_subscription_sandbox_initial','license_finance_subscription_enrollment_offer','license_finance_subscription_reserve',
        'license_finance_subscription_create_profile','license_finance_subscription_enroll_opaque','license_finance_subscription_finalize',
        'license_finance_subscription_payoff_due','license_finance_subscription_payoff_retire','license_finance_subscription_reconcile',
        'license_finance_subscription_replacement_reserve','license_finance_subscription_replacement_review','license_finance_subscription_replacement_declined',
        'license_finance_subscription_replacement_finalize','license_finance_subscription_reenroll_opaque','license_finance_subscription_signed_remaining_contract','license_finance_subscription_replacement_retire'],
    'app/billing/finance_authorize_net.php'=>['license_finance_anet_retire_paid_off_subscription','license_finance_anet_retire_subscription'],
    'app/licenses/financing.php'=>['license_financing_sync_by_invoice'],
    'app/licenses/finance_installments.php'=>['license_finance_scheduler_run'],
    'app/billing/ledger.php'=>['ledger_receive']
];
foreach($groups as $path=>$names){
    $source=file_get_contents($root.'/site/'.$path);
    foreach($names as $name){
        if(!preg_match('/^function '.$name.'\(.*?(?=^function |\z)/ms',$source,$match)){
            if(in_array($name,['license_finance_subscription_payoff_due','license_finance_subscription_payoff_retire','license_finance_anet_retire_paid_off_subscription','license_finance_subscription_signed_remaining_contract','license_finance_subscription_replacement_retire'],true))continue;
            throw new \RuntimeException('Missing source function '.$name);
        }
        eval('namespace FinancePayoffRetirement; use \\RuntimeException; use \\InvalidArgumentException; use \\LogicException; use \\Throwable; use \\PDOException; '.$match[0]);
    }
}
