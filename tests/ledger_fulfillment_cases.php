<?php
/** Additional native evidence cases, included by the retained ledger suite. Synthetic CI only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!defined('CWP_BOOT')||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
require_once CWP_CODE_ROOT.'/app/billing/ledger_fulfillment.php';
function le_read(int $invoice,int $client=1):array {return staff_locked(fn()=>staff_transaction(fn()=>ledger_fulfillment_evidence($invoice,$client)));}
function le_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;l_ok($name,true);return;}l_ok($name,false);}
function le_invoice(int $amount=1000,string $status='issued'):int {
    query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,instructions,status,created_at) VALUES (1,?,?,?,\'USD\',?,?,?)',['EVIDENCE-'.bin2hex(random_bytes(8)),'Synthetic fulfillment invoice',$amount,'Synthetic payment evidence only',$status,gmdate('Y-m-d H:i:s')]);
    return (int)db()->lastInsertId();
}
function le_tamper(int $id,string $field,mixed $value,callable $check):void {
    $allowed=['client_id','job_id','currency','kind','invoice_reference','invoice_title','invoice_total_cents','invoice_hash','external_hash','request_key','request_hash','received_on','method','private_note','reversal_of','actor_id'];
    if(!in_array($field,$allowed,true))throw new LogicException('Unexpected fixture field.');
    $old=one('SELECT * FROM '.table('invoice_payments').' WHERE id=?',[$id])[$field];
    query('UPDATE '.table('invoice_payments').' SET '.$field.'=? WHERE id=?',[$value,$id]);
    try{$check();}finally{query('UPDATE '.table('invoice_payments').' SET '.$field.'=? WHERE id=?',[$old,$id]);}
}
l_actor();
$obligation=le_invoice();$empty=le_read($obligation);
l_ok('Unpaid evidence cannot authorize a service purchase',!$empty['payment_evidence_ready']&&$empty['verified_cents']===0&&$empty['blockers']===['verified-payment-incomplete']);
le_deny('Evidence refuses a different client binding',fn()=>le_read($obligation,2));
le_deny('Evidence cannot escape its owning transaction',fn()=>ledger_fulfillment_evidence($obligation,1));
$part=l_receive(400,'Synthetic fulfillment partial',$obligation);$partial=le_read($obligation);
l_ok('Partial verified receipts remain incomplete',$partial['verified_cents']===400&&$partial['due_cents']===600&&!$partial['payment_evidence_ready']&&$partial['receipt_ids']===[$part]);
$finish=l_receive(600,'Synthetic fulfillment remainder',$obligation);$complete=le_read($obligation);
l_ok('Only intact fully verified external receipts satisfy payment evidence',$complete['payment_evidence_ready']&&$complete['verified_cents']===1000&&$complete['legacy_cents']===0&&$complete['receipt_ids']===[$part,$finish]);
l_ok('Evidence pins exact invoice and complete retained receipt history',$complete['invoice_sha256']===ledger_invoice_hash(ledger_invoice($obligation))&&preg_match('/\A[a-f0-9]{64}\z/',$complete['history_sha256'])&&$complete['review_sha256']!==$partial['review_sha256']);
$beforeEvidence=all('SELECT * FROM '.table('invoice_payments').' ORDER BY id');$beforeAudit=all('SELECT * FROM '.table('audit').' ORDER BY id');
l_ok('Repeated evidence review is deterministic and does not write history',le_read($obligation)===$complete&&all('SELECT * FROM '.table('invoice_payments').' ORDER BY id')===$beforeEvidence&&all('SELECT * FROM '.table('audit').' ORDER BY id')===$beforeAudit);
l_ok('Evidence excludes private references staff identities and sandbox data',!str_contains(json_encode($complete),'Synthetic fulfillment')&&!array_key_exists('actor_id',$complete)&&!array_key_exists('private_note',$complete)&&!array_key_exists('external_hash',$complete));
foreach(['client_id'=>2,'job_id'=>999,'currency'=>'EUR','invoice_reference'=>'ALTERED','invoice_title'=>'Altered','invoice_total_cents'=>1001,'invoice_hash'=>str_repeat('0',64),'external_hash'=>str_repeat('0',64),'request_key'=>null,'request_hash'=>'invalid','received_on'=>'2030-02-30','method'=>'sandbox','private_note'=>'x','reversal_of'=>$finish,'actor_id'=>999] as $field=>$value){
    le_tamper($part,$field,$value,fn()=>le_deny('Payment evidence rejects corrupted '.$field,fn()=>le_read($obligation)));
}
$origin=one('SELECT * FROM '.table('audit').' WHERE action=? AND detail=?',['payments.ledger.manual','Entry '.$part.' invoice '.$obligation.' amount 400 USD']);
query('UPDATE '.table('audit').' SET detail=? WHERE id=?',['Synthetic missing receipt origin',$origin['id']]);
try{le_deny('Missing original payment audit refuses fulfillment evidence',fn()=>le_read($obligation));}finally{query('UPDATE '.table('audit').' SET detail=? WHERE id=?',[$origin['detail'],$origin['id']]);}
query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');
try{$inactive=le_read($obligation);l_ok('Inactive client keeps balance history but blocks fulfillment evidence',!$inactive['payment_evidence_ready']&&$inactive['verified_cents']===1000&&in_array('client-inactive',$inactive['blockers'],true));}finally{query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');}
// Reuse the retained sandbox attempt as synthetic state evidence, never a production capture.
$trial=one('SELECT * FROM '.table('payment_attempts').' ORDER BY id LIMIT 1');
if(!$trial)throw new RuntimeException('Retained sandbox fixture missing.');
query('UPDATE '.table('payment_attempts').' SET invoice_id=? WHERE id=?',[$obligation,$trial['id']]);
try{
    foreach(['submitting','unknown','held'] as $state){
        query('UPDATE '.table('payment_attempts').' SET state=? WHERE id=?',[$state,$trial['id']]);$blocked=le_read($obligation);
        l_ok('Unresolved '.$state.' trial blocks otherwise verified evidence',!$blocked['payment_evidence_ready']&&$blocked['verified_cents']===1000&&in_array('payment-review-required',$blocked['blockers'],true));
    }
    save_setting('payment_attempts_schema_version','');
    try{l_ok('Missing trial marker cannot hide an existing unresolved attempt',in_array('payment-review-required',le_read($obligation)['blockers'],true));}finally{save_setting('payment_attempts_schema_version','1');}
    query('UPDATE '.table('payment_attempts').' SET state=\'captured\' WHERE id=?',[$trial['id']]);
    l_ok('Resolved sandbox capture adds no real payment evidence',le_read($obligation)===$complete);
}finally{query('UPDATE '.table('payment_attempts').' SET invoice_id=?,state=? WHERE id=?',[$trial['invoice_id'],$trial['state'],$trial['id']]);}
$rename=table('payment_attempts_saved');
db()->exec('RENAME TABLE '.table('payment_attempts').' TO '.$rename);
try{le_deny('Missing established attempt storage refuses fulfillment',fn()=>le_read($obligation));}finally{db()->exec('RENAME TABLE '.$rename.' TO '.table('payment_attempts'));}
$corrected=ledger_reverse($finish,'Synthetic fulfillment receipt correction',l_key(),l_review($obligation),$password);$afterCorrection=le_read($obligation);
l_ok('Reversed receipt cannot contribute to new fulfillment',$afterCorrection['verified_cents']===400&&!$afterCorrection['payment_evidence_ready']&&$afterCorrection['receipt_ids']===[$part]&&$afterCorrection['review_sha256']!==$complete['review_sha256']);
le_tamper($corrected,'reversal_of',999999,fn()=>le_deny('Orphan correction cannot reduce arbitrary receipt evidence',fn()=>le_read($obligation)));
$replacementReceipt=l_receive(600,'Synthetic replacement verified receipt',$obligation);$replacementEvidence=le_read($obligation);
l_ok('Replacement receipt restores coverage without erasing original correction',$replacementEvidence['payment_evidence_ready']&&$replacementEvidence['receipt_ids']===[$part,$replacementReceipt]&&$replacementEvidence['history_sha256']!==$complete['history_sha256']);
$legacyInvoice=le_invoice(1000,'paid');
$imported=staff_locked(fn()=>staff_transaction(fn()=>ledger_insert(ledger_invoice($legacyInvoice),1,'legacy',1000,'legacy','Synthetic imported paid status','')));
$importedEvidence=le_read($legacyInvoice);
l_ok('Imported paid total never becomes verified fulfillment money',$importedEvidence['paid_cents']===1000&&$importedEvidence['legacy_cents']===1000&&$importedEvidence['verified_cents']===0&&!$importedEvidence['payment_evidence_ready']&&$importedEvidence['receipt_ids']===[]);
le_tamper($imported,'kind','manual',fn()=>le_deny('Changing legacy kind cannot manufacture an original verified receipt',fn()=>le_read($legacyInvoice)));
$zero=le_invoice(0,'paid');$zeroEvidence=le_read($zero);
l_ok('Zero total is not proof of payment or a free-service policy',!$zeroEvidence['payment_evidence_ready']&&$zeroEvidence['blockers']===['no-charge-policy-required']);
$void=le_invoice(1000,'void');
l_ok('Void zero balance cannot qualify for fulfillment',!le_read($void)['payment_evidence_ready']&&in_array('invoice-void',le_read($void)['blockers'],true));
$unpaid=le_invoice();
query('UPDATE '.table('payment_attempts').' SET invoice_id=?,state=\'captured\' WHERE id=?',[$unpaid,$trial['id']]);
try{$sandboxOnly=le_read($unpaid);l_ok('Sandbox-only capture cannot settle or qualify an unpaid invoice',!$sandboxOnly['payment_evidence_ready']&&$sandboxOnly['verified_cents']===0&&$sandboxOnly['paid_cents']===0);}finally{query('UPDATE '.table('payment_attempts').' SET invoice_id=?,state=? WHERE id=?',[$trial['invoice_id'],$trial['state'],$trial['id']]);}
