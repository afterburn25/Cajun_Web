<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function provider_settings_validate_schema(string $name,string $sql): void {
    $unique=match($name) {
        'provider_profiles'=>['provider_environment'=>['provider_code','environment']],
        'provider_revisions'=>['profile_revision'=>['profile_id','revision']],
        'provider_checks','provider_check_results'=>[],
        default=>throw new LogicException('Unknown provider storage.'),
    };
    catalog_validate_schema($name,$sql,$unique);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        if($column['Extra']!=='' || $column['Default']!==null) throw new RuntimeException('Incompatible provider columns. Existing data was preserved.');
        $expected=match($column['Field']) {
            'id','profile_id','provider_code','environment','credentials','action','state','result_code'=>'ascii_bin',
            'label'=>'utf8mb4_unicode_ci',
            default=>null,
        };
        if($column['Collation']!==$expected) throw new RuntimeException('Incompatible provider identifier or text storage. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]];
    foreach($unique as $key=>$columns)$expected[$key]=[0,$columns];
    $other=match($name) {
        'provider_profiles'=>['updated_by'=>['updated_by']],
        'provider_revisions'=>['actor_id'=>['actor_id']],
        'provider_checks'=>['profile_checks'=>['profile_id','started_epoch'],'actor_checks'=>['actor_id','started_epoch']],
        'provider_check_results'=>[],
    };
    foreach($other as $key=>$columns)$expected[$key]=[1,$columns];
    $actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index) {
        if($index['Sub_part']!==null || $index['Index_type']!=='BTREE') throw new RuntimeException('Incompatible provider indexes. Existing data was preserved.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];
        $actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible provider indexes. Existing data was preserved.');
    $expected=match($name) {
        'provider_profiles'=>['updated_by'=>['users','id']],
        'provider_revisions','provider_checks'=>['profile_id'=>['provider_profiles','id'],'actor_id'=>['users','id']],
        'provider_check_results'=>['id'=>['provider_checks','id']],
    };
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Incompatible provider relationships. Existing data was preserved.');
    $seen=[];
    foreach($relations as $relation) {
        $e=$expected[$relation['COLUMN_NAME']]??null;
        if(!$e || isset($seen[$relation['COLUMN_NAME']]) || $relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$e[0] || $relation['REFERENCED_COLUMN_NAME']!==$e[1] || !in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true) || !in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible provider relationships. Existing data was preserved.');
        $seen[$relation['COLUMN_NAME']]=true;
    }
}

function provider_settings_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function()use($password):void {
        $actor=provider_settings_owner($password,false);
        $version=(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_settings_schema_version'])['value']??'');
        if(!in_array($version,['','1'],true))throw new RuntimeException('This provider storage version is not supported. Existing data was preserved.');
        foreach(provider_settings_schema(config()['db']['prefix']) as $name=>$sql) {
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
            provider_settings_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void {
            if(!provider_settings_ready()) {
                foreach(array_keys(provider_settings_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized provider records exist. No data was adopted.');
                save_setting('provider_settings_schema_version','1');
            }
            provider_settings_audit((int)$actor['id'],'setup','Additive owner provider storage verified');
        });
    });
}
