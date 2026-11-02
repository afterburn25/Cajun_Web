<?php
/** Owner-gated additive finance-offer storage. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/license_finance_offers_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
require_once CWP_CODE_ROOT.'/app/licenses/financing.php';
require_once CWP_CODE_ROOT.'/app/billing/payments.php';

function license_finance_offers_ready():bool{
    return setting('license_finance_offers_schema_version')==='1'
        &&(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
            [config()['db']['prefix'].'license_finance_offers']);
}
function license_finance_offers_validate_schema():void{
    $sql=license_finance_offers_schema(config()['db']['prefix'])['license_finance_offers'];
    catalog_validate_schema('license_finance_offers',$sql,['schedule_lookup'=>['schedule_id']]);
    $columns=array_column(all('SHOW FULL COLUMNS FROM '.table('license_finance_offers')),'Field');
    $expected=['id','license_id','client_id','job_id','schedule_id','schedule_revision','schedule_sha256','status','title',
        'cash_price_cents','initial_payment_cents','amount_financed_cents','finance_charge_cents','apr_basis_points',
        'installments','grace_days','payment_link_days','terms_json','terms_sha256','created_by','expires_at',
        'signed_name','signed_email','signed_at','signed_terms_sha256','signature_evidence_hash',
        'activation_invoice_id','payment_request_id','created_at','updated_at'];
    if($columns!==$expected)throw new RuntimeException('Incompatible finance-offer columns. Preserve the database for recovery.');
}
function license_finance_offers_prepare(#[\SensitiveParameter]string $password):void{
    if(db()->inTransaction())throw new LogicException('Finance-offer setup cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        if(support_current())throw new RuntimeException('Leave support mode before finance-offer setup.');
        $actor=staff_owner($password);
        if(license_financing_feature_state()!==true)throw new RuntimeException('Prepare financed-site licensing first.');
        recurring_billing_require();
        if(!payments_ready()||!ledger_ready())throw new RuntimeException('Prepare payment links and invoice ledger first.');
        $version=(string)(setting('license_finance_offers_schema_version')??'');
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unknown finance-offer storage version.');
        $pending=(string)(setting('license_finance_offers_schema_pending')??'');
        if(!in_array($pending,['','1'],true))throw new RuntimeException('Unknown finance-offer setup history.');
        $present=(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
            [config()['db']['prefix'].'license_finance_offers']);
        if($version==='1'&&!$present)throw new RuntimeException('Established finance-offer storage is missing. Restore the original database.');
        if($version===''&&$pending===''&&$present)throw new RuntimeException('Unrecognized finance-offer storage exists. No data was adopted.');
        if($version===''&&$pending===''){
            staff_transaction(function()use($actor):void{
                save_setting('license_finance_offers_schema_pending','1');
                license_audit((int)$actor['id'],'financing.offers.setup.begin','Client finance-agreement storage preparation started');
            });
            $pending='1';
        }
        if($version===''&&$pending==='1'&&$present
            &&(int)query('SELECT COUNT(*) FROM '.table('license_finance_offers'))->fetchColumn()!==0){
            throw new RuntimeException('Interrupted finance-offer preparation contains data. Preserve and review the database.');
        }
        if(!$present)db()->exec(license_finance_offers_schema(config()['db']['prefix'])['license_finance_offers']);
        license_finance_offers_validate_schema();
        staff_transaction(function()use($actor):void{
            if(setting('license_finance_offers_schema_version')!=='1'){
                if((int)query('SELECT COUNT(*) FROM '.table('license_finance_offers'))->fetchColumn()!==0){
                    throw new RuntimeException('Unrecognized finance-offer records exist. No data was adopted.');
                }
                save_setting('license_finance_offers_schema_version','1');
                save_setting('license_finance_offers_schema_pending','');
                license_audit((int)$actor['id'],'financing.offers.setup','Additive client finance-agreement storage verified');
            }
        });
    });
}
