<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function mail_messages_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function()use($password):void {
        $actor=mail_accounts_owner($password);
        foreach(mail_messages_schema(config()['db']['prefix']) as $name=>$sql) {
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])) {
                if($name==='mail_message_reads' && strtolower((string)(one('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[config()['db']['prefix'].$name,'folder'])['COLUMN_TYPE']??''))==='varchar(5)')
                    db()->exec('ALTER TABLE `'.config()['db']['prefix'].$name.'` MODIFY COLUMN folder VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
                catalog_validate_schema($name,$sql,[]);
            } else db()->exec($sql);
            foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column)if(in_array($column['Field'],['id','account_id','folder','uidvalidity','uid'],true)&&$column['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible message request identifiers. Existing data was preserved.');
            $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
            [$field,$parent]=$name==='mail_message_reads'?['account_id','mail_accounts']:['id','mail_message_reads'];
            if(count($relations)!==1||$relations[0]['COLUMN_NAME']!==$field||$relations[0]['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$parent||$relations[0]['REFERENCED_COLUMN_NAME']!=='id'||!in_array($relations[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($relations[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible message request relationships. Existing data was preserved.');
        }
        staff_transaction(function()use($actor):void {
            if((one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_messages_schema_version'])['value']??'')==='') {
                foreach(array_keys(mail_messages_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized message request records exist. No data was adopted.');
            }
            save_setting('mail_messages_schema_version','2');
            mail_accounts_audit((int)$actor['id'],'message_setup','Additive message request storage verified');
        });
    });
}
