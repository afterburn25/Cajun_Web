<?php
/** Offline Authorize.net subscription transaction shape tests. */
declare(strict_types=1);
define('CWP_BOOT', true);
require dirname(__DIR__).'/site/app/billing/authorize_net_contract.php';

function good(bool $ok,string $name):void{
    if(!$ok)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
function blocked(callable $fn,string $name):void{
    try{$fn();}catch(RuntimeException|InvalidArgumentException){echo 'PASS '.$name.PHP_EOL;return;}
    throw new RuntimeException('FAIL '.$name);
}
$details=[
    'messages'=>['resultCode'=>'Ok'],
    'transaction'=>[
        'transId'=>'600000123',
        'transactionStatus'=>'settledSuccessfully',
        'authAmount'=>'49.99',
        'subscription'=>['id'=>'1234567'],
        'order'=>['invoiceNumber'=>'CWP-81']
    ]
];
$v=software_anet_subscription_charge_details($details,'600000123');
good($v['subscription_id']==='1234567'&&$v['amount_cents']===4999&&$v['invoice_number']==='CWP-81',
    'Verified recurring transaction shape is normalized');
$pending=$details;$pending['transaction']['transactionStatus']='capturedPendingSettlement';
good(software_anet_subscription_charge_details($pending,'600000123')['amount_cents']===4999,
    'Captured pending settlement is accepted for verified reconciliation');
$bad=$details;$bad['transaction']['authAmount']='49.999';
blocked(fn()=>software_anet_subscription_charge_details($bad,'600000123'),
    'Excess currency precision rejected');
$bad=$details;$bad['transaction']['subscription']['id']='bad';
blocked(fn()=>software_anet_subscription_charge_details($bad,'600000123'),
    'Malformed subscription id rejected');
$bad=$details;$bad['transaction']['transactionStatus']='declined';
blocked(fn()=>software_anet_subscription_charge_details($bad,'600000123'),
    'Declined transaction is not a subscription payment');
blocked(fn()=>software_anet_subscription_charge_details($details,'600000999'),
    'Mismatched transaction id rejected');
echo 'Software subscription gateway contract: PASS'.PHP_EOL;
