<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/base.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/app/support/sessions.php';

/**
 * Narrow legacy check for populated unmarked tables: every existing column
 * must belong to the known historical layout and the core columns must be
 * present, or the table holds unrecognized records and adoption refuses.
 */
function license_legacy_columns(string $prefix, string $name): void {
    $known=['website_licenses'=>['id','license_key','customer_id','product_id','invoice_id','is_active','is_master','created_at','updated_at'],'license_domains'=>['id','license_id','domain','is_active','registered_at','deactivated_at'],'license_bundles'=>['id','license_id','file_path','title','sha256','created_at']][$name]??null;
    $required=['website_licenses'=>['license_key','customer_id','product_id','is_active','created_at'],'license_domains'=>['license_id','domain'],'license_bundles'=>['license_id','file_path']][$name]??[];
    if($known===null)throw new RuntimeException('Unrecognized licensing table. No data was adopted.');
    $have=array_column(all('SHOW COLUMNS FROM '.table($name)),'Field');
    foreach($have as $col)if(!in_array($col,$known,true))throw new RuntimeException('Unrecognized '.$name.' column '.$col.'. Existing records were preserved.');
    foreach($required as $col)if(!in_array($col,$have,true))throw new RuntimeException('Unrecognized '.$name.' layout (missing '.$col.'). Existing records were preserved.');
}

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
            // First adoption — an unmarked install is either empty (adopt)
            // or a genuine legacy layout predating markers: its tables may
            // be populated, but every column must belong to the known
            // historical schema and every licensing table must be one of
            // the canonical set. Anything unrecognized refuses before any
            // DDL — no legacy row is touched by a failed adoption.
            $known=array_keys($defs);
            $stray=all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND (table_name LIKE ? OR table_name=?)',[$prefix.'license_%',$prefix.'website_licenses']);
            foreach($stray as $t)if(!in_array(substr($t['TABLE_NAME'],strlen($prefix)),$known,true))throw new RuntimeException('Unrecognized licensing table '.$t['TABLE_NAME'].'. No data was adopted.');
            foreach ($known as $name) {
                if (one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$prefix.$name])
                    && (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()) {
                    license_legacy_columns($prefix,$name);
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
                    $product=one('SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[$prefix.$name,'product_id']);
                    if ($product && strtolower((string)$product['DATA_TYPE'])!=='varchar') {
                        // Genuine pre-marker installs stored product_id as INT — widen in place.
                        db()->exec('ALTER TABLE `'.$prefix.$name.'` MODIFY COLUMN product_id VARCHAR(60) NOT NULL');
                    }
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
