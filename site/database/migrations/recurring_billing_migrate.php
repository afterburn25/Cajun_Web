<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function recurring_billing_validate_schema(string $name,string $sql):void {
    $c=recurring_billing_constraints($name);catalog_validate_schema($name,$sql,$c['unique']);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column){
        $expected=in_array($column['Field'],['id','latest_id','latest_sha256','subscription_id','previous_id','previous_sha256','journal_sha256','schedule_id','before_sha256','after_sha256','schedule_sha256','request_sha256','snapshot_sha256'],true)?'ascii_bin':(str_ends_with($column['Field'],'_json')?'utf8mb4_bin':null);
        if($column['Extra']!==''||$column['Default']!==null||$column['Collation']!==$expected)throw new RuntimeException('Incompatible recurring billing columns. Existing records were preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]];$actual=[];
    foreach($c['unique'] as $key=>$fields)$expected[$key]=[0,$fields];
    foreach($c['indexes'] as $key=>$fields)$expected[$key]=[1,$fields];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index){
        if($index['Sub_part']!==null||$index['Index_type']!=='BTREE')throw new RuntimeException('Incompatible recurring billing index.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$entry){ksort($entry[1]);$entry[1]=array_values($entry[1]);}unset($entry);ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible recurring billing indexes.');
    $rows=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);$seen=[];
    if(count($rows)!==count($c['relations']))throw new RuntimeException('Incompatible recurring billing relationships.');
    foreach($rows as $row){$target=$c['relations'][$row['COLUMN_NAME']]??null;
        if(!$target||isset($seen[$row['COLUMN_NAME']])||$row['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target[0]||$row['REFERENCED_COLUMN_NAME']!==$target[1]||!in_array($row['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($row['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible recurring billing relationship.');$seen[$row['COLUMN_NAME']]=true;
    }
}
function recurring_billing_migrate(#[\SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Recurring billing setup cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        recurring_billing_staff(false);$actor=payments_owner($password);service_plans_require_ready();
        if(!ledger_ready())throw new RuntimeException('Prepare the invoice ledger first.');
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['recurring_billing_schema_version'])['value']??'';
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unknown recurring billing storage version.');
        foreach(recurring_billing_schema(config()['db']['prefix']) as $name=>$sql){
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
            recurring_billing_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void{
            if(!recurring_billing_ready()){
                foreach(array_keys(recurring_billing_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized recurring records exist. No data was adopted.');
                save_setting('recurring_billing_schema_version','1');
            }
            recurring_billing_audit('staff:'.$actor['id'],'setup','Additive recurring billing storage verified');
        });
    });
}
