<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function mail_copies_validate_schema(string $name,string $sql):void {
    catalog_validate_schema($name,$sql,[]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        $collation=match(true) {
            in_array($column['Field'],['id','submission_id','account_id','sent_folder','folder','mime_sha256'],true)=>'ascii_bin',
            in_array($column['Field'],['outcome','result_code'],true)=>'utf8mb4_unicode_ci',
            default=>null,
        };
        if($column['Collation']!==$collation||$column['Default']!==null||$column['Extra']!=='')throw new RuntimeException('Incompatible Sent-copy columns. Existing data was preserved.');
    }
    $expected=match($name) {
        'mail_sent_folders'=>['PRIMARY'=>[0,['id']],'updated_by'=>[1,['updated_by']]],
        'mail_copy_attempts'=>['PRIMARY'=>[0,['id']],'submission_copies'=>[1,['submission_id']],'account_copies'=>[1,['account_id','started_epoch']],'actor_copies'=>[1,['actor_id','started_epoch']]],
        'mail_copy_results'=>['PRIMARY'=>[0,['id']]],
        default=>throw new RuntimeException('Unknown Sent-copy table.'),
    };
    $actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index) {
        if($index['Sub_part']!==null||$index['Index_type']!=='BTREE')throw new RuntimeException('Incompatible Sent-copy indexes. Existing data was preserved.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible Sent-copy indexes. Existing data was preserved.');
    $expectedRelations=match($name) {
        'mail_sent_folders'=>['id'=>'mail_accounts','updated_by'=>'users'],
        'mail_copy_attempts'=>['submission_id'=>'mail_submissions','account_id'=>'mail_accounts','actor_id'=>'users'],
        'mail_copy_results'=>['id'=>'mail_copy_attempts'],
    };
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    $database=(string)query('SELECT DATABASE()')->fetchColumn();$seen=[];
    if(count($relations)!==count($expectedRelations))throw new RuntimeException('Incompatible Sent-copy relationships. Existing data was preserved.');
    foreach($relations as $relation) {
        $parent=$expectedRelations[$relation['COLUMN_NAME']]??null;
        if(!$parent||isset($seen[$relation['COLUMN_NAME']])||$relation['REFERENCED_TABLE_SCHEMA']!==$database||$relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$parent||$relation['REFERENCED_COLUMN_NAME']!=='id'||!in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible Sent-copy relationships. Existing data was preserved.');
        $seen[$relation['COLUMN_NAME']]=true;
    }
}

function mail_copies_migrate():void {
    staff_locked(function():void {
        $actor=mail_accounts_owner('');
        if(!mail_submissions_ready())throw new RuntimeException('An owner must prepare email submission storage first.');
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_copies_schema_version'])['value']??'';
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unsupported Sent-copy storage version. Existing data was preserved.');
        $schema=mail_copies_schema(config()['db']['prefix']);$present=[];
        foreach($schema as $name=>$sql) {
            $present[$name]=(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
            if($present[$name])mail_copies_validate_schema($name,$sql);
        }
        if($version==='1'&&in_array(false,$present,true))throw new RuntimeException('Sent-copy storage is incomplete. Existing data was preserved.');
        if($version==='')foreach($present as $name=>$exists)if($exists&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized Sent-copy records exist. No data was adopted.');
        foreach($schema as $name=>$sql) {
            if(!$present[$name])db()->exec($sql);
            mail_copies_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void {
            if(!mail_copies_ready())save_setting('mail_copies_schema_version','1');
            mail_accounts_audit((int)$actor['id'],'copy_setup','Additive Sent-copy storage verified');
        });
    });
}
