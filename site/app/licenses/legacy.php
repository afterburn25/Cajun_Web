<?php
/** Explicit correction for pre-invoice licensing history; never guesses a match. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/licenses/fulfill.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/app/support/sessions.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_invoices.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_dispatch.php';

function license_resolve_legacy_invoice(int $invoiceId,int $licenseId,bool $confirmed,string $evidence,#[\SensitiveParameter] string $password): array {
    if(db()->inTransaction())throw new LogicException('License invoice review requires its own transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($invoiceId,$licenseId,$confirmed,$evidence,$password):array{
        if(support_current())throw new RuntimeException('Leave support before reviewing a license invoice.');
        $actor=staff_owner($password);
        if(!$confirmed||strlen(trim($evidence))<10||strlen($evidence)>200)throw new InvalidArgumentException('Confirm the exact invoice decision and record its evidence (10–200 characters).');
        if(!license_ready()||!license_storage_ready()||!catalog_ready())throw new RuntimeException('Prepare licensing and the service catalog first.');
        if($invoiceId<1||$licenseId<0||$invoiceId>license_invoice_cutover())throw new InvalidArgumentException('Choose an invoice from before licensing history was prepared.');
        $invoice=one('SELECT i.*,j.client_id,c.active FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON i.job_id=j.id JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE i.id=? FOR UPDATE',[$invoiceId]);
        $stored=one('SELECT * FROM '.table('catalog_invoice_snapshots').' WHERE invoice_id=? FOR UPDATE',[$invoiceId]);
        if(!$invoice||!$stored||$invoice['status']!=='paid'||(int)$invoice['active']!==1||(int)$stored['job_id']!==(int)$invoice['job_id']||(int)$stored['client_id']!==(int)$invoice['client_id'])throw new RuntimeException('Choose the original paid catalog invoice for an active client.');
        recurring_billing_invoice_guard($invoice);domain_renewal_dispatch_invoice_guard($invoiceId);
        $snapshot=json_decode($stored['snapshot'],true,32,JSON_THROW_ON_ERROR);
        if(!is_array($snapshot)||license_product_industry($snapshot)===null)throw new RuntimeException('This invoice is not a licensed website purchase.');
        if(one('SELECT id FROM '.table('website_licenses').' WHERE invoice_id=? FOR UPDATE',[$invoiceId]))throw new RuntimeException('This invoice already has a license. Review its existing record.');
        if($licenseId>0){
            $license=one('SELECT * FROM '.table('website_licenses').' WHERE id=? FOR UPDATE',[$licenseId]);
            if(!$license||$license['invoice_id']!==null||(int)$license['is_master']!==0||(int)$license['customer_id']!==(int)$invoice['client_id']||$license['product_id']!==$snapshot['sku'])throw new RuntimeException('Choose an unlinked ordinary license for this exact client and product.');
            // This is an explicit owner attestation, not inferred provenance.
            query('UPDATE '.table('website_licenses').' SET invoice_id=? WHERE id=? AND invoice_id IS NULL',[$invoiceId,$licenseId]);
        }else{
            $key=license_create((int)$invoice['client_id'],(string)$snapshot['sku'],(int)$actor['id'],false,$invoiceId);
            $license=license_get($key);if(!$license)throw new RuntimeException('The newly created license record is unavailable.');$licenseId=(int)$license['id'];
        }
        license_audit((int)$actor['id'],'legacy.resolve','Invoice '.$invoiceId.' license '.$licenseId.' owner decision: '.trim($evidence));
        return one('SELECT * FROM '.table('website_licenses').' WHERE id=?',[$licenseId])??throw new RuntimeException('The reviewed license record is unavailable.');
    }));
}
