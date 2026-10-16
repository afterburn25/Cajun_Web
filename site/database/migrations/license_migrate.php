<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/base.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/app/support/sessions.php';

function license_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use ($password): void {
        if(support_current())throw new RuntimeException('Leave support before setup.');
        $actor=staff_owner($password);
        if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $prefix=config()['db']['prefix'];
        $defs=license_schema($prefix);
        // Marker gate runs before any schema change. MySQL DDL commits
        // implicitly, so an unknown or future version must be refused
        // untouched — a later migration path can then still inspect it.
        $version=(one('SELECT value FROM '.table('settings').' WHERE name=?',['license_schema_version'])['value']??'');
        if ($version!=='' && !in_array($version,['1','2','3','4'],true)) {
            throw new RuntimeException('Unsupported license schema version: '.$version);
        }
        if ($version==='') {
            // First adoption — refuse before altering anything when
            // unrecognized license records already exist.
            foreach (array_keys($defs) as $name) {
                if (one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$prefix.$name])
                    && (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()) {
                    throw new RuntimeException('Unrecognized license records exist. No data was adopted.');
                }
            }
        }
        $keys=[
            'website_licenses'=>['license_key'=>['license_key'],'invoice_lookup'=>['invoice_id']],
            'license_domains'=>['license_domain'=>['license_id','domain']],
            'license_bundles'=>[],
        ];
        foreach ($defs as $name=>$sql) {
            if (one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$prefix.$name])) {
                // Additive column upgrades for earlier adoptions — apply the
                // missing-column ALTER before validating against the full
                // schema (v2: website_licenses.invoice_id; v3: license_bundles.sha256;
                // v4: website_licenses.is_master for pre-master databases).
                if ($name==='website_licenses') {
                    if (!one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[$prefix.$name,'invoice_id'])) {
                        db()->exec('ALTER TABLE `'.$prefix.$name.'` ADD COLUMN invoice_id INT UNSIGNED NULL AFTER product_id, ADD UNIQUE KEY invoice_lookup (invoice_id)');
                    }
                    if (!one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[$prefix.$name,'is_master'])) {
                        db()->exec('ALTER TABLE `'.$prefix.$name.'` ADD COLUMN is_master TINYINT NOT NULL DEFAULT 0 AFTER is_active');
                    }
                }
                if ($name==='license_bundles' && !one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[$prefix.$name,'sha256'])) {
                    db()->exec('ALTER TABLE `'.$prefix.$name.'` ADD COLUMN sha256 CHAR(64) NULL AFTER title');
                }
                catalog_validate_schema($name,$sql,$keys[$name]);
            }
            else db()->exec($sql);
        }
        staff_transaction(function() use ($actor): void {
            save_setting('license_schema_version','4');
            license_audit((int)$actor['id'],'setup','Additive license storage verified');
        });
    });
}
