<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function provider_catalog_import_prepare(#[\SensitiveParameter] string $password): void {
    if(db()->inTransaction())throw new LogicException('Import preparation requires its own transaction.');
    staff_locked(function()use($password):void {
        $actor=provider_catalog_import_owner($password);
        if(provider_catalog_import_ready())return;
        if(provider_catalog_import_exists())provider_catalog_import_schema_valid();
        else db()->exec(provider_catalog_import_schema(config()['db']['prefix'])['catalog_provider_imports']);
        provider_catalog_import_schema_valid();
        staff_transaction(function()use($actor):void {
            if(provider_catalog_import_ready())return;
            if((int)query('SELECT COUNT(*) FROM '.table('catalog_provider_imports'))->fetchColumn()!==0)throw new RuntimeException('Unrecognized imported products exist. No records were adopted.');
            save_setting('provider_catalog_import_version','1');save_setting('provider_catalog_import_count','0');
            catalog_audit((int)$actor['id'],'provider_import.setup','Additive observed hosting type imports');
        });
    });
}
