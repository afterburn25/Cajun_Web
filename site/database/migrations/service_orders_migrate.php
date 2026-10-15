<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function service_orders_validate_schema(string $name,string $sql): void {
    $unique=match($name) {
        'service_orders'=>['agreement_id'=>['agreement_id'],'invoice_id'=>['invoice_id']],
        'service_order_events'=>['order_revision'=>['order_id','revision']],
        'hosting_order_links'=>['account_id'=>['account_id']],
        'hosting_fulfillments'=>[],
        default=>throw new LogicException('Unknown service-order storage.'),
    };
    catalog_validate_schema($name,$sql,$unique);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        if($column['Extra']!=='' || $column['Default']!==null) throw new RuntimeException('Incompatible service-order columns. Existing data was preserved.');
        $expected=match($column['Field']) {
            'id','agreement_id','order_id','account_id','profile_id','binding_sha256','journal_sha256','before_sha256','after_sha256'=>'ascii_bin',
            'initial_json','journal_json','input_json','binding_json'=>'utf8mb4_bin',
            default=>null,
        };
        if($column['Collation']!==$expected) throw new RuntimeException('Incompatible service-order identifier or text storage. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]];
    foreach($unique as $key=>$columns)$expected[$key]=[0,$columns];
    $other=match($name) {
        'service_orders'=>['client_id'=>['client_id'],'created_by'=>['created_by']],
        'service_order_events'=>['actor_staff_id'=>['actor_staff_id'],'actor_client_id'=>['actor_client_id']],
        'hosting_order_links'=>['client_id'=>['client_id'],'created_by'=>['created_by'],'profile_id'=>['profile_id']],
        'hosting_fulfillments'=>['order_id'=>['order_id'],'account_id'=>['account_id'],'actor_id'=>['actor_id']],
    };
    foreach($other as $key=>$columns)$expected[$key]=[1,$columns];
    $actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index) {
        if($index['Sub_part']!==null || $index['Index_type']!=='BTREE') throw new RuntimeException('Incompatible service-order indexes. Existing data was preserved.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];
        $actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible service-order indexes. Existing data was preserved.');
    $expected=match($name) {
        'service_orders'=>['agreement_id'=>['service_subscriptions','id'],'invoice_id'=>['studio_invoices','id'],'client_id'=>['studio_clients','id'],'created_by'=>['users','id']],
        'service_order_events'=>['order_id'=>['service_orders','id'],'actor_staff_id'=>['users','id'],'actor_client_id'=>['studio_clients','id']],
        'hosting_order_links'=>['id'=>['service_orders','id'],'account_id'=>['hosting_records','id'],'client_id'=>['studio_clients','id'],'created_by'=>['users','id'],'profile_id'=>['provider_profiles','id']],
        'hosting_fulfillments'=>['id'=>['hosting_dispatches','id'],'order_id'=>['hosting_order_links','id'],'account_id'=>['hosting_records','id'],'actor_id'=>['users','id']],
    };
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Incompatible service-order relationships. Existing data was preserved.');
    $seen=[];
    foreach($relations as $relation) {
        $e=$expected[$relation['COLUMN_NAME']]??null;
        if(!$e || isset($seen[$relation['COLUMN_NAME']]) || $relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$e[0] || $relation['REFERENCED_COLUMN_NAME']!==$e[1] || !in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true) || !in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible service-order relationships. Existing data was preserved.');
        $seen[$relation['COLUMN_NAME']]=true;
    }
}

function service_orders_migrate(#[\SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Order setup cannot inherit a transaction.');
    staff_locked(function()use($password):void {
        $actor=service_orders_owner($password,false);$s=service_orders_storage();
        if(!in_array($s['version'],['','1'],true))throw new RuntimeException('This order storage version is unsupported. Existing data was preserved.');
        if(($s['version']==='1'&&!$s['complete'])||($s['version']===''&&one('SELECT id FROM '.table('audit').' WHERE action=? LIMIT 1',['service_orders.setup'])))throw new RuntimeException('Established order history is missing. Restore the original database before continuing.');
        if($s['version']==='')foreach($s['existing'] as $name)if(one('SELECT 1 AS present FROM '.table($name).' LIMIT 1'))throw new RuntimeException('Unrecognized order history exists. No data was adopted.');
        foreach(service_orders_schema(config()['db']['prefix']) as $name=>$sql){
            if(!in_array($name,$s['existing'],true))db()->exec($sql);
            service_orders_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void {
            save_setting('service_orders_schema_version','1');
            service_orders_audit('staff:'.$actor['id'],'setup','Additive initial hosting order storage verified');
        });
    });
}
