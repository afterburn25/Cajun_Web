<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function hosting_records_validate_schema(string $name,string $sql): void {
    $unique=match($name) {
        'hosting_records'=>[],
        'hosting_record_events'=>['account_revision'=>['account_id','revision']],
        default=>throw new LogicException('Unknown hosting storage.'),
    };
    catalog_validate_schema($name,$sql,$unique);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        if($column['Extra']!=='' || $column['Default']!==null) throw new RuntimeException('Incompatible hosting columns. Existing data was preserved.');
        $expected=match($column['Field']) {
            'id','account_id','journal_sha256','before_sha256','after_sha256'=>'ascii_bin',
            'initial_json','journal_json','input_json'=>'utf8mb4_bin',
            default=>null,
        };
        if($column['Collation']!==$expected) throw new RuntimeException('Incompatible hosting identifier or text storage. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]];
    foreach($unique as $key=>$columns)$expected[$key]=[0,$columns];
    $other=match($name) {
        'hosting_records'=>['client_id'=>['client_id'],'created_by'=>['created_by']],
        'hosting_record_events'=>['actor_id'=>['actor_id']],
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
        'hosting_records'=>['client_id'=>['studio_clients','id'],'created_by'=>['users','id']],
        'hosting_record_events'=>['account_id'=>['hosting_records','id'],'actor_id'=>['users','id']],
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

function hosting_records_migrate(#[\SensitiveParameter] string $password): void {
    if(db()->inTransaction())throw new LogicException('Hosting setup cannot run inside a transaction.');
    staff_locked(function()use($password):void {
        $actor=hosting_records_password($password,false);
        $version=(one('SELECT value FROM '.table('settings').' WHERE name=?',['hosting_records_schema_version'])['value']??'');
        if(!in_array($version,['','1'],true))throw new RuntimeException('This hosting storage version is not supported. Existing data was preserved.');
        hosting_storage_prepare('records');
        foreach(hosting_records_schema(config()['db']['prefix']) as $name=>$sql) {
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
            hosting_records_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void {
            if(!hosting_records_ready()) {
                foreach(array_keys(hosting_records_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized hosting records exist. No data was adopted.');
                save_setting('hosting_records_schema_version','1');
            }
            hosting_records_audit((int)$actor['id'],'setup','Additive owner hosting storage verified');
        });
    });
}
