<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function hosting_fulfillment_migrate(#[\SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Hosting fulfillment preparation cannot inherit a transaction.');
    staff_locked(function()use($password):void {
        $actor=service_orders_owner($password);hosting_records_owner();provider_settings_owner($password);
        hosting_order_links_require();hosting_operations_require_ready();hosting_storage_require('reconciliation');
        if(!provider_operations_ready())throw new RuntimeException('Prepare the existing provider operation ledger first.');
        hosting_storage_prepare('fulfillment');
        foreach(hosting_fulfillment_schema(config()['db']['prefix']) as $name=>$sql){
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
            service_orders_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void {
            save_setting('hosting_fulfillment_schema_version','1');
            hosting_records_audit((int)$actor['id'],'fulfillment_setup','Additive order fulfillment approval history verified');
        });
    });
}
