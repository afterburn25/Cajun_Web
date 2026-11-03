<?php
/** Exercise real routing services with isolated database reads; no gateway traffic. */
declare(strict_types=1);
define('CWP_BOOT', true);
define('CWP_CODE_ROOT', dirname(__DIR__).'/site');
$fixture = ['prepared'=>false, 'charge'=>null, 'origin'=>null, 'reads'=>[]];
function setting(string $name, mixed $default=null): mixed {
    global $fixture;
    return match ($name) {
        'license_finance_gateway_schema_version' => $fixture['prepared'] ? '3' : null,
        'staff_schema_version' => '1',
        'studio_schema_version' => '2',
        'payments_schema_version' => '2',
        'software_gateway_mode' => 'production',
        default => $default,
    };
}
function config(): array { return ['db'=>['prefix'=>'isolated_']]; }
function table(string $name): string { return 'isolated_'.$name; }
function one(string $sql, array $params=[]): ?array {
    global $fixture;
    $fixture['reads'][]=$sql;
    if(str_contains($sql, 'information_schema.tables')) return ['TABLE_NAME'=>$params[0]];
    if(str_contains($sql, 'information_schema.columns')) return ['COLUMN_NAME'=>$params[1]];
    if($sql==='SELECT value FROM isolated_settings WHERE name=?'
        &&$params===['invoice_ledger_schema_version']) return ['value'=>'1'];
    if(!$fixture['prepared']) throw new RuntimeException('Optional finance storage was queried before preparation.');
    if($sql==='SELECT * FROM isolated_license_finance_charges WHERE invoice_id=?') return $fixture['charge'];
    if($sql==='SELECT invoice_id,offer_id,amount_cents FROM isolated_license_finance_charges WHERE transaction_id=?') return $fixture['origin'];
    if(str_contains($sql, 'FROM isolated_license_finance_subscription_receipts')
        ||str_contains($sql, 'FROM isolated_license_finance_reversals')) return null;
    throw new RuntimeException('Unexpected database read: '.$sql);
}
require CWP_CODE_ROOT.'/app/billing/finance_payment_review.php';

$capture=['messages'=>['resultCode'=>'Ok'], 'transaction'=>[
    'transId'=>'600000001', 'transactionType'=>'authCaptureTransaction',
    'transactionStatus'=>'settledSuccessfully', 'authAmount'=>'12.50',
    'order'=>['invoiceNumber'=>'CWP-42'],
]];
$refund=['messages'=>['resultCode'=>'Ok'], 'transaction'=>[
    'transId'=>'700000001', 'transactionType'=>'refundTransaction',
    'refTransId'=>'600000001', 'authAmount'=>'12.50',
]];
$void=$refund;
$void['transaction']['transactionType']='voidTransaction';
$checks=[];
function check_case(string $name, callable $test): void {
    global $checks;
    try { if(!$test()) throw new RuntimeException('Unexpected routing result.'); $checks[]=['name'=>$name,'passed'=>true]; }
    catch(Throwable $error) { $checks[]=['name'=>$name,'passed'=>false,'error'=>$error->getMessage()]; }
}
check_case('Software capture does not require optional finance tables',
    fn()=>license_finance_charge_webhook_review($capture,'600000001')==='finance_payment_unmatched');
check_case('Unprepared finance capture performs no database query', fn()=>$fixture['reads']===[]);
check_case('Unprepared financing declines reversal routing',
    fn()=>license_finance_payment_reversal_review($refund,'700000001','net.authorize.payment.refund.created')==='finance_reversal_unmatched');
$fixture['prepared']=true;
check_case('Prepared financing leaves another product capture unmatched',
    fn()=>license_finance_charge_webhook_review($capture,'600000001')==='finance_payment_unmatched');
check_case('Software refund does not require finance production activation',
    fn()=>license_finance_payment_reversal_review($refund,'700000001','net.authorize.payment.refund.created')==='finance_reversal_unmatched');
check_case('Software void does not require finance production activation',
    fn()=>license_finance_payment_reversal_review($void,'700000001','net.authorize.payment.void.created')==='finance_reversal_unmatched');
$fixture['charge']=['status'=>'paid','transaction_id'=>'600000001','amount_cents'=>1250];
check_case('Recognized paid financing capture remains idempotent',
    fn()=>license_finance_charge_webhook_review($capture,'600000001')==='finance_payment_replay');
$mismatch=$capture;
$mismatch['transaction']['authAmount']='12.51';
check_case('Recognized financing capture still enforces exact amount',
    fn()=>license_finance_charge_webhook_review($mismatch,'600000001')==='finance_payment_mismatch');
$fixture['origin']=['invoice_id'=>42,'offer_id'=>str_repeat('a',32),'amount_cents'=>1250];
check_case('Recognized financing reversal still requires production activation', function()use($refund):bool {
    try { license_finance_payment_reversal_review($refund,'700000001','net.authorize.payment.refund.created'); }
    catch(RuntimeException $error) { return str_contains($error->getMessage(),'Production financing checkout is locked'); }
    return false;
});
$bad=$refund;
$bad['transaction']['transId']='700000002';
check_case('Financing reversal keeps exact gateway transaction validation', function()use($bad):bool {
    try { license_finance_payment_reversal_review($bad,'700000001','net.authorize.payment.refund.created'); }
    catch(RuntimeException $error) { return str_contains($error->getMessage(),'verified original transaction'); }
    return false;
});
$passed=!in_array(false,array_column($checks,'passed'),true);
echo json_encode(['passed'=>$passed,'scope'=>'Actual PHP services with isolated read-only database doubles; no native SQL or real gateway evidence','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed?0:1);
