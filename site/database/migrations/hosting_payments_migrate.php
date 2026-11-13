<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/hosting_payments_schema.php';

function hosting_payments_prepare(#[\SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Hosting payment setup cannot inherit a transaction.');
    staff_locked(function()use($password):void {
        unset($GLOBALS['cwp_settings']);$actor=payments_owner($password);
        $checkoutReady=staff_transaction(static fn():bool=>hosting_checkout_ready());
        if(!$checkoutReady||setting('gateway_receipts_schema_version')!=='1')throw new RuntimeException('Prepare hosting checkout and verified gateway receipts first.');
        foreach(hosting_payments_schema(config()['db']['prefix']) as $name=>$sql){
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
            hosting_payments_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void {
            if(setting('hosting_payments_schema_version')!=='1'){
                foreach(array_keys(hosting_payments_schema(config()['db']['prefix'])) as $name)if(one('SELECT 1 AS present FROM '.table($name).' LIMIT 1'))throw new RuntimeException('Unrecognized hosting payment history exists. Restore its original marker.');
                if(setting('hosting_gateway_mode')==='')save_setting('hosting_gateway_mode','disabled');
                if(setting('hosting_gateway_production_reviewed')==='')save_setting('hosting_gateway_production_reviewed','0');
            }
            save_setting('hosting_payments_schema_version','1');payments_audit((int)$actor['id'],'hosting_payments.setup','Additive hosting payment history version 1; gateway remains explicitly configured');
        });
    });
}
