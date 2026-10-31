<?php
/** Test-only provider boundaries; application functions below retain exact source bytes. */
declare(strict_types=1);
namespace FinanceEnrollmentPayoff;
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
function license_finance_anet_settings():array{return ['mode'=>'sandbox'];}
function license_finance_subscription_contract(array $offer):array{
    $contract=\license_finance_subscription_contract($offer);
    if($GLOBALS['fepMutation']??null){$work=$GLOBALS['fepMutation'];$GLOBALS['fepMutation']=null;$work();}
    return $contract;
}
function license_finance_anet_execute(string $method,array $request):array{
    $GLOBALS['fepCalls'][]=$method;
    return $method==='ARBCreateSubscriptionRequest'
        ?['messages'=>['resultCode'=>'Ok'],'subscriptionId'=>(string)++$GLOBALS['fepGatewayId']]
        :['subscription'=>['profile'=>['customerProfileId'=>'1001','customerPaymentProfileId'=>'2001']]];
}
function license_finance_anet_subscription_readback(array $details,string $offer,string $gateway,int $amount,int $months,int $occurrences,string $start):bool{
    $GLOBALS['fepReadback']=[$gateway,$amount,$months,$occurrences,$start];return true;
}
$source=file_get_contents($root.'/site/app/billing/finance_subscription.php');
foreach(['license_finance_subscription_sandbox_initial','license_finance_subscription_enrollment_offer','license_finance_subscription_reserve','license_finance_subscription_create_profile','license_finance_subscription_enroll_opaque'] as $name){
    if(!preg_match('/^function '.$name.'\(.*?(?=^function |\z)/ms',$source,$match)){
        if($name==='license_finance_subscription_sandbox_initial')continue;
        throw new \RuntimeException('Missing source function '.$name);
    }
    eval('namespace FinanceEnrollmentPayoff; use \\RuntimeException; use \\InvalidArgumentException; use \\LogicException; use \\Throwable; '.$match[0]);
}
