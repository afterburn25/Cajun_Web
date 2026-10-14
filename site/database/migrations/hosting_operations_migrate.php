<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function hosting_operations_validate_schema(string $name,string $sql): void {
    $unique=match($name) {
        'hosting_dispatches'=>['account_revision'=>['account_id','revision'],'account_attempt'=>['account_id','attempt_ref']],
        'hosting_operation_results'=>['event_id'=>['event_id']],
        'hosting_resources'=>['account_id'=>['account_id'],'provider_resource'=>['profile_id','resource_ref']],
        default=>throw new LogicException('Unknown hosting operation storage.'),
    };
    catalog_validate_schema($name,$sql,$unique);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        if($column['Extra']!=='' || $column['Default']!==null) throw new RuntimeException('Incompatible hosting columns. Existing data was preserved.');
        $expected=match($column['Field']) {
            'id','account_id','profile_id','event_id','operation','attempt_ref'=>'ascii_bin',
            'input_json','binding_json','result_json','resource_ref'=>'utf8mb4_bin',
            default=>null,
        };
        if($column['Collation']!==$expected) throw new RuntimeException('Incompatible hosting identifier or text storage. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]];
    foreach($unique as $key=>$columns)$expected[$key]=[0,$columns];
    $other=match($name) {
        'hosting_dispatches'=>['profile_id'=>['profile_id'],'actor_id'=>['actor_id']],
        'hosting_operation_results','hosting_resources'=>[],
    };
    foreach($other as $key=>$columns)$expected[$key]=[1,$columns];
    $actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index) {
        if($index['Sub_part']!==null || $index['Index_type']!=='BTREE') throw new RuntimeException('Incompatible hosting indexes. Existing data was preserved.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];
        $actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible hosting indexes. Existing data was preserved.');
    $expected=match($name) {
        'hosting_dispatches'=>['id'=>['hosting_record_events','id'],'account_id'=>['hosting_records','id'],'profile_id'=>['provider_profiles','id'],'actor_id'=>['users','id']],
        'hosting_operation_results'=>['id'=>['hosting_dispatches','id'],'event_id'=>['hosting_record_events','id']],
        'hosting_resources'=>['id'=>['hosting_operation_results','id'],'account_id'=>['hosting_records','id'],'profile_id'=>['provider_profiles','id']],
    };
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Incompatible hosting relationships. Existing data was preserved.');
    $seen=[];
    foreach($relations as $relation) {
        $e=$expected[$relation['COLUMN_NAME']]??null;
        if(!$e || isset($seen[$relation['COLUMN_NAME']]) || $relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$e[0] || $relation['REFERENCED_COLUMN_NAME']!==$e[1] || !in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true) || !in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible hosting relationships. Existing data was preserved.');
        $seen[$relation['COLUMN_NAME']]=true;
    }
}

function hosting_operations_migrate(#[\SensitiveParameter] string $password): void {
    if(db()->inTransaction())throw new LogicException('Hosting setup cannot run inside a transaction.');
    staff_locked(function()use($password):void {
        $actor=hosting_records_password($password);provider_settings_manager();
        $version=(one('SELECT value FROM '.table('settings').' WHERE name=?',['hosting_operations_schema_version'])['value']??'');
        if(!in_array($version,['','1'],true))throw new RuntimeException('This hosting storage version is not supported. Existing data was preserved.');
        foreach(hosting_operations_schema(config()['db']['prefix']) as $name=>$sql) {
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
            hosting_operations_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void {
            if(!hosting_operations_ready()) {
                foreach(array_keys(hosting_operations_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized hosting records exist. No data was adopted.');
                $after='';
                do {
                    $rows=all('SELECT * FROM '.table('hosting_records').' WHERE id>? ORDER BY id LIMIT 100',[$after]);
                    foreach($rows as $row){hosting_operations_validate($row);$after=$row['id'];}
                }while(count($rows)===100);
                save_setting('hosting_operations_schema_version','1');
            }
            hosting_records_audit((int)$actor['id'],'operations_setup','Additive hosting operation storage verified');
        });
    });
}
