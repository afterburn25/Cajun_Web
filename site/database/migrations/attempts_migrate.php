<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function attempts_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {
        $actor=payments_owner($password);if(!payments_ready())throw new RuntimeException('Enable payment preparation first.');
        $missing=[];
        foreach(attempts_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){
                catalog_validate_schema($name,$sql,$name==='payment_attempts'?['request_id'=>['request_id'],'attempt_key'=>['attempt_key'],'reference'=>['reference'],'gateway_transaction'=>['environment','account_hash','transaction_id']]:[]);
                foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c)if(in_array($c['Field'],['attempt_key','reference','account_hash'],true)&&$c['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible sandbox identifiers.');
            }else $missing[]=$sql;
        }
        foreach($missing as $sql)db()->exec($sql);
        staff_transaction(function() use($actor): void {
            if(setting('payment_attempts_schema_version')!=='1')foreach(array_keys(attempts_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized sandbox records exist. No data was adopted.');
            save_setting('payment_attempts_schema_version','1');payments_audit((int)$actor['id'],'sandbox.setup','Additive trial ledger 1; real invoice settlement disabled');
        });
    });
}
/** Caller holds the staff mutex and transaction, shared by all financial changes. */
