<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function backups_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {if(support_current())throw new RuntimeException('Leave support before setup.');$actor=staff_owner($password);backups_directory();
        // Preflight the recorded marker against physical table state before any
        // DDL: an established marker with a missing history table means lost
        // backup records — refuse to silently recreate it as an empty table.
        // Legitimate v1 -> v2 upgrades still pass: backup_drills did not exist
        // at v1, so only tables established by the recorded marker are required.
        $marker=(one('SELECT value FROM '.table('settings').' WHERE name=?',['backups_schema_version'])['value']??'');
        if($marker!==''&&!in_array($marker,['1','2'],true))throw new RuntimeException('This backups schema version is not supported by this installation. No changes were made.');
        $present=[];
        foreach(backups_schema(config()['db']['prefix']) as $name=>$sql)$present[$name]=(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
        $required=$marker===''?[]:($marker==='1'?['backups']:array_keys(backups_schema(config()['db']['prefix'])));
        foreach($required as $name)if(!$present[$name])throw new RuntimeException('Recorded backup history is missing '.config()['db']['prefix'].$name.'. Restore the lost table instead of recreating empty records. No changes were made.');
        foreach(backups_schema(config()['db']['prefix']) as $name=>$sql){if($present[$name]){catalog_validate_schema($name,$sql,$name==='backups'?['request_key'=>['request_key']]:[]);foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c)if(in_array($c['Field'],['id','manifest_hash','request_key','request_hash','archive_file','backup_id'],true)&&str_contains(strtolower($c['Type']),'char')&&$c['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible backup identifiers.');
                if($name==='backup_drills'){$relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);if(count($relations)!==1||$relations[0]['COLUMN_NAME']!=='backup_id'||$relations[0]['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].'backups'||$relations[0]['REFERENCED_COLUMN_NAME']!=='id'||$relations[0]['DELETE_RULE']!=='CASCADE')throw new RuntimeException('Incompatible backup drill relationships. Existing data was preserved.');}}else db()->exec($sql);}
        staff_transaction(function() use($actor): void {$version=(string)(one('SELECT value FROM '.table('settings').' WHERE name=?',['backups_schema_version'])['value']??'');
            if($version===''){if((int)query('SELECT COUNT(*) FROM '.table('backups'))->fetchColumn())throw new RuntimeException('Unrecognized backup records exist. No data was adopted.');}
            elseif(!in_array($version,['1','2'],true))throw new RuntimeException('Unrecognized backup storage version. Existing data was preserved.');
            save_setting('backups_schema_version','2');backups_audit((int)$actor['id'],'setup','Additive backup schema verified');});
    });
}
