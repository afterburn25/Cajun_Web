<?php
/** Owner-approved additive software commerce database setup. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/software_commerce_schema.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';

function software_commerce_ready(): bool {
    return setting('software_commerce_version') === '2';
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
function software_commerce_prepare(string $password): void {
    if (db()->inTransaction()) throw new LogicException('Prepare outside a transaction.');
    staff_locked(function() use ($password): void {
        if (support_current()) throw new RuntimeException('Leave support mode first.');
        $actor = staff_owner($password);
        if (!studio_ready() || !license_ready() || !catalog_ready()) {
            throw new RuntimeException('Prepare client workspace, catalog and licensing first.');
        }
        $v = (string)setting('software_commerce_version');
        if (!in_array($v, ['', '1', '2'], true)) throw new RuntimeException('Unknown software commerce version.');
        $pending = (string)setting('software_commerce_pending');
        if (!in_array($pending, ['', '2'], true)) throw new RuntimeException('Unknown software setup history.');
        $schema = software_commerce_schema(config()['db']['prefix']);

        if ($v === '') {
            $present = [];
            foreach ($schema as $name => $sql) {
                if (software_commerce_table_present($name)) $present[] = $name;
            }
            if ($pending === '' && $present !== []) {
                throw new RuntimeException('Unrecognized software tables: preserve and review the existing database.');
            }
            if ($pending === '') {
                staff_transaction(function() use ($actor): void {
                    save_setting('software_commerce_pending', '2');
                    license_audit((int)$actor['id'], 'software.setup.begin', 'Software commerce v2 preparation started');
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
            staff_transaction(function() use ($actor): void {
                save_setting('software_commerce_version','2');
                save_setting('software_commerce_pending','');
                license_audit((int)$actor['id'], 'software.setup', 'Software commerce v2 enabled; prior licenses preserved');
            });
            return;
        }

        if ($v === '1') {
            $legacyTables = [
                'software_entitlements','software_orders','software_assets','software_charges',
                'software_subscriptions','software_subscription_receipts','software_notices','software_usage'
            ];
            foreach ($legacyTables as $name) {
                if (!software_commerce_table_present($name)) {
                    throw new RuntimeException('Established software v1 tables are missing; preserve the database.');
                }
            }
            if ($pending === '') {
                staff_transaction(function() use ($actor): void {
                    save_setting('software_commerce_pending','2');
                    license_audit((int)$actor['id'], 'software.upgrade.begin', 'Software commerce v1 to v2 upgrade started');
                });
            }
            if (!software_commerce_column_present('software_entitlements','purpose')) {
                $table=chr(96).config()['db']['prefix'].'software_entitlements'.chr(96);
                db()->exec("ALTER TABLE ".$table." ADD COLUMN purpose VARCHAR(200) NOT NULL DEFAULT '' AFTER issued_by");
            }
            foreach (['software_charge_attempts','software_payment_reversals','software_payment_disputes'] as $name) {
                if (!software_commerce_table_present($name)) db()->exec($schema[$name]);
            }
            foreach (array_keys($schema) as $name) {
                if (!software_commerce_table_present($name)) {
                    throw new RuntimeException('Software v2 upgrade is incomplete; preserve the database.');
                }
            }
            if (!software_commerce_column_present('software_entitlements','purpose')) {
                throw new RuntimeException('Software v2 entitlement upgrade is incomplete; preserve the database.');
            }
            staff_transaction(function() use ($actor): void {
                save_setting('software_commerce_version','2');
                save_setting('software_commerce_pending','');
                license_audit((int)$actor['id'], 'software.upgrade', 'Software commerce upgraded from v1 to v2');
            });
            return;
        }

        foreach (array_keys($schema) as $name) {
            if (!software_commerce_table_present($name)) {
                throw new RuntimeException('Established software v2 tables are missing; preserve the database.');
            }
        }
        if (!software_commerce_column_present('software_entitlements','purpose')) {
            throw new RuntimeException('Established software v2 entitlement metadata is incomplete; preserve the database.');
        }
        if ($pending !== '') {
            staff_transaction(function() use ($actor): void {
                save_setting('software_commerce_pending','');
                license_audit((int)$actor['id'], 'software.recovery', 'Cleared completed software v2 preparation marker');
            });
        }
    });
}
