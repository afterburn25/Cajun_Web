<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/app/providers/operations.php';
require_once CWP_CODE_ROOT.'/app/providers/cloud_site_requests_document.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_cloud_site_requests_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function provider_cloud_site_requests_json(array $d):string {$nodes=0;return provider_operations_canonical($d,0,$nodes);}
function provider_cloud_site_requests_table():bool {return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'provider_cloud_site_request_events'])!==null;}
function provider_cloud_site_requests_witness():bool {return one('SELECT name FROM '.table('settings').' WHERE name=?',['provider_cloud_site_request_head'])!==null||one('SELECT id FROM '.table('audit').' WHERE BINARY action IN (?,?) LIMIT 1',['provider.cloud_site_request_prepared','provider.cloud_site_request_event'])!==null;}
/** Unknown local extension state cannot be mistaken for an unused store. Literal prefixes, not LIKE patterns. */
function provider_cloud_site_requests_namespace():void {
    $prefix=config()['db']['prefix'].'provider_cloud_site_request';
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen($prefix),$prefix]) as $row)if($row['TABLE_NAME']!==config()['db']['prefix'].'provider_cloud_site_request_events')throw new RuntimeException('Unknown cloud website request storage needs recovery.');
    $prefix='provider_cloud_site_request_';
    foreach(all('SELECT name FROM '.table('settings').' WHERE BINARY LEFT(name,?)=BINARY ?',[strlen($prefix),$prefix]) as $row)if(!in_array($row['name'],['provider_cloud_site_request_schema_version','provider_cloud_site_request_head'],true))throw new RuntimeException('Unknown cloud website request marker needs recovery.');
    $prefix='provider.cloud_site_request_';
    foreach(all('SELECT DISTINCT action FROM '.table('audit').' WHERE BINARY LEFT(action,?)=BINARY ?',[strlen($prefix),$prefix]) as $row)if(!in_array($row['action'],['provider.cloud_site_request_prepared','provider.cloud_site_request_event'],true))throw new RuntimeException('Unknown cloud website request audit needs recovery.');
}
function provider_cloud_site_requests_validate_schema():void {
    $name='provider_cloud_site_request_events';catalog_validate_schema($name,provider_cloud_site_requests_schema(config()['db']['prefix']),['event_ordinal'=>['ordinal']]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){$ascii=in_array($c['Field'],['id','subject_id','kind','fingerprint'],true);if($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==($ascii?'ascii_bin':($c['Field']==='document'?'utf8mb4_unicode_ci':null)))throw new RuntimeException('Cloud website request history columns are incompatible.');}
    $expected=['PRIMARY'=>[0,['id']],'event_ordinal'=>[0,['ordinal']],'cloud_site_request_subject'=>[1,['subject_id','ordinal']],'cloud_site_request_client'=>[1,['client_id','ordinal']]];$actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Cloud website request history indexes are incompatible.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}
    foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Cloud website request history indexes are incompatible.');
    $r=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($r)!==1||$r[0]['COLUMN_NAME']!=='client_id'||$r[0]['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].'studio_clients'||$r[0]['REFERENCED_COLUMN_NAME']!=='id'||!in_array($r[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Cloud website request history relationships are incompatible.');
}
/** NULL means never prepared; established missing rows, audits, marker or pointer never become empty history. */
function provider_cloud_site_requests_events():?array {
    foreach(['settings','audit'] as $name){$e=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($e['ENGINE']??'')!=='innodb')throw new RuntimeException('Cloud website request history requires transactional audit storage.');}
    provider_cloud_site_requests_namespace();
    $marker=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_site_request_schema_version']);$exists=provider_cloud_site_requests_table();
    // A process may stop after the single CREATE and before the marker transaction.
    // Only an exact empty table with no settings/audit witnesses may resume preparation.
    if($exists)provider_cloud_site_requests_validate_schema();
    if($marker===null&&!provider_cloud_site_requests_witness()&&(!$exists||(int)query('SELECT COUNT(*) FROM '.table('provider_cloud_site_request_events'))->fetchColumn()===0))return null;
    if(($marker['value']??null)!=='1'||!$exists)throw new RuntimeException('Restore the original cloud website request history.');
    $events=[];$head=str_repeat('0',64);$ordinal=0;$epoch=0;
    foreach(all('SELECT * FROM '.table('provider_cloud_site_request_events').' ORDER BY ordinal') as $row){
        if(strlen($row['document'])>16384)throw new RuntimeException('Cloud website request evidence exceeds its bound.');
        try{$d=json_decode($row['document'],true,16,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('Cloud website request evidence needs recovery.');}
        if(!is_array($d)||provider_cloud_site_requests_json($d)!==$row['document']||!hash_equals($row['fingerprint'],provider_operations_scope('cloud-site-request.event',$d)))throw new RuntimeException('Cloud website request evidence needs recovery.');
        provider_cloud_site_requests_event_document($d);
        if($d['id']!==$row['id']||$d['subject_id']!==$row['subject_id']||$d['kind']!==$row['kind']||$d['client_id']!==(int)$row['client_id']||$d['ordinal']!==++$ordinal||$ordinal!==(int)$row['ordinal']||$d['previous']!==$head||$d['recorded_epoch']<$epoch)throw new RuntimeException('Cloud website request history sequence is inconsistent.');
        $audit=all('SELECT user_id FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=? LIMIT 2',['provider.cloud_site_request_event','Cloud website request event '.$d['id'].' '.$row['fingerprint']]);
        if(count($audit)!==1||(int)$audit[0]['user_id']!==($d['actor']['kind']==='owner'?$d['actor']['id']:0))throw new RuntimeException('Cloud website request history audit is missing or ambiguous.');
        $head=$row['fingerprint'];$epoch=$d['recorded_epoch'];$events[]=$d;
    }
    $pointer=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_site_request_head'])['value']??'';
    if($pointer!==$ordinal.':'.$head||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['provider.cloud_site_request_event'])->fetchColumn()!==$ordinal||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['provider.cloud_site_request_prepared'])->fetchColumn()!==1)throw new RuntimeException('Cloud website request history is incomplete. Restore the original database.');
    $prepared=one('SELECT user_id,detail FROM '.table('audit').' WHERE BINARY action=?',['provider.cloud_site_request_prepared']);
    if(!$prepared||(int)$prepared['user_id']<1||$prepared['detail']!=='Cloud website request history ready')throw new RuntimeException('Cloud website request preparation witness needs recovery.');
    provider_cloud_site_requests_project($events);return $events;
}
function provider_cloud_site_requests_ready():bool {return provider_cloud_site_requests_events()!==null;}
function provider_cloud_site_requests_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Cloud website request preparation cannot run inside a transaction.');
    staff_locked(function()use($password):void{
        unset($GLOBALS['cwp_settings']);
        $owner=provider_settings_owner($password);if(!provider_inventory_ready()||!provider_operations_ready()||!staff_ready()||!studio_ready())throw new RuntimeException('Prepare hosting inventory and client accounts first.');
        $ready=provider_cloud_site_requests_ready();$exists=provider_cloud_site_requests_table();if($exists)provider_cloud_site_requests_validate_schema();else db()->exec(provider_cloud_site_requests_schema(config()['db']['prefix']));provider_cloud_site_requests_validate_schema();
        if(!$ready)staff_transaction(function()use($owner):void{save_setting('provider_cloud_site_request_schema_version','1');save_setting('provider_cloud_site_request_head','0:'.str_repeat('0',64));provider_settings_audit((int)$owner['id'],'cloud_site_request_prepared','Cloud website request history ready');});
        if(!provider_cloud_site_requests_ready())throw new RuntimeException('Cloud website request preparation is incomplete.');
    });
}
/** Internal append only under the caller's staff mutex and transaction. No provider ledger is written. */
function provider_cloud_site_requests_append(string $id,string $subject,int $clientId,string $kind,array $actor,array $payload):array {
    $mutex='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(!db()->inTransaction()||(int)query('SELECT COALESCE(IS_USED_LOCK(?),0)=CONNECTION_ID()',[$mutex])->fetchColumn()!==1)throw new LogicException('Cloud website request events require the caller transaction and staff mutex.');
    $events=provider_cloud_site_requests_events();if($events===null)throw new RuntimeException('Enable cloud website request history first.');
    $pointer=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_site_request_head'])['value'];[$ordinal,$head]=explode(':',$pointer,2);
    $d=['version'=>1,'id'=>$id,'subject_id'=>$subject,'client_id'=>$clientId,'kind'=>$kind,'actor'=>$actor,'recorded_epoch'=>max(time(),$events!==[]?$events[array_key_last($events)]['recorded_epoch']:0),'ordinal'=>(int)$ordinal+1,'previous'=>$head,'payload'=>$payload];provider_cloud_site_requests_event_document($d);provider_cloud_site_requests_project([...$events,$d]);
    $json=provider_cloud_site_requests_json($d);if(strlen($json)>16384)throw new InvalidArgumentException('The website request is too large.');$hash=provider_operations_scope('cloud-site-request.event',$d);
    query('INSERT INTO '.table('provider_cloud_site_request_events').' (id,ordinal,subject_id,client_id,kind,document,fingerprint) VALUES (?,?,?,?,?,?,?)',[$id,$d['ordinal'],$subject,$clientId,$kind,$json,$hash]);
    provider_settings_audit($actor['kind']==='owner'?$actor['id']:0,'cloud_site_request_event','Cloud website request event '.$id.' '.$hash);save_setting('provider_cloud_site_request_head',$d['ordinal'].':'.$hash);return $d;
}
