<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function payments_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {
        $actor=payments_owner($password);if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $version=setting('payments_schema_version');
        if(!in_array($version,['','1','2'],true))throw new RuntimeException('Unrecognized payment schema version '.$version.'. Existing data was preserved.');
        $missing=[];foreach(payments_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){
                if($name==='payment_requests'){
                    $cols=array_column(all('SHOW FULL COLUMNS FROM '.table($name)),'Field');
                    if(!in_array('link_hash',$cols,true))db()->exec('ALTER TABLE '.table($name).' ADD COLUMN link_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER invoice_hash, ADD COLUMN link_secret VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER link_hash, ADD UNIQUE KEY link_token(link_hash)');
                }
                catalog_validate_schema($name,$sql,$name==='payment_gateways'?['environment'=>['environment']]:['request_key'=>['request_key'],'link_token'=>['link_hash']]);
                if($name==='payment_requests')foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $col)if($col['Field']==='invoice_hash'&&$col['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible payment request identifiers.');
            }else $missing[]=$sql;
        }
        foreach($missing as $sql)db()->exec($sql);
        staff_transaction(function() use($actor,$version): void {
            if($version==='')foreach(array_keys(payments_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0)throw new RuntimeException('Unrecognized payment records exist. No data was adopted.');
            if($version!=='2'){save_setting('payments_schema_version','2');payments_audit((int)$actor['id'],'setup','Additive payment schema 2; shareable request links; charging disabled');}
        });
    });
}
