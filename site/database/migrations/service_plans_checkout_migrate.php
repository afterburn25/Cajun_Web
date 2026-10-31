<?php
/** Explicit additive checkout attribution. No checkout writer, invoice or provider effect. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function service_plans_checkout_schema_enabled():bool {
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['service_plans_checkout_schema_version'])['value']??'';
    if(!in_array($version,['','1'],true))throw new RuntimeException('Checkout storage is incomplete or unsupported. An owner must finish its explicit migration.');
    return $version==='1';
}
/** Validate every column/index/FK before classifying a recognized, resumable DDL boundary. */
function service_plans_checkout_schema_stage():int {
    $old=service_plans_schema(config()['db']['prefix']);$new=service_plans_schema(config()['db']['prefix'],true);
    $names=array_map(fn($name)=>config()['db']['prefix'].$name,array_keys($old));
    if(count(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN (?,?,?,?)',$names))!==4)throw new RuntimeException('Original service-plan history storage is missing. No table was recreated.');
    foreach(['service_plans','service_plan_events'] as $name)service_plans_validate_schema($name,$old[$name]);
    $variants=[];
    foreach(['service_subscriptions'=>'created_by_client','service_subscription_events'=>'actor_system'] as $name=>$field){
        $extended=in_array($field,array_column(all('SHOW FULL COLUMNS FROM '.table($name)),'Field'),true);
        service_plans_validate_schema($name,($extended?$new:$old)[$name],$extended);
        $variants[]=$extended;
    }
    if($variants===[false,false])return 0;
    if($variants===[true,false])return 1;
    if($variants===[true,true])return 2;
    throw new RuntimeException('Checkout schema steps are out of order. Existing data was preserved.');
}
function service_plans_checkout_ready():bool {
    return service_plans_ready()&&service_plans_checkout_schema_enabled();
}
/** No cap or success fallback: examine all original documents and event rows before migration adoption. */
function service_plans_checkout_validate_history(bool $legacyOnly):void {
    foreach([false,true] as $subscription){
        $name=$subscription?'service_subscriptions':'service_plans';$cursor='';$count=0;$eventCount=0;
        do{
            $rows=all('SELECT * FROM '.table($name).' WHERE id>? ORDER BY id LIMIT 64',[$cursor]);
            foreach($rows as $row){
                if($legacyOnly){
                    $raw=json_decode($row['journal_json'],true,40,JSON_THROW_ON_ERROR);
                    if(!is_array($raw)||($raw['format']??null)!==1)throw new RuntimeException('Unprepared checkout rows cannot be adopted by migration.');
                }
                $document=service_plans_validate($row,$subscription);
                $eventCount+=$document->revision();$count++;$cursor=$row['id'];
            }
        }while(count($rows)===64);
        if($count!==(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()
            ||$eventCount!==(int)query('SELECT COUNT(*) FROM '.table($subscription?'service_subscription_events':'service_plan_events'))->fetchColumn()){
            throw new RuntimeException('Original service-plan history is missing or unlinked. Existing data was preserved.');
        }
    }
}
/** The only recognized ALTER sequence; no table recreation, clearing, backfill or document rewriting. */
function service_plans_checkout_steps():array {
    return [
        'ALTER TABLE '.table('service_subscriptions').' MODIFY created_by INT UNSIGNED NULL, ADD created_by_client INT UNSIGNED NULL AFTER revision, ADD INDEX created_by_client(created_by_client), ADD FOREIGN KEY (created_by_client) REFERENCES '.table('studio_clients').' (id) ON DELETE RESTRICT',
        'ALTER TABLE '.table('service_subscription_events').' ADD actor_system VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER after_sha256',
    ];
}
function service_plans_checkout_migrate(#[\SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Checkout setup cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        unset($GLOBALS['cwp_settings']);
        $actor=service_plans_password($password,false);
        if((one('SELECT value FROM '.table('settings').' WHERE name=?',['service_plans_schema_version'])['value']??'')!=='1')throw new RuntimeException('Prepare the original service-plan storage first.');
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['service_plans_checkout_schema_version'])['value']??'';
        if(!in_array($version,['','preparing-1','1'],true))throw new RuntimeException('Unknown checkout storage version. Existing data was preserved.');
        $stage=service_plans_checkout_schema_stage();
        if(($version===''&&$stage!==0)||($version==='1'&&$stage!==2))throw new RuntimeException('Checkout schema does not match its explicit preparation marker.');
        service_plans_checkout_validate_history($version!=='1');
        if($version===''){
            staff_transaction(function()use($actor):void{
                save_setting('service_plans_checkout_schema_version','preparing-1');
                service_plans_audit((int)$actor['id'],'checkout_preparing','Explicit additive checkout attribution preparation');
            });
        }
        if($version!=='1'){
            $steps=service_plans_checkout_steps();
            while($stage<2){
                db()->exec($steps[$stage]);
                $next=service_plans_checkout_schema_stage();
                if($next!==$stage+1)throw new RuntimeException('Checkout schema did not reach the expected migration boundary.');
                $stage=$next;
            }
        }
        staff_transaction(function()use($password,$version):void{
            unset($GLOBALS['cwp_settings']);
            $current=service_plans_password($password,false);
            if(service_plans_checkout_schema_stage()!==2)throw new RuntimeException('Checkout schema changed before activation.');
            // Re-read retained records after DDL, before committing the separate prepared marker.
            service_plans_checkout_validate_history($version!=='1');
            save_setting('service_plans_checkout_schema_version','1');
            service_plans_audit((int)$current['id'],'checkout_setup','Explicit additive checkout attribution verified');
        });
    });
}
