<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function hosting_order_links_migrate(#[\SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Order association setup cannot inherit a transaction.');
    staff_locked(function()use($password):void {
        $actor=service_orders_owner($password);hosting_records_owner();provider_settings_owner($password);
        hosting_storage_prepare('order_links');
        foreach(hosting_order_links_schema(config()['db']['prefix']) as $name=>$sql){
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
            service_orders_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void {
            save_setting('hosting_order_links_schema_version','1');
            hosting_records_audit((int)$actor['id'],'order_links_setup','Additive order to local hosting association verified');
        });
    });
}
