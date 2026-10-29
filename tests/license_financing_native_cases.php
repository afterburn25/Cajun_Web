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
rb_ok('Financing native setup creates v3 gateway replacement and reversal storage',
    license_financing_feature_state()===true&&license_finance_offers_ready()&&license_finance_gateway_ready()
    &&setting('license_finance_gateway_schema_version')==='3'
    &&license_finance_gateway_table_present('license_finance_reversals'));

db()->exec('ALTER TABLE '.table('license_financing_rights_grants').' DROP INDEX license_history, ADD INDEX license_history(license_id,created_at,id)');
rb_deny('Financing setup rejects an expanded nonunique rights-history index',
    fn()=>license_financing_prepare($password));
db()->exec('ALTER TABLE '.table('license_financing_rights_grants').' DROP INDEX license_history, ADD INDEX license_history(license_id,id)');
license_financing_prepare($password);
rb_ok('Restored financing rights-history index is accepted on repeated setup',license_financing_ready());

$licenseKey=license_create(1,'WEBSITE-FINANCE-NATIVE',1,false,null);
$license=license_get($licenseKey);$licenseId=(int)$license['id'];
license_register_domain($licenseId,'finance-native.example',1);

rb_historical_source(19000,2500);
$fields=rb_fields(19100,19000);
$fields['anchor']=gmdate('Y-m-d',time()+2*86400);
$fields['due_after_days']=0;
$offeredSchedule=recurring_billing_offer($fields);
rb_ok('Financing native schedule begins as exact future offered terms',
    $offeredSchedule->phase()==='offered'&&$offeredSchedule->data()['terms']['anchor']===$fields['anchor']);

rb_deny('Initial financing agreement cannot grant resale or distribution rights before separate paid evidence',
    fn()=>license_finance_offer_create(
        $licenseId,rb_id(19100),'Synthetic financed website with premature resale',
        10000,0,10000,0,0,4,5,1,7,
        ['resale'=>true,'distribution'=>false,'exclusive_resale'=>false,'exclusive_distribution'=>false],
        'Synthetic financed-site agreement that must remain site-use only until separate commercial-rights payment is verified.',
        $password
    ));

