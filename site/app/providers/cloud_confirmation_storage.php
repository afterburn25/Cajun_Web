<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/provider_cloud_confirmation_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function provider_cloud_confirmation_table():bool {
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'provider_cloud_confirmations'])!==null;
}
function provider_cloud_confirmation_witness():bool {
    return one('SELECT id FROM '.table('audit').' WHERE action IN (?,?) LIMIT 1',['provider.cloud_confirmation_prepared','provider.cloud_confirmed'])!==null
        ||one('SELECT name FROM '.table('settings').' WHERE name=?',['provider_cloud_confirmation_head'])!==null;
}
function provider_cloud_confirmation_json(array $document):string {
    $nodes=0;return provider_operations_canonical($document,0,$nodes);
}
function provider_cloud_confirmation_document(array $row):array {
    try{$d=json_decode($row['document'],true,16,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('Original cloud confirmation evidence needs recovery.');}
    if(!is_array($d)||($d['version']??null)!==1||strlen($row['document'])>16384||provider_cloud_confirmation_json($d)!==$row['document']||!hash_equals($row['fingerprint'],provider_operations_scope('cloudconfirmation.evidence',$d)))throw new RuntimeException('Original cloud confirmation evidence needs recovery.');
    foreach(['id','profile_id','refresh_id','link_id','confirmed_at'] as $key)if(($d[$key]??null)!==$row[$key])throw new RuntimeException('Cloud confirmation identity is inconsistent.');
    foreach(['package_id','client_id','actor_id'] as $key)if(($d[$key]??null)!==(int)$row[$key])throw new RuntimeException('Cloud confirmation identity is inconsistent.');
    return $d;
}
/** Independent pointer + per-row audit + chained evidence refuse paired history loss. */
function provider_cloud_confirmation_ready():bool {
    $v=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_confirmation_schema_version'])['value']??'';
    $exists=provider_cloud_confirmation_table();
    if($v===''&&!provider_cloud_confirmation_witness()&&(!$exists||(int)query('SELECT COUNT(*) FROM '.table('provider_cloud_confirmations'))->fetchColumn()===0))return false;
    if($v!=='1'||!$exists)throw new RuntimeException('Cloud confirmation storage needs recovery from the original database.');
    $rows=all('SELECT * FROM '.table('provider_cloud_confirmations').' ORDER BY seq');$count=0;$head=str_repeat('0',64);
    foreach($rows as $row){
        $d=provider_cloud_confirmation_document($row);++$count;
        if(($d['ordinal']??null)!==$count||($d['previous']??null)!==$head||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=? AND user_id=? AND detail=?',['provider.cloud_confirmed',$row['actor_id'],'Cloud confirmation '.$row['id'].' '.$row['fingerprint']])->fetchColumn()!==1)throw new RuntimeException('Cloud confirmation history is incomplete. Restore the original database.');
        $head=$row['fingerprint'];
        provider_cloud_confirmation_integrity($row,$d);
    }
    $pointer=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_confirmation_head'])['value']??'';
    if($pointer!==$count.':'.$head||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=?',['provider.cloud_confirmed'])->fetchColumn()!==$count||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=?',['provider.cloud_confirmation_prepared'])->fetchColumn()!==1)throw new RuntimeException('Cloud confirmation history is incomplete. Restore the original database.');
    return true;
}

function provider_cloud_confirmation_validate(string $sql):void {
    $name='provider_cloud_confirmations';$unique=['seq'=>['seq'],'confirmed_package'=>['profile_id','package_id']];catalog_validate_schema($name,$sql,$unique);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){
        $ascii=in_array($c['Field'],['id','profile_id','refresh_id','link_id','document','fingerprint'],true);
        if($c['Extra']!==($c['Field']==='seq'?'auto_increment':'')||$c['Default']!==null||$c['Collation']!==($ascii?'ascii_bin':null))throw new RuntimeException('Incompatible cloud confirmation columns. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']],'seq'=>[0,['seq']],'confirmed_package'=>[0,['profile_id','package_id']],'confirmation_client'=>[1,['client_id']],'confirmation_refresh'=>[1,['refresh_id']],'confirmation_actor'=>[1,['actor_id']]];$actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Incompatible cloud confirmation indexes.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}
    foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible cloud confirmation indexes.');
    $expected=['id'=>'provider_operation_grants','profile_id'=>'provider_profiles','client_id'=>'studio_clients','refresh_id'=>'provider_refreshes','actor_id'=>'users'];
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Incompatible cloud confirmation relationships.');$seen=[];
    foreach($relations as $r){$target=$expected[$r['COLUMN_NAME']]??null;if(!$target||isset($seen[$r['COLUMN_NAME']])||$r['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target||$r['REFERENCED_COLUMN_NAME']!=='id'||!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible cloud confirmation relationships.');$seen[$r['COLUMN_NAME']]=true;}
}

function provider_cloud_confirmation_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Cloud confirmation preparation requires its own transaction boundary.');
    staff_locked(function()use($password):void{
        $actor=provider_settings_owner($password);if(!provider_inventory_ready()||!provider_operations_ready())throw new RuntimeException('Prepare the original inventory and operation storage first.');
        provider_cloud_sites_history_ready();$v=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_confirmation_schema_version'])['value']??'';
        if(!in_array($v,['','1'],true))throw new RuntimeException('Unsupported cloud confirmation storage.');
        $exists=provider_cloud_confirmation_table();
        if($v==='1'){provider_cloud_confirmation_ready();}
        elseif(provider_cloud_confirmation_witness()||($exists&&(int)query('SELECT COUNT(*) FROM '.table('provider_cloud_confirmations'))->fetchColumn()))throw new RuntimeException('Unmarked cloud confirmation history needs recovery.');
        $sql=provider_cloud_confirmation_schema(config()['db']['prefix'])['provider_cloud_confirmations'];
        if($exists)provider_cloud_confirmation_validate($sql);else db()->exec($sql);
        provider_cloud_confirmation_validate($sql);
        if($v==='')staff_transaction(function()use($actor):void{
            save_setting('provider_cloud_confirmation_schema_version','1');save_setting('provider_cloud_confirmation_head','0:'.str_repeat('0',64));
            provider_settings_audit((int)$actor['id'],'cloud_confirmation_prepared','Cloud package observation and assignment evidence ready');
        });
    });
}
