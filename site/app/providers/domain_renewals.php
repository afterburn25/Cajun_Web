<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/registrar.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_renewals_document.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_renewals_storage.php';
function domain_renewals_actor(int $clientId):array {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Renewal requests require the client to sign in directly.');
    $client=studio_client();if(!$client||(int)$client['id']!==$clientId||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]))throw new RuntimeException('Sign in to review your renewal requests.');return $client;
}
function domain_renewals_require():void {if(!domain_renewals_ready())throw new RuntimeException('The studio has not enabled renewal requests yet.');}
function domain_renewals_resource(string $linkId):array {
    domain_renewals_id($linkId);$raw=one('SELECT * FROM '.table('provider_registrar_links').' WHERE id=?',[$linkId]);if(!$raw)throw new RuntimeException('This registered-domain assignment is unavailable.');
    $link=provider_registrar_current($linkId,(int)$raw['revision']);$profile=provider_settings_load($link['profile_id']);$latest=provider_registrar_latest($link['profile_id']);
    if(!$latest||!provider_registrar_fresh($profile,$latest)||!provider_inventory_link_context_matches($link,$profile,$latest)||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$link['client_id']]))throw new RuntimeException('The studio must refresh and confirm this registered-domain assignment first.');
    [$observed]=provider_registrar_observed($link['profile_id'],(int)$profile['revision'],$latest['id'],(int)$link['domain_id']);if($observed['domain']!==$link['domain'])throw new RuntimeException('This registered-domain identity changed.');return [$link,$latest];
}
function domain_renewals_pending(string $linkId):bool {
    $pending=false;foreach(all('SELECT id FROM '.table('domain_renewal_requests').' WHERE link_id=?',[$linkId]) as $row)if(domain_renewals_load($row['id'])['state']==='pending_review')$pending=true;return $pending;
}
function domain_renewals_options(int $clientId):array {
    domain_renewals_actor($clientId);if(!domain_renewals_ready()||!provider_registrar_ready())return [];$out=[];
    foreach(all('SELECT id FROM '.table('provider_registrar_links').' WHERE client_id=? ORDER BY domain',[$clientId]) as $row){
        try{[$link,$latest]=domain_renewals_resource($row['id']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;continue;}
        if(domain_renewals_pending($link['id']))continue;
        $out[]=['link_id'=>$link['id'],'link_revision'=>(int)$link['revision'],'profile_revision'=>(int)$link['profile_revision'],'refresh_id'=>$latest['id'],'domain'=>$link['domain']];
    }return $out;
}
function domain_renewals_verify_audit(int $actor,string $command,string $detail):void {
    if((int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE user_id=? AND BINARY action=? AND BINARY detail=?',[$actor,'domain_renewals.'.$command,$detail])->fetchColumn()!==1)throw new RuntimeException('Renewal request audit history needs recovery.');
}
function domain_renewals_load(string $id):array {
    domain_renewals_require();domain_renewals_id($id);$row=one('SELECT * FROM '.table('domain_renewal_requests').' WHERE id=?',[$id]);if(!$row)throw new RuntimeException('Renewal request unavailable.');
    try{$d=domain_renewals_document(json_decode($row['document_json'],true,8,JSON_THROW_ON_ERROR));}catch(JsonException|TypeError $e){throw new RuntimeException('Renewal request history needs recovery.',0,$e);}
    $hash=hash('sha256',domain_renewals_json($d));if($row['document_json']!==domain_renewals_json($d)||!hash_equals($row['document_sha256'],$hash)||$d['id']!==$id||(int)$row['client_id']!==$d['client_id']||$row['link_id']!==$d['link_id']||(int)$row['created_epoch']!==$d['created_epoch'])throw new RuntimeException('Renewal request history is inconsistent.');
    domain_renewals_verify_audit(0,'created','Request '.$id.' sha256 '.$hash);$revision=0;$state='pending_review';$events=[];$previousTime=$d['created_epoch'];
    foreach(all('SELECT * FROM '.table('domain_renewal_events').' WHERE request_id=? ORDER BY revision',[$id]) as $event){
        try{$e=domain_renewals_event_document(json_decode($event['document_json'],true,8,JSON_THROW_ON_ERROR));}catch(JsonException|TypeError $error){throw new RuntimeException('Renewal review history needs recovery.',0,$error);}
        $json=domain_renewals_json($e);$next=hash('sha256',$json);
        if($state!=='pending_review'||$event['document_json']!==$json||!hash_equals($event['document_sha256'],$next)||$e['id']!==$event['id']||$e['request_id']!==$id||$e['revision']!==++$revision||(int)$event['revision']!==$revision||!hash_equals($e['previous_sha256'],$hash)||$e['recorded_epoch']<$previousTime||($e['actor_kind']==='client'&&$e['actor_id']!==$d['client_id']))throw new RuntimeException('Renewal review sequence is inconsistent.');
        domain_renewals_verify_audit($e['actor_kind']==='staff'?$e['actor_id']:0,$e['command'],'Request '.$id.' event '.$e['id'].' revision '.$revision.' sha256 '.$next);
        if($e['command']!=='reviewed')$state=$e['command'];$hash=$next;$previousTime=$e['recorded_epoch'];$events[]=$e;
    }
    if((int)$row['revision']!==$revision||$row['state']!==$state||!hash_equals($row['current_sha256'],$hash))throw new RuntimeException('Renewal review history is incomplete.');return ['request'=>$d,'state'=>$state,'revision'=>$revision,'sha256'=>$hash,'events'=>$events];
}
function domain_renewals_create(string $id,int $clientId,string $linkId,int $linkRevision,int $profileRevision,string $refreshId,int $years,?bool $privacy,bool $confirmed):array {
    return staff_locked(fn()=>staff_transaction(function()use($id,$clientId,$linkId,$linkRevision,$profileRevision,$refreshId,$years,$privacy,$confirmed):array{
        domain_renewals_actor($clientId);domain_renewals_require();domain_renewals_id($id);if(!$confirmed)throw new InvalidArgumentException('Confirm that this is a request for studio review.');
        $raw=one('SELECT client_id FROM '.table('provider_registrar_links').' WHERE id=?',[$linkId]);if(!$raw||(int)$raw['client_id']!==$clientId)throw new RuntimeException('Registered domain unavailable.');
        [$link,$latest]=domain_renewals_resource($linkId);if((int)$link['revision']!==$linkRevision||(int)$link['profile_revision']!==$profileRevision||$latest['id']!==$refreshId)throw new RuntimeException('The assignment or observation changed. Reload the request form.');
        $old=one('SELECT id FROM '.table('domain_renewal_requests').' WHERE id=?',[$id]);$existing=$old?domain_renewals_load($id):null;
        $d=domain_renewals_document(['version'=>1,'id'=>$id,'client_id'=>$clientId,'link_id'=>$linkId,'link_revision'=>$linkRevision,'profile_id'=>$link['profile_id'],'profile_revision'=>$profileRevision,'key_fingerprint'=>$link['key_fingerprint'],'domain_id'=>(int)$link['domain_id'],'domain'=>$link['domain'],'refresh_id'=>$refreshId,'requested_years'=>$years,'renew_privacy'=>$privacy,'created_epoch'=>$existing['request']['created_epoch']??time()]);
        if($existing){if($existing['request']!==$d)throw new RuntimeException('This request identifier already belongs to a different request.');return $existing;}
        if(domain_renewals_pending($linkId))throw new RuntimeException('A renewal request for this assignment is already awaiting review.');
        if((int)query('SELECT COUNT(*) FROM '.table('domain_renewal_requests').' WHERE client_id=? AND created_epoch>=?',[$clientId,time()-3600])->fetchColumn()>=10)throw new RuntimeException('Please wait before adding more renewal requests.');
        $json=domain_renewals_json($d);$hash=hash('sha256',$json);
        query('INSERT INTO '.table('domain_renewal_requests').' (id,client_id,link_id,created_epoch,document_json,document_sha256,state,revision,current_sha256) VALUES (?,?,?,?,?,?,?,0,?)',[$id,$clientId,$linkId,$d['created_epoch'],$json,$hash,'pending_review',$hash]);domain_renewals_audit(0,'created','Request '.$id.' sha256 '.$hash);
        $count=(int)(one('SELECT value FROM '.table('settings').' WHERE name=?',['domain_renewals_request_count'])['value']??'-1');save_setting('domain_renewals_request_count',(string)($count+1));return domain_renewals_load($id);
    }));
}
function domain_renewals_change(string $id,int $revision,string $eventId,string $command,string $note,?int $clientId=null,#[SensitiveParameter] string $password=''):array {
    return staff_locked(fn()=>staff_transaction(function()use($id,$revision,$eventId,$command,$note,$clientId,$password):array{
        if($clientId!==null){domain_renewals_actor($clientId);if($command!=='withdrawn')throw new RuntimeException('Clients may only withdraw their own request.');$actor=$clientId;$kind='client';}
        else{$actor=(int)provider_settings_owner($password)['id'];if(!in_array($command,['reviewed','declined'],true))throw new RuntimeException('Unknown review action.');$kind='staff';}
        if($clientId!==null&&!one('SELECT id FROM '.table('domain_renewal_requests').' WHERE id=? AND client_id=?',[$id,$clientId]))throw new RuntimeException('Renewal request unavailable.');
        $doc=domain_renewals_load($id);$d=$doc['request'];if($clientId!==null&&$d['client_id']!==$clientId)throw new RuntimeException('Renewal request unavailable.');domain_renewals_id($eventId);$note=domain_renewals_note($note);
        foreach($doc['events'] as $old)if($old['id']===$eventId){if($old['revision']!==$revision+1||$old['command']!==$command||$old['actor_kind']!==$kind||$old['actor_id']!==$actor||$old['note']!==$note)throw new RuntimeException('This review identifier was already used.');return $doc;}
        if($doc['revision']!==$revision||$doc['state']!=='pending_review'||$revision>=4294967295)throw new RuntimeException('This request changed. Reload its current review.');
        if($command==='reviewed'){
            [$link]=domain_renewals_resource($d['link_id']);foreach(['client_id','profile_id','profile_revision','key_fingerprint','domain_id','domain'] as $key)if((string)$d[$key]!==(string)$link[$key])throw new RuntimeException('This request belongs to an earlier assignment or account context. Decline it and ask the client to submit a current request.');if($d['link_revision']!==(int)$link['revision'])throw new RuntimeException('The assignment changed. Decline this request and request a new one.');
        }
        $e=domain_renewals_event_document(['id'=>$eventId,'request_id'=>$id,'revision'=>$revision+1,'command'=>$command,'actor_kind'=>$kind,'actor_id'=>$actor,'recorded_epoch'=>max(time(),$d['created_epoch'],$doc['events']!==[]?$doc['events'][array_key_last($doc['events'])]['recorded_epoch']:0),'note'=>$note,'previous_sha256'=>$doc['sha256']]);$json=domain_renewals_json($e);$hash=hash('sha256',$json);$state=$command==='reviewed'?'pending_review':$command;
        query('INSERT INTO '.table('domain_renewal_events').' (id,request_id,revision,document_json,document_sha256) VALUES (?,?,?,?,?)',[$eventId,$id,$revision+1,$json,$hash]);
        $changed=query('UPDATE '.table('domain_renewal_requests').' SET state=?,revision=?,current_sha256=? WHERE id=? AND revision=? AND current_sha256=?',[$state,$revision+1,$hash,$id,$revision,$doc['sha256']]);if($changed->rowCount()!==1)throw new RuntimeException('This request changed. Reload its current review.');
        domain_renewals_audit($kind==='staff'?$actor:0,$command,'Request '.$id.' event '.$eventId.' revision '.($revision+1).' sha256 '.$hash);return domain_renewals_load($id);
    }));
}
function domain_renewals_history(?int $clientId=null,int $page=1):array {
    if($clientId!==null)domain_renewals_actor($clientId);else provider_settings_manager();if(!domain_renewals_ready())return [];$offset=(max(1,min(10000,$page))-1)*20;$out=[];
    foreach(all('SELECT id FROM '.table('domain_renewal_requests').($clientId!==null?' WHERE client_id=?':'').' ORDER BY created_epoch DESC,id DESC LIMIT 20 OFFSET '.$offset,$clientId!==null?[$clientId]:[]) as $r)$out[]=domain_renewals_load($r['id']);return $out;
}
