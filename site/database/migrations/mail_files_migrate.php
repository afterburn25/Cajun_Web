<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function mail_files_validate_schema(string $name,string $sql):void {
    catalog_validate_schema($name,$sql,[]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        $collation=in_array($column['Field'],['id','draft_id','submission_id','name','mime','sha256'],true)?'ascii_bin':null;
        if($column['Collation']!==$collation||$column['Default']!==null||$column['Extra']!=='')throw new RuntimeException('Incompatible attachment columns. Existing data was preserved.');
    }
    $foreign=$name==='mail_draft_files'?'draft_id':'submission_id';$parent=$name==='mail_draft_files'?'mail_drafts':'mail_submissions';
    $expected=['PRIMARY'=>[0,['id']],str_replace('_id','_files',$foreign)=>[1,[$foreign]]];
    $actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index) {
        if($index['Sub_part']!==null||$index['Index_type']!=='BTREE')throw new RuntimeException('Incompatible attachment indexes. Existing data was preserved.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible attachment indexes. Existing data was preserved.');
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    $database=(string)query('SELECT DATABASE()')->fetchColumn();
    if(count($relations)!==1||$relations[0]['COLUMN_NAME']!==$foreign||$relations[0]['REFERENCED_TABLE_SCHEMA']!==$database||$relations[0]['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$parent||$relations[0]['REFERENCED_COLUMN_NAME']!=='id'||!in_array($relations[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($relations[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible attachment relationships. Existing data was preserved.');
}

/** Current-owner gated additive storage; unknown or populated foreign tables are never adopted. */
function mail_files_migrate():void {
    staff_locked(function():void {
        $actor=mail_accounts_owner('');
        $draftsReady=in_array(one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_drafts_schema_version'])['value']??'',['1','2'],true);
        $submissionsReady=(one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_submissions_schema_version'])['value']??'')==='1';
        if(!$draftsReady||!$submissionsReady)throw new RuntimeException('An owner must prepare draft and submission storage first.');
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_files_schema_version'])['value']??'';
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unsupported attachment storage version. Existing data was preserved.');
        $schema=mail_files_schema(config()['db']['prefix']);$present=[];
        foreach($schema as $name=>$sql) {
            $present[$name]=(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
            if($present[$name])mail_files_validate_schema($name,$sql);
        }
        if($version==='1'&&in_array(false,$present,true))throw new RuntimeException('Attachment storage is incomplete. Existing data was preserved.');
        if($version==='')foreach($present as $name=>$exists)if($exists&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized attachment records exist. No data was adopted.');
        foreach($schema as $name=>$sql) {
            if(!$present[$name])db()->exec($sql);
            mail_files_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor,$version):void {
            if($version!=='1')save_setting('mail_files_schema_version','1');
            mail_accounts_audit((int)$actor['id'],'files_setup','Additive attachment storage verified');
        });
    });
}
