<?php
declare(strict_types=1);
define('CWP_BOOT',true);
require dirname(__DIR__).'/site/app/billing/authorize_net_contract.php';
require dirname(__DIR__).'/site/app/billing/authorize_net_webhooks.php';
require dirname(__DIR__).'/site/database/schema/software_commerce_schema.php';

function check(bool $value,string $name):void{
    if(!$value)throw new RuntimeException('FAILED '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
function reject(callable $f,string $name):void{
    try{$f();}catch(InvalidArgumentException){echo 'PASS '.$name.PHP_EOL;return;}
    throw new RuntimeException('FAILED '.$name);
}
$req=software_anet_charge(81,12500,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-token');
check($req['transactionRequest']['amount']==='125.00','Charge uses integer minor units');
check($req['transactionRequest']['payment']['opaqueData']['dataValue']==='synthetic-token','Only opaque Accept.js token leaves browser');
reject(fn()=>software_anet_nonce('CARD_NUMBER','4111111111111111'),'Never submit card numbers as opaque tokens');
check(software_anet_approved(['messages'=>['resultCode'=>'Ok'],'transactionResponse'=>['responseCode'=>'1','transId'=>'123456789']])==='123456789','Approved charge requires nested approval code');
check(software_anet_approved(['messages'=>['resultCode'=>'Ok'],'transactionResponse'=>['responseCode'=>'4','transId'=>'123456789']])===null,'Held fraud-screen transaction is not approved');
check(software_anet_approved(['messages'=>['resultCode'=>'Error'],'transactionResponse'=>['responseCode'=>'1','transId'=>'123456789']])===null,'Envelope error overrides nested code');
$sub=software_anet_arb_request('Test subscription',9900,'2030-01-01',12,12,'123456','987654');
check($sub['subscription']['paymentSchedule']['interval']['length']===12,'Yearly recurring uses 12-month ARB interval');
reject(fn()=>software_anet_arb_request('Test',9900,'2024-02-31',1,12,'123456','987654'),'Impossible billing date refused');
$key=str_repeat('aa',64);
$raw='{"notificationId":"test","eventType":"net.authorize.payment.authcapture.created","payload":{"id":"123456"}}';
$signature='sha512='.strtoupper(hash_hmac('sha512',$raw,hex2bin($key)));
check(software_anet_signature_matches($raw,$signature,$key),'HMAC-SHA512 webhook accepted');
check(!software_anet_signature_matches($raw.'x',$signature,$key),'Tampered webhook rejected');
$opaque=software_anet_arb_opaque(12,9900,1,'2030-12-01','COMMON.ACCEPT.INAPP.PAYMENT','synthetic-token');
check($opaque['subscription']['payment']['opaqueData']['dataDescriptor']==='COMMON.ACCEPT.INAPP.PAYMENT',
    'ARB uses one-use Accept.js opaque payment data rather than storing card details');
check($opaque['subscription']['paymentSchedule']['interval']['length']===1,
    'ARB monthly interval is one month');
reject(fn()=>software_anet_arb_opaque(12,9900,1,'2000-01-01','COMMON.ACCEPT.INAPP.PAYMENT','synthetic-token'),
    'ARB cannot begin in the past');
$refundDetails=[
    'messages'=>['resultCode'=>'Ok'],
    'transaction'=>[
        'transId'=>'700000001',
        'transactionType'=>'refundTransaction',
        'refTransId'=>'600000001',
        'authAmount'=>'5.00'
    ]
];
$refund=software_anet_reversal_details($refundDetails,'700000001','net.authorize.payment.refund.created');
check($refund['kind']==='refund'&&$refund['original_transaction_id']==='600000001'&&$refund['amount_cents']===500,
    'Refund readback binds follow-on transaction, original transaction and exact amount');
$voidDetails=[
    'messages'=>['resultCode'=>'Ok'],
    'transaction'=>[
        'transId'=>'700000002',
        'transactionType'=>'voidTransaction',
        'refTransId'=>'600000002'
    ]
];
$void=software_anet_reversal_details($voidDetails,'700000002','net.authorize.payment.void.created');
check($void['kind']==='void'&&$void['original_transaction_id']==='600000002'&&$void['amount_cents']===null,
    'Void readback binds the exact original transaction without inventing a refund amount');
reject(fn()=>software_anet_reversal_details($refundDetails,'700000009','net.authorize.payment.refund.created'),
    'Reversal readback rejects mismatched gateway transaction id');
check(software_subscription_event_target('net.authorize.customer.subscription.failed','active')==='review',
    'Failed subscription enters owner review');
check(software_subscription_event_target('net.authorize.customer.subscription.cancelled','review')==='review',
    'Owner review is sticky across later cancellation notifications');
check(software_subscription_event_target('net.authorize.customer.subscription.cancelled','active')==='cancelled',
    'Cancellation stops future renewal when no review is pending');
check(software_subscription_event_target('net.authorize.customer.subscription.updated','active')===null,
    'Non-terminal subscription updates do not mutate local entitlement state');
$schema=software_commerce_schema('cwp_');
check(count($schema)===11,'Eleven additive software-commerce tables');
check(isset($schema['software_subscription_receipts']),'Subscription renewals keep unique gateway-transaction receipt history');
check(isset($schema['software_charge_attempts']),'One-time checkout keeps separate retry records');
check(isset($schema['software_payment_reversals']),'Refunds and voids keep unique reversal receipts');
check(isset($schema['software_payment_disputes']),'Merchant disputes keep durable owner-reviewed evidence');
check(isset($schema['software_notices']),'Paid-license delivery has a durable email outbox');
check(!str_contains(implode('',$schema),'DROP TABLE'),'Setup never requests destructive table deletion');
echo 'Software commerce payment and schema contracts PASS'.PHP_EOL;
