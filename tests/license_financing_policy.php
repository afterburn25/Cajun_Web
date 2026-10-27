<?php
/** Pure financed-license policy checks. */
declare(strict_types=1);
define('CWP_BOOT',true);
require dirname(__DIR__).'/site/app/licenses/financing_policy.php';

function finance_ok(bool $ok,string $name):void{
    if(!$ok)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
function finance_reject(callable $fn,string $name):void{
    try{$fn();}catch(InvalidArgumentException){echo 'PASS '.$name.PHP_EOL;return;}
    throw new RuntimeException('FAIL '.$name);
}

$r=license_financing_evaluate([],24,10,'2026-10-26');
finance_ok($r['state']==='active'&&$r['paid_count']===0,
    'Unissued future financing periods never suspend a license');

$r=license_financing_evaluate([
    ['index'=>0,'due'=>'2026-10-20','paid'=>false],
],24,10,'2026-10-26');
finance_ok($r['state']==='grace'&&$r['days_late']===6&&$r['overdue_period']===0,
    'Issued unpaid installment remains in grace before grace days expire');

$r=license_financing_evaluate([
    ['index'=>0,'due'=>'2026-10-01','paid'=>false],
],24,10,'2026-10-26');
finance_ok($r['state']==='suspended'&&$r['days_late']===25,
    'Issued unpaid installment suspends payment-dependent access after grace');

$r=license_financing_evaluate([
    ['index'=>0,'due'=>'2026-10-01','paid'=>true],
    ['index'=>1,'due'=>'2026-11-01','paid'=>false],
],24,10,'2026-10-26');
finance_ok($r['state']==='active'&&$r['paid_count']===1,
    'Catch-up payment clears the overdue state while future installments remain');

$periods=[];
for($i=0;$i<24;$i++)$periods[]=['index'=>$i,'due'=>'2026-10-01','paid'=>true];
$r=license_financing_evaluate($periods,24,10,'2026-10-26');
finance_ok($r['state']==='paid_off'&&$r['paid_count']===24,
    'All required installments permanently reach paid-off state');

$r=license_financing_evaluate([
    ['index'=>0,'due'=>'2026-01-01','paid'=>false],
],24,10,'2030-01-01',true);
finance_ok($r['state']==='paid_off',
    'Persisted payoff ignores all later billing delinquency');

$math=license_financing_terms_math(650000,65000,585000,15000,25000,24);
finance_ok($math['installment_total_cents']===600000&&$math['total_customer_cost_cents']===665000,
    'Tailored finance amounts reconcile cash down payment financed amount charge and installments');
finance_reject(fn()=>license_financing_terms_math(650000,65000,580000,15000,25000,24),
    'Mismatched amount financed is rejected');
finance_reject(fn()=>license_financing_terms_math(650000,65000,585000,14000,25000,24),
    'Mismatched installment total and finance charge is rejected');

finance_ok(license_financing_cadence_months('monthly')===1&&license_financing_cadence_months('quarterly')===3&&license_financing_cadence_months('annually')===12,
    'Financing cadence maps exactly to one three or twelve month intervals');
finance_reject(fn()=>license_financing_cadence_months('weekly'),'Unsupported financing cadence is rejected');
finance_ok(license_financing_period_from_pay_num(1,24)===0&&license_financing_period_from_pay_num(24,24)===23,
    'Gateway subscription payment number maps exactly to signed installment index');
finance_reject(fn()=>license_financing_period_from_pay_num(25,24),'Gateway payment number beyond signed installments is rejected');
finance_ok(license_financing_period_from_replacement_pay_num(1,17,7,24)===7
    &&license_financing_period_from_replacement_pay_num(17,17,7,24)===23,
    'Replacement subscription payment numbers map onto the exact remaining signed periods');
finance_reject(fn()=>license_financing_period_from_replacement_pay_num(1,18,7,24),
    'Replacement occurrence count must equal the remaining signed installments');
finance_reject(fn()=>license_financing_period_from_replacement_pay_num(1,17,24,24),
    'Replacement period offset cannot start beyond the signed agreement');
finance_ok(license_financing_recovery_link_allowed('billing_suspended')&&!license_financing_recovery_link_allowed('payment_failed')&&!license_financing_recovery_link_allowed('review'),
    'Self-service recovery links require confirmed billing suspension');

$rights=license_financing_rights(false,false,false,false);
finance_ok(!$rights['resale']&&!$rights['distribution'],
    'Default financed-site rights prohibit resale and distribution');
$rights=license_financing_rights(true,false,true,false);
finance_ok($rights['resale']&&$rights['exclusive_resale']&&!$rights['distribution'],
    'Exclusive resale can be granted explicitly without distribution');
finance_reject(fn()=>license_financing_rights(false,false,true,false),
    'Exclusive resale cannot exist without resale rights');
finance_reject(fn()=>license_financing_evaluate([],0,10,'2026-10-26'),
    'Zero-installment financing is rejected');
finance_reject(fn()=>license_financing_evaluate([],24,91,'2026-10-26'),
    'Grace period above policy maximum is rejected');

echo 'License financing policy: PASS'.PHP_EOL;
