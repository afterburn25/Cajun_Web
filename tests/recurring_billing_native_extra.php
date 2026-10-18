<?php
/** Additional integrity and current-authority cases on the isolated native fixture. */
declare(strict_types=1);
use CajunWebPro\Foundation\RecurringBilling\{RecurringBillingTerms,RecurringBillingSchedule};
if(PHP_SAPI!=='cli'||!defined('CWP_BOOT')||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
rb_staff();$wrong=rb_fields(900);$wrong['amount_cents']=1002;rb_deny('Offer cannot reprice the agreed service',fn()=>recurring_billing_offer($wrong));$wrong=rb_fields(900);$wrong['cadence']='annual';rb_deny('Offer cannot change subscribed cadence',fn()=>recurring_billing_offer($wrong));$wrong=rb_fields(900);$wrong['project_id']=2;rb_deny('Offer cannot use another client project',fn()=>recurring_billing_offer($wrong));$wrong=rb_fields(900);$wrong['client_id']=2;$wrong['project_id']=2;rb_deny('Offer cannot use another clients subscription',fn()=>recurring_billing_offer($wrong));
$_GET['support']='synthetic';rb_deny('Staff support parameter refuses invoices',fn()=>rb_issue(300,900,3));rb_staff();$GLOBALS['cwp_support']=['synthetic'=>true];rb_deny('Active support context refuses staff reads',fn()=>rb_read(300));rb_staff();$_SESSION['last_activity']=time()-4000;rb_deny('Expired staff session refuses invoice replay',fn()=>rb_issue(300,400));rb_staff();
query('UPDATE '.table('studio_jobs').' SET client_id=2 WHERE id=1');rb_deny('Changed project owner refuses recurring agreement reads',fn()=>rb_read(300));rb_deny('Changed project owner refuses existing invoice balance',fn()=>ledger_balance(ledger_invoice($issued['invoice_id'])));query('UPDATE '.table('studio_jobs').' SET client_id=1 WHERE id=1');
$saved=one('SELECT * FROM '.table('recurring_billing_events').' WHERE id=?',[rb_id(301)]);query('UPDATE '.table('recurring_billing_events').' SET actor_staff_id=1 WHERE id=?',[rb_id(301)]);rb_deny('Ambiguous retained client consent attribution refuses invoice balance',fn()=>ledger_balance(ledger_invoice($issued['invoice_id'])));query('UPDATE '.table('recurring_billing_events').' SET actor_staff_id=NULL WHERE id=?',[rb_id(301)]);
db()->beginTransaction();query('DELETE FROM '.table('recurring_billing_invoices').' WHERE id=?',[rb_id(400)]);rb_deny('Missing permanent mapping refuses recurring invoice balance',fn()=>ledger_balance(ledger_invoice($issued['invoice_id'])));db()->rollBack();
// Independently reproduce lost-row recovery even when the editable reference changes.
$beforeLostBinding=rb_snapshot();$originReview=ledger_review_hash(ledger_invoice($issued['invoice_id']));$paymentReview=payments_invoice_hash(payments_invoice($issued['invoice_id']));
db()->beginTransaction();
try {
    query('DELETE FROM '.table('recurring_billing_invoices').' WHERE id=?',[rb_id(400)]);
    query('UPDATE '.table('studio_invoices').' SET reference=? WHERE id=?',['Changed legacy reference',$issued['invoice_id']]);
    rb_deny('Lost mapping plus changed reference cannot bypass retained recurring origin',fn()=>ledger_balance(ledger_invoice($issued['invoice_id'])));
    rb_deny('Lost mapping plus changed reference refuses a new manual receipt',fn()=>ledger_receive($issued['invoice_id'],100,'bank','Synthetic lost-origin receipt',gmdate('Y-m-d'),str_repeat('6',64),$originReview));
    rb_deny('Lost mapping plus changed reference refuses a new payment request',fn()=>payments_prepare($issued['invoice_id'],'full',7,$paymentReview,str_repeat('7',64)));
} finally {db()->rollBack();}
rb_ok('Restoring lost binding restores original invoice and audit exactly',rb_snapshot()===$beforeLostBinding&&ledger_balance(ledger_invoice($issued['invoice_id']))['due_cents']===1001);
db()->beginTransaction();
try {
    query('DELETE FROM '.table('settings').' WHERE name=?',['recurring_billing_schema_version']);
    query('UPDATE '.table('studio_invoices').' SET reference=? WHERE id=?',['Changed legacy reference',$issued['invoice_id']]);
    rb_deny('Missing version marker cannot bypass retained recurring mapping',fn()=>ledger_balance(ledger_invoice($issued['invoice_id'])));
    rb_deny('Missing version marker refuses recurring manual receipt',fn()=>ledger_receive($issued['invoice_id'],100,'bank','Synthetic missing marker receipt',gmdate('Y-m-d'),str_repeat('8',64),$originReview));
    rb_deny('Missing version marker refuses recurring payment request',fn()=>payments_prepare($issued['invoice_id'],'full',7,$paymentReview,str_repeat('9',64)));
    query('DELETE FROM '.table('recurring_billing_invoices').' WHERE id=?',[rb_id(400)]);
    rb_deny('Missing marker and mapping still consult independent recurring origin',fn()=>ledger_balance(ledger_invoice($issued['invoice_id'])));
} finally {db()->rollBack();}
rb_ok('Version marker restoration retains original recurring invoice and history',recurring_billing_ready()&&rb_snapshot()===$beforeLostBinding);
$origin=one('SELECT * FROM '.table('audit').' WHERE action=? AND detail LIKE ?',['recurring_billing.issued','% invoice '.$issued['invoice_id']]);
$mapping=one('SELECT * FROM '.table('recurring_billing_invoices').' WHERE id=?',[rb_id(400)]);
query('DELETE FROM '.table('recurring_billing_invoices').' WHERE id=?',[rb_id(400)]);
try {rb_deny('Retained issuance origin prevents another request duplicating a lost period',fn()=>rb_issue(300,977));}
finally {query('INSERT INTO '.table('recurring_billing_invoices').' (`'.implode('`,`',array_keys($mapping)).'`) VALUES ('.implode(',',array_fill(0,count($mapping),'?')).')',array_values($mapping));}
rb_ok('Lost period refusal retains original invoice and audit',rb_snapshot()===$beforeLostBinding);
db()->beginTransaction();
try {
    query('DELETE FROM '.table('audit').' WHERE id=?',[$origin['id']]);
    rb_deny('Missing independent issuance origin refuses intact invoice mapping',fn()=>ledger_balance(ledger_invoice($issued['invoice_id'])));
} finally {db()->rollBack();}
db()->beginTransaction();
try {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$origin['user_id'],$origin['action'],$origin['detail'],$origin['created_at']]);
    rb_deny('Ambiguous duplicate issuance origin refuses invoice use',fn()=>ledger_balance(ledger_invoice($issued['invoice_id'])));
} finally {db()->rollBack();}
rb_ok('Origin history restoration preserves original agreement invoice and audit',rb_snapshot()===$beforeLostBinding);