$offer=license_finance_offer_create(
    $licenseId,rb_id(19100),'Synthetic financed website',
    10000,0,10000,0,0,4,5,1,7,
    ['resale'=>false,'distribution'=>false,'exclusive_resale'=>false,'exclusive_distribution'=>false],
    'Synthetic financed-site agreement retained only for native acceptance. Site use is licensed to this customer; resale and distribution are not granted.',
    $password
);
$originalOfferTerms=$offer['terms_json'];$originalOfferHash=$offer['terms_sha256'];
$legacyOfferTerms=json_decode($originalOfferTerms,true,16,JSON_THROW_ON_ERROR);
$legacyOfferTerms['rights']['resale']=true;
$legacyOfferJson=json_encode($legacyOfferTerms,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
query('UPDATE '.table('license_finance_offers').' SET terms_json=?,terms_sha256=? WHERE id=?',
    [$legacyOfferJson,hash('sha256',$legacyOfferJson),$offer['id']]);
rb_client();
rb_deny('Coherently retained pre-patch finance offer with unpaid resale rights cannot be signed',
    fn()=>license_finance_offer_sign((string)$offer['id'],1,'Recurring client 1',true,true));
rb_staff();
query('UPDATE '.table('license_finance_offers').' SET terms_json=?,terms_sha256=? WHERE id=?',
    [$originalOfferTerms,$originalOfferHash,$offer['id']]);
rb_client();
$signed=license_finance_offer_sign((string)$offer['id'],1,'Recurring client 1',true,true);
rb_ok('Zero-down client signature persists signed offer status',($signed['offer']['status']??'')==='signed');
rb_ok('Zero-down client signature returns explicit payment field',array_key_exists('payment',$signed));
rb_ok('Zero-down client signature requires no initial payment request',$signed['payment']===null);
rb_staff();
$plan=license_financing_sync($licenseId);
rb_ok('Signed zero-down financing begins active on the same website license',
    ($plan['state']??'')==='active'&&(int)license_get($licenseKey)['id']===$licenseId);

// A zero-down agreement has no initial invoice to collect. Signing it must
// not depend on payment-link storage being enabled.
$zeroKey=license_create(1,'WEBSITE-FINANCE-ZERO-NOPAY',1,false,null);
$zeroLicense=(int)license_get($zeroKey)['id'];
license_register_domain($zeroLicense,'finance-zero-nopay.example',1);
rb_historical_source(19010,2500);
$zeroFields=rb_fields(19101,19010);
$zeroFields['anchor']=gmdate('Y-m-d',time()+3*86400);
$zeroFields['due_after_days']=0;
recurring_billing_offer($zeroFields);
$zeroOffer=license_finance_offer_create(
    $zeroLicense,rb_id(19101),'Zero-down financing without payment-link storage',
    10000,0,10000,0,0,4,5,1,7,
    ['resale'=>false,'distribution'=>false,'exclusive_resale'=>false,'exclusive_distribution'=>false],
    'Synthetic zero-down agreement verifies that no initial invoice or payment link is required merely to sign financing.',
    $password
);
save_setting('payments_schema_version','');
rb_client();
$zeroSigned=license_finance_offer_sign((string)$zeroOffer['id'],1,'Recurring client 1',true,true);
rb_ok('Zero-down financing signs and activates even when payment-link storage is unavailable',
    ($zeroSigned['offer']['status']??'')==='signed'&&array_key_exists('payment',$zeroSigned)&&$zeroSigned['payment']===null
    &&license_financing_authorized($zeroLicense)===true
    &&one('SELECT activation_invoice_id,payment_request_id FROM '.table('license_finance_offers').' WHERE id=?',
        [$zeroOffer['id']])===['activation_invoice_id'=>null,'payment_request_id'=>null]);
rb_staff();
save_setting('payments_schema_version','2');

// A signed agreement with a real down payment is still legally signed even
// if payment-link infrastructure is temporarily unavailable. The client must
// see payment setup as pending rather than a false signature failure.
$downKey=license_create(1,'WEBSITE-FINANCE-DOWN-PENDING',1,false,null);
$downLicense=(int)license_get($downKey)['id'];
license_register_domain($downLicense,'finance-down-pending.example',1);
rb_historical_source(19020,2500);
$downFields=rb_fields(19102,19020);
$downFields['anchor']=gmdate('Y-m-d',time()+4*86400);
$downFields['due_after_days']=0;
recurring_billing_offer($downFields);
$downOffer=license_finance_offer_create(
    $downLicense,rb_id(19102),'Down-payment financing with delayed payment-link setup',
    10000,2000,8000,2000,0,4,5,1,7,
    ['resale'=>false,'distribution'=>false,'exclusive_resale'=>false,'exclusive_distribution'=>false],
    'Synthetic down-payment agreement verifies that signature evidence survives temporary payment-link unavailability.',
    $password
);
save_setting('payments_schema_version','');
rb_client();
$downSigned=license_finance_offer_sign((string)$downOffer['id'],1,'Recurring client 1',true,true);
rb_ok('Down-payment agreement stays signed when payment-link storage is temporarily unavailable',
    ($downSigned['offer']['status']??'')==='signed'
    &&($downSigned['payment']??null)===null
    &&($downSigned['payment_pending']??false)===true
    &&one('SELECT status,activation_invoice_id,payment_request_id FROM '.table('license_finance_offers').' WHERE id=?',
        [$downOffer['id']])===['status'=>'signed','activation_invoice_id'=>null,'payment_request_id'=>null]
    &&license_financing_access($downLicense)===[
        'authorized'=>false,'state'=>'pending_initial','reason'=>'Financing initial payment is still required'
    ]);
rb_staff();
save_setting('payments_schema_version','2');
$downPayment=license_finance_offer_payment_prepare((string)$downOffer['id'],1);
$downPaymentReplay=license_finance_offer_payment_prepare((string)$downOffer['id'],1);
$downOfferRow=license_finance_offer_row((string)$downOffer['id']);
$downInvoice=ledger_invoice((int)$downOfferRow['activation_invoice_id']);
rb_ok('Delayed down-payment setup creates one exact initial invoice and one reusable current payment link',
    is_array($downPayment)&&is_array($downPaymentReplay)
    &&(int)$downPayment['id']===(int)$downPaymentReplay['id']
    &&(int)$downInvoice['amount_cents']===2000&&$downInvoice['status']==='issued'
    &&(int)query('SELECT COUNT(*) FROM '.table('studio_invoices').' WHERE id=?',[(int)$downInvoice['id']])->fetchColumn()===1
    &&(int)query('SELECT COUNT(*) FROM '.table('payment_requests').' WHERE invoice_id=? AND purpose=?',
        [(int)$downInvoice['id'],'financing'])->fetchColumn()===1);

$downReceipt=ledger_insert($downInvoice,1,'manual',2000,'synthetic',
    'Synthetic verified financing down payment',gmdate('Y-m-d'),null,null,
    hash('sha256','finance-native-down-payment-'.$downInvoice['id']));
query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',
    ['paid','Synthetic down-payment receipt '.$downReceipt,gmdate('Y-m-d H:i:s'),$downInvoice['id']]);
