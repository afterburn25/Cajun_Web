<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/document_root_controls.php';
require_once CWP_CODE_ROOT.'/app/providers/document_root_requests_storage.php';

/** Eligibility alone may be hidden in a screen. Corrupt history and SQL errors must propagate. */
class DocumentRootRequestUnavailable extends RuntimeException {}

function provider_document_root_requests_session_blocked():bool {
    foreach(['uid','contact_id','contact_auth','support'] as $key)if(array_key_exists($key,$_SESSION))return true;
    foreach(['uid','contact_id','contact_auth','support'] as $key)if(array_key_exists($key,$_GET)||array_key_exists($key,$_POST))return true;
    return support_current()!==null;
}
function provider_document_root_requests_actor(?int $clientId,#[SensitiveParameter] ?string $password=null):array {
    if(array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION)||array_key_exists('support',$_SESSION)||array_key_exists('support',$_GET)||array_key_exists('support',$_POST)||support_current()!==null)throw new RuntimeException('Sign in directly to manage website folder requests.');
    if($clientId!==null){
        if(provider_document_root_requests_session_blocked())throw new RuntimeException('Sign in to your primary client account.');
        $client=studio_client();
        if(!$client||$clientId<1||(int)$client['id']!==$clientId||(int)($_SESSION['client_id']??0)!==$clientId||!hash_equals($client['auth_version'],(string)($_SESSION['client_auth']??''))||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]))throw new RuntimeException('Sign in to your primary client account.');
        return ['kind'=>'client','id'=>$clientId,'auth_sha256'=>hash('sha256',$client['auth_version'])];
    }
    $owner=$password===null?provider_settings_manager():provider_settings_owner($password);return ['kind'=>'owner','id'=>(int)$owner['id'],'auth_sha256'=>hash('sha256',$owner['auth_version'])];
}

function provider_document_root_requests_static(string $linkId):array {
    provider_document_root_requests_guard();provider_document_root_requests_id($linkId);
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE id=?',[$linkId]);
    if(!$link)throw new DocumentRootRequestUnavailable('This hosting assignment is unavailable.');
    $profile=provider_settings_load($link['profile_id']);
    if((int)$profile['enabled']!==1||$profile['credentials']===''||(int)$profile['revision']!==(int)$link['profile_revision']||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$link['client_id']]))throw new DocumentRootRequestUnavailable('This hosting account or assignment needs owner confirmation.');
    $a=provider_document_roots_assignment($linkId);$owner=one('SELECT * FROM '.table('users').' WHERE id=?',[$a['event']['actor_id']]);$owner=$owner?staff_profile($owner):null;
    if(!$owner||$owner['role']!=='owner'||(int)$owner['enabled']!==1)throw new DocumentRootRequestUnavailable('The studio must confirm this hosting assignment again.');
    $package=['profile_id'=>$link['profile_id'],'profile_revision'=>(int)$link['profile_revision'],'key_fingerprint'=>$link['key_fingerprint'],'credential_sha256'=>hash('sha256',$profile['credentials']),
        'package_id'=>(int)$link['package_id'],'client_id'=>(int)$link['client_id'],'link_id'=>$linkId,'link_sha256'=>$a['link_sha256'],'event_id'=>(int)$a['event']['id'],'event_sha256'=>$a['event_sha256'],'audit_id'=>(int)$a['audit']['id'],'audit_sha256'=>$a['audit_sha256']];
    if(!provider_document_roots_ready())throw new DocumentRootRequestUnavailable('The studio must prepare website folder observations first.');
    $permission=provider_document_roots_permission($linkId);
    if(!$permission||!$permission['document']['enabled'])throw new DocumentRootRequestUnavailable('The studio must share website folder observations for this service first.');
    $sharingOwner=one('SELECT * FROM '.table('users').' WHERE id=?',[$permission['document']['actor_id']]);$sharingOwner=$sharingOwner?staff_profile($sharingOwner):null;
    if(!$sharingOwner||$sharingOwner['role']!=='owner'||(int)$sharingOwner['enabled']!==1)throw new DocumentRootRequestUnavailable('The studio must confirm website folder observation sharing again.');
    foreach($package as $key=>$value)if($permission['binding']->data['package'][$key]!==$value)throw new DocumentRootRequestUnavailable('The shared website folder observation belongs to an earlier assignment.');
    return ['binding'=>['operation'=>\CajunWebPro\Foundation\TwentyIDocumentRoots\DocumentRootsBinding::OPERATION,'package'=>$package],
        'owner_id'=>(int)$owner['id'],'owner_auth_sha256'=>hash('sha256',$owner['auth_version']),
        'sharing'=>['id'=>$permission['document']['id'],'revision'=>$permission['document']['revision'],'sha256'=>$permission['sha256'],'owner_id'=>(int)$sharingOwner['id'],'owner_auth_sha256'=>hash('sha256',$sharingOwner['auth_version'])]];
}

