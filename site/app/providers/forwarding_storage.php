<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/mailbox_policies.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_forwarding_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function provider_forwarding_json(array $d):string {$nodes=0;return provider_operations_canonical($d,0,$nodes);}
function provider_forwarding_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/',$id))throw new InvalidArgumentException('Reload the forwarding request form.');}
function provider_forwarding_table():bool {return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'provider_forwarding_events'])!==null;}
function provider_forwarding_witness():bool {return one('SELECT name FROM '.table('settings').' WHERE name=?',['provider_forwarding_head'])!==null||one('SELECT id FROM '.table('audit').' WHERE BINARY action IN (?,?) LIMIT 1',['provider.forwarding_prepared','provider.forwarding_event'])!==null;}
function provider_forwarding_validate_schema():void {
    $name='provider_forwarding_events';catalog_validate_schema($name,provider_forwarding_schema(config()['db']['prefix']),['event_ordinal'=>['ordinal']]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){$ascii=in_array($c['Field'],['id','subject_id','kind','fingerprint'],true);if($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==($ascii?'ascii_bin':($c['Field']==='document'?'utf8mb4_unicode_ci':null)))throw new RuntimeException('Forwarding history columns are incompatible.');}
    $expected=['PRIMARY'=>[0,['id']],'event_ordinal'=>[0,['ordinal']],'forwarding_subject'=>[1,['subject_id','ordinal']],'forwarding_client'=>[1,['client_id','ordinal']]];$actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Forwarding history indexes are incompatible.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}
    foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Forwarding history indexes are incompatible.');
    $r=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($r)!==1||$r[0]['COLUMN_NAME']!=='client_id'||$r[0]['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].'studio_clients'||$r[0]['REFERENCED_COLUMN_NAME']!=='id'||!in_array($r[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Forwarding history relationships are incompatible.');
}
/** NULL means never prepared; established missing rows, audits, marker or pointer never become empty history. */
function provider_forwarding_events():?array {
    foreach(['settings','audit'] as $name){$e=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($e['ENGINE']??'')!=='innodb')throw new RuntimeException('Forwarding history requires transactional audit storage.');}
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_forwarding_schema_version'])['value']??'';$exists=provider_forwarding_table();
    if($version===''&&!provider_forwarding_witness()&&(!$exists||(int)query('SELECT COUNT(*) FROM '.table('provider_forwarding_events'))->fetchColumn()===0))return null;
    if($version!=='1'||!$exists)throw new RuntimeException('Restore the original forwarding request history.');
    $engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'provider_forwarding_events']);if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Forwarding history must be transactional.');
    $events=[];$head=str_repeat('0',64);$ordinal=0;$epoch=0;
    foreach(all('SELECT * FROM '.table('provider_forwarding_events').' ORDER BY ordinal') as $row){
        try{$d=json_decode($row['document'],true,16,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('Forwarding request evidence needs recovery.');}
        if(!is_array($d)||strlen($row['document'])>16384||provider_forwarding_json($d)!==$row['document']||!hash_equals($row['fingerprint'],provider_operations_scope('forwarding.local-event',$d)))throw new RuntimeException('Forwarding request evidence needs recovery.');
        provider_forwarding_event_document($d);
        if($d['id']!==$row['id']||$d['subject_id']!==$row['subject_id']||$d['kind']!==$row['kind']||$d['client_id']!==(int)$row['client_id']||$d['ordinal']!==++$ordinal||$ordinal!==(int)$row['ordinal']||$d['previous']!==$head||$d['recorded_epoch']<$epoch)throw new RuntimeException('Forwarding history sequence is inconsistent.');
        $audit=all('SELECT user_id FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=? LIMIT 2',['provider.forwarding_event','Forwarding event '.$d['id'].' '.$row['fingerprint']]);
        if(count($audit)!==1||(int)$audit[0]['user_id']!==($d['actor']['kind']==='owner'?$d['actor']['id']:0))throw new RuntimeException('Forwarding history audit is missing or ambiguous.');
        $head=$row['fingerprint'];$epoch=$d['recorded_epoch'];$events[]=$d;
    }
    $pointer=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_forwarding_head'])['value']??'';
    if($pointer!==$ordinal.':'.$head||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['provider.forwarding_event'])->fetchColumn()!==$ordinal||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['provider.forwarding_prepared'])->fetchColumn()!==1)throw new RuntimeException('Forwarding history is incomplete. Restore the original database.');
    provider_forwarding_project($events);return $events;
}
function provider_forwarding_ready():bool {return provider_forwarding_events()!==null;}
function provider_forwarding_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Forwarding preparation cannot run inside a transaction.');
    staff_locked(function()use($password):void{
        $owner=provider_settings_owner($password);if(!provider_inventory_ready()||!provider_operations_ready()||!staff_ready()||!studio_ready())throw new RuntimeException('Prepare hosting inventory and client accounts first.');
        $ready=provider_forwarding_ready();$exists=provider_forwarding_table();if($exists)provider_forwarding_validate_schema();else db()->exec(provider_forwarding_schema(config()['db']['prefix']));provider_forwarding_validate_schema();
        if(!$ready)staff_transaction(function()use($owner):void{save_setting('provider_forwarding_schema_version','1');save_setting('provider_forwarding_head','0:'.str_repeat('0',64));provider_settings_audit((int)$owner['id'],'forwarding_prepared','Local forwarding request history ready');});
    });
}
/** Internal append only under the caller's staff mutex and transaction. No provider ledger is written. */
function provider_forwarding_append(string $id,string $subject,int $clientId,string $kind,array $actor,array $payload):array {
    $mutex='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(!db()->inTransaction()||(int)query('SELECT COALESCE(IS_USED_LOCK(?),0)=CONNECTION_ID()',[$mutex])->fetchColumn()!==1)throw new LogicException('Forwarding events require the caller transaction and staff mutex.');
    $events=provider_forwarding_events();if($events===null)throw new RuntimeException('Enable forwarding request history first.');
    $pointer=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_forwarding_head'])['value'];[$ordinal,$head]=explode(':',$pointer,2);
    $d=['version'=>1,'id'=>$id,'subject_id'=>$subject,'client_id'=>$clientId,'kind'=>$kind,'actor'=>$actor,'recorded_epoch'=>max(time(),$events!==[]?$events[array_key_last($events)]['recorded_epoch']:0),'ordinal'=>(int)$ordinal+1,'previous'=>$head,'payload'=>$payload];provider_forwarding_event_document($d);provider_forwarding_project([...$events,$d]);
    $json=provider_forwarding_json($d);$hash=provider_operations_scope('forwarding.local-event',$d);
    query('INSERT INTO '.table('provider_forwarding_events').' (id,ordinal,subject_id,client_id,kind,document,fingerprint) VALUES (?,?,?,?,?,?,?)',[$id,$d['ordinal'],$subject,$clientId,$kind,$json,$hash]);
    provider_settings_audit($actor['kind']==='owner'?$actor['id']:0,'forwarding_event','Forwarding event '.$id.' '.$hash);save_setting('provider_forwarding_head',$d['ordinal'].':'.$hash);return $d;
}