query('UPDATE '.table('payment_requests')." SET status='superseded' WHERE invoice_id=? AND status='prepared'",
    [$downInvoice['id']]);
$downActive=license_financing_sync($downLicense);
rb_ok('Verified initial financing payment activates the retained financed license',
    ($downActive['state']??'')==='active'&&license_financing_authorized($downLicense)===true);

$downPaidInvoice=ledger_invoice((int)$downInvoice['id']);
$downReversal=ledger_reverse(
    $downReceipt,
    'Synthetic financing down-payment correction before payoff',
    hash('sha256','finance-native-down-reversal-'.$downReceipt),
    ledger_review_hash($downPaidInvoice),
    $password
);
$downReopened=license_financing_plan($downLicense);
rb_ok('Reversing the initial payment before payoff immediately restores the initial-payment lock',
    $downReversal>0
    &&($downReopened['state']??'')==='pending_initial'
    &&license_financing_access($downLicense)===[
        'authorized'=>false,'state'=>'pending_initial','reason'=>'Financing initial payment is still required'
    ]);

$downInvoiceAgain=ledger_invoice((int)$downInvoice['id']);
$downRepayment=ledger_insert($downInvoiceAgain,1,'manual',2000,'synthetic',
    'Synthetic replacement financing down payment',gmdate('Y-m-d'),null,null,
    hash('sha256','finance-native-down-repayment-'.$downInvoice['id']));
query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',
    ['paid','Synthetic replacement down-payment receipt '.$downRepayment,gmdate('Y-m-d H:i:s'),$downInvoice['id']]);
$downRestored=license_financing_sync($downLicense);
rb_ok('Replacement initial payment restores the same financed license and key',
    ($downRestored['state']??'')==='active'
    &&license_financing_authorized($downLicense)===true
    &&license_get($downKey)['license_key']===$downKey);

// The legal signature commits before payment request preparation. Simulate a
// transient ledger-readiness outage at that later boundary and prove recovery
// reuses the one retained initial invoice without a second signature.
$recoverKey=license_create(1,'WEBSITE-FINANCE-SIGN-RECOVER',1,false,null);
$recoverLicense=(int)license_get($recoverKey)['id'];
license_register_domain($recoverLicense,'finance-sign-recover.example',1);
rb_historical_source(19030,2500);
$recoverFields=rb_fields(19103,19030);
$recoverFields['anchor']=gmdate('Y-m-d',time()+5*86400);
$recoverFields['due_after_days']=0;
recurring_billing_offer($recoverFields);
$recoverOffer=license_finance_offer_create(
    $recoverLicense,rb_id(19103),'Signed agreement with transient payment setup outage',
    10000,2000,8000,2000,0,4,5,1,7,
    ['resale'=>false,'distribution'=>false,'exclusive_resale'=>false,'exclusive_distribution'=>false],
    'Synthetic agreement proves a transient payment infrastructure failure cannot roll back or misrepresent a committed client signature.',
    $password
);
save_setting('invoice_ledger_schema_version','');
rb_client();
$recoverSigned=license_finance_offer_sign((string)$recoverOffer['id'],1,'Recurring client 1',true,true);
rb_staff();
$recoverRow=license_finance_offer_row((string)$recoverOffer['id']);
rb_ok('Committed financing signature survives later payment infrastructure failure',
    ($recoverSigned['offer']['status']??'')==='signed'
    &&($recoverSigned['payment']??null)===null
    &&($recoverSigned['payment_pending']??false)===true
    &&$recoverRow['status']==='signed'
    &&$recoverRow['activation_invoice_id']!==null
    &&$recoverRow['payment_request_id']===null
    &&license_financing_access($recoverLicense)===[
        'authorized'=>false,'state'=>'pending_initial','reason'=>'Financing initial payment is still required'
    ]);
