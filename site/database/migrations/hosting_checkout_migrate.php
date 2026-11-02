<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/catalog_checkout_storage.php';

/** Explicit owner setup only. DDL resumes only recognized empty, unmarked initial tables. */
function hosting_checkout_prepare(#[\SensitiveParameter] string $password): void {
    if (db()->inTransaction()) throw new LogicException('Hosting checkout setup cannot inherit a transaction.');
    staff_locked(function()use($password):void {
        unset($GLOBALS['cwp_settings']);
        $actor=service_plans_password($password);
        if (!staff_can('payments.manage',$actor)) throw new RuntimeException('Current financial access is required.');
        $prepared=staff_transaction(static fn():bool=>hosting_checkout_ready());
        if ($prepared) return;
        foreach (hosting_checkout_schema(config()['db']['prefix']) as $name=>$sql) {
            if (!hosting_checkout_exists($name)) db()->exec($sql);
            hosting_checkout_validate_schema($name,$sql);
        }
        staff_transaction(function()use($password,$actor):void {
            unset($GLOBALS['cwp_settings']);hosting_catalog_guard();
            $current=service_plans_password($password);
            if ((int)$actor['id']!==(int)$current['id'] || !hash_equals($actor['auth_version'],$current['auth_version']) || !staff_can('payments.manage',$current)) throw new RuntimeException('Hosting checkout setup authority changed.');
            if (hosting_checkout_ready()) return;
            foreach (hosting_checkout_tables() as $name) save_setting($name.'_count','0');
            save_setting('hosting_checkout_version','1');
            query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[(int)$current['id'],'hosting_checkout.prepared','Hosting checkout storage version 1',gmdate('Y-m-d H:i:s')]);
            if (!hosting_checkout_ready()) hosting_checkout_recovery();
        });
    });
}
