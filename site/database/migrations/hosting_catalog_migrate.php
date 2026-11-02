<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/catalog_offers_storage.php';
require_once CWP_CODE_ROOT.'/app/providers/plan_catalog.php';

/** Owner/password setup is explicit. Established history is checked before any possible CREATE. */
function hosting_catalog_prepare(#[\SensitiveParameter] string $password): void {
    if (db()->inTransaction()) throw new LogicException('Hosting offer setup cannot inherit a transaction.');
    staff_locked(function()use($password):void {
        $actor=service_plans_password($password);
        if (!catalog_ready()) throw new RuntimeException('Prepare the service catalog before hosting offers.');
        if (hosting_catalog_ready()) return;
        if (!provider_plan_catalog_state()['ready']) throw new RuntimeException('Prepare provider plan mappings before hosting offers.');
        foreach (hosting_catalog_schema(config()['db']['prefix']) as $name=>$sql) {
            if (!hosting_catalog_exists($name)) db()->exec($sql);
            hosting_catalog_validate_schema($name,$sql);
        }
        staff_transaction(function()use($password,$actor):void {
            hosting_catalog_guard();
            $current=service_plans_password($password);
            if ((int)$current['id']!==(int)$actor['id'] || !hash_equals($actor['auth_version'],$current['auth_version'])) throw new RuntimeException('Hosting setup authority changed.');
            if (hosting_catalog_ready()) return;
            foreach (hosting_catalog_tables() as $name) save_setting($name.'_count','0');
            save_setting('hosting_catalog_version','1');
            query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[(int)$actor['id'],'hosting_catalog.prepared','Hosting offer storage version 1',gmdate('Y-m-d H:i:s')]);
            if (!hosting_catalog_ready()) hosting_catalog_recovery();
        });
    });
}
