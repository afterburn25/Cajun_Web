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
        'authAmount'=>49.99,
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
$profile=[
    'messages'=>['resultCode'=>'Ok'],
    'subscription'=>[
        'name'=>'CajunWebPro software 81',
        'paymentSchedule'=>[
            'interval'=>['length'=>'1','unit'=>'months'],
            'startDate'=>'2026-11-01',
            'totalOccurrences'=>'9999',
        ],
        'amount'=>49.99,
        'status'=>'active',
        'order'=>['invoiceNumber'=>'CWP-81'],
    ],
];
$p=software_anet_subscription_profile_details($profile,'1234567',81,4999,1,'2026-11-01');
good($p['subscription_id']==='1234567'&&$p['status']==='active'&&$p['invoice_number']==='CWP-81',
    'ARB subscription readback binds exact invoice amount schedule and gateway id');
$bad=$profile;$bad['subscription']['amount']='50.00';
blocked(fn()=>software_anet_subscription_profile_details($bad,'1234567',81,4999,1,'2026-11-01'),
    'ARB subscription readback rejects amount mismatch');
$bad=$profile;$bad['subscription']['paymentSchedule']['interval']['length']='12';
blocked(fn()=>software_anet_subscription_profile_details($bad,'1234567',81,4999,1,'2026-11-01'),
    'ARB subscription readback rejects interval mismatch');
$bad=$profile;$bad['subscription']['paymentSchedule']['startDate']='2026-11-02';
blocked(fn()=>software_anet_subscription_profile_details($bad,'1234567',81,4999,1,'2026-11-01'),
    'ARB subscription readback rejects start-date mismatch');
$bad=$profile;$bad['subscription']['order']['invoiceNumber']='CWP-82';
blocked(fn()=>software_anet_subscription_profile_details($bad,'1234567',81,4999,1,'2026-11-01'),
    'ARB subscription readback rejects invoice mismatch');
$bad=$profile;$bad['subscription']['name']='Different subscription';
blocked(fn()=>software_anet_subscription_profile_details($bad,'1234567',81,4999,1,'2026-11-01'),
    'ARB subscription readback rejects subscription-name mismatch');
$bad=$profile;$bad['subscription']['status']='paused';
blocked(fn()=>software_anet_subscription_profile_details($bad,'1234567',81,4999,1,'2026-11-01'),
    'ARB subscription readback rejects unknown status');
echo 'Software subscription gateway contract: PASS'.PHP_EOL;
