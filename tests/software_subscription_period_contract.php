<?php
/** Offline calendar-period checks for recurring software entitlement extension. */
declare(strict_types=1);
define('CWP_BOOT', true);
require dirname(__DIR__).'/site/app/billing/software_subscriptions.php';

function period_ok(bool $ok,string $name):void{
    if(!$ok)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
function period_reject(callable $fn,string $name):void{
    try{$fn();}catch(InvalidArgumentException){echo 'PASS '.$name.PHP_EOL;return;}
    throw new RuntimeException('FAIL '.$name);
}
$utc=new DateTimeZone('UTC');

$now=new DateTimeImmutable('2027-01-15 12:00:00',$utc);
period_ok(
    software_subscription_extend_expiry('2027-01-31 13:15:00',1,$now)==='2027-02-28 13:15:00',
    'January 31 monthly renewal clamps to February 28'
);

$now=new DateTimeImmutable('2028-01-15 12:00:00',$utc);
period_ok(
    software_subscription_extend_expiry('2028-01-31 13:15:00',1,$now)==='2028-02-29 13:15:00',
    'January 31 monthly renewal clamps to leap-day February 29'
);

$now=new DateTimeImmutable('2028-02-01 12:00:00',$utc);
period_ok(
    software_subscription_extend_expiry('2028-02-29 09:30:00',12,$now)==='2029-02-28 09:30:00',
    'Leap-day annual renewal clamps to February 28 next year'
);

$now=new DateTimeImmutable('2026-10-25 12:00:00',$utc);
period_ok(
    software_subscription_extend_expiry('2026-01-01 00:00:00',1,$now)==='2026-11-25 12:00:00',
    'Expired subscription extends from current UTC time rather than stale expiry'
);

period_reject(
    fn()=>software_subscription_extend_expiry('2027-01-31 13:15:00',2,$now),
    'Unsupported renewal interval is rejected'
);
period_reject(
    fn()=>software_subscription_extend_expiry('2027-02-30 13:15:00',1,$now),
    'Impossible renewal timestamp is rejected'
);

echo 'Software subscription period contract: PASS'.PHP_EOL;
