<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function delivery_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {if(support_current())throw new RuntimeException('Leave support before setup.');$actor=staff_owner($password);if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');delivery_directory();
        foreach(delivery_schema(config()['db']['prefix']) as $name=>$sql){if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){catalog_validate_schema($name,$sql,$name==='website_releases'?['request_key'=>['request_key'],'project_version'=>['job_id','version'],'project_sequence'=>['job_id','release_no']]:[]);foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c)if(in_array($c['Field'],['id','release_id','version','sha256','request_key','request_hash'],true)&&str_contains(strtolower($c['Type']),'char')&&$c['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible delivery identifiers.');}else db()->exec($sql);}
        staff_transaction(function() use($actor): void {if(!delivery_ready()){foreach(['website_releases','delivery_events'] as $table)if((int)query('SELECT COUNT(*) FROM '.table($table))->fetchColumn())throw new RuntimeException('Unrecognized delivery records exist. No data was adopted.');save_setting('delivery_schema_version','1');}delivery_audit((int)$actor['id'],'setup','Additive website delivery schema verified');});
    });
}
