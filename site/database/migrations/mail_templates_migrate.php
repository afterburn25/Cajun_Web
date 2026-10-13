<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function mail_templates_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use ($password): void {
        if (support_current()) throw new RuntimeException('Leave client support before enabling templates.');
        $actor=staff_owner($password);
        foreach (mail_templates_schema(config()['db']['prefix']) as $name=>$sql) {
            if (one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])) {
                catalog_validate_schema($name,$sql,$name==='mail_templates'?[]:['template_revision'=>['template_id','revision']]);
                foreach (all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
                    if (in_array($column['Field'],['id','template_id'],true) && str_starts_with($column['Type'],'char(') && $column['Collation']!=='ascii_bin') {
                        throw new RuntimeException('Incompatible template identifiers. Existing records were preserved.');
                    }
                }
            } else db()->exec($sql);
        }
        staff_transaction(function() use ($actor): void {
            if (!mail_templates_ready()) {
                foreach (array_keys(mail_templates_schema(config()['db']['prefix'])) as $name) {
                    if ((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()) throw new RuntimeException('Unrecognized template records exist. No data was adopted.');
                }
                save_setting('mail_templates_schema_version','1');
            }
            mail_templates_audit((int)$actor['id'],'setup','Additive template storage verified');
        });
    });
}
