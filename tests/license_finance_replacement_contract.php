<?php
/** Actual replacement-retirement orchestration with an isolated synthetic gateway. */
declare(strict_types=1);
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/app/licenses/financing_offers.php';
require_once CWP_CODE_ROOT.'/app/billing/finance_authorize_net.php';
$offer=str_repeat('a',32);
$old=['gateway_subscription_id'=>'810001','amount_cents'=>2500,'interval_months'=>1,'total_occurrences'=>4,'start_date'=>'2026-01-01'];
function retire_reply(string $status,string $amount='25.00'):array{
    global $offer;
    return ['messages'=>['resultCode'=>'Ok'],'subscription'=>[
        'name'=>substr('CajunWebPro finance '.$offer,0,50),'status'=>$status,'amount'=>$amount,
        'paymentSchedule'=>['interval'=>['length'=>1,'unit'=>'months'],'totalOccurrences'=>4,'startDate'=>'2026-01-01'],
        'order'=>['invoiceNumber'=>license_finance_anet_subscription_ref($offer)]
    ]];
}
function retire_case(string $name,array $steps,?string $expected):void{
    global $offer,$old;
    $calls=0;$error=null;$result=null;
    try{
        $result=license_finance_anet_retire_subscription($offer,$old,
            function(string $method,array $request)use($steps,&$calls):array{
                $step=$steps[$calls++]??throw new LogicException('Unexpected additional gateway request.');
                if($method!==$step[0]||($request['subscriptionId']??'')!=='810001')throw new LogicException('Wrong retirement request identity or order.');
                if($step[1] instanceof Throwable)throw $step[1];
                return $step[1];
            });
    }catch(RuntimeException $e){$error=$e;}
    if($calls!==count($steps)||($expected===null?($error===null):($error!==null||$result!==$expected))){
        throw new RuntimeException('FAIL '.$name.' · '.($error?->getMessage()??'unexpected call count or result'));
    }
    echo 'PASS '.$name.PHP_EOL;
}
$read='ARBGetSubscriptionRequest';$cancel='ARBCancelSubscriptionRequest';$ok=['messages'=>['resultCode'=>'Ok']];
retire_case('Suspended prior ARB is cancelled and independently read back before retirement',
    [[$read,retire_reply('suspended')],[$cancel,$ok],[$read,retire_reply('canceled')]],'canceled');
foreach(['canceled','expired','terminated'] as $terminal){
    retire_case('Already '.$terminal.' prior ARB needs no further gateway mutation',[[$read,retire_reply($terminal)]],$terminal);
}
retire_case('Reactivated prior ARB prevents a parallel replacement billing path',[[$read,retire_reply('active')]],null);
retire_case('Rejected cancellation prevents replacement billing',
    [[$read,retire_reply('suspended')],[$cancel,['messages'=>['resultCode'=>'Error']]]],null);
retire_case('An accepted cancellation without terminal readback prevents replacement billing',
    [[$read,retire_reply('suspended')],[$cancel,$ok],[$read,retire_reply('suspended')]],null);
retire_case('Mismatched prior amount prevents cancellation and replacement',[[$read,retire_reply('suspended','26.00')]],null);
retire_case('Uncertain gateway transport prevents replacement billing',[[$read,new RuntimeException('Synthetic transport uncertainty')]],null);
echo 'Finance replacement retirement contract: PASS'.PHP_EOL;