save_setting('invoice_ledger_schema_version','1');
$recoverPayment=license_finance_offer_payment_prepare((string)$recoverOffer['id'],1);
$recoverAfter=license_finance_offer_row((string)$recoverOffer['id']);
rb_ok('Recovered payment setup reuses the signed agreement and one retained initial invoice',
    is_array($recoverPayment)
    &&(int)$recoverAfter['activation_invoice_id']===(int)$recoverRow['activation_invoice_id']
    &&(int)$recoverAfter['payment_request_id']===(int)$recoverPayment['id']
    &&(int)query('SELECT COUNT(*) FROM '.table('studio_invoices').' WHERE id=?',
        [(int)$recoverRow['activation_invoice_id']])->fetchColumn()===1);

$now=gmdate('Y-m-d H:i:s');
query('INSERT INTO '.table('license_finance_subscriptions').
    ' (offer_id,gateway_subscription_id,customer_profile_id,payment_profile_id,interval_months,amount_cents,total_occurrences,period_offset,replacement_count,start_date,status,created_at,updated_at)'.
    ' VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
    [$offer['id'],'810001','910001','920001',1,2500,4,0,0,$fields['anchor'],'active',$now,$now]);

// Recreate the published v1 finance-gateway shape with one retained subscription,
// then prove the explicit owner migration adds replacement and reversal metadata without losing it.
db()->exec('DROP TABLE '.table('license_finance_reversals'));
db()->exec('DROP TABLE '.table('license_finance_subscription_history'));
db()->exec('ALTER TABLE '.table('license_finance_subscriptions').' DROP COLUMN replacement_count, DROP COLUMN period_offset');
save_setting('license_finance_gateway_schema_version','1');
license_finance_gateway_prepare($password);
$migratedSub=license_finance_subscription_row((string)$offer['id']);
rb_ok('Finance gateway v1 to v3 migration preserves ARB identity and adds replacement/reversal metadata',
    license_finance_gateway_ready()
    &&setting('license_finance_gateway_schema_version')==='3'
    &&($migratedSub['gateway_subscription_id']??'')==='810001'
    &&(int)($migratedSub['period_offset']??-1)===0
    &&(int)($migratedSub['replacement_count']??-1)===0
    &&license_finance_gateway_table_present('license_finance_subscription_history')
    &&license_finance_gateway_table_present('license_finance_reversals'));

// Recreate populated v2 around the same retained subscription and prove the
// v2->v3 step adds only reversal history.
db()->exec('DROP TABLE '.table('license_finance_reversals'));
save_setting('license_finance_gateway_schema_version','2');
license_finance_gateway_prepare($password);
rb_ok('Finance gateway v2 to v3 migration adds reversal history without replacing active subscription',
    license_finance_gateway_ready()
    &&setting('license_finance_gateway_schema_version')==='3'
    &&license_finance_gateway_table_present('license_finance_reversals')
    &&(license_finance_subscription_row((string)$offer['id'])['gateway_subscription_id']??'')==='810001');

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

rb_ok('Cancelled automatic billing is recorded without itself changing retained financing debt',
    license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.cancelled','810001')
        ==='finance_subscription_cancelled'
    &&one('SELECT status FROM '.table('license_finance_subscriptions').' WHERE offer_id=?',[$offer['id']])
        ===['status'=>'cancelled']
    &&license_financing_access($licenseId)===[
        'authorized'=>false,'state'=>'suspended',
        'reason'=>'Financing payment is past the agreed grace period'
    ]);
save_setting('finance_scheduler_cursor','');
$cancelledRecovery=license_finance_scheduler_run(
    (new DateTimeImmutable($lateDate.'T12:00:00',new DateTimeZone('UTC')))->getTimestamp()
);
rb_ok('Prepared financing scheduler records durable last-run and success health',
    ctype_digit((string)setting('finance_scheduler_last_run'))
    &&(int)setting('finance_scheduler_last_run')>0
    &&setting('finance_scheduler_last_state')==='complete'
    &&setting('finance_scheduler_last_errors')==='0'
    &&setting('finance_scheduler_last_success')===setting('finance_scheduler_last_run'));
