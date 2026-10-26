<?php
/** Owner-approved additive software commerce database setup. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/software_commerce_schema.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';

function software_commerce_ready(): bool {
    return setting('software_commerce_version') === '3';
}
function software_commerce_table_present(string $name): bool {
    return (bool)one(
        'SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
        [config()['db']['prefix'].$name]
    );
}
function software_commerce_column_present(string $tableName,string $column): bool {
    return (bool)one(
        'SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',
        [config()['db']['prefix'].$tableName,$column]
    );
}
function software_commerce_index_present(string $tableName,string $index): bool {
    return (bool)one(
        'SELECT INDEX_NAME FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?',
        [config()['db']['prefix'].$tableName,$index]
    );
}
function software_commerce_add_v3_entitlement_columns(): void {
    $table=chr(96).config()['db']['prefix'].'software_entitlements'.chr(96);
    if(!software_commerce_column_present('software_entitlements','updates_until')){
        db()->exec('ALTER TABLE '.$table.' ADD COLUMN updates_until DATETIME NULL AFTER expires_at');
    }
    if(!software_commerce_column_present('software_entitlements','max_installations')){
        db()->exec('ALTER TABLE '.$table.' ADD COLUMN max_installations SMALLINT UNSIGNED NOT NULL DEFAULT 2 AFTER updates_until');
    }
    if(!software_commerce_column_present('software_entitlements','policy_revision')){
        db()->exec('ALTER TABLE '.$table.' ADD COLUMN policy_revision INT UNSIGNED NOT NULL DEFAULT 0 AFTER max_installations');
    }
    if(!software_commerce_index_present('software_entitlements','update_lookup')){
        db()->exec('ALTER TABLE '.$table.' ADD INDEX update_lookup (updates_until)');
    }
}
function software_commerce_add_v3_asset_columns(): void {
    $table=chr(96).config()['db']['prefix'].'software_assets'.chr(96);
    if(!software_commerce_column_present('software_assets','released_at')){
        db()->exec('ALTER TABLE '.$table.' ADD COLUMN released_at DATETIME NULL AFTER is_published');
    }
    if(!software_commerce_index_present('software_assets','release_lookup')){
        db()->exec('ALTER TABLE '.$table.' ADD INDEX release_lookup (sku,is_published,released_at)');
    }
    query('UPDATE '.table('software_assets').' SET released_at=created_at WHERE is_published=1 AND released_at IS NULL');
}
function software_commerce_verify_v3(array $schema): void {
    foreach(array_keys($schema) as $name){
        if(!software_commerce_table_present($name)){
            throw new RuntimeException('Software v3 storage is incomplete; preserve the database.');
        }
    }
    foreach(['purpose','updates_until','max_installations','policy_revision'] as $column){
        if(!software_commerce_column_present('software_entitlements',$column)){
            throw new RuntimeException('Software v3 entitlement metadata is incomplete; preserve the database.');
        }
    }
    if(!software_commerce_index_present('software_entitlements','update_lookup')){
        throw new RuntimeException('Software v3 update-entitlement index is incomplete; preserve the database.');
    }
    if(!software_commerce_column_present('software_assets','released_at')
        ||!software_commerce_index_present('software_assets','release_lookup')){
        throw new RuntimeException('Software v3 release-timestamp metadata is incomplete; preserve the database.');
    }
    foreach(['product_id','module_key','dependencies','update_months','max_installations','revision','updated_by','updated_at'] as $column){
        if(!software_commerce_column_present('software_product_policies',$column)){
            throw new RuntimeException('Software v3 product policy metadata is incomplete; preserve the database.');
        }
    }
}
function software_commerce_prepare(string $password): void {
    if (db()->inTransaction()) throw new LogicException('Prepare outside a transaction.');
    staff_locked(function() use ($password): void {
        if (support_current()) throw new RuntimeException('Leave support mode first.');
        $actor = staff_owner($password);
        if (!studio_ready() || !license_ready() || !catalog_ready()) {
            throw new RuntimeException('Prepare client workspace, catalog and licensing first.');
        }
        $v = (string)setting('software_commerce_version');
        if (!in_array($v, ['', '1', '2', '3'], true)) throw new RuntimeException('Unknown software commerce version.');
        $pending = (string)setting('software_commerce_pending');
        if (!in_array($pending, ['', '2', '3'], true)) throw new RuntimeException('Unknown software setup history.');
        $schema = software_commerce_schema(config()['db']['prefix']);

        if ($v === '') {
            $present = [];
            foreach ($schema as $name => $sql) {
                if (software_commerce_table_present($name)) $present[] = $name;
            }
            // An interrupted v2/v3 preparation is recognizable by its pending
            // marker. Unmarked tables could be unrelated or manually created:
            // preserve them for owner review instead of adopting them.
            if ($pending === '' && $present !== []) {
                throw new RuntimeException('Unrecognized software tables: preserve and review the existing database.');
            }
            if ($pending !== '3') {
                staff_transaction(function() use ($actor): void {
                    save_setting('software_commerce_pending', '3');
                    license_audit((int)$actor['id'], 'software.setup.begin', 'Software commerce v3 preparation started');
                });
            }
            foreach ($schema as $name => $sql) {
                if (!software_commerce_table_present($name)) {
                    db()->exec($sql);
                    continue;
                }
                $count = (int)query(
                    'SELECT COUNT(*) FROM '.chr(96).config()['db']['prefix'].$name.chr(96)
                )->fetchColumn();
                if ($count !== 0) {
                    throw new RuntimeException('Interrupted software preparation contains data: preserve and review.');
                }
            }
            software_commerce_add_v3_entitlement_columns();
            software_commerce_add_v3_asset_columns();
            software_commerce_verify_v3($schema);
            staff_transaction(function() use ($actor): void {
                save_setting('software_commerce_version','3');
                save_setting('software_commerce_pending','');
                license_audit((int)$actor['id'], 'software.setup', 'Software commerce v3 enabled; prior licenses preserved');
            });
            return;
        }

        if (in_array($v,['1','2'],true)) {
            $v1Tables = [
                'software_entitlements','software_orders','software_assets','software_charges',
                'software_subscriptions','software_subscription_receipts','software_notices','software_usage'
            ];
            $v2Tables = array_merge($v1Tables,['software_charge_attempts','software_payment_reversals','software_payment_disputes']);
            foreach($v==='1'?$v1Tables:$v2Tables as $name){
                if(!software_commerce_table_present($name)){
                    throw new RuntimeException('Established software v'.$v.' tables are missing; preserve the database.');
                }
            }
            if ($pending !== '3') {
                staff_transaction(function() use ($actor,$v): void {
                    save_setting('software_commerce_pending','3');
                    license_audit((int)$actor['id'], 'software.upgrade.begin', 'Software commerce v'.$v.' to v3 upgrade started');
                });
            }
            if (!software_commerce_column_present('software_entitlements','purpose')) {
                $table=chr(96).config()['db']['prefix'].'software_entitlements'.chr(96);
                db()->exec("ALTER TABLE ".$table." ADD COLUMN purpose VARCHAR(200) NOT NULL DEFAULT '' AFTER issued_by");
            }
            foreach(['software_charge_attempts','software_payment_reversals','software_payment_disputes','software_product_policies'] as $name){
                if(!software_commerce_table_present($name))db()->exec($schema[$name]);
            }
            software_commerce_add_v3_entitlement_columns();
            software_commerce_add_v3_asset_columns();

            // Existing v2 rows retain lifetime updates and two installations.
            // Recurring paid-through entitlements are the exception: update access
            // follows the already-recorded paid-through expiry and will continue to
            // advance with verified renewals.
            query(
                'UPDATE '.table('software_entitlements').' e JOIN '.table('software_subscriptions').' s ON s.invoice_id=e.invoice_id '.
                'SET e.updates_until=e.expires_at WHERE e.license_type=? AND e.expires_at IS NOT NULL AND e.updates_until IS NULL',
                ['temporary']
            );

            software_commerce_verify_v3($schema);
            staff_transaction(function() use ($actor,$v): void {
                save_setting('software_commerce_version','3');
                save_setting('software_commerce_pending','');
                license_audit((int)$actor['id'], 'software.upgrade', 'Software commerce upgraded from v'.$v.' to v3');
            });
            return;
        }

        software_commerce_add_v3_entitlement_columns();
        software_commerce_add_v3_asset_columns();
        software_commerce_verify_v3($schema);
        if ($pending !== '') {
            staff_transaction(function() use ($actor): void {
                save_setting('software_commerce_pending','');
                license_audit((int)$actor['id'], 'software.recovery', 'Cleared completed software v3 preparation marker');
            });
        }
    });
}
