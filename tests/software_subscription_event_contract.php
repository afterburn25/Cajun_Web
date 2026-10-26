<?php
/** Offline subscription lifecycle state-policy tests. */
declare(strict_types=1);
define('CWP_BOOT', true);
require dirname(__DIR__).'/site/app/billing/authorize_net_contract.php';
require dirname(__DIR__).'/site/app/billing/software_subscriptions.php';

function pass(bool $ok,string $name):void{
    if(!$ok)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
pass(software_subscription_event_target('net.authorize.customer.subscription.failed','active')==='review',
    'Failed subscription enters review');
pass(software_subscription_event_target('net.authorize.customer.subscription.suspended','active')==='review',
    'Suspended subscription enters review');
pass(software_subscription_event_target('net.authorize.customer.subscription.terminated','active')==='review',
    'Terminated subscription enters review');
pass(software_subscription_event_target('net.authorize.customer.subscription.cancelled','active')==='cancelled',
    'Cancelled subscription stops future renewals');
pass(software_subscription_event_target('net.authorize.customer.subscription.expired','active')==='cancelled',
    'Expired subscription stops future renewals');
pass(software_subscription_event_target('net.authorize.customer.subscription.cancelled','review')==='review',
    'Review state survives later cancellation event');
pass(software_subscription_event_target('net.authorize.customer.subscription.updated','active')===null,
    'Non-terminal update does not invent local state');
$phase=software_subscription_settlement_phase('cancelling',false);
pass($phase!==null&&$phase['first']===true&&$phase['preserve_status']===true,
    'First captured payment during cancellation is credited without erasing cancellation');
$phase=software_subscription_settlement_phase('cancelled',true);
pass($phase!==null&&$phase['first']===false&&$phase['preserve_status']===true,
    'Late captured renewal after cancellation is credited while cancellation stays sticky');
$phase=software_subscription_settlement_phase('review',true);
pass($phase!==null&&$phase['first']===false&&$phase['preserve_status']===true,
    'Captured renewal under billing review is credited without clearing review');
$phase=software_subscription_settlement_phase('active',true);
pass($phase!==null&&$phase['first']===false&&$phase['preserve_status']===false,
    'Normal active subscription charge remains a renewal');
pass(software_subscription_settlement_phase('sandbox_test',false)===null,
    'Non-production subscription state cannot settle a production charge');
try{
    software_subscription_settlement_phase('pending_first_payment',true);
    throw new RuntimeException('FAIL Pending subscription accepted contradictory receipt history');
}catch(RuntimeException $expected){
    if(str_starts_with($expected->getMessage(),'FAIL '))throw $expected;
    echo 'PASS Pending subscription rejects contradictory receipt history'.PHP_EOL;
}
try{
    software_subscription_settlement_phase('active',false);
    throw new RuntimeException('FAIL Active subscription accepted missing first receipt');
}catch(RuntimeException $expected){
    if(str_starts_with($expected->getMessage(),'FAIL '))throw $expected;
    echo 'PASS Active subscription rejects missing first receipt'.PHP_EOL;
}
echo 'Software subscription lifecycle contract: PASS'.PHP_EOL;
