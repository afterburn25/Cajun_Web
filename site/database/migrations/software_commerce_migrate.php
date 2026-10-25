<?php
/** Owner-approved additive software commerce database setup. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/software_commerce_schema.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';

function software_commerce_ready(): bool {
    return setting('software_commerce_version') === '1';
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
        if ($v !== '' && $v !== '1') throw new RuntimeException('Unknown software commerce version.');
        $schema = software_commerce_schema(config()['db']['prefix']);
        $present = [];
        foreach ($schema as $name => $sql) {
            $found = one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', [config()['db']['prefix'].$name]);
            if ($found) $present[] = $name;
        }
        $pending = (string)setting('software_commerce_pending');
        if ($v === '' && $pending === '' && $present !== []) {
            throw new RuntimeException('Unrecognized software tables: preserve and review the existing database.');
        }
        if ($v === '' && !in_array($pending, ['', '1'], true)) {
            throw new RuntimeException('Unknown software setup history.');
        }
        if ($v === '1' && count($present) !== count($schema)) {
            throw new RuntimeException('Established software tables are missing; preserve the database.');
        }
        if ($v === '' && $pending === '') {
            staff_transaction(function() use ($actor): void {
                save_setting('software_commerce_pending', '1');
                license_audit((int)$actor['id'], 'software.setup.begin', 'Software commerce preparation started');
            });
        }
        foreach ($schema as $name => $sql) {
            if (!in_array($name, $present, true)) db()->exec($sql);
            if ($v === '' && in_array($name, $present, true)) {
                $count = (int)query('SELECT COUNT(*) FROM '.chr(96).config()['db']['prefix'].$name.chr(96))->fetchColumn();
                if ($count !== 0) throw new RuntimeException('Interrupted software preparation contains data: preserve and review.');
            }
        }
        staff_transaction(function() use ($actor): void {
            save_setting('software_commerce_version','1');
            save_setting('software_commerce_pending','');
            license_audit((int)$actor['id'], 'software.setup', 'Software commerce enabled; prior licenses preserved');
        });
    });
}
