<?php
/** Source-level financing integration invariants; no database or network required. */
declare(strict_types=1);
$root=dirname(__DIR__);
function fc_file(string $path):string{
    global $root;$data=file_get_contents($root.'/'.$path);
    if($data===false)throw new RuntimeException('FAIL cannot read '.$path);
    return $data;
}
function fc_ok(bool $ok,string $name):void{
    if(!$ok)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
$admin=fc_file('site/admin/views/studio/licenses.php');
fc_ok(substr_count($admin,'Create customer financing agreement')===1,'Finance agreement creator appears once');
fc_ok(substr_count($admin,'Finance agreement history')===1,'Finance agreement history appears once');
fc_ok(substr_count($admin,'Authorize.net payment configuration')===1,'Software gateway configuration appears once');
fc_ok(substr_count($admin,'License register')===1,'License register appears once');
fc_ok(!str_contains($admin,'.number_format($candidate'),'No corrupted financing splice remains in licensing view');
fc_ok(str_contains($admin,"'replacing','review','payment_failed','billing_suspended'"),
    'Owner reconciliation queue includes interrupted financing replacement attempts');

$controller=fc_file('site/admin/controllers/studio.php');
fc_ok(str_contains($controller,'Direct financing binding is retired'),'Unsigned direct financing binding is blocked');
fc_ok(str_contains($controller,'studio_license_finance_gateway_setup'),'Finance gateway setup action is wired');
fc_ok(str_contains($controller,'studio_license_finance_charge_reconcile'),'Finance one-time reconciliation is wired');
fc_ok(str_contains($controller,'studio_license_finance_subscription_reconcile'),'Finance subscription reconciliation is wired');

$bundleDownload=fc_file('site/api/bundle-download.php');
$workspace=fc_file('site/portal/views/workspace.php');
$deliveries=fc_file('site/app/projects/deliveries.php');
$deliveryLayout=fc_file('site/includes/layout/deliveries.php');
fc_ok(str_contains($bundleDownload,'license_financing_access((int)$bundle[\'license_id\'])')
    &&str_contains($bundleDownload,"http_response_code(503)")
    &&str_contains($bundleDownload,'!$financing[\'authorized\']'),
    'Website bundle endpoint follows financed-license payment access and fails closed on unknown state');
fc_ok(str_contains($workspace,"['active','grace','paid_off']")
    &&str_contains($workspace,'Website bundle downloads are paused until financing access is current'),
    'Client workspace hides financed website bundles while payment access is locked');
fc_ok(str_contains($deliveries,'function delivery_financing_access')
    &&substr_count($deliveries,'delivery_financing_access(')>=3
    &&str_contains($deliveries,'Financing access is locked.'),
    'Published project release streaming rechecks financed-license access before file delivery');
fc_ok(str_contains($deliveryLayout,'Website release download is paused until financing access is current')
    &&str_contains($deliveryLayout,'Website release download is paused while financing status is reviewed'),
    'Project release UI hides financed downloads while payment access is locked or uncertain');

$payments=fc_file('site/app/billing/payments.php');
fc_ok(str_contains($payments,"r['purpose']==='financing'"),'Online checkout is scoped to financing payment requests');
fc_ok(str_contains($payments,'data-card-number')&&str_contains($payments,'data-card-code'),'Financing checkout uses tokenized card fields');
fc_ok(!preg_match('/name=["\'](?:card|card_number|card_code|cvv|cvc)["\']/i',$payments),'Raw card/CVV fields have no submitted names');
fc_ok(str_contains($payments,'payments_financing_request'),'Financing payment links use dedicated idempotent request preparation');

$anet=fc_file('site/app/billing/finance_authorize_net.php');
fc_ok(str_contains($anet,'CWP_FINANCE_PRODUCTION_PAYMENTS')&&str_contains($anet,'finance_gateway_production_reviewed'),
    'Finance production charging requires code and owner-review gates');
fc_ok(str_contains($anet,"'monthly'=>1")||str_contains(fc_file('site/app/licenses/financing_policy.php'),"'monthly'=>1"),
    'Finance cadence policy defines monthly interval');
fc_ok(str_contains($anet,'totalOccurrences'),'Finance ARB request is finite');

$recurring=fc_file('site/app/billing/recurring_billing.php');
$recurringInvoices=fc_file('site/app/billing/recurring_billing_invoices.php');
fc_ok(str_contains($recurring,'recurring_billing_finance_managed'),'Generic recurring lifecycle detects finance-managed schedules');
fc_ok(str_contains($recurringInvoices,'Financing installment invoices are issued only by the signed finance workflow'),
    'Generic recurring invoice path cannot issue financing installments');

$offers=fc_file('site/app/licenses/financing_offers.php');
fc_ok(str_contains($offers,"?'pending_initial':'active'"),'Down-payment agreements begin payment-locked');
fc_ok(str_contains($offers,'signed_terms_sha256')&&str_contains($offers,'signature_evidence_hash'),
    'Electronic signature binds exact finance terms and evidence hash');

$licenseApi=fc_file('site/api/handlers/license.php');
fc_ok(strpos($licenseApi,'license_domain_authorized($license, $domain)')
    < strpos($licenseApi,'license_financing_access((int)$license[\'id\'])'),
    'Public validation checks product/domain authority before evaluating private financing state');

$finance=fc_file('site/app/licenses/financing.php');
fc_ok(str_contains($finance,"'pending_initial'=>['authorized'=>false")
    &&str_contains($finance,"'suspended'=>['authorized'=>false"),
    'Initial-payment and delinquency states expose separate definitive payment-lock reasons');
fc_ok(str_contains($finance,"state='paid_off'")||str_contains($finance,"'paid_off'=>['authorized'=>true"),
    'Paid-off state is retained as a one-way financing boundary');
fc_ok(str_contains($finance,'license_financing_rights_grants')
    &&str_contains($finance,'rightsInvoiceId')
    &&str_contains($finance,'normal financing installment cannot be reused')===false
    &&str_contains($finance,'A normal financing installment cannot be reused as payment for commercial rights.')
    &&str_contains($finance,'This paid commercial-rights invoice has already been consumed'),
    'Post-sale commercial rights require a separate single-use paid invoice');

$coreFinanceSchema=fc_file('site/database/schema/license_financing_schema.php');
$coreFinanceMigration=fc_file('site/database/migrations/license_financing_migrate.php');
fc_ok(str_contains($coreFinanceSchema,'license_financing_rights_grants')
    &&str_contains($coreFinanceSchema,'UNIQUE KEY invoice_once(invoice_id)'),
    'Financing v2 stores immutable single-use commercial-rights payment evidence');
fc_ok(str_contains($coreFinanceMigration,"license_financing_schema_version','2'")
    &&str_contains($coreFinanceMigration,"['','1','2']"),
    'Core financed-license storage upgrades v1 additively to v2 rights evidence');

$financeSchema=fc_file('site/database/schema/license_finance_gateway_schema.php');
$financeMigration=fc_file('site/database/migrations/license_finance_gateway_migrate.php');
$financeSubscription=fc_file('site/app/billing/finance_subscription.php');
$financePortal=fc_file('site/portal/views/financing.php');
$portalActions=fc_file('site/portal/controllers/actions.php');
fc_ok(str_contains($financeSchema,'license_finance_subscription_history')
    &&str_contains($financeSchema,'period_offset')&&str_contains($financeSchema,'replacement_count'),
    'Finance gateway v2 retains replaced subscription identities and period offsets');
fc_ok(str_contains($financeMigration,"license_finance_gateway_schema_version','2'")
    &&str_contains($financeMigration,'license_finance_gateway_upgrade_v2'),
    'Finance gateway storage upgrades existing v1 data additively to v2');
fc_ok(str_contains($financeMigration,"setting('license_finance_gateway_schema_version')!=='2'")
    &&str_contains($financeMigration,'replacement_count'),
    'Finance gateway readiness requires complete v2 replacement metadata');
fc_ok(str_contains($financeSubscription,'license_finance_subscription_reenroll_opaque')
    &&str_contains($financeSubscription,'license_finance_subscription_replacement_finalize')
    &&str_contains($financeSubscription,'historical_subscription_transaction')
    &&str_contains($financeSubscription,'period_offset'),
    'Suspended financing subscriptions can be replaced without replaying earlier installments');
fc_ok(str_contains($financePortal,'finance_subscription_reenroll')
    &&str_contains($financePortal,'preserve it in history')
    &&str_contains($financePortal,'authorize_net_security_headers'),
    'Client finance portal exposes caught-up replacement enrollment under payment CSP');
fc_ok(str_contains($financePortal,'Signed agreement rights:')
    &&str_contains($financePortal,'Current commercial rights:')
    &&str_contains($financePortal,'Paid commercial-rights evidence'),
    'Client finance portal separates immutable signed rights from later paid current rights');
fc_ok(str_contains($financePortal,'license_financing_rights_grants_table_present()')
    &&str_contains($financePortal,"? all('SELECT invoice_id,allow_resale"),
    'Client paid-rights evidence remains compatible with readable financing v1 storage');
fc_ok(str_contains($portalActions,"\$action==='finance_subscription_reenroll'"),
    'Client financing replacement action is wired through authenticated portal handling');
$sharedWebhook=fc_file('site/app/billing/software_subscription_settle.php');
fc_ok(substr_count($sharedWebhook,'license_finance_subscription_known_gateway_id')>=4,
    'Shared Authorize.Net webhook routes active and archived finance subscription IDs through finance history');

$job=fc_file('site/app/jobs/finance.php');
fc_ok(str_contains($job,"PHP_SAPI!=='cli'")&&str_contains($job,'takes no arguments'),'Finance worker is CLI-only and argument-free');
fc_ok(str_contains(fc_file('site/app/licenses/finance_installments.php'),'finance_scheduler_cursor'),
    'Finance worker uses a retained cursor instead of starving agreements after a fixed batch');

echo "License financing source contract: PASS".PHP_EOL;