$schedule=recurring_billing_row(rb_id(300));query('UPDATE '.table('recurring_billing_schedules').' SET journal_json=? WHERE id=?',[str_repeat('x',RecurringBillingSchedule::MAX_BYTES+1),rb_id(300)]);rb_deny('Oversized retained agreement refuses before parsing',fn()=>rb_read(300));query('UPDATE '.table('recurring_billing_schedules').' SET journal_json=? WHERE id=?',[$schedule['journal_json'],rb_id(300)]);
$source=service_plans_row(rb_id(200),true);query('UPDATE '.table('service_subscriptions').' SET journal_sha256=? WHERE id=?',[str_repeat('f',64),rb_id(200)]);rb_deny('Corrupt current source refuses historical invoice use',fn()=>ledger_balance(ledger_invoice($issued['invoice_id'])));query('UPDATE '.table('service_subscriptions').' SET journal_sha256=? WHERE id=?',[$source['journal_sha256'],rb_id(200)]);
rb_deny('Negative invoice period refuses',fn()=>rb_issue(300,900,-1));rb_deny('Unrepresentable invoice period refuses',fn()=>rb_issue(300,900,120000));rb_deny('Future invoice period refuses',fn()=>rb_issue(300,900,1000));
$current=rb_read(300);$period=RecurringBillingTerms::fromArray($current->data()['terms'])->periodAt(time())['index']+1;
$pause=rb_action(300,902,'pause',['reason'=>'Arguments supplied in reverse field order','period_index'=>$period]);rb_ok('Canonical command input survives reverse argument order',rb_read(300)->json()===$pause->json());
rb_ok('Canonical reordered action retry remains a no-op',recurring_billing_change(rb_id(300),1,2,rb_id(902),'pause',['period_index'=>$period,'reason'=>'Arguments supplied in reverse field order'])->json()===$pause->json());
rb_ok('Later lifecycle does not invalidate originally issued invoice prefix',ledger_balance(ledger_invoice($issued['invoice_id']))['due_cents']===1001);rb_ok('Original invoice request replays after later lifecycle',recurring_billing_issue(rb_id(300),1,2,$history->fingerprint(),0,rb_id(400))['replayed']);
$s=service_plans_validate(service_plans_row(rb_id(200),true),true);service_plans_subscription_change(rb_id(200),1,$s->revision(),rb_id(903),'cancel',['reason'=>'Ended service'],$password);rb_deny('Cancelled source refuses new invoice',fn()=>rb_issue(300,900,3));rb_deny('Cancelled source refuses resuming billing',fn()=>rb_action(300,904,'resume',['period_index'=>$period,'reason'=>'Cannot resume cancelled service']));
rb_ok('Cancelled source retains invoice history',ledger_balance(ledger_invoice($issued['invoice_id']))['due_cents']===1001);rb_ok('Staff can end billing after the source is cancelled',rb_action(300,904,'end',['period_index'=>$period,'reason'=>'End billing after service cancellation'])->phase()==='ended');
$before=rb_snapshot();save_setting('recurring_billing_schema_version','');rb_deny('Setup refuses populated unrecognized recurring storage',fn()=>recurring_billing_migrate($password));rb_ok('Refused adoption preserves all existing rows',rb_snapshot()===$before);save_setting('recurring_billing_schema_version','1');

