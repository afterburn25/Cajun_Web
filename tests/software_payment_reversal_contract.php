<?php
/** Offline reversal verification tests; no database or gateway traffic. */
declare(strict_types=1);
define('CWP_BOOT', true);
require dirname(__DIR__).'/site/app/billing/authorize_net_contract.php';

function ok(bool $pass,string $name):void{
    if(!$pass)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
function denied(callable $fn,string $name):void{
    try{$fn();}catch(RuntimeException|InvalidArgumentException){echo 'PASS '.$name.PHP_EOL;return;}
    throw new RuntimeException('FAIL '.$name);
}
$refund=[
    'messages'=>['resultCode'=>'Ok'],
    'transaction'=>[
        'transId'=>'700000001',
        'transactionType'=>'refundTransaction',
        'refTransId'=>'600000001',
        'authAmount'=>'12.50'
    ]
];
$r=software_anet_reversal_details($refund,'700000001','net.authorize.payment.refund.created');
ok($r['kind']==='refund' && $r['original_transaction_id']==='600000001' && $r['amount_cents']===1250,
    'Refund must bind verified follow-on transaction to original charge');
$void=[
    'messages'=>['resultCode'=>'Ok'],
    'transaction'=>[
        'transId'=>'700000002',
        'transactionType'=>'voidTransaction',
        'refTransId'=>'600000002'
    ]
];
$v=software_anet_reversal_details($void,'700000002','net.authorize.payment.void.created');
ok($v['kind']==='void' && $v['original_transaction_id']==='600000002',
    'Void must bind to original transaction');
denied(fn()=>software_anet_reversal_details($refund,'700000009','net.authorize.payment.refund.created'),
    'Webhook transaction mismatch rejected');
$bad=$refund;$bad['transaction']['refTransId']='not-a-transaction';
denied(fn()=>software_anet_reversal_details($bad,'700000001','net.authorize.payment.refund.created'),
    'Malformed original transaction rejected');
$bad=$refund;$bad['transaction']['transactionType']='authCaptureTransaction';
denied(fn()=>software_anet_reversal_details($bad,'700000001','net.authorize.payment.refund.created'),
    'Wrong gateway transaction type rejected');
denied(fn()=>software_anet_reversal_details($refund,'700000001','net.authorize.payment.capture.created'),
    'Non-reversal event rejected by reversal parser');
echo 'Software reversal contract: PASS'.PHP_EOL;
