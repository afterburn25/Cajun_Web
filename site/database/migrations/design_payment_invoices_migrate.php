<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function design_payment_invoices_validate_schema(string $sql):void {
    $name='design_payment_invoices';catalog_validate_schema($name,$sql,['obligation'=>['schedule_id','stage_id'],'invoice_id'=>['invoice_id']]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column){
        $text=in_array($column['Field'],['id','schedule_id','schedule_sha256','stage_id','consent_id','invoice_sha256','request_sha256'],true);
        if($column['Extra']!==''||$column['Default']!==null||$column['Collation']!==($text?'ascii_bin':null))throw new RuntimeException('Incompatible obligation columns. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']],'obligation'=>[0,['schedule_id','stage_id']],'invoice_id'=>[0,['invoice_id']],'job_id'=>[1,['job_id']],'client_id'=>[1,['client_id']],'staff_id'=>[1,['staff_id']],'consent_id'=>[1,['consent_id']]];$actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index){
        if($index['Sub_part']!==null||$index['Index_type']!=='BTREE')throw new RuntimeException('Incompatible obligation indexes.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$entry){ksort($entry[1]);$entry[1]=array_values($entry[1]);}unset($entry);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible obligation indexes. Existing data was preserved.');
    $expected=['id'=>['design_payment_events','id'],'schedule_id'=>['design_payment_events','id'],'consent_id'=>['design_payment_events','id'],'job_id'=>['studio_jobs','id'],'client_id'=>['studio_clients','id'],'staff_id'=>['users','id'],'invoice_id'=>['studio_invoices','id']];
    $rows=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);$seen=[];
    if(count($rows)!==count($expected))throw new RuntimeException('Incompatible obligation relationships.');
    foreach($rows as $row){$target=$expected[$row['COLUMN_NAME']]??null;if(!$target||isset($seen[$row['COLUMN_NAME']])||$row['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target[0]||$row['REFERENCED_COLUMN_NAME']!==$target[1]||!in_array($row['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($row['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible obligation relationships.');$seen[$row['COLUMN_NAME']]=true;}
}
function design_payment_invoices_migrate(#[\SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Invoice setup cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        design_payments_staff();$actor=payments_owner($password);if(!ledger_ready())throw new RuntimeException('Prepare the invoice payment ledger first.');
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['design_payment_invoices_schema_version'])['value']??'';
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unknown obligation storage version. Existing data was preserved.');
        $sql=design_payment_invoices_schema(config()['db']['prefix'])['design_payment_invoices'];
        if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'design_payment_invoices']))db()->exec($sql);
        design_payment_invoices_validate_schema($sql);
        staff_transaction(function()use($actor):void{
            if(!design_payment_invoices_ready()){
                if((int)query('SELECT COUNT(*) FROM '.table('design_payment_invoices'))->fetchColumn())throw new RuntimeException('Unrecognized obligation records exist. No data was adopted.');
                save_setting('design_payment_invoices_schema_version','1');
            }
            design_payments_audit(['type'=>'staff','id'=>(int)$actor['id']],'invoices_setup','Invoice obligation storage verified');
        });
    });
}