$mapping=one('SELECT * FROM '.table('recurring_billing_invoices').' WHERE id=?',[rb_id(400)]);$changed=json_decode($mapping['snapshot_json'],true);$changed['period']['due']='2099-01-01';$bytes=recurring_billing_json($changed);query('UPDATE '.table('recurring_billing_invoices').' SET snapshot_json=?,snapshot_sha256=? WHERE id=?',[$bytes,hash('sha256',$bytes),rb_id(400)]);rb_deny('Rehashed snapshot cannot invent another due date',fn()=>ledger_balance(ledger_invoice($issued['invoice_id'])));query('UPDATE '.table('recurring_billing_invoices').' SET snapshot_json=?,snapshot_sha256=? WHERE id=?',[$mapping['snapshot_json'],$mapping['snapshot_sha256'],rb_id(400)]);
foreach(['invoice_ledger_schema_version','service_plans_schema_version','payments_schema_version'] as $setting){save_setting($setting,'');rb_deny('Missing prerequisite refuses recurring invoice use: '.$setting,fn()=>ledger_balance(ledger_invoice($issued['invoice_id'])));save_setting($setting,'1');}
rb_historical_source(600,0);rb_historical_billing(700,600);$invoiceCount=rb_count('studio_invoices');rb_deny('Zero-price agreed period cannot create an invoice',fn()=>rb_issue(700,950));rb_ok('Zero-price refusal creates no debt or fabricated receipt',rb_count('studio_invoices')===$invoiceCount&&rb_count('payment_attempts')===0);