$cancelledRequest=one('SELECT p.id,p.invoice_id,p.status,p.amount_cents FROM '.table('payment_requests').' p '.
    'JOIN '.table('recurring_billing_invoices').' r ON r.invoice_id=p.invoice_id '.
    'WHERE r.schedule_id=? AND r.period_index=0 AND p.purpose=? ORDER BY p.id DESC LIMIT 1',
    [$offer['schedule_id'],'financing']);
rb_ok('Cancelled automatic billing prepares one secure manual catch-up for the real overdue installment',
    ($cancelledRecovery['errors']??-1)===0
    &&$cancelledRequest!==null&&$cancelledRequest['status']==='prepared'
    &&(int)$cancelledRequest['invoice_id']===(int)$invoice0['id']
    &&(int)$cancelledRequest['amount_cents']===2500);
$cancelledRequestId=(int)$cancelledRequest['id'];

query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE offer_id=?',
    ['billing_suspended',gmdate('Y-m-d H:i:s'),$offer['id']]);
save_setting('finance_scheduler_cursor','');
license_finance_scheduler_run(
    (new DateTimeImmutable($lateDate.'T12:00:00',new DateTimeZone('UTC')))->getTimestamp()
);
rb_ok('Changing cancelled automatic billing to suspended reuses the same overdue payment request',
    (int)query('SELECT COUNT(*) FROM '.table('payment_requests').' WHERE invoice_id=? AND purpose=?',
        [$invoice0['id'],'financing'])->fetchColumn()===1
    &&(int)one('SELECT id FROM '.table('payment_requests').' WHERE invoice_id=? AND purpose=? ORDER BY id DESC LIMIT 1',
        [$invoice0['id'],'financing'])['id']===$cancelledRequestId);
$entry0=ledger_insert($invoice0,0,'gateway',2500,'authorize_net','Synthetic catch-up financing receipt',$lateDate,
    null,null,hash('sha256','finance-native-period-0'));
query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',
    ['paid','Synthetic financing catch-up receipt '.$entry0,gmdate('Y-m-d H:i:s'),$invoice0['id']]);
$restored=license_financing_sync($licenseId,$lateDate,true);
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
license_deactivate($licenseId,1);
rb_ok('Pre-payoff manual deactivation can exist only before permanent unlock',
    (int)license_get($licenseKey)['is_active']===0);
$paidOff=license_financing_sync($licenseId,$afterLast);
rb_ok('All signed installments permanently unlock and reactivate the financed key',
    ($paidOff['state']??'')==='paid_off'&&is_string($paidOff['paid_off_at'])&&$paidOff['paid_off_at']!==''
    &&license_financing_authorized($licenseId)===true
    &&(int)license_get($licenseKey)['is_active']===1);
$paidAccess=license_financing_access($licenseId);
rb_ok('Paid-off financing exposes permanent authorization with no payment denial reason',
    $paidAccess===['authorized'=>true,'state'=>'paid_off','reason'=>null]);
$paidDeactivateBlocked=false;
try{license_deactivate($licenseId,1);}catch(RuntimeException $expected){$paidDeactivateBlocked=true;}
rb_ok('Paid-off financed license cannot be disabled after the customer completes payment',
    $paidDeactivateBlocked&&(int)license_get($licenseKey)['is_active']===1);
$paidDomainDeactivateBlocked=false;
try{license_deactivate_domain($licenseId,'finance-native.example',1);}catch(RuntimeException $expected){$paidDomainDeactivateBlocked=true;}
rb_ok('Paid-off financed license cannot be disabled by removing its active domain',
    $paidDomainDeactivateBlocked
    &&one('SELECT domain FROM '.table('license_domains').' WHERE license_id=? AND domain=? AND is_active=1',
        [$licenseId,'finance-native.example'])!==null);

query('DELETE FROM '.table('invoice_payments').' WHERE invoice_id=?',[$paidInvoices[3]]);
query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=NULL WHERE id=?',
    ['issued','Synthetic post-payoff correction',$paidInvoices[3]]);
$afterCorrection=license_financing_sync($licenseId,$afterLast);
rb_ok('Paid-off financing boundary ignores later billing correction and can never reapply the payment lock',
    ($afterCorrection['state']??'')==='paid_off'&&$afterCorrection['paid_off_at']===$paidOff['paid_off_at']
    &&license_financing_authorized($licenseId)===true);

$rightsPlan=license_financing_plan($licenseId);
$rights=$rightsPlan===null?null:license_financing_rights_from_row($rightsPlan);
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
