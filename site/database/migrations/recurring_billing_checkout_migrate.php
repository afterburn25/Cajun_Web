<?php
/** Explicit additive client checkout attribution; never creates a schedule or invoice. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function recurring_billing_checkout_schema_enabled():bool {
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['recurring_billing_checkout_schema_version'])['value']??'';
    if(!in_array($version,['','1'],true))throw new RuntimeException('Recurring checkout storage is incomplete or unsupported. Finish its explicit owner migration.');
    return $version==='1';
}
/** Exact column/index/FK states only; an unmarked extension or reversed partial step is not adoptable. */
function recurring_billing_checkout_schema_stage():int {
    $old=recurring_billing_schema(config()['db']['prefix']);$new=recurring_billing_schema(config()['db']['prefix'],true);
    $names=array_map(fn($name)=>config()['db']['prefix'].$name,array_keys($old));
    if(count(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN (?,?,?,?)',$names))!==4)throw new RuntimeException('Original recurring history storage is missing. No table was recreated.');
    foreach(['recurring_billing_sources','recurring_billing_events'] as $name)recurring_billing_validate_schema($name,$old[$name]);
    $variants=[];
    foreach(['recurring_billing_schedules'=>'created_by_client','recurring_billing_invoices'=>'client_id'] as $name=>$field){
        $extended=in_array($field,array_column(all('SHOW FULL COLUMNS FROM '.table($name)),'Field'),true);
        recurring_billing_validate_schema($name,($extended?$new:$old)[$name],$extended);$variants[]=$extended;
    }
    if($variants===[false,false])return 0;
    if($variants===[true,false])return 1;
    if($variants===[true,true])return 2;
    throw new RuntimeException('Recurring checkout schema steps are out of order. Existing data was preserved.');
}
/** Whole retained histories are checked before activation and explicit checkout use. */
function recurring_billing_checkout_ready():bool {
    if(!recurring_billing_ready()||!recurring_billing_checkout_schema_enabled())return false;
    if(!service_plans_checkout_ready())throw new RuntimeException('Prepare and validate checkout service-agreement storage first.');
    recurring_billing_checkout_validate_history(false);return true;
}
/** Stream every family and compare event/map/origin totals; no success fallback or document rewriting. */
function recurring_billing_checkout_validate_history(bool $legacyOnly):void {
    require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_invoices.php';
    $cursor='';$sourceCount=0;$scheduleCount=0;$eventCount=0;$invoiceCount=0;
    do{
        $heads=all('SELECT id FROM '.table('recurring_billing_sources').' WHERE id>? ORDER BY id LIMIT 64',[$cursor]);
        foreach($heads as $head){
            if($legacyOnly){
                foreach(all('SELECT journal_json FROM '.table('recurring_billing_schedules').' WHERE subscription_id=? ORDER BY generation LIMIT 257',[$head['id']]) as $row){
                    $raw=json_decode($row['journal_json'],true,12,JSON_THROW_ON_ERROR);
                    if(!is_array($raw)||($raw['format']??null)!==1)throw new RuntimeException('Unprepared checkout recurring rows cannot be adopted.');
                }
            }
            recurring_billing_family($head['id']);
            foreach(all('SELECT * FROM '.table('recurring_billing_schedules').' WHERE subscription_id=? ORDER BY generation LIMIT 257',[$head['id']]) as $row){
                $doc=recurring_billing_validate($row);$scheduleCount++;$eventCount+=$doc->revision();$mapCursor='';
                do{
                    $maps=all('SELECT * FROM '.table('recurring_billing_invoices').' WHERE schedule_id=? AND id>? ORDER BY id LIMIT 64',[$row['id'],$mapCursor]);
                    foreach($maps as $map){recurring_billing_invoice_validate($map,$doc);$invoiceCount++;$mapCursor=$map['id'];}
                }while(count($maps)===64);
                if($doc->data()['format']===2&&!one('SELECT id FROM '.table('recurring_billing_invoices').' WHERE schedule_id=? AND period_index=0',[$row['id']]))throw new RuntimeException('Checkout first-invoice history is missing. Restore the original atomic checkout records.');
            }
            $sourceCount++;$cursor=$head['id'];
        }
    }while(count($heads)===64);
    foreach(['recurring_billing_sources'=>$sourceCount,'recurring_billing_schedules'=>$scheduleCount,'recurring_billing_events'=>$eventCount,'recurring_billing_invoices'=>$invoiceCount] as $name=>$count){
        if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==$count)throw new RuntimeException('Recurring history is missing or unlinked. Existing data was preserved.');
    }
    $auditCursor=0;$origins=0;
    do{
        $rows=all('SELECT id,detail FROM '.table('audit').' WHERE id>? AND action IN (?,?) ORDER BY id LIMIT 64',[$auditCursor,'recurring_billing.issued','recurring_billing.checkout_issued']);
        foreach($rows as $row){
            if(!preg_match('/ invoice ([1-9][0-9]*)\z/',$row['detail'],$match))throw new RuntimeException('Recurring issuance origin is malformed. Restore the original database.');
            $invoice=(int)$match[1];recurring_billing_invoice_origin($invoice);
            if(!one('SELECT id FROM '.table('recurring_billing_invoices').' WHERE invoice_id=?',[$invoice]))throw new RuntimeException('Recurring issuance origin has no retained obligation. Restore the original database.');
            $origins++;$auditCursor=(int)$row['id'];
        }
    }while(count($rows)===64);
    if($origins!==$invoiceCount)throw new RuntimeException('Recurring invoice origins and obligations no longer agree.');
}
function recurring_billing_checkout_steps():array {
    return [
        'ALTER TABLE '.table('recurring_billing_schedules').' MODIFY created_by INT UNSIGNED NULL, ADD created_by_client INT UNSIGNED NULL AFTER revision, ADD INDEX created_by_client(created_by_client), ADD FOREIGN KEY (created_by_client) REFERENCES '.table('studio_clients').' (id) ON DELETE RESTRICT',
        'ALTER TABLE '.table('recurring_billing_invoices').' MODIFY staff_id INT UNSIGNED NULL, ADD client_id INT UNSIGNED NULL AFTER snapshot_sha256, ADD INDEX client_id(client_id), ADD FOREIGN KEY (client_id) REFERENCES '.table('studio_clients').' (id) ON DELETE RESTRICT',
    ];
}
function recurring_billing_checkout_migrate(#[\SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Recurring checkout setup cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        unset($GLOBALS['cwp_settings']);recurring_billing_staff(false);$actor=payments_owner($password);
        if((one('SELECT value FROM '.table('settings').' WHERE name=?',['recurring_billing_schema_version'])['value']??'')!=='1')throw new RuntimeException('Prepare original recurring billing storage first.');
        if(!service_plans_checkout_ready()||!ledger_ready())throw new RuntimeException('Prepare checkout service agreements and invoice ledger first.');
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['recurring_billing_checkout_schema_version'])['value']??'';
        if(!in_array($version,['','preparing-1','1'],true))throw new RuntimeException('Unknown recurring checkout storage version.');
        $stage=recurring_billing_checkout_schema_stage();
        if(($version===''&&$stage!==0)||($version==='1'&&$stage!==2))throw new RuntimeException('Recurring checkout schema differs from its explicit preparation marker.');
        recurring_billing_checkout_validate_history($version!=='1');
        if($version==='')staff_transaction(function()use($actor):void{
            save_setting('recurring_billing_checkout_schema_version','preparing-1');
            recurring_billing_audit('staff:'.$actor['id'],'checkout_preparing','Explicit additive client checkout attribution preparation');
        });
        if($version!=='1'){
            $steps=recurring_billing_checkout_steps();
            while($stage<2){db()->exec($steps[$stage]);$next=recurring_billing_checkout_schema_stage();if($next!==$stage+1)throw new RuntimeException('Recurring checkout schema did not reach its expected boundary.');$stage=$next;}
        }
        staff_transaction(function()use($password,$version):void{
            unset($GLOBALS['cwp_settings']);recurring_billing_staff(false);$current=payments_owner($password);
            if(recurring_billing_checkout_schema_stage()!==2)throw new RuntimeException('Recurring checkout schema changed before activation.');
            recurring_billing_checkout_validate_history($version!=='1');
            save_setting('recurring_billing_checkout_schema_version','1');
            recurring_billing_audit('staff:'.$current['id'],'checkout_setup','Explicit additive recurring checkout attribution verified');
        });
    });
}
