<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function provider_catalog_variant_prepare(#[\SensitiveParameter] string $password): void {
    if(db()->inTransaction())throw new LogicException('Billing option preparation requires its own transaction.');
    staff_locked(function()use($password):void {
        $actor=provider_catalog_variant_owner($password);
        if(!provider_catalog_import_ready())throw new RuntimeException('Enable hosting imports before billing options.');
        if(provider_catalog_variant_ready())return;
        // Exact empty DDL is resumable; unknown columns/indexes are refused before any write.
        if(!provider_catalog_variant_exists())db()->exec(provider_catalog_variant_schema(config()['db']['prefix'])['catalog_provider_variants']);
        provider_catalog_variant_schema_valid();
        staff_transaction(function()use($actor):void {
            if(provider_catalog_variant_ready())return;
            save_setting('provider_catalog_variant_version','1');save_setting('provider_catalog_variant_count','0');
            catalog_audit((int)$actor['id'],'provider_variant.setup','Additive hosting billing options');
        });
    });
}
