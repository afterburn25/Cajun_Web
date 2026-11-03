<?php
/** Only gateway transport/mode is synthetic; named source bodies are copied unchanged. */
declare(strict_types=1);
namespace FinancePayoffRetirement;
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
function license_finance_anet_settings():array{return ['mode'=>'production']; /* Test-only mode; no real activation. */}
function license_finance_anet_execute(string $method,array $request):array{
    $id=(string)($request['subscriptionId']??'');
    $sub=$id!==''?\one('SELECT * FROM '.\table('license_finance_subscriptions').' WHERE gateway_subscription_id=?',[$id]):null;
    $GLOBALS['fepCalls'][]=['method'=>$method,'id'=>$id,'transaction'=>\db()->inTransaction(),'retained'=>(bool)$sub];
    if($method==='ARBCreateSubscriptionRequest'){
        if($GLOBALS['postReservation']??null){$work=$GLOBALS['postReservation'];$GLOBALS['postReservation']=null;$work();}
        $id=(string)++$GLOBALS['fepGatewayId'];$GLOBALS['provider'][$id]=$request['subscription'];
        $GLOBALS['provider'][$id]['status']='active';
        return ['messages'=>['resultCode'=>'Ok'],'subscriptionId'=>$id];
    }
    $scenario=$GLOBALS['providerScenario'];
    if($method==='ARBCancelSubscriptionRequest'){
        if($scenario==='cancel_timeout')throw new \RuntimeException('Synthetic transport timeout');
        if($scenario==='cancel_error')return ['messages'=>['resultCode'=>'Error']];
        if($scenario!=='still_active')$GLOBALS['provider'][$id]['status']='canceled';
        if($scenario==='lost_cancel_reply')throw new \RuntimeException('Synthetic lost cancellation response');
        return ['messages'=>['resultCode'=>'Ok']];
    }
    if($method!=='ARBGetSubscriptionRequest'||!isset($GLOBALS['provider'][$id]))throw new \RuntimeException('Unexpected synthetic gateway call');
    $reply=$GLOBALS['provider'][$id];$reply['profile']=['customerProfileId'=>'1001','customerPaymentProfileId'=>'2001'];
    // Initial readback remains valid. Faults occur during payoff retirement.
    if($sub){
        if(str_starts_with($scenario,'already_'))$reply['status']=substr($scenario,8);
        if($scenario==='wrong_readback')$reply['amount']='999.00';
        if($reply['status']==='canceled'&&($GLOBALS['retirementMutation']??null)){
            $work=$GLOBALS['retirementMutation'];$GLOBALS['retirementMutation']=null;$work();
        }
    }
    return ['messages'=>['resultCode'=>'Ok'],'subscription'=>$reply];
}
$groups=[
    'app/billing/finance_subscription.php'=>['license_finance_subscription_sandbox_initial','license_finance_subscription_enrollment_offer','license_finance_subscription_reserve',
        'license_finance_subscription_create_profile','license_finance_subscription_enroll_opaque','license_finance_subscription_finalize',
        'license_finance_subscription_payoff_due','license_finance_subscription_payoff_retire','license_finance_subscription_reconcile'],
    'app/billing/finance_authorize_net.php'=>['license_finance_anet_retire_paid_off_subscription'],
    'app/licenses/financing.php'=>['license_financing_sync_by_invoice'],
    'app/licenses/finance_installments.php'=>['license_finance_scheduler_run'],
    'app/billing/ledger.php'=>['ledger_receive']
];
foreach($groups as $path=>$names){
    $source=file_get_contents($root.'/site/'.$path);
    foreach($names as $name){
        if(!preg_match('/^function '.$name.'\(.*?(?=^function |\z)/ms',$source,$match)){
            if(in_array($name,['license_finance_subscription_payoff_due','license_finance_subscription_payoff_retire','license_finance_anet_retire_paid_off_subscription'],true))continue;
            throw new \RuntimeException('Missing source function '.$name);
        }
        eval('namespace FinancePayoffRetirement; use \\RuntimeException; use \\InvalidArgumentException; use \\LogicException; use \\Throwable; use \\PDOException; '.$match[0]);
    }
}
