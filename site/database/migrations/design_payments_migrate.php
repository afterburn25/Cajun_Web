<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function design_payments_validate_schema(string $name,string $sql):void {
    $events=$name==='design_payment_events';if(!$events&&$name!=='design_payment_heads')throw new LogicException('Unknown financial table.');
    catalog_validate_schema($name,$sql,$events?['job_sequence'=>['job_id','sequence']]:['head_event'=>['event_id']]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column){
        $collation=match($column['Field']){'id'=>$events?'ascii_bin':null,'event_id','event_sha256','input_sha256','kind'=>'ascii_bin','event_json'=>'utf8mb4_bin',default=>null};
        if($column['Extra']!==''||$column['Default']!==null||$column['Collation']!==$collation)throw new RuntimeException('Incompatible financial columns. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]]+($events?['job_sequence'=>[0,['job_id','sequence']],'client_history'=>[1,['client_id','created_at','id']],'staff_id'=>[1,['staff_id']],'client_actor_id'=>[1,['client_actor_id']]]:['head_event'=>[0,['event_id']]]);
    $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $index){
        if($index['Sub_part']!==null||$index['Index_type']!=='BTREE')throw new RuntimeException('Incompatible financial indexes.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$entry){ksort($entry[1]);$entry[1]=array_values($entry[1]);}unset($entry);ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible financial indexes. Existing data was preserved.');
    $expected=$events?['job_id'=>['studio_jobs','id'],'client_id'=>['studio_clients','id'],'staff_id'=>['users','id'],'client_actor_id'=>['studio_clients','id']]:['id'=>['studio_jobs','id'],'event_id'=>['design_payment_events','id']];
    $rows=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($rows)!==count($expected))throw new RuntimeException('Incompatible financial relationships.');$seen=[];
    foreach($rows as $row){$target=$expected[$row['COLUMN_NAME']]??null;
        if(!$target||isset($seen[$row['COLUMN_NAME']])||$row['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target[0]||$row['REFERENCED_COLUMN_NAME']!==$target[1]||!in_array($row['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($row['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible financial relationships. Existing data was preserved.');
        $seen[$row['COLUMN_NAME']]=true;
    }
}
function design_payments_migrate(#[\SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Financial setup cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        design_payments_staff(false);$actor=payments_owner($password);
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['design_payments_schema_version'])['value']??'';
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unknown financial storage version. Existing data was preserved.');
        foreach(design_payments_schema(config()['db']['prefix']) as $name=>$sql){
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
            design_payments_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void{
            if(!design_payments_ready()){
                foreach(array_keys(design_payments_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized financial records exist. No data was adopted.');
                save_setting('design_payments_schema_version','1');
            }
            design_payments_audit(['type'=>'staff','id'=>(int)$actor['id']],'setup','Financial schedule storage verified');
        });
    });
}
