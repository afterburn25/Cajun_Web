<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function payments_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {
        $actor=payments_owner($password);if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $missing=[];foreach(payments_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){
                catalog_validate_schema($name,$sql,$name==='payment_gateways'?['environment'=>['environment']]:['request_key'=>['request_key']]);
                if($name==='payment_requests')foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $col)if($col['Field']==='invoice_hash'&&$col['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible payment request identifiers.');
            }else $missing[]=$sql;
        }
        foreach($missing as $sql)db()->exec($sql);
        staff_transaction(function() use($actor): void {
            if(setting('payments_schema_version')!=='1')foreach(array_keys(payments_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0)throw new RuntimeException('Unrecognized payment records exist. No data was adopted.');
            save_setting('payments_schema_version','1');payments_audit((int)$actor['id'],'setup','Additive payment schema 1; charging disabled');
        });
    });
}
