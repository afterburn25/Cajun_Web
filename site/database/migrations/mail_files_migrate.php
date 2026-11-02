<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
/** Owner-password gated additive attachment storage; unknown or populated foreign tables are never adopted. */
function mail_files_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function()use($password):void {
        $actor=mail_accounts_owner($password);
        if(!mail_drafts_ready()||!mail_submissions_ready())throw new RuntimeException('An owner must prepare draft and submission storage first.');
        $relations=['mail_draft_files'=>[['draft_id','mail_drafts']],'mail_submission_files'=>[['submission_id','mail_submissions']]];
        foreach(mail_files_schema(config()['db']['prefix']) as $name=>$sql) {
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))catalog_validate_schema($name,$sql,[]);else db()->exec($sql);
            foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column)if(in_array($column['Field'],['id','draft_id','submission_id','name','mime','sha256'],true)&&$column['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible attachment identifiers. Existing data was preserved.');
            $found=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.ORDINAL_POSITION',[config()['db']['prefix'].$name]);
            $expected=$relations[$name];
            if(count($found)!==count($expected))throw new RuntimeException('Incompatible attachment relationships. Existing data was preserved.');
            foreach($expected as $i=>$relation) {
                if($found[$i]['COLUMN_NAME']!==$relation[0]||$found[$i]['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$relation[1]||$found[$i]['REFERENCED_COLUMN_NAME']!=='id'||!in_array($found[$i]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($found[$i]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible attachment relationships. Existing data was preserved.');
            }
        }
        staff_transaction(function()use($actor):void {
            if(!mail_files_ready()) {
                foreach(array_keys(mail_files_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized attachment records exist. No data was adopted.');
                save_setting('mail_files_schema_version','1');
            }
            mail_accounts_audit((int)$actor['id'],'files_setup','Additive attachment storage verified');
        });
    });
}
