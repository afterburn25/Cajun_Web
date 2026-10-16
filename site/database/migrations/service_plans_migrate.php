<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function service_plans_validate_schema(string $name,string $sql): void {
    $unique=match($name) {
        'service_plans','service_subscriptions'=>[],
        'service_plan_events'=>['plan_revision'=>['plan_id','revision']],
        'service_subscription_events'=>['subscription_revision'=>['subscription_id','revision']],
        default=>throw new LogicException('Unknown service-plan storage.'),
    };
    catalog_validate_schema($name,$sql,$unique);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        if($column['Extra']!=='' || $column['Default']!==null) throw new RuntimeException('Incompatible service-plan columns. Existing data was preserved.');
        $expected=match($column['Field']) {
            'id','plan_id','subscription_id','plan_sha256','journal_sha256','before_sha256','after_sha256'=>'ascii_bin',
            'initial_json','journal_json','input_json'=>'utf8mb4_bin',
            default=>null,
        };
        if($column['Collation']!==$expected) throw new RuntimeException('Incompatible service-plan identifier or text storage. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]];
    foreach($unique as $key=>$columns)$expected[$key]=[0,$columns];
    $other=match($name) {
        'service_plans'=>['created_by'=>['created_by']],
        'service_plan_events'=>['actor_id'=>['actor_id']],
        'service_subscriptions'=>['plan_id'=>['plan_id'],'client_id'=>['client_id'],'created_by'=>['created_by']],
        'service_subscription_events'=>['actor_staff_id'=>['actor_staff_id'],'actor_client_id'=>['actor_client_id']],
    };
    foreach($other as $key=>$columns)$expected[$key]=[1,$columns];
    $actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index) {
        if($index['Sub_part']!==null || $index['Index_type']!=='BTREE') throw new RuntimeException('Incompatible service-plan indexes. Existing data was preserved.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];
        $actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible service-plan indexes. Existing data was preserved.');
    $expected=match($name) {
        'service_plans'=>['created_by'=>['users','id']],
        'service_plan_events'=>['plan_id'=>['service_plans','id'],'actor_id'=>['users','id']],
        'service_subscriptions'=>['plan_id'=>['service_plans','id'],'client_id'=>['studio_clients','id'],'created_by'=>['users','id']],
        'service_subscription_events'=>['subscription_id'=>['service_subscriptions','id'],'actor_staff_id'=>['users','id'],'actor_client_id'=>['studio_clients','id']],
    };
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Incompatible service-plan relationships. Existing data was preserved.');
    $seen=[];
    foreach($relations as $relation) {
        $e=$expected[$relation['COLUMN_NAME']]??null;
        if(!$e || isset($seen[$relation['COLUMN_NAME']]) || $relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$e[0] || $relation['REFERENCED_COLUMN_NAME']!==$e[1] || !in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true) || !in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible service-plan relationships. Existing data was preserved.');
        $seen[$relation['COLUMN_NAME']]=true;
    }
}

function service_plans_migrate(#[\SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Service-plan setup cannot inherit a transaction.');
    staff_locked(function()use($password):void {
        $actor=service_plans_password($password,false);
        $version=(one('SELECT value FROM '.table('settings').' WHERE name=?',['service_plans_schema_version'])['value']??'');
        if(!in_array($version,['','1'],true))throw new RuntimeException('This service-plan storage version is not supported. Existing data was preserved.');
        foreach(service_plans_schema(config()['db']['prefix']) as $name=>$sql){
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
            service_plans_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void {
            if(!service_plans_ready()){
                foreach(array_keys(service_plans_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized service-plan records exist. No data was adopted.');
                save_setting('service_plans_schema_version','1');
            }
            service_plans_audit((int)$actor['id'],'setup','Additive service-plan storage verified');
        });
    });
}
