<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_domain_search_schema.php';

function provider_domain_search_validate_schema(string $name,string $sql): void {
    if(!in_array($name,['provider_domain_searches','provider_domain_search_results'],true))throw new LogicException('Unknown domain search storage.');
    catalog_validate_schema($name,$sql,[]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        $expected=in_array($column['Field'],['id','profile_id','query_name','tlds','state','reason','result','key_fingerprint'],true)?'ascii_bin':null;
        if($column['Extra']!=='' || $column['Default']!==null || $column['Collation']!==$expected)throw new RuntimeException('Incompatible domain search columns. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]];
    if($name==='provider_domain_searches')$expected+=['profile_searches'=>[1,['profile_id','started_epoch']],'search_actor'=>[1,['actor_id']]];
    $actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index) {
        if($index['Sub_part']!==null || $index['Index_type']!=='BTREE')throw new RuntimeException('Incompatible domain search indexes. Existing data was preserved.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];
        $actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible domain search indexes. Existing data was preserved.');
    $expected=$name==='provider_domain_searches'?['profile_id'=>['provider_profiles','id'],'actor_id'=>['users','id']]:['id'=>['provider_domain_searches','id']];
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Incompatible domain search relationships. Existing data was preserved.');
    $seen=[];
    foreach($relations as $relation) {
        $e=$expected[$relation['COLUMN_NAME']]??null;
        if(!$e || isset($seen[$relation['COLUMN_NAME']]) || $relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$e[0] || $relation['REFERENCED_COLUMN_NAME']!==$e[1] || !in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true) || !in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible domain search relationships. Existing data was preserved.');
        $seen[$relation['COLUMN_NAME']]=true;
    }
}
function provider_domain_search_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function()use($password):void {
        $actor=provider_settings_owner($password);
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_domain_search_schema_version'])['value']??'';
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unsupported domain search storage. Existing data was preserved.');
        foreach(provider_domain_search_schema(config()['db']['prefix']) as $name=>$sql) {
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])) {
                if($version==='1')throw new RuntimeException('Domain search history is missing. Restore the original database before preparing.');
                db()->exec($sql);
            }
            provider_domain_search_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor,$version):void {
            if($version==='')foreach(array_keys(provider_domain_search_schema(config()['db']['prefix'])) as $name)
                if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized domain search history. Restore its original database and version marker.');
            save_setting('provider_domain_search_schema_version','1');
            provider_settings_audit((int)$actor['id'],'domain_search_prepared','Domain lookup history verified');
        });
    });
}
