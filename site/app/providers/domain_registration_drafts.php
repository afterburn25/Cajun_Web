<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_registration_drafts_document.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_registration_drafts_crypto.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_registration_drafts_storage.php';
use CajunWebPro\Foundation\TwentyIDomainRegistration\RegistrationDraft;

function registration_drafts_no_support():void {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Registration drafts require a direct client or owner session.');
}
function registration_drafts_owner(#[SensitiveParameter] ?string $password=null):array {
    registration_drafts_no_support();if(!staff_ready())throw new RuntimeException('Enable administrator access first.');
    $actor=staff_require('staff.manage');$profile=staff_profile($actor);
    if(!$profile||$profile['role']!=='owner')throw new RuntimeException('An active owner must review registration drafts.');
    if(!admin_session_matches($actor))throw new InvalidArgumentException('Your administrator session is no longer valid. Sign in again.');return $actor;
}
function registration_drafts_client(int $id):array {
    registration_drafts_no_support();$client=studio_client();
    if(!$client||(int)$client['id']!==$id)throw new RuntimeException('Sign in directly to review your registration drafts.');return $client;
}
function registration_drafts_target(int $id,?string $version=null):array {
    if(!studio_ready()||$id<1||$id>4294967295)throw new RuntimeException('Client unavailable.');
    $client=one('SELECT id,name,auth_version FROM '.table('studio_clients').' WHERE id=? AND active=1',[$id]);
    if(!$client||($version!==null&&!hash_equals($client['auth_version'],$version)))throw new RuntimeException('The client account changed. Reload its registration draft.');return $client;
}
function registration_drafts_require():void {if(!registration_drafts_ready())throw new RuntimeException('The studio has not enabled private registration drafts yet.');}
function registration_drafts_head(string $id,int $client):array {
    registration_drafts_id($id);$head=one('SELECT * FROM '.table('domain_registration_drafts').' WHERE id=? AND client_id=?',[$id,$client]);
    if(!$head)throw new RuntimeException('Registration draft unavailable.');return $head;
}
/** Only call after current actor, target and global authenticated readiness checks. */
function registration_drafts_open(array $row):RegistrationDraft {
    $d=registration_drafts_decode($row);$key=registration_drafts_key();
    try{$plain=registration_drafts_decrypt($row['ciphertext'],$key,registration_drafts_aad($d['draft_id'],$d['client_id'],$d['revision'],$d['id']));}
    finally{unset($key);}
    try{
        $draft=RegistrationDraft::fromPrivateJson($plain);$content=registration_drafts_content($draft);
        if($content['id']!==$d['draft_id']||$content['client_id']!==$d['client_id']||$content['domain']!==$d['domain']||$content['requested_years']!==$d['requested_years']||$content['privacy_service']!==$d['privacy_service']||!hash_equals(registration_drafts_mac('content',$plain),$d['content_hmac']))throw new RuntimeException();return $draft;
    }catch(Throwable $e){throw new RuntimeException('Protected registration draft needs recovery.',0,$e);}finally{unset($plain,$content);}
}
function registration_drafts_summary(array $row):array {
    $d=registration_drafts_decode($row);return ['id'=>$d['draft_id'],'client_id'=>$d['client_id'],'revision'=>$d['revision'],'state'=>$d['state'],'sha256'=>$row['document_sha256'],'domain'=>$d['domain'],'requested_years'=>$d['requested_years'],'privacy_service'=>$d['privacy_service'],'recorded_epoch'=>$d['recorded_epoch'],'actor_kind'=>$d['actor_kind'],'actor_id'=>$d['actor_id'],'action'=>$d['action']];
}
function domain_registration_drafts_list(?int $clientId=null,int $page=1):array {
    return staff_locked(fn()=>staff_transaction(function()use($clientId,$page):array{
        if($clientId===null)registration_drafts_owner();else registration_drafts_client($clientId);
        if(!registration_drafts_ready())return ['ready'=>false,'drafts'=>[],'has_more'=>false];$offset=(max(1,min(10000,$page))-1)*20;$out=[];
        $sql='SELECT r.* FROM '.table('domain_registration_drafts').' d JOIN '.table('domain_registration_revisions').' r ON r.id=d.current_id JOIN '.table('studio_clients').' c ON c.id=d.client_id AND c.active=1';
        foreach(all($sql.($clientId===null?'':' WHERE d.client_id=?').' ORDER BY d.updated_epoch DESC,d.id DESC LIMIT 21 OFFSET '.$offset,$clientId===null?[]:[$clientId]) as $row)$out[]=registration_drafts_summary($row);
        return ['ready'=>true,'drafts'=>array_slice($out,0,20),'has_more'=>count($out)>20];
    }));
}
function domain_registration_drafts_get(string $id,?int $clientId=null):array {
    return staff_locked(fn()=>staff_transaction(function()use($id,$clientId):array{
        if($clientId===null)registration_drafts_owner();else registration_drafts_client($clientId);registration_drafts_require();registration_drafts_id($id);
        $head=one('SELECT * FROM '.table('domain_registration_drafts').' WHERE id=?'.($clientId===null?'':' AND client_id=?'),$clientId===null?[$id]:[$id,$clientId]);
        if(!$head)throw new RuntimeException('Registration draft unavailable.');$target=registration_drafts_target((int)$head['client_id']);$history=[];
        foreach(all('SELECT * FROM '.table('domain_registration_revisions').' WHERE draft_id=? ORDER BY revision DESC LIMIT 256',[$id]) as $i=>$row){if($i===0){$draft=registration_drafts_open($row);$summary=registration_drafts_summary($row);}$history[]=registration_drafts_summary($row);}
        return ['summary'=>$summary,'content'=>$draft,'history'=>$history,'client_version'=>$target['auth_version'],'client_name'=>$target['name']];
    }));
}
function domain_registration_drafts_owner_target(int $clientId):array {
    return staff_locked(fn()=>staff_transaction(function()use($clientId):array{registration_drafts_owner();registration_drafts_require();return registration_drafts_target($clientId);}));
}
function domain_registration_drafts_owner_clients(int $page=1):array {
    return staff_locked(fn()=>staff_transaction(function()use($page):array{registration_drafts_owner();registration_drafts_require();$offset=(max(1,min(10000,$page))-1)*100;$clients=all('SELECT id,name FROM '.table('studio_clients').' WHERE active=1 ORDER BY name,id LIMIT 101 OFFSET '.$offset);return ['clients'=>array_slice($clients,0,100),'has_more'=>count($clients)>100];}));
}
function domain_registration_drafts_client_save(int $clientId,string $id,int $expectedRevision,string $expectedHead,string $command,RegistrationDraft $content):string {
    return registration_drafts_mutate($clientId,null,$id,$expectedRevision,$expectedHead,$command,'save',$content,null);
}
function domain_registration_drafts_owner_save(int $clientId,string $clientVersion,string $id,int $expectedRevision,string $expectedHead,string $command,RegistrationDraft $content,#[SensitiveParameter] string $password):string {
    return registration_drafts_mutate($clientId,$clientVersion,$id,$expectedRevision,$expectedHead,$command,'save',$content,$password);
}
function domain_registration_drafts_client_change(int $clientId,string $id,int $expectedRevision,string $expectedHead,string $command,string $action):string {
    if(!in_array($action,['submit','withdraw','reopen'],true))throw new InvalidArgumentException('Unknown registration draft action.');
    return registration_drafts_mutate($clientId,null,$id,$expectedRevision,$expectedHead,$command,$action,null,null);
}
/** One durable local command path. There is no provider or payment operation. */
function registration_drafts_mutate(int $clientId,?string $clientVersion,string $id,int $expectedRevision,string $expectedHead,string $command,string $action,?RegistrationDraft $draft,#[SensitiveParameter] ?string $password):string {
    return staff_locked(fn()=>staff_transaction(function()use($clientId,$clientVersion,$id,$expectedRevision,$expectedHead,$command,$action,$draft,$password):string{
        if($password===null){$actor=registration_drafts_client($clientId);$kind='client';$clientVersion=$actor['auth_version'];}
        else{$actor=registration_drafts_owner($password);$kind='staff';if($clientVersion===null)throw new InvalidArgumentException('Reload the client registration form.');}
        $target=registration_drafts_target($clientId,$clientVersion);registration_drafts_require();registration_drafts_id($id);registration_drafts_id($command);registration_drafts_hash($expectedHead);
        if($expectedRevision<0||$expectedRevision>256||($action==='save')!==($draft!==null)||($kind==='staff'&&$action!=='save'))throw new InvalidArgumentException('Reload the registration draft form.');
        $head=one('SELECT * FROM '.table('domain_registration_drafts').' WHERE id=?',[$id]);
        if($head&&(int)$head['client_id']!==$clientId)throw new RuntimeException('Registration draft unavailable.');
        if($draft===null){$old=one('SELECT * FROM '.table('domain_registration_revisions').' WHERE draft_id=? AND revision=? AND client_id=?',[$id,$expectedRevision,$clientId]);if(!$old||$old['document_sha256']!==$expectedHead)throw new RuntimeException('This draft changed. Reload it before continuing.');$draft=registration_drafts_open($old);}
        $data=registration_drafts_content($draft);if($data['id']!==$id||$data['client_id']!==$clientId)throw new InvalidArgumentException('The draft identity changed. Reload the form.');
        $plain=$draft->privateJson();$contentMac=registration_drafts_mac('content',$plain);$oldCommand=one('SELECT * FROM '.table('domain_registration_revisions').' WHERE id=?',[$command]);
        if($oldCommand){$old=registration_drafts_decode($oldCommand);if($old['draft_id']!==$id||$old['client_id']!==$clientId||$old['expected_revision']!==$expectedRevision||$old['previous_sha256']!==$expectedHead||$old['action']!==$action||$old['actor_kind']!==$kind||$old['actor_id']!==(int)$actor['id']||$old['actor_version']!==$actor['auth_version']||$old['client_version']!==$target['auth_version']||!hash_equals($old['content_hmac'],$contentMac))throw new RuntimeException('This draft command was already used. Reload the current draft.');return $id;}
        if(($head===null&&($expectedRevision!==0||$expectedHead!==str_repeat('0',64)||$action!=='save'))||($head!==null&&((int)$head['revision']!==$expectedRevision||$head['current_sha256']!==$expectedHead))||$expectedRevision>=256)throw new RuntimeException('This draft changed or reached its revision limit. Reload it before continuing.');
        if($head===null&&(int)query('SELECT COUNT(*) FROM '.table('domain_registration_drafts').' WHERE client_id=?',[$clientId])->fetchColumn()>=100)throw new RuntimeException('This client has reached the saved draft limit. Contact the studio.');
        $revision=$expectedRevision+1;$state=registration_drafts_state($head['state']??null,$action,$kind);$epoch=max(time(),(int)($head['updated_epoch']??0));$key=registration_drafts_key();
        try{$cipher=registration_drafts_encrypt($plain,$key,registration_drafts_aad($id,$clientId,$revision,$command));}finally{unset($key,$plain);}
        $document=registration_drafts_envelope(['version'=>1,'id'=>$command,'draft_id'=>$id,'client_id'=>$clientId,'revision'=>$revision,'state'=>$state,'action'=>$action,'actor_kind'=>$kind,'actor_id'=>(int)$actor['id'],'actor_version'=>$actor['auth_version'],'client_version'=>$target['auth_version'],'recorded_epoch'=>$epoch,'expected_revision'=>$expectedRevision,'previous_sha256'=>$expectedHead,'domain'=>$data['domain'],'requested_years'=>$data['requested_years'],'privacy_service'=>$data['privacy_service'],'content_hmac'=>$contentMac,'cipher_sha256'=>hash('sha256',$cipher)]);
        $json=registration_drafts_json($document);$hash=hash('sha256',$json);$proof=registration_drafts_mac('revision',$json);
        if($head===null)query('INSERT INTO '.table('domain_registration_drafts').' (id,client_id,revision,state,current_id,current_sha256,created_epoch,updated_epoch) VALUES (?,?,0,?,?,?,?,?)',[$id,$clientId,'draft',str_repeat('0',32),str_repeat('0',64),$epoch,$epoch]);
        $columns=['id','draft_id','client_id','revision','state','action','actor_kind','actor_id','recorded_epoch'];$values=[];foreach($columns as $name)$values[]=$document[$name];
        query('INSERT INTO '.table('domain_registration_revisions').' ('.implode(',',$columns).',document_json,document_sha256,proof,ciphertext) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[...$values,$json,$hash,$proof,$cipher]);
        $changed=query('UPDATE '.table('domain_registration_drafts').' SET revision=?,state=?,current_id=?,current_sha256=?,updated_epoch=? WHERE id=? AND client_id=? AND revision=? AND current_sha256=?',[$revision,$state,$command,$hash,$epoch,$id,$clientId,$expectedRevision,$expectedHead]);if($changed->rowCount()!==1)throw new RuntimeException('This draft changed. Reload it before continuing.');
        $audit=registration_drafts_audit($kind==='staff'?(int)$actor['id']:0,'revision',registration_drafts_detail(['id'=>$command,'draft_id'=>$id,'revision'=>$revision,'document_sha256'=>$hash]));
        $cp=registration_drafts_read_checkpoint();$cp['drafts']+=$head===null?1:0;$cp['revisions']++;$cp['last_id']=$command;$cp['last_sha256']=$hash;$cp['last_audit_id']=$audit;registration_drafts_write_checkpoint($cp);registration_drafts_ready();return $id;
    }));
}
