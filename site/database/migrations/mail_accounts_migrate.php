<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function mail_accounts_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use ($password): void {
        $actor=mail_accounts_owner($password,false);
        $keys=['mail_accounts'=>[],'mail_account_revisions'=>['account_revision'=>['account_id','revision']],
            'mail_account_grants'=>['account_staff'=>['account_id','user_id']],
            'mail_grant_revisions'=>['grant_revision'=>['account_id','user_id','revision']]];
        foreach (mail_accounts_schema(config()['db']['prefix']) as $name=>$sql) {
            if (one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])) {
                catalog_validate_schema($name,$sql,$keys[$name]);
                foreach (all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
                    if (in_array($column['Field'],['id','account_id'],true) && str_starts_with($column['Type'],'char(') && $column['Collation']!=='ascii_bin') {
                        throw new RuntimeException('Incompatible email account identifiers. Existing data was preserved.');
                    }
                }
            } else db()->exec($sql);
        }
        staff_transaction(function() use ($actor): void {
            if (!mail_accounts_ready()) {
                foreach (array_keys(mail_accounts_schema(config()['db']['prefix'])) as $name) {
                    if ((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()) throw new RuntimeException('Unrecognized email account records exist. No data was adopted.');
                }
                save_setting('mail_accounts_schema_version','1');
            }
            mail_accounts_audit((int)$actor['id'],'setup','Additive account and assignment storage verified');
        });
    });
}
