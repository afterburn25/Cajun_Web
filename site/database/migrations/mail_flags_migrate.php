<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function mail_flags_validate_schema(string $name,string $sql): void {
    catalog_validate_schema($name,$sql,[]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        $collation=match(true) {
            in_array($column['Field'],['id','account_id','folder','uidvalidity','uid'],true)=>'ascii_bin',
            in_array($column['Field'],['outcome','result_code'],true)=>'utf8mb4_unicode_ci',
            default=>null,
        };
        if($column['Collation']!==$collation||$column['Default']!==null||$column['Extra']!=='')throw new RuntimeException('Incompatible flag request columns. Existing data was preserved.');
    }
    $expected=$name==='mail_flag_requests'?
        ['PRIMARY'=>[0,['id']],'account_flags'=>[1,['account_id','started_epoch']],'actor_flags'=>[1,['actor_id','started_epoch']]]:
        ['PRIMARY'=>[0,['id']]];
    $actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index) {
        if($index['Sub_part']!==null||$index['Index_type']!=='BTREE')throw new RuntimeException('Incompatible flag request indexes. Existing data was preserved.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible flag request indexes. Existing data was preserved.');
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    [$field,$parent]=$name==='mail_flag_requests'?['account_id','mail_accounts']:['id','mail_flag_requests'];
    $database=(string)query('SELECT DATABASE()')->fetchColumn();
    if(count($relations)!==1||$relations[0]['COLUMN_NAME']!==$field||$relations[0]['REFERENCED_TABLE_SCHEMA']!==$database||$relations[0]['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$parent||$relations[0]['REFERENCED_COLUMN_NAME']!=='id'||!in_array($relations[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($relations[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible flag request relationships. Existing data was preserved.');
}
function mail_flags_migrate(): void {
    staff_locked(function():void {
        $actor=mail_accounts_owner('');
        if(!mail_messages_ready())throw new RuntimeException('An owner must prepare message reading in Email accounts first.');
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_flags_schema_version'])['value']??'';
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unsupported message flag storage version. Existing data was preserved.');
        $schema=mail_flags_schema(config()['db']['prefix']);$present=[];
        foreach($schema as $name=>$sql) {
            $present[$name]=(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
            if($present[$name])mail_flags_validate_schema($name,$sql);
        }
        if($version==='1'&&in_array(false,$present,true))throw new RuntimeException('Message flag storage is incomplete. Existing data was preserved.');
        if($version==='')foreach($present as $name=>$exists)if($exists&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized flag request records exist. No data was adopted.');
        foreach($schema as $name=>$sql) {
            if(!$present[$name])db()->exec($sql);
            mail_flags_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void {
            if(!mail_flags_ready()) {
                save_setting('mail_flags_schema_version','1');
            }
            mail_accounts_audit((int)$actor['id'],'flag_setup','Additive message flag storage verified');
        });
    });
}
