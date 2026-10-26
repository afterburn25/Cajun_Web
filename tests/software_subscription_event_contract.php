<?php
/** Offline subscription lifecycle state-policy tests. */
declare(strict_types=1);
define('CWP_BOOT', true);
require dirname(__DIR__).'/site/app/billing/authorize_net_contract.php';

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
echo 'Software subscription lifecycle contract: PASS'.PHP_EOL;
