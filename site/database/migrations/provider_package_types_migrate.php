<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function provider_package_types_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Package type preparation cannot run inside a transaction.');
    staff_locked(function()use($password):void{
        $actor=provider_settings_owner($password);if(!provider_inventory_ready())throw new RuntimeException('Prepare hosting inventory first.');
        if(provider_package_types_ready()){provider_package_types_validate();return;}
        foreach(provider_package_types_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])===null)db()->exec($sql);
        }
        provider_package_types_validate();
        staff_transaction(function()use($actor):void{
            $current=provider_settings_manager();if((int)$current['id']!==(int)$actor['id']||!hash_equals($current['auth_version'],$actor['auth_version']))throw new RuntimeException('Current owner changed during preparation.');
            if(provider_package_types_history_exists())throw new RuntimeException('Package type history appeared during preparation. Restore it before continuing.');save_setting('provider_package_types_schema_version','1');save_setting('provider_package_types_started_count','0');save_setting('provider_package_types_finished_count','0');provider_settings_audit((int)$actor['id'],'package_types_prepared','Package type observation storage ready');});
    });
}
