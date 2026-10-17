<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function backups_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {if(support_current())throw new RuntimeException('Leave support before setup.');$actor=staff_owner($password);backups_directory();
        foreach(backups_schema(config()['db']['prefix']) as $name=>$sql){if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){catalog_validate_schema($name,$sql,['request_key'=>['request_key'],'backups_retention'=>['retain_until','status']]);foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c)if(in_array($c['Field'],['id','manifest_hash','request_key','request_hash','archive_file'],true)&&str_contains(strtolower($c['Type']),'char')&&$c['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible backup identifiers.');}else db()->exec($sql);}
        staff_transaction(function() use($actor): void {if(!backups_ready()){if((int)query('SELECT COUNT(*) FROM '.table('backups'))->fetchColumn())throw new RuntimeException('Unrecognized backup records exist. No data was adopted.');save_setting('backups_schema_version','1');}backups_audit((int)$actor['id'],'setup','Additive backup schema verified');});
    });
}
