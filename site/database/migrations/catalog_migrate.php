<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function catalog_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {
        $actor=staff_owner($password);if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $missing=[];foreach(catalog_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))catalog_validate_schema($name,$sql);else $missing[]=$sql;
        }
        foreach($missing as $sql)db()->exec($sql);
        staff_transaction(function() use($actor): void {
            if(setting('catalog_schema_version')!=='1')foreach(array_keys(catalog_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0)throw new RuntimeException('Unrecognized catalog records exist. No data was adopted.');
            save_setting('catalog_schema_version','1');catalog_audit((int)$actor['id'],'setup','Additive catalog schema 1');
        });
    });
}
