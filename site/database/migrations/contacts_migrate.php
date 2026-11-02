<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function contacts_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {
        $actor=payments_owner($password);if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $version=setting('contacts_schema_version');
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unrecognized contacts schema version '.$version.'. Existing data was preserved.');
        $missing=[];foreach(contacts_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))catalog_validate_schema($name,$sql,['email'=>['email']]);
            else $missing[]=$sql;
        }
        foreach($missing as $sql)db()->exec($sql);
        staff_transaction(function() use($actor,$version): void {
            if($version==='')foreach(array_keys(contacts_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0)throw new RuntimeException('Unrecognized contact records exist. No data was adopted.');
            if($version!=='1'){save_setting('contacts_schema_version','1');payments_audit((int)$actor['id'],'contacts.setup','Additive contacts schema 1; scoped client contact accounts');}
        });
    });
}
