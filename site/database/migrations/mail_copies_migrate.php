<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function mail_copies_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function()use($password):void {
        $actor=mail_accounts_owner($password);
        if(!mail_submissions_ready())throw new RuntimeException('An owner must prepare email submission storage first.');
        $relations=['mail_sent_folders'=>[['id','mail_accounts']],'mail_copy_attempts'=>[['submission_id','mail_submissions'],['account_id','mail_accounts']],'mail_copy_results'=>[['id','mail_copy_attempts']]];
        foreach(mail_copies_schema(config()['db']['prefix']) as $name=>$sql) {
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))catalog_validate_schema($name,$sql,[]);else db()->exec($sql);
            foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column)if(in_array($column['Field'],['id','submission_id','account_id','sent_folder','folder','mime_sha256'],true)&&$column['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible copy identifiers. Existing data was preserved.');
            $found=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.ORDINAL_POSITION',[config()['db']['prefix'].$name]);
            $expected=$relations[$name];
            if(count($found)!==count($expected))throw new RuntimeException('Incompatible copy relationships. Existing data was preserved.');
            foreach($expected as $i=>$relation) {
                if($found[$i]['COLUMN_NAME']!==$relation[0]||$found[$i]['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$relation[1]||$found[$i]['REFERENCED_COLUMN_NAME']!=='id'||!in_array($found[$i]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($found[$i]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible copy relationships. Existing data was preserved.');
            }
        }
        staff_transaction(function()use($actor):void {
            if(!mail_copies_ready()) {
                foreach(array_keys(mail_copies_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized copy records exist. No data was adopted.');
                save_setting('mail_copies_schema_version','1');
            }
            mail_accounts_audit((int)$actor['id'],'copy_setup','Additive Sent-copy storage verified');
        });
    });
}
