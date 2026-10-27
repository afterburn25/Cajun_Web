<?php
/** Focused financed-license cases executed inside recurring_billing_native.php's disposable MariaDB fixture. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!defined('CWP_BOOT')||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);

require_once CWP_CODE_ROOT.'/app/licenses/financing_offers.php';
require_once CWP_CODE_ROOT.'/app/licenses/finance_installments.php';
require_once CWP_CODE_ROOT.'/app/billing/finance_subscription.php';

rb_staff();
save_setting('license_schema_version','3');
save_setting('license_invoice_cutover','0');
license_financing_prepare($password);
license_finance_offers_prepare($password);
license_finance_gateway_prepare($password);
rb_ok('Financing native setup creates v2 gateway replacement storage',
    license_financing_feature_state()===true&&license_finance_offers_ready()&&license_finance_gateway_ready()
    &&setting('license_finance_gateway_schema_version')==='2');

$licenseKey=license_create(1,'WEBSITE-FINANCE-NATIVE',1,false,null);
$license=license_get($licenseKey);$licenseId=(int)$license['id'];
license_register_domain($licenseId,'finance-native.example',1);

rb_historical_source(9000,2500);
$fields=rb_fields(9100,9000);
$fields['anchor']=gmdate('Y-m-d',time()+2*86400);
$fields['due_after_days']=0;
$offeredSchedule=recurring_billing_offer($fields);
rb_ok('Financing native schedule begins as exact future offered terms',
    $offeredSchedule->phase()==='offered'&&$offeredSchedule->data()['terms']['anchor']===$fields['anchor']);

$offer=license_finance_offer_create(
    $licenseId,rb_id(9100),'Synthetic financed website',
    10000,0,10000,0,0,4,5,1,7,
    ['resale'=>false,'distribution'=>false,'exclusive_resale'=>false,'exclusive_distribution'=>false],
    'Synthetic financed-site agreement retained only for native acceptance. Site use is licensed to this customer; resale and distribution are not granted.',
    $password
);
rb_client();
$signed=license_finance_offer_sign((string)$offer['id'],1,'Recurring client 1',true,true);
rb_ok('Client signature activates financing without an initial payment when zero down',
    ($signed['offer']['status']??'')==='signed'&&($signed['payment']??'sentinel')===null);
rb_staff();
$plan=license_financing_sync($licenseId);
rb_ok('Signed zero-down financing begins active on the same website license',
    ($plan['state']??'')==='active'&&(int)license_get($licenseKey)['id']===$licenseId);

// A zero-down agreement has no initial invoice to collect. Signing it must
// not depend on payment-link storage being enabled.
$zeroKey=license_create(1,'WEBSITE-FINANCE-ZERO-NOPAY',1,false,null);
$zeroLicense=(int)license_get($zeroKey)['id'];
license_register_domain($zeroLicense,'finance-zero-nopay.example',1);
rb_historical_source(9001,2500);
$zeroFields=rb_fields(9101,9001);
$zeroFields['anchor']=gmdate('Y-m-d',time()+3*86400);
$zeroFields['due_after_days']=0;
recurring_billing_offer($zeroFields);
$zeroOffer=license_finance_offer_create(
    $zeroLicense,rb_id(9101),'Zero-down financing without payment-link storage',
    10000,0,10000,0,0,4,5,1,7,
    ['resale'=>false,'distribution'=>false,'exclusive_resale'=>false,'exclusive_distribution'=>false],
    'Synthetic zero-down agreement verifies that no initial invoice or payment link is required merely to sign financing.',
    $password
);
save_setting('payments_schema_version','');
rb_client();
$zeroSigned=license_finance_offer_sign((string)$zeroOffer['id'],1,'Recurring client 1',true,true);
rb_ok('Zero-down financing signs and activates even when payment-link storage is unavailable',
    ($zeroSigned['offer']['status']??'')==='signed'&&($zeroSigned['payment']??'sentinel')===null
    &&license_financing_authorized($zeroLicense)===true
    &&one('SELECT activation_invoice_id,payment_request_id FROM '.table('license_finance_offers').' WHERE id=?',
        [$zeroOffer['id']])===['activation_invoice_id'=>null,'payment_request_id'=>null]);
rb_staff();
save_setting('payments_schema_version','2');

$now=gmdate('Y-m-d H:i:s');
query('INSERT INTO '.table('license_finance_subscriptions').
    ' (offer_id,gateway_subscription_id,customer_profile_id,payment_profile_id,interval_months,amount_cents,total_occurrences,period_offset,replacement_count,start_date,status,created_at,updated_at)'.
    ' VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
    [$offer['id'],'810001','910001','920001',1,2500,4,0,0,$fields['anchor'],'active',$now,$now]);

// Recreate the published v1 finance-gateway shape with one retained subscription,
// then prove the explicit owner migration adds replacement metadata without losing it.
db()->exec('DROP TABLE '.table('license_finance_subscription_history'));
db()->exec('ALTER TABLE '.table('license_finance_subscriptions').' DROP COLUMN replacement_count, DROP COLUMN period_offset');
save_setting('license_finance_gateway_schema_version','1');
license_finance_gateway_prepare($password);
$migratedSub=license_finance_subscription_row((string)$offer['id']);
rb_ok('Finance gateway v1 to v2 migration preserves existing ARB identity and defaults offset metadata',
    license_finance_gateway_ready()
    &&($migratedSub['gateway_subscription_id']??'')==='810001'
    &&(int)($migratedSub['period_offset']??-1)===0
    &&(int)($migratedSub['replacement_count']??-1)===0
    &&license_finance_gateway_table_present('license_finance_subscription_history'));

$schedule=recurring_billing_row((string)$offer['schedule_id']);
$doc=recurring_billing_validate($schedule);
$terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);
$p0=$terms->period(0);
$period0At=strtotime($p0['start'].' 12:00:00 UTC');
if($period0At===false)throw new RuntimeException('Synthetic financing period timestamp unavailable.');
$issued0=license_finance_issue_period((string)$offer['id'],0,$period0At);
$invoice0=ledger_invoice((int)$issued0['invoice_id']);
rb_ok('Begun signed financing period issues the exact real ledger invoice',
    (int)$invoice0['amount_cents']===2500&&$invoice0['status']==='issued');

$lateDate=(new DateTimeImmutable($p0['due'].'T00:00:00',new DateTimeZone('UTC')))
    ->modify('+6 days')->format('Y-m-d');
$suspended=license_financing_sync($licenseId,$lateDate);
rb_ok('Issued unpaid installment past the five-day grace suspends payment-dependent financing access',
    ($suspended['state']??'')==='suspended'
    &&(int)license_get($licenseKey)['is_active']===1
    &&($suspended['evaluation']['overdue_period']??null)===0);
$access=license_financing_access($licenseId);
rb_ok('Suspended financing exposes the stable delinquency denial reason',
    $access===['authorized'=>false,'state'=>'suspended',
        'reason'=>'Financing payment is past the agreed grace period']);

query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE offer_id=?',
    ['billing_suspended',gmdate('Y-m-d H:i:s'),$offer['id']]);
$entry0=ledger_insert($invoice0,0,'gateway',2500,'authorize_net','Synthetic catch-up financing receipt',$lateDate,
    null,null,hash('sha256','finance-native-period-0'));
query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',
    ['paid','Synthetic financing catch-up receipt '.$entry0,gmdate('Y-m-d H:i:s'),$invoice0['id']]);
$restored=license_financing_sync($licenseId,$lateDate);
rb_ok('Catch-up payment restores the same financed license after suspension',
    ($restored['state']??'')==='active'&&license_get($licenseKey)['license_key']===$licenseKey);

$replacement=license_finance_subscription_replacement_contract(license_finance_offer_row((string)$offer['id']));
$p1=$terms->period(1);
rb_ok('Replacement ARB contract begins at the first future unpaid signed installment',
    $replacement['period_offset']===1&&$replacement['occurrences']===3
    &&$replacement['start_date']===$p1['start']&&$replacement['amount_cents']===2500);

$reserved=license_finance_subscription_replacement_reserve((string)$offer['id']);
rb_ok('Suspended ARB identity is retained while replacement enrollment is reserved',
    ($reserved['gateway_subscription_id']??'')==='810001'&&($reserved['status']??'')==='replacing');
$replacementState=license_finance_subscription_replacement_finalize(
    (string)$offer['id'],'810001','810002',$replacement,
    ['customer_profile_id'=>'910002','payment_profile_id'=>'920002'],'pending_first_payment'
);
$currentSub=license_finance_subscription_row((string)$offer['id']);
$oldSub=license_finance_subscription_history_row('810001');
rb_ok('Replacement ARB archives the suspended identity and activates only remaining periods',
    $replacementState==='pending_first_payment'
    &&($currentSub['gateway_subscription_id']??'')==='810002'
    &&(int)$currentSub['period_offset']===1&&(int)$currentSub['total_occurrences']===3
    &&(int)$currentSub['replacement_count']===1
    &&($oldSub['terminal_status']??'')==='billing_suspended'
    &&($oldSub['replaced_by_gateway_subscription_id']??'')==='810002');
rb_ok('Delayed lifecycle event from retired ARB identity is historical and cannot mutate current billing',
    license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.suspended','810001')==='historical_subscription');

$paidInvoices=[(int)$invoice0['id']];
for($period=1;$period<4;$period++){
    $facts=$terms->period($period);$at=strtotime($facts['start'].' 12:00:00 UTC');
    if($at===false)throw new RuntimeException('Synthetic financing period timestamp unavailable.');
    $issued=license_finance_issue_period((string)$offer['id'],$period,$at);
    $invoice=ledger_invoice((int)$issued['invoice_id']);
    $entry=ledger_insert($invoice,0,'gateway',2500,'authorize_net','Synthetic financing payoff period '.$period,
        $facts['due'],null,null,hash('sha256','finance-native-period-'.$period));
    query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',
        ['paid','Synthetic financing payoff receipt '.$entry,gmdate('Y-m-d H:i:s'),$invoice['id']]);
    $paidInvoices[$period]=(int)$invoice['id'];
}
$last=$terms->period(3);
$afterLast=(new DateTimeImmutable($last['due'].'T00:00:00',new DateTimeZone('UTC')))
    ->modify('+6 days')->format('Y-m-d');
$paidOff=license_financing_sync($licenseId,$afterLast);
rb_ok('All signed installments permanently unlock the financing payment dependency',
    ($paidOff['state']??'')==='paid_off'&&is_string($paidOff['paid_off_at'])&&$paidOff['paid_off_at']!==''
    &&license_financing_authorized($licenseId)===true);
$paidAccess=license_financing_access($licenseId);
rb_ok('Paid-off financing exposes permanent authorization with no payment denial reason',
    $paidAccess===['authorized'=>true,'state'=>'paid_off','reason'=>null]);

query('DELETE FROM '.table('invoice_payments').' WHERE invoice_id=?',[$paidInvoices[3]]);
query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=NULL WHERE id=?',
    ['issued','Synthetic post-payoff correction',$paidInvoices[3]]);
$afterCorrection=license_financing_sync($licenseId,$afterLast);
rb_ok('Paid-off financing boundary ignores later billing correction and can never reapply the payment lock',
    ($afterCorrection['state']??'')==='paid_off'&&$afterCorrection['paid_off_at']===$paidOff['paid_off_at']
    &&license_financing_authorized($licenseId)===true);

$rights=license_financing_rights_for_license($licenseId);
rb_ok('Default financed-site commercial rights remain site-use only after payoff',
    $rights!==null&&!$rights['resale']&&!$rights['distribution']
    &&!$rights['exclusive_resale']&&!$rights['exclusive_distribution']);

// Recreate the published v1 financing shape around a populated paid-off plan,
// then prove the explicit v1->v2 migration preserves that plan while adding
// immutable commercial-rights payment evidence.
db()->exec('DROP TABLE '.table('license_financing_rights_grants'));
save_setting('license_financing_schema_version','1');
license_financing_prepare($password);
$upgradedPlan=license_financing_plan($licenseId);
rb_ok('Populated financed-license v1 upgrades additively to v2 rights evidence storage',
    license_financing_ready()
    &&setting('license_financing_schema_version')==='2'
    &&license_financing_rights_grants_table_present()
    &&($upgradedPlan['state']??'')==='paid_off'
    &&($upgradedPlan['paid_off_at']??null)===$paidOff['paid_off_at']);

$installmentRejected=false;
try{
    license_financing_grant_rights(
        $licenseId,
        ['resale'=>true,'distribution'=>false,'exclusive_resale'=>false,'exclusive_distribution'=>false],
        $paidInvoices[0],
        'Synthetic financing installment must not buy resale rights',
        $password
    );
}catch(InvalidArgumentException|RuntimeException $expected){$installmentRejected=true;}
rb_ok('Normal financing installment cannot be reused as commercial-rights payment',$installmentRejected);

$rightsInvoiceNow=gmdate('Y-m-d H:i:s');
query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,instructions,created_at) VALUES (?,?,?,?,?,?,?)',
    [(int)$offer['job_id'],'RIGHTS-'.strtoupper(bin2hex(random_bytes(5))),
     'Commercial resale rights for financed license',5000,'USD',
     'Separate paid commercial-rights addendum for financed license '.$licenseId,$rightsInvoiceNow]);
$rightsInvoiceId=(int)db()->lastInsertId();
$rightsInvoice=ledger_invoice($rightsInvoiceId);
$rightsPayment=ledger_insert($rightsInvoice,1,'manual',5000,'synthetic',
    'Synthetic paid commercial-rights invoice',gmdate('Y-m-d'),
    null,null,hash('sha256','finance-native-rights-'.$rightsInvoiceId));
query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',
    ['paid','Synthetic commercial-rights receipt '.$rightsPayment,gmdate('Y-m-d H:i:s'),$rightsInvoiceId]);
$granted=license_financing_grant_rights(
    $licenseId,
    ['resale'=>true,'distribution'=>false,'exclusive_resale'=>false,'exclusive_distribution'=>false],
    $rightsInvoiceId,
    'Paid resale-rights addendum retained in the same customer invoice ledger',
    $password
);
rb_ok('Separate fully paid same-customer invoice can grant post-sale resale rights',
    (int)$granted['allow_resale']===1&&(int)$granted['allow_distribution']===0
    &&(int)$granted['exclusive_resale']===0&&(int)$granted['exclusive_distribution']===0);
$grantEvidence=one('SELECT license_id,invoice_id,allow_resale,allow_distribution,exclusive_resale,exclusive_distribution,evidence,granted_by FROM '.
    table('license_financing_rights_grants').' WHERE invoice_id=?',[$rightsInvoiceId]);
rb_ok('Paid commercial-rights invoice is retained as immutable single-use grant evidence',
    $grantEvidence!==null
    &&(int)$grantEvidence['license_id']===$licenseId
    &&(int)$grantEvidence['invoice_id']===$rightsInvoiceId
    &&(int)$grantEvidence['allow_resale']===1
    &&(int)$grantEvidence['allow_distribution']===0
    &&(int)$grantEvidence['exclusive_resale']===0
    &&(int)$grantEvidence['exclusive_distribution']===0
    &&(int)$grantEvidence['granted_by']===1);

$reuseRejected=false;
try{
    license_financing_grant_rights(
        $licenseId,
        ['resale'=>true,'distribution'=>true,'exclusive_resale'=>false,'exclusive_distribution'=>false],
        $rightsInvoiceId,
        'Attempt to reuse paid resale invoice for later distribution rights',
        $password
    );
}catch(InvalidArgumentException|RuntimeException $expected){$reuseRejected=true;}
rb_ok('Consumed commercial-rights invoice cannot be reused to add broader rights',$reuseRejected);

$removalRejected=false;
try{
    license_financing_grant_rights(
        $licenseId,
        ['resale'=>false,'distribution'=>false,'exclusive_resale'=>false,'exclusive_distribution'=>false],
        $rightsInvoiceId,
        'Attempt to remove an already paid commercial right',
        $password
    );
}catch(InvalidArgumentException|RuntimeException $expected){$removalRejected=true;}
rb_ok('Paid commercial rights remain monotonic and cannot be silently removed',$removalRejected);