/** Intake uses the explicit shared full-map anchor. Fresh review is required at owner approval. */
function provider_document_root_requests_resource(string $linkId):array {
    $resource=provider_document_root_requests_static($linkId);$permission=provider_document_roots_permission($linkId);
    foreach(['source_id','source_seq','source_sha256'] as $key)$resource['binding']['package'][$key]=$permission['binding']->data['package'][$key];
    $resource['observation']=['id'=>$permission['anchor']['id'],'seq'=>$permission['anchor']['seq'],'proof'=>$permission['anchor']['proof']];
    provider_document_root_requests_retained($resource);return $resource;
}

function provider_document_root_requests_retained(array $resource):array {
    provider_document_root_requests_resource_document($resource);$b=provider_document_roots_from_data(provider_document_root_requests_binding($resource['binding']));$p=$b->data['package'];$s=$resource['sharing'];
    if(!provider_document_roots_ready())throw new RuntimeException('The original website folder sharing history needs recovery.');
    provider_document_roots_retained_binding($b);
    $sharing=one('SELECT * FROM '.table('provider_document_roots_permissions').' WHERE id=?',[$s['id']]);
    if(!$sharing||$sharing['link_id']!==$p['link_id']||(int)$sharing['client_id']!==$p['client_id']||(int)$sharing['revision']!==$s['revision']||(int)$sharing['actor_id']!==$s['owner_id']||!hash_equals($sharing['document_sha256'],$s['sha256'])||!hash_equals(hash('sha256',$sharing['document_json']),$s['sha256']))throw new RuntimeException('The original website folder sharing permission needs recovery.');
    try{$d=json_decode($sharing['document_json'],true,12,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new RuntimeException('The original website folder sharing permission needs recovery.',0,$e);}
    if(!is_array($d)||($d['enabled']??null)!==true||($d['id']??null)!==$s['id']||($d['revision']??null)!==$s['revision']||provider_document_root_requests_binding($d['binding']??[])!==$b->data)throw new RuntimeException('The original website folder sharing scope needs recovery.');
    $o=$resource['observation'];$anchor=provider_document_roots_source($o['id']);if(!$anchor||$d['anchor_id']!==$o['id'])throw new RuntimeException('The original shared website folder map needs recovery.');$observed=provider_document_roots_observation($anchor);
    if($observed['seq']!==$o['seq']||!hash_equals($observed['binding']->sha256,$b->sha256)||!hash_equals($observed['proof'],$o['proof'])||!hash_equals($observed['proof'],$d['anchor_proof']))throw new RuntimeException('The original shared website folder map changed.');
    $source=provider_document_roots_package_source($p['source_id'],$p['package_id']);$package=service_observations_source($source['row'],$p['package_id'])['package'];
    if(!$package||!$package['enabled'])throw new RuntimeException('The original assigned hosting package needs recovery.');return $package;
}
/** Name and old path are independently tied to the original complete map, including empty paths. */
function provider_document_root_requests_retained_request(array $request):void {
    $p=$request['payload'];provider_document_root_requests_retained($p['resource']);$source=provider_document_roots_source($p['resource']['observation']['id']);
    $anchor=provider_document_roots_observation($source);
    if(provider_document_root_controls_entry($anchor,$p['name'])!==$p['before_path'])throw new RuntimeException('The submitted website folder observation needs recovery.');
}

function provider_document_root_requests_event_sha(array $event):string {return hash('sha256',provider_document_root_requests_json($event));}
function provider_document_root_requests_context(array $resource,array $actor):string {return provider_operations_scope('website-folder-requests.form',['resource'=>$resource,'actor'=>$actor]);}

function provider_document_root_requests_graph():?array {$events=provider_document_root_requests_events();return $events===null?null:provider_document_root_requests_project($events);}
function provider_document_root_requests_state():array {
    provider_document_root_requests_guard();$state=provider_document_root_requests_graph();
    if($state===null)throw new RuntimeException('The studio has not enabled website folder requests yet.');
    if(!provider_document_root_controls_ready())throw new RuntimeException('The studio must prepare Website folder controls first.');provider_document_root_requests_owner_history();return $state;
}
/** Historical cross-link only. This never re-enters owner readiness. */
function provider_document_root_requests_validate_origin(array $owner):void {
    $d=$owner['document'];$origin=$d['client_origin']??null;
    if($d['version']!==2||!is_array($origin)||array_keys($origin)!==['id','request_sha256']||!is_string($origin['id'])||!is_string($origin['request_sha256']))throw new RuntimeException('website folder client origin needs recovery.');
    $state=provider_document_root_requests_graph();$r=$state['requests'][$origin['id']]??null;
    if(!$r||$r['state']!=='dispatched'||!hash_equals(provider_document_root_requests_event_sha($r['request']),$origin['request_sha256']))throw new RuntimeException('website folder client origin needs recovery.');
    $event=$r['events'][array_key_last($r['events'])];$q=$r['request'];$p=$q['payload'];
    if($event['kind']!=='dispatch'||$event['id']!==$owner['row']['id']||$event['payload']['owner_request_id']!==$owner['row']['id']||!hash_equals($event['payload']['owner_request_sha256'],$owner['row']['document_sha256'])
        ||$event['actor']['kind']!=='owner'||$event['actor']['id']!==(int)$owner['row']['actor_id']||!hash_equals($event['actor']['auth_sha256'],$d['actor_auth'])||$event['recorded_epoch']<$d['approved_epoch']||$q['recorded_epoch']>$d['approved_epoch']
        ||$p['name']!==$d['params']['name']||$p['desired_path']!==$d['params']['path'])throw new RuntimeException('website folder dispatch association needs recovery.');
    provider_document_root_requests_retained_request($q);
    foreach($p['resource']['binding']['package'] as $key=>$value)if(!in_array($key,['source_id','source_seq','source_sha256'],true)&&$d['binding']['package'][$key]!==$value)throw new RuntimeException('website folder client assignment association needs recovery.');
}
function provider_document_root_requests_owner_history():void {
    $state=provider_document_root_requests_graph();if($state===null)return;
    foreach($state['requests'] as $r){
        provider_document_root_requests_retained_request($r['request']);
        if($r['state']==='dispatched'){$event=$r['events'][array_key_last($r['events'])];provider_document_root_requests_validate_origin(provider_document_root_controls_request($event['payload']['owner_request_id']));}
    }
}

function provider_document_root_requests_current(array $r):array {
    $q=$r['request'];$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1',[$q['client_id']]);
    if(!$client||!hash_equals($q['actor']['auth_sha256'],hash('sha256',$client['auth_version'])))throw new DocumentRootRequestUnavailable('The client account changed. Ask for a new website folder request.');
    $original=$q['payload']['resource'];provider_document_root_requests_retained($original);$current=provider_document_root_requests_static($original['binding']['package']['link_id']);$static=$original;
    foreach(['source_id','source_seq','source_sha256'] as $key)unset($static['binding']['package'][$key]);
    unset($static['observation']);
    if(provider_document_root_requests_json($static)!==provider_document_root_requests_json($current))throw new DocumentRootRequestUnavailable('The request belongs to an earlier hosting assignment.');return $original;
}
function provider_document_root_requests_origin_current(array $owner):void {
    provider_document_root_requests_guard();provider_document_root_requests_actor(null);provider_document_root_requests_validate_origin($owner);$state=provider_document_root_requests_graph();$r=$state['requests'][$owner['document']['client_origin']['id']];
    if(db()->inTransaction())provider_document_root_requests_current($r);else staff_transaction(fn()=>provider_document_root_requests_current($r));
}
function provider_document_root_requests_pending(array $state,array $resource):bool {
    $b=$resource['binding']['package'];foreach($state['requests'] as $r){$old=$r['request']['payload']['resource']['binding']['package'];if($r['state']==='pending'&&$old['profile_id']===$b['profile_id']&&$old['package_id']===$b['package_id'])return true;}
    return provider_document_root_controls_pending($b['profile_id'],$b['package_id'])!==null;
}

function provider_document_root_requests_create(string $id,int $clientId,string $linkId,string $reviewSha,string $name,string $desiredPath,bool $confirmed):array {
    return staff_locked(fn()=>staff_transaction(function()use($id,$clientId,$linkId,$reviewSha,$name,$desiredPath,$confirmed):array{
        $actor=provider_document_root_requests_actor($clientId);$state=provider_document_root_requests_state();provider_document_root_requests_id($id);provider_document_root_requests_id($linkId);
        if(!$confirmed)throw new InvalidArgumentException('Confirm that the studio must review this request.');
        if(!\CajunWebPro\Foundation\TwentyIDocumentRoots\DocumentRootsObservation::validName($name))throw new InvalidArgumentException('Select an exact shared website hostname.');
        $desiredPath=\CajunWebPro\Foundation\TwentyI\Operations\DocumentRootChange::path($desiredPath);
        $old=$state['requests'][$id]??null;if($old&&$old['request']['client_id']!==$clientId)throw new RuntimeException('This request identifier was already used.');$resource=$old?provider_document_root_requests_current($old):provider_document_root_requests_resource($linkId);
        if($resource['binding']['package']['client_id']!==$clientId||$resource['binding']['package']['link_id']!==$linkId||!hash_equals(provider_document_root_requests_context($resource,$actor),$reviewSha))throw new RuntimeException('The hosting assignment or shared website folder observation changed. Reload the request form.');
        $anchor=provider_document_roots_observation(provider_document_roots_source($resource['observation']['id']));$before=provider_document_root_controls_entry($anchor,$name);
        if($before===null)throw new InvalidArgumentException('Select an existing hostname in the shared website folder map.');
        if($before===$desiredPath)throw new InvalidArgumentException('The shared map already reports this folder for the website.');
        $payload=['resource'=>$resource,'name'=>$name,'before_path'=>$before,'desired_path'=>$desiredPath,'confirmed'=>true];
        if($old){if($old['request']['client_id']!==$clientId||provider_document_root_requests_json($old['request']['actor'])!==provider_document_root_requests_json($actor)||provider_document_root_requests_json($old['request']['payload'])!==provider_document_root_requests_json($payload))throw new RuntimeException('This request identifier was already used.');return provider_document_root_requests_item($old,$clientId);}
        if(provider_document_root_requests_pending($state,$resource))throw new RuntimeException('A request for this hosting service still needs studio review.');
        $recent=0;foreach($state['requests'] as $r)if($r['request']['client_id']===$clientId&&$r['request']['recorded_epoch']>=time()-86400)$recent++;
        if($recent>=10)throw new RuntimeException('The daily website folder request limit has been reached. Please try later.');
        provider_document_root_requests_append($id,$id,$clientId,'request',$actor,$payload);return provider_document_root_requests_item(provider_document_root_requests_state()['requests'][$id],$clientId);
    }));
}

function provider_document_root_requests_change(string $id,string $expectedEvent,string $eventId,string $command,string $note,?int $clientId=null,#[SensitiveParameter] string $password=''):array {
    return staff_locked(fn()=>staff_transaction(function()use($id,$expectedEvent,$eventId,$command,$note,$clientId,$password):array{
        $actor=provider_document_root_requests_actor($clientId,$clientId===null?$password:null);$state=provider_document_root_requests_state();foreach([$id,$expectedEvent,$eventId] as $value)provider_document_root_requests_id($value);
        if($command!==($clientId===null?'decline':'cancel'))throw new RuntimeException('This website folder request action is unavailable.');
        $r=$state['requests'][$id]??null;if(!$r||($clientId!==null&&$r['request']['client_id']!==$clientId))throw new RuntimeException('Website folder request unavailable.');$note=provider_document_root_requests_note($note);$payload=['previous_event'=>$expectedEvent,'note'=>$note];
        foreach($r['events'] as $event)if($event['id']===$eventId){if($event['kind']!==$command||provider_document_root_requests_json($event['actor'])!==provider_document_root_requests_json($actor)||provider_document_root_requests_json($event['payload'])!==provider_document_root_requests_json($payload))throw new RuntimeException('This review identifier was already used.');return provider_document_root_requests_item($r,$clientId);}
        if($r['state']!=='pending'||$r['latest_event']!==$expectedEvent)throw new RuntimeException('This website folder request changed. Reload its review.');
        if($clientId!==null)provider_document_root_requests_current($r);
        provider_document_root_requests_append($eventId,$id,$r['request']['client_id'],$command,$actor,$payload);return provider_document_root_requests_item(provider_document_root_requests_state()['requests'][$id],$clientId);
    }));
}
/** Internal admission check before the reciprocal dispatch event exists. */
/** Validate a pending client intent before its reciprocal dispatch has been appended. */
function provider_document_root_requests_admission_origin(array $origin,string $linkId,string $name,string $desiredPath):void {
    provider_document_root_requests_guard(true);
    if(array_keys($origin)!==['id','request_sha256']||!is_string($origin['id'])||!is_string($origin['request_sha256']))throw new RuntimeException('Invalid client website folder origin.');
    $state=provider_document_root_requests_state();$r=$state['requests'][$origin['id']]??null;
    if(!$r||$r['state']!=='pending'||!hash_equals(provider_document_root_requests_event_sha($r['request']),$origin['request_sha256'])||$r['request']['payload']['resource']['binding']['package']['link_id']!==$linkId||$r['request']['payload']['name']!==$name||$r['request']['payload']['desired_path']!==$desiredPath)throw new RuntimeException('The original client website folder request changed.');provider_document_root_requests_current($r);
}

function provider_document_root_requests_approval(array $r):?array {
    $resource=provider_document_root_requests_current($r);$link=$resource['binding']['package']['link_id'];$latest=provider_document_roots_latest($link);
    if(!$latest||$latest['state']!=='complete'||!service_observations_fresh($latest))return null;
    $anchor=provider_document_roots_observation($latest);
    try{$binding=provider_document_roots_binding($link);provider_document_root_controls_precondition($link,$anchor['id'],$binding->sha256);}catch(RuntimeException $e){
        if($e instanceof PDOException||!in_array($e->getMessage(),['Refresh hosting inventory for this exact assignment first.','Refresh hosting inventory after this assignment or reapproval.','Refresh and review the latest website folder map before changing it.'],true))throw $e;
        return null;
    }
    $before=provider_document_root_controls_entry($anchor,$r['request']['payload']['name']);$p=$binding->data['package'];
    if($before===null||$before===$r['request']['payload']['desired_path']||provider_document_root_controls_pending($p['profile_id'],$p['package_id']))return null;
    return ['request_id'=>bin2hex(random_bytes(16)),'anchor_id'=>$anchor['id'],'binding_sha256'=>$binding->sha256,'before_path'=>$before,'observed_epoch'=>$anchor['observed_epoch']];
}

function provider_document_root_requests_approve(string $id,string $expectedEvent,string $requestSha,string $ownerRequestId,string $anchorId,string $bindingSha,bool $confirmed,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null):array {
    if(db()->inTransaction())throw new LogicException('website folder approval cannot run in a transaction.');
    return staff_locked(function()use($id,$expectedEvent,$requestSha,$ownerRequestId,$anchorId,$bindingSha,$confirmed,$password,$resolver,$perform):array{
        $fresh=staff_transaction(function()use($id,$expectedEvent,$requestSha,$ownerRequestId,$anchorId,$bindingSha,$confirmed,$password):bool{
            $actor=provider_document_root_requests_actor(null,$password);$state=provider_document_root_requests_state();foreach([$id,$expectedEvent,$ownerRequestId] as $value)provider_document_root_requests_id($value);
            if(!$confirmed)throw new InvalidArgumentException('Confirm this exact requested website folder change first.');$r=$state['requests'][$id]??null;
            if(!$r||!hash_equals(provider_document_root_requests_event_sha($r['request']),$requestSha))throw new RuntimeException('The original client request changed. Reload its review.');provider_document_root_requests_current($r);
            if($r['state']==='dispatched'){
                $event=$r['events'][array_key_last($r['events'])];$owner=provider_document_root_controls_request($event['payload']['owner_request_id']);
                if($event['id']!==$ownerRequestId||$event['payload']['previous_event']!==$expectedEvent||provider_document_root_requests_json($event['actor'])!==provider_document_root_requests_json($actor)||$owner['document']['anchor_id']!==$anchorId||!hash_equals($owner['binding']->sha256,$bindingSha))throw new RuntimeException('This approval identifier or reviewed request changed.');
                provider_document_root_controls_context($owner['binding']);if((int)$owner['row']['actor_id']!==$actor['id']||!hash_equals($owner['document']['actor_auth'],$actor['auth_sha256']))throw new RuntimeException('The original approval owner changed.');return false;
            }
            if($r['state']!=='pending'||$r['latest_event']!==$expectedEvent)throw new RuntimeException('This request was already reviewed or withdrawn.');$p=$r['request']['payload'];
            if(!provider_document_root_controls_admit_locked($ownerRequestId,$p['resource']['binding']['package']['link_id'],$anchorId,$bindingSha,$p['name'],$p['desired_path'],true,$password,['id'=>$id,'request_sha256'=>$requestSha]))throw new RuntimeException('This approval identifier was already used.');
            $owner=one('SELECT document_sha256 FROM '.table('provider_document_root_changes').' WHERE id=?',[$ownerRequestId]);
            provider_document_root_requests_append($ownerRequestId,$id,$r['request']['client_id'],'dispatch',$actor,['previous_event'=>$expectedEvent,'owner_request_id'=>$ownerRequestId,'owner_request_sha256'=>$owner['document_sha256']]);
            provider_document_root_requests_state();return true;
        });
        if($fresh)provider_document_root_controls_send_locked($ownerRequestId,$resolver,$perform);
        return staff_transaction(fn()=>provider_document_root_requests_item(provider_document_root_requests_state()['requests'][$id],null));
    });
}
/** Closed client projection: receipt status only, never private paths, grants or credentials. */
function provider_document_root_requests_item(array $r,?int $clientId):array {
    $q=$r['request'];if($clientId!==null&&$q['client_id']!==$clientId)throw new RuntimeException('Website folder request unavailable.');
    $client=null;if($clientId===null){$row=one('SELECT id,name FROM '.table('studio_clients').' WHERE id=?',[$q['client_id']]);if(!$row||(int)$row['id']!==$q['client_id']||!is_string($row['name']))throw new RuntimeException('The original request client record needs recovery.');$client=['id'=>$q['client_id'],'name'=>$row['name']];}
    $current=true;try{provider_document_root_requests_current($r);}catch(DocumentRootRequestUnavailable){$current=false;}
    $state=$r['state'];$lastCheck=null;$ownerId=null;$dispatch=null;$approval=null;$unavailable=false;$note='';
    foreach($r['events'] as $event)if($event['kind']==='decline')$note=$event['payload']['note'];
    if($state==='dispatched'){
        $event=$r['events'][array_key_last($r['events'])];$ownerId=$event['payload']['owner_request_id'];$owner=provider_document_root_controls_request($ownerId);$terminal=provider_document_root_controls_terminal($owner);
        $state=$terminal===null?'interrupted':($terminal['row']['disposition']==='not_sent'?'not_sent':'unknown');
        $check=one('SELECT c.* FROM '.table('provider_document_root_change_checks').' c JOIN '.table('provider_refreshes').' f ON f.id=c.receipt_id WHERE c.request_id=? ORDER BY f.seq DESC LIMIT 1',[$ownerId]);
        if($check){$checked=provider_document_root_controls_check_document($check,$owner);$lastCheck=['disposition'=>$checked['disposition'],'recorded_epoch'=>$checked['recorded_epoch']];if($checked['disposition']==='observed')$state='observed';}
        if($clientId===null){$dispatch=provider_document_root_controls_summary($ownerId);$dispatch['can_verify']=false;if($current&&$terminal&&$terminal['row']['disposition']==='attempted'&&$state!=='observed'){try{provider_document_root_controls_finished_ledger($owner,time());$dispatch['can_verify']=true;}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;}}if(!$current){$dispatch['can_verify']=false;$dispatch['recovery_ledger_sha256']=null;}}
    }elseif($state==='pending'&&$clientId===null&&$current){try{$approval=provider_document_root_requests_approval($r);}catch(DocumentRootRequestUnavailable){$current=false;}$unavailable=$approval===null;}
    return ['id'=>$q['id'],'client'=>$client,'package_id'=>$q['payload']['resource']['binding']['package']['package_id'],'link_id'=>$q['payload']['resource']['binding']['package']['link_id'],'package_name'=>provider_document_root_requests_retained($q['payload']['resource'])['name'],'name'=>$q['payload']['name'],'before_path'=>$q['payload']['before_path'],'desired_path'=>$q['payload']['desired_path'],'created_epoch'=>$q['recorded_epoch'],'state'=>$state,'latest_event'=>$r['latest_event'],'request_sha256'=>provider_document_root_requests_event_sha($q),'current_assignment'=>$current,'can_cancel'=>$clientId!==null&&$current&&$r['state']==='pending','can_decline'=>$clientId===null&&$r['state']==='pending','note'=>$note,'owner_request_id'=>$clientId===null?$ownerId:null,'last_check'=>$lastCheck,'approval'=>$approval,'approval_unavailable'=>$clientId===null?$unavailable:false,'dispatch'=>$dispatch];
}
function provider_document_root_requests_screen(?int $clientId=null,int $page=1,?string $detailId=null):array {
    return staff_locked(fn()=>staff_transaction(function()use($clientId,$page,$detailId):array{
        $actor=provider_document_root_requests_actor($clientId);$page=max(1,min(500,$page));$out=['ready'=>false,'options'=>[],'items'=>[],'page'=>$page,'has_more'=>false];
        if(!provider_document_root_requests_ready()){provider_document_root_controls_ready();return $out;}$state=provider_document_root_requests_state();$out['ready']=true;
        if($detailId!==null){provider_document_root_requests_id($detailId);$r=$state['requests'][$detailId]??null;if(!$r||($clientId!==null&&$r['request']['client_id']!==$clientId))throw new RuntimeException('Website folder request unavailable.');$out['items'][]=provider_document_root_requests_item($r,$clientId);return $out;}
        if($clientId!==null)foreach(all('SELECT id FROM '.table('provider_package_links').' WHERE client_id=? ORDER BY id',[$clientId]) as $row){
            try{$resource=provider_document_root_requests_resource($row['id']);if($resource['binding']['package']['client_id']!==$clientId)continue;$package=provider_document_root_requests_retained($resource);
                $permission=provider_document_roots_permission($row['id']);$out['options'][]=['link_id'=>$row['id'],'package_id'=>$resource['binding']['package']['package_id'],'package_name'=>$package['name'],'review_sha256'=>provider_document_root_requests_context($resource,$actor),'entries'=>$permission['anchor']['entries'],'observed_epoch'=>$permission['anchor']['observed_epoch']];
            }catch(DocumentRootRequestUnavailable){}
        }
        $items=array_reverse(array_values($state['requests']));if($clientId!==null)$items=array_values(array_filter($items,static fn($r)=>$r['request']['client_id']===$clientId));$offset=($page-1)*20;$out['has_more']=count($items)>$offset+20;
        foreach(array_slice($items,$offset,20) as $r)$out['items'][]=provider_document_root_requests_item($r,$clientId);return $out;
    }));
}
function provider_document_root_requests_dispatch(string $id,string $ownerRequestId,#[SensitiveParameter] string $password):array {
    provider_document_root_requests_actor(null,$password);$state=provider_document_root_requests_state();provider_document_root_requests_id($id);provider_document_root_requests_id($ownerRequestId);$r=$state['requests'][$id]??null;
    if(!$r||$r['state']!=='dispatched'||$r['latest_event']!==$ownerRequestId)throw new RuntimeException('This owner approval does not belong to the client request.');provider_document_root_requests_current($r);
    $owner=provider_document_root_controls_request($ownerRequestId);provider_document_root_requests_validate_origin($owner);return $owner;
}
/** Existing inventory recorder and quotas, with original client consent at each read boundary. */
function provider_document_root_requests_refresh(string $ownerRequestId,\CajunWebPro\Foundation\TwentyIDocumentRoots\DocumentRootsBinding $binding,#[SensitiveParameter] string $password,?callable $resolver,?callable $transport):array {
    $authorize=static function()use($ownerRequestId,$binding):void{
        provider_document_roots_authority($binding);$owner=provider_document_root_controls_request($ownerRequestId);provider_document_root_requests_origin_current($owner);
    };
    return provider_inventory_refresh_attempt($binding->data['package']['profile_id'],$binding->data['package']['profile_revision'],$password,$resolver,$transport,operation:\CajunWebPro\Foundation\TwentyIDocumentRoots\DocumentRootsBinding::OPERATION,scopeAuthority:$authorize,documentRootsContext:$binding);
}

function provider_document_root_requests_verify(string $id,string $ownerRequestId,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    if(db()->inTransaction())throw new LogicException('website folder verification cannot inherit a transaction.');
    staff_locked(fn()=>staff_transaction(fn()=>provider_document_root_requests_dispatch($id,$ownerRequestId,$password)));
    provider_document_root_controls_verify($ownerRequestId,$password,$resolver,$transport);return provider_document_root_requests_screen(null,1,$id)['items'][0];
}
function provider_document_root_requests_recover(string $id,string $ownerRequestId,string $ledgerSha,#[SensitiveParameter] string $password):array {
    if(db()->inTransaction())throw new LogicException('website folder recovery cannot inherit a transaction.');
    return staff_locked(function()use($id,$ownerRequestId,$ledgerSha,$password):array{
        staff_transaction(fn()=>provider_document_root_requests_dispatch($id,$ownerRequestId,$password));provider_document_root_controls_recover_finished($ownerRequestId,$ledgerSha,$password);
        return staff_transaction(fn()=>provider_document_root_requests_item(provider_document_root_requests_state()['requests'][$id],null));
    });
}
