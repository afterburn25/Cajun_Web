<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/base.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';

function license_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use ($password): void {
        if(support_current())throw new RuntimeException('Leave support before setup.');
        $actor=staff_owner($password);
        if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $prefix=config()['db']['prefix'];
        $keys=[
            'website_licenses'=>['license_key'=>['license_key'],'invoice_lookup'=>['invoice_id']],
            'license_domains'=>['license_domain'=>['license_id','domain']],
            'license_bundles'=>[],
        ];
        foreach (license_schema($prefix) as $name=>$sql) {
            if (one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$prefix.$name])) {
                // Version 2 added website_licenses.invoice_id — add it to a
                // version-1 adoption before validating against the full schema.
                if ($name==='website_licenses' && !one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[$prefix.$name,'invoice_id'])) {
                    db()->exec('ALTER TABLE `'.$prefix.$name.'` ADD COLUMN invoice_id INT UNSIGNED NULL AFTER product_id, ADD UNIQUE KEY invoice_lookup (invoice_id)');
                }
                catalog_validate_schema($name,$sql,$keys[$name]);
            }
            else db()->exec($sql);
        }
        staff_transaction(function() use ($actor): void {
            $version=(one('SELECT value FROM '.table('settings').' WHERE name=?',['license_schema_version'])['value']??'');
            if ($version==='') {
                // First adoption — only adopt known-empty tables.
                foreach (array_keys(license_schema(config()['db']['prefix'])) as $name) if ((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()) throw new RuntimeException('Unrecognized license records exist. No data was adopted.');
            }
            save_setting('license_schema_version','2');
            license_audit((int)$actor['id'],'setup','Additive license storage verified');
        });
    });
}
