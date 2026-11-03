<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/operations.php';
require_once CWP_CODE_ROOT.'/app/providers/document_roots.php';
require_once CWP_CODE_ROOT.'/app/providers/document_root_requests_document.php';
require_once CWP_CODE_ROOT.'/database/migrations/provider_document_root_requests_migrate.php';

function provider_document_root_requests_guard(bool $transaction=false):void {
    $mutex='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(($transaction&&!db()->inTransaction())||(int)query('SELECT COALESCE(IS_USED_LOCK(?),0)=CONNECTION_ID()',[$mutex])->fetchColumn()!==1)throw new LogicException('Website folder request history requires the owning staff mutex and, for writes, transaction.');
}
/** Exact owned namespace, separate from the existing Website folder owner lifecycle. */
function provider_document_root_requests_inventory():array {
    $prefix=config()['db']['prefix'].'provider_website_folder_requests_';$tables=all('SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen($prefix),$prefix]);
    $expected=config()['db']['prefix'].'provider_website_folder_requests_events';
    foreach($tables as $row)if($row['TABLE_NAME']!==$expected)throw new RuntimeException('Unknown website folder request storage needs recovery.');
    if(count($tables)>1)throw new RuntimeException('Ambiguous website folder request storage needs recovery.');
    $prefix='provider_website_folder_requests_';$settings=all('SELECT name,value FROM '.table('settings').' WHERE BINARY LEFT(name,?)=BINARY ? ORDER BY name',[strlen($prefix),$prefix]);$values=[];
    foreach($settings as $row){if(!in_array($row['name'],['provider_website_folder_requests_schema_version','provider_website_folder_requests_head'],true)||array_key_exists($row['name'],$values))throw new RuntimeException('Unknown website folder request marker needs recovery.');$values[$row['name']]=$row['value'];}
    $prefix='provider.website_folder_requests_';
    if(one('SELECT id FROM '.table('audit').' WHERE BINARY LEFT(action,?)=BINARY ? AND BINARY action NOT IN (?,?) LIMIT 1',[strlen($prefix),$prefix,'provider.website_folder_requests_prepared','provider.website_folder_requests_event']))throw new RuntimeException('Unknown website folder request audit needs recovery.');
    return ['table'=>$tables!==[],'settings'=>$values];
}
/** Local graph only: no owner-change readiness, grant, terminal or provider lookup. */
function provider_document_root_requests_events():?array {
    provider_document_root_requests_guard();
    foreach(['settings','audit'] as $name){$e=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($e['ENGINE']??'')!=='innodb')throw new RuntimeException('Website folder requests require transactional audit and settings storage.');}
    $inventory=provider_document_root_requests_inventory();$values=$inventory['settings'];$exists=$inventory['table'];
    $auditCount=(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action IN (?,?)',['provider.website_folder_requests_prepared','provider.website_folder_requests_event'])->fetchColumn();
    if($exists)provider_document_root_requests_validate_schema();
    $count=$exists?(int)query('SELECT COUNT(*) FROM '.table('provider_website_folder_requests_events'))->fetchColumn():0;
    if($values===[]&&$auditCount===0&&$count===0)return null;
    if(!$exists||count($values)!==2||($values['provider_website_folder_requests_schema_version']??null)!=='1')throw new RuntimeException('Restore the original website folder request history.');
    $prepared=all('SELECT id,user_id,detail FROM '.table('audit').' WHERE BINARY action=? LIMIT 2',['provider.website_folder_requests_prepared']);
    if(count($prepared)!==1||(int)$prepared[0]['user_id']<1||$prepared[0]['detail']!=='Website folder request history ready')throw new RuntimeException('Website folder request preparation audit needs recovery.');
    $auditId=(int)$prepared[0]['id'];$events=[];$head=str_repeat('0',64);$ordinal=0;$epoch=0;
    // Return the complete authenticated graph. No silent history truncation or older-success fallback.
    foreach(all('SELECT * FROM '.table('provider_website_folder_requests_events').' ORDER BY ordinal') as $row){
        if(strlen($row['document'])>32768)throw new RuntimeException('Website folder request evidence exceeds its local size bound.');
        try{$d=json_decode($row['document'],true,16,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new RuntimeException('Website folder request evidence needs recovery.',0,$e);}
        if(!is_array($d)||provider_document_root_requests_json($d)!==$row['document']||!hash_equals($row['fingerprint'],provider_operations_scope('website-folder-requests.local-event',$d)))throw new RuntimeException('Website folder request evidence needs recovery.');
        provider_document_root_requests_event_document($d);
        if($d['id']!==$row['id']||$d['subject_id']!==$row['subject_id']||$d['kind']!==$row['kind']||$d['client_id']!==(int)$row['client_id']||$d['ordinal']!==++$ordinal||$ordinal!==(int)$row['ordinal']||$d['previous']!==$head||$d['recorded_epoch']<$epoch)throw new RuntimeException('Website folder request history sequence needs recovery.');
        $audit=all('SELECT id,user_id FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=? LIMIT 2',['provider.website_folder_requests_event','Website folder request event '.$d['id'].' '.$row['fingerprint']]);
        if(count($audit)!==1||(int)$audit[0]['id']<=$auditId||(int)$audit[0]['user_id']!==($d['actor']['kind']==='owner'?$d['actor']['id']:0))throw new RuntimeException('Website folder request event audit is missing, ambiguous or out of order.');
        $auditId=(int)$audit[0]['id'];$head=$row['fingerprint'];$epoch=$d['recorded_epoch'];$events[]=$d;
    }
    if($ordinal!==$count||$auditCount!==$ordinal+1||$values['provider_website_folder_requests_head']!==$ordinal.':'.$head)throw new RuntimeException('Website folder request history is incomplete. Restore the original database.');
    provider_document_root_requests_project($events);return $events;
}
function provider_document_root_requests_ready():bool {return provider_document_root_requests_events()!==null;}
function provider_document_root_requests_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Website folder request preparation cannot run inside a transaction.');
    staff_locked(function()use($password):void{
        unset($GLOBALS['cwp_settings']);provider_document_root_requests_actor(null,$password);$owner=provider_settings_manager();
        if(!staff_ready()||!studio_ready()||!provider_inventory_ready()||!provider_operations_ready()||!provider_document_root_controls_ready())throw new RuntimeException('Prepare client accounts, hosting observations and Website folder controls first.');
        $ready=provider_document_root_requests_ready();$inventory=provider_document_root_requests_inventory();
        if(!$inventory['table'])db()->exec(provider_document_root_requests_schema(config()['db']['prefix']));provider_document_root_requests_validate_schema();
        if(!$ready)staff_transaction(function()use($owner):void{save_setting('provider_website_folder_requests_schema_version','1');save_setting('provider_website_folder_requests_head','0:'.str_repeat('0',64));provider_settings_audit((int)$owner['id'],'website_folder_requests_prepared','Website folder request history ready');});
        if(!provider_document_root_requests_ready())throw new RuntimeException('Website folder request preparation did not complete.');
    });
}
/** Internal atomic append. The service owns current authority and shared-owner origin correspondence. */
function provider_document_root_requests_append(string $id,string $subject,int $clientId,string $kind,array $actor,array $payload):array {
    provider_document_root_requests_guard(true);$events=provider_document_root_requests_events();if($events===null)throw new RuntimeException('Enable website folder request history first.');
    $pointer=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_website_folder_requests_head'])['value'];[$ordinal,$head]=explode(':',$pointer,2);
    $d=['version'=>1,'id'=>$id,'subject_id'=>$subject,'client_id'=>$clientId,'kind'=>$kind,'actor'=>$actor,'recorded_epoch'=>max(time(),$events!==[]?$events[array_key_last($events)]['recorded_epoch']:0),'ordinal'=>(int)$ordinal+1,'previous'=>$head,'payload'=>$payload];provider_document_root_requests_project([...$events,$d]);
    $json=provider_document_root_requests_json($d);$hash=provider_operations_scope('website-folder-requests.local-event',$d);
    query('INSERT INTO '.table('provider_website_folder_requests_events').' (id,ordinal,subject_id,client_id,kind,document,fingerprint) VALUES (?,?,?,?,?,?,?)',[$id,$d['ordinal'],$subject,$clientId,$kind,$json,$hash]);
    provider_settings_audit($actor['kind']==='owner'?$actor['id']:0,'website_folder_requests_event','Website folder request event '.$id.' '.$hash);save_setting('provider_website_folder_requests_head',$d['ordinal'].':'.$hash);return $d;
}
