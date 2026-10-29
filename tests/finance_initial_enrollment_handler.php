<?php
/** Actual client handler orchestration, synthetic admission/reservation/provider boundaries. */
declare(strict_types=1);
define('CWP_BOOT',true);$root=dirname(__DIR__);
require $root.'/site/app/billing/authorize_net_contract.php';
$path=$argv[1]??$root.'/site/app/billing/finance_subscription.php';$source=file_get_contents($path);
if(!preg_match('/^function license_finance_subscription_enroll_opaque\(.*?(?=^function |\z)/ms',$source,$m))throw new RuntimeException('Handler missing');
eval($m[0]);$fixture=[];$checks=[];
function support_current():bool{global $fixture;return $fixture['name']==='support';}
function license_finance_offer_row(string $id,int $client):array{global $fixture;return ['id'=>$id,'status'=>'signed','initial_payment_cents'=>$fixture['initial']];}
function license_finance_subscription_enrollment_offer(string $id,int $client):array{
    global $fixture;$fixture['admission'][]=$client;
    if($fixture['name']==='ineligible')throw new RuntimeException('ineligible');
    return license_finance_offer_row($id,$client);
}
function license_finance_subscription_contract(array $offer):array{return ['amount_cents'=>1000,'start_date'=>'2098-01-01','months'=>1,'occurrences'=>3,'period_offset'=>0];}
function license_finance_subscription_reserve(array $offer,array $contract,array $profiles,?int $client=null):void{
    global $fixture;$fixture['reservations'][]=$client;
    if($client!==7)throw new RuntimeException('Missing current client at reservation');
    if($fixture['name']==='changed_before_reserve')throw new RuntimeException('changed_before_reserve');
}
function license_finance_anet_arb_opaque_request(mixed ...$args):array{return ['synthetic'=>$args];}
function license_finance_anet_execute(string $method,array $request):array{
    global $fixture;$fixture['provider'][]=$method;
    return $method==='ARBCreateSubscriptionRequest'?['messages'=>['resultCode'=>'Ok'],'subscriptionId'=>'12345']:['subscription'=>['profile'=>['customerProfileId'=>'1001','customerPaymentProfileId'=>'2001']]];
}
function license_finance_anet_subscription_readback(array $details,string $offer,string $gateway,int $amount,int $months,int $occurrences,string $start):bool{
    global $fixture;$fixture['readback']=[$gateway,$amount,$months,$occurrences,$start];return true;
}
function license_finance_anet_settings():array{return ['mode'=>'sandbox'];}
function license_finance_subscription_finalize(string $offer,string $gateway,array $contract,array $profiles,string $state):string{
    global $fixture;$fixture['finalize']=[$gateway,$profiles,$state];return $state;
}
function license_finance_subscription_mark_review(mixed ...$args):void{throw new LogicException('Unexpected review');}
function report_error(Throwable $e):void{throw new LogicException('Unexpected error',0,$e);}
foreach(['paid','zero','ineligible','changed_before_reserve','support','invalid_token'] as $name){
    $fixture=['name'=>$name,'initial'=>$name==='zero'?0:500,'admission'=>[],'reservations'=>[],'provider'=>[]];$result=null;$error=null;
    try{$result=license_finance_subscription_enroll_opaque(str_repeat('a',32),7,'COMMON.ACCEPT.INAPP.PAYMENT',$name==='invalid_token'?'':'synthetic');}catch(Throwable $e){$error=$e->getMessage();}
    $allowed=in_array($name,['paid','zero'],true);
    $checks[]=['name'=>$name.' handler outcome','passed'=>$allowed?$result==='sandbox_test':$error!==null];
    $checks[]=['name'=>$name.' provider admission','passed'=>$fixture['provider']===($allowed?['ARBCreateSubscriptionRequest','ARBGetSubscriptionRequest']:[])];
    $checks[]=['name'=>$name.' current client reaches reservation','passed'=>$fixture['reservations']===(in_array($name,['paid','zero','changed_before_reserve'],true)?[7]:[])];
    if($allowed){
        $checks[]=['name'=>$name.' exact finite signed contract readback','passed'=>($fixture['readback']??null)===['12345',1000,1,3,'2098-01-01']];
        $checks[]=['name'=>$name.' sandbox outcome retained','passed'=>($fixture['finalize']??null)===['12345',['customer_profile_id'=>'1001','payment_profile_id'=>'2001'],'sandbox_test']];
    }
}
$failed=count(array_filter($checks,fn($c)=>!$c['passed']));
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'source_sha256'=>hash('sha256',$source),
    'scope'=>'Exact client handler with synthetic admission, reservation and provider boundaries; native companion verifies real SQL and ledger admission. No gateway/HTTP claim.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed?1:0);
