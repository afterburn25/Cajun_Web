<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/force_ssl_controls.php';
require_once CWP_CODE_ROOT.'/app/providers/force_ssl_requests_storage.php';

/** Eligibility alone may be hidden in a screen. Corrupt history and SQL errors must propagate. */
class ForceSslRequestUnavailable extends RuntimeException {}

function provider_force_ssl_requests_session_blocked():bool {
    foreach(['uid','contact_id','contact_auth','support'] as $key)if(array_key_exists($key,$_SESSION))return true;
    foreach(['uid','contact_id','contact_auth','support'] as $key)if(array_key_exists($key,$_GET)||array_key_exists($key,$_POST))return true;
    return support_current()!==null;
}
function provider_force_ssl_requests_actor(?int $clientId,#[SensitiveParameter] ?string $password=null):array {
    if(array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION)||array_key_exists('support',$_SESSION)||array_key_exists('support',$_GET)||array_key_exists('support',$_POST)||support_current()!==null)throw new RuntimeException('Sign in directly to manage HTTPS requests.');
    if($clientId!==null){
        if(provider_force_ssl_requests_session_blocked())throw new RuntimeException('Sign in to your primary client account.');
        $client=studio_client();
        if(!$client||$clientId<1||(int)$client['id']!==$clientId||(int)($_SESSION['client_id']??0)!==$clientId||!hash_equals($client['auth_version'],(string)($_SESSION['client_auth']??''))||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]))throw new RuntimeException('Sign in to your primary client account.');
        return ['kind'=>'client','id'=>$clientId,'auth_sha256'=>hash('sha256',$client['auth_version'])];
    }
    $owner=$password===null?provider_settings_manager():provider_settings_owner($password);return ['kind'=>'owner','id'=>(int)$owner['id'],'auth_sha256'=>hash('sha256',$owner['auth_version'])];
}

function provider_force_ssl_requests_static(string $linkId):array {
    provider_force_ssl_requests_guard();provider_force_ssl_requests_id($linkId);
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE id=?',[$linkId]);
    if(!$link)throw new ForceSslRequestUnavailable('This hosting assignment is unavailable.');
    $profile=provider_settings_load($link['profile_id']);
    if((int)$profile['enabled']!==1||$profile['credentials']===''||(int)$profile['revision']!==(int)$link['profile_revision']||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$link['client_id']]))throw new ForceSslRequestUnavailable('This hosting account or assignment needs owner confirmation.');
    $a=provider_force_ssl_assignment($linkId);$owner=one('SELECT * FROM '.table('users').' WHERE id=?',[$a['event']['actor_id']]);$owner=$owner?staff_profile($owner):null;
    if(!$owner||$owner['role']!=='owner'||(int)$owner['enabled']!==1)throw new ForceSslRequestUnavailable('The studio must confirm this hosting assignment again.');
    $resource=['binding'=>['profile_id'=>$link['profile_id'],'profile_revision'=>(int)$link['profile_revision'],'key_fingerprint'=>$link['key_fingerprint'],'credential_sha256'=>hash('sha256',$profile['credentials']),
        'package_id'=>(int)$link['package_id'],'client_id'=>(int)$link['client_id'],'link_id'=>$linkId,'link_sha256'=>$a['link_sha256'],'event_id'=>(int)$a['event']['id'],'event_sha256'=>$a['event_sha256'],'audit_id'=>(int)$a['audit']['id'],'audit_sha256'=>$a['audit_sha256']],
        'owner_id'=>(int)$owner['id'],'owner_auth_sha256'=>hash('sha256',$owner['auth_version'])];
    if(!provider_force_ssl_ready())throw new ForceSslRequestUnavailable('The studio must prepare HTTPS observations first.');
    $permission=provider_force_ssl_permission($linkId);
    if(!$permission||!$permission['document']['enabled'])throw new ForceSslRequestUnavailable('The studio must share HTTPS observations for this service first.');
    $sharingOwner=one('SELECT * FROM '.table('users').' WHERE id=?',[$permission['document']['actor_id']]);$sharingOwner=$sharingOwner?staff_profile($sharingOwner):null;if(!$sharingOwner||$sharingOwner['role']!=='owner'||(int)$sharingOwner['enabled']!==1)throw new ForceSslRequestUnavailable('The studio must confirm HTTPS observation sharing again.');
    foreach($resource['binding'] as $key=>$value)if($permission['binding']->data[$key]!==$value)throw new ForceSslRequestUnavailable('The shared HTTPS observation belongs to an earlier assignment.');
    return $resource+['sharing_owner_id'=>(int)$sharingOwner['id'],'sharing_owner_auth_sha256'=>hash('sha256',$sharingOwner['auth_version']),'sharing_id'=>$permission['document']['id'],'sharing_revision'=>$permission['document']['revision'],'sharing_sha256'=>$permission['sha256']];
}
/** Intake uses the explicitly shared historical anchor; fresh flag review is required only for owner dispatch. */
function provider_force_ssl_requests_resource(string $linkId):array {
    $resource=provider_force_ssl_requests_static($linkId);$permission=provider_force_ssl_permission($linkId);
    foreach(['source_id','source_seq','source_sha256'] as $key)$resource['binding'][$key]=$permission['binding']->data[$key];
    provider_force_ssl_requests_retained($resource);return $resource;
}

function provider_force_ssl_requests_retained(array $resource):array {
    provider_force_ssl_requests_resource_document($resource);$b=new \CajunWebPro\Foundation\TwentyIPackageForceSsl\PackageForceSslBinding(provider_force_ssl_requests_binding($resource['binding']));
    if(!provider_force_ssl_ready())throw new RuntimeException('The original HTTPS sharing history needs recovery.');
    provider_force_ssl_retained_binding($b);
    $sharing=one('SELECT * FROM '.table('provider_force_ssl_permissions').' WHERE id=?',[$resource['sharing_id']]);
    if(!$sharing||$sharing['link_id']!==$b->data['link_id']||(int)$sharing['client_id']!==$b->data['client_id']||(int)$sharing['revision']!==$resource['sharing_revision']||(int)$sharing['actor_id']!==$resource['sharing_owner_id']||!hash_equals($sharing['document_sha256'],$resource['sharing_sha256'])||!hash_equals(hash('sha256',$sharing['document_json']),$resource['sharing_sha256']))throw new RuntimeException('The original HTTPS sharing permission needs recovery.');
    try{$d=json_decode($sharing['document_json'],true,12,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new RuntimeException('The original HTTPS sharing permission needs recovery.',0,$e);}
    if(!is_array($d)||($d['enabled']??null)!==true||($d['id']??null)!==$resource['sharing_id']||($d['revision']??null)!==$resource['sharing_revision']||provider_force_ssl_requests_binding($d['binding']??[])!==$b->data)throw new RuntimeException('The original HTTPS sharing scope needs recovery.');
    $anchor=provider_force_ssl_source($d['anchor_id']);if(!$anchor)throw new RuntimeException('The original shared HTTPS flag needs recovery.');$observed=provider_force_ssl_observation($anchor);
    if(!hash_equals($observed['binding']->sha256,$b->sha256)||!hash_equals($observed['proof'],$d['anchor_proof']))throw new RuntimeException('The original shared HTTPS flag changed.');
    $source=provider_force_ssl_package_source($b->data['source_id'],$b->data['package_id']);$package=service_observations_source($source['row'],$b->data['package_id'])['package'];
    if(!$package||!$package['enabled'])throw new RuntimeException('The original assigned hosting package needs recovery.');return $package;
}

function provider_force_ssl_requests_event_sha(array $event):string {return hash('sha256',provider_force_ssl_requests_json($event));}
function provider_force_ssl_requests_context(array $resource,array $actor):string {return provider_operations_scope('force-ssl-requests.form',['resource'=>$resource,'actor'=>$actor]);}

function provider_force_ssl_requests_graph():?array {$events=provider_force_ssl_requests_events();return $events===null?null:provider_force_ssl_requests_project($events);}
function provider_force_ssl_requests_state():array {
    provider_force_ssl_requests_guard();$state=provider_force_ssl_requests_graph();
    if($state===null)throw new RuntimeException('The studio has not enabled HTTPS requests yet.');
    if(!provider_force_ssl_controls_ready())throw new RuntimeException('The studio must prepare Force SSL controls first.');provider_force_ssl_requests_owner_history();return $state;
}
/** Historical cross-link only. This never re-enters owner readiness. */
function provider_force_ssl_requests_validate_origin(array $owner):void {
    $d=$owner['document'];$origin=$d['client_origin']??null;
    if($d['version']!==2||!is_array($origin)||array_keys($origin)!==['id','request_sha256']||!is_string($origin['id'])||!is_string($origin['request_sha256']))throw new RuntimeException('HTTPS client origin needs recovery.');
    $state=provider_force_ssl_requests_graph();$r=$state['requests'][$origin['id']]??null;
    if(!$r||$r['state']!=='dispatched'||!hash_equals(provider_force_ssl_requests_event_sha($r['request']),$origin['request_sha256']))throw new RuntimeException('HTTPS client origin needs recovery.');
    $event=$r['events'][array_key_last($r['events'])];$q=$r['request'];$p=$q['payload'];
    if($event['kind']!=='dispatch'||$event['id']!==$owner['row']['id']||$event['payload']['owner_request_id']!==$owner['row']['id']||!hash_equals($event['payload']['owner_request_sha256'],$owner['row']['document_sha256'])
        ||$event['actor']['kind']!=='owner'||$event['actor']['id']!==(int)$owner['row']['actor_id']||!hash_equals($event['actor']['auth_sha256'],$d['actor_auth'])||$event['recorded_epoch']<$d['approved_epoch']||$q['recorded_epoch']>$d['approved_epoch']
        ||$p['desired']!==$d['params']['value'])throw new RuntimeException('HTTPS dispatch association needs recovery.');
    provider_force_ssl_requests_retained($p['resource']);
    foreach($p['resource']['binding'] as $key=>$value)if(!str_starts_with($key,'source_')&&$d['binding'][$key]!==$value)throw new RuntimeException('HTTPS client assignment association needs recovery.');
}
function provider_force_ssl_requests_owner_history():void {
    $state=provider_force_ssl_requests_graph();if($state===null)return;
    foreach($state['requests'] as $r){
        provider_force_ssl_requests_retained($r['request']['payload']['resource']);
        if($r['state']==='dispatched'){$event=$r['events'][array_key_last($r['events'])];provider_force_ssl_requests_validate_origin(provider_force_ssl_controls_request($event['payload']['owner_request_id']));}
    }
}

function provider_force_ssl_requests_current(array $r):array {
    $q=$r['request'];$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1',[$q['client_id']]);
    if(!$client||!hash_equals($q['actor']['auth_sha256'],hash('sha256',$client['auth_version'])))throw new ForceSslRequestUnavailable('The client account changed. Ask for a new HTTPS request.');
    $original=$q['payload']['resource'];provider_force_ssl_requests_retained($original);$current=provider_force_ssl_requests_static($original['binding']['link_id']);$static=$original;
    foreach(['source_id','source_seq','source_sha256'] as $key)unset($static['binding'][$key]);
    if(provider_force_ssl_requests_json($static)!==provider_force_ssl_requests_json($current))throw new ForceSslRequestUnavailable('The request belongs to an earlier hosting assignment.');return $original;
}
function provider_force_ssl_requests_origin_current(array $owner):void {
    provider_force_ssl_requests_guard();provider_force_ssl_requests_actor(null);provider_force_ssl_requests_validate_origin($owner);$state=provider_force_ssl_requests_graph();$r=$state['requests'][$owner['document']['client_origin']['id']];
    if(db()->inTransaction())provider_force_ssl_requests_current($r);else staff_transaction(fn()=>provider_force_ssl_requests_current($r));
}
function provider_force_ssl_requests_pending(array $state,array $resource):bool {
    $b=$resource['binding'];foreach($state['requests'] as $r){$old=$r['request']['payload']['resource']['binding'];if($r['state']==='pending'&&$old['profile_id']===$b['profile_id']&&$old['package_id']===$b['package_id'])return true;}
    return provider_force_ssl_controls_pending($b['profile_id'],$b['package_id'])!==null;
}

function provider_force_ssl_requests_create(string $id,int $clientId,string $linkId,string $reviewSha,bool $desired,bool $confirmed):array {
    return staff_locked(fn()=>staff_transaction(function()use($id,$clientId,$linkId,$reviewSha,$desired,$confirmed):array{
        $actor=provider_force_ssl_requests_actor($clientId);$state=provider_force_ssl_requests_state();provider_force_ssl_requests_id($id);provider_force_ssl_requests_id($linkId);
        if(!$confirmed)throw new InvalidArgumentException('Confirm that the studio must review this request.');
        $old=$state['requests'][$id]??null;if($old&&$old['request']['client_id']!==$clientId)throw new RuntimeException('This request identifier was already used.');$resource=$old?provider_force_ssl_requests_current($old):provider_force_ssl_requests_resource($linkId);
        if($resource['binding']['client_id']!==$clientId||$resource['binding']['link_id']!==$linkId||!hash_equals(provider_force_ssl_requests_context($resource,$actor),$reviewSha))throw new RuntimeException('The hosting assignment or shared HTTPS observation changed. Reload the request form.');
        $payload=['resource'=>$resource,'desired'=>$desired,'confirmed'=>true];
        if($old){if($old['request']['client_id']!==$clientId||provider_force_ssl_requests_json($old['request']['actor'])!==provider_force_ssl_requests_json($actor)||provider_force_ssl_requests_json($old['request']['payload'])!==provider_force_ssl_requests_json($payload))throw new RuntimeException('This request identifier was already used.');return provider_force_ssl_requests_item($old,$clientId);}
        if(provider_force_ssl_requests_pending($state,$resource))throw new RuntimeException('A request for this hosting service still needs studio review.');
        $recent=0;foreach($state['requests'] as $r)if($r['request']['client_id']===$clientId&&$r['request']['recorded_epoch']>=time()-86400)$recent++;
        if($recent>=10)throw new RuntimeException('The daily HTTPS request limit has been reached. Please try later.');
        provider_force_ssl_requests_append($id,$id,$clientId,'request',$actor,$payload);return provider_force_ssl_requests_item(provider_force_ssl_requests_state()['requests'][$id],$clientId);
    }));
}
function provider_force_ssl_requests_change(string $id,string $expectedEvent,string $eventId,string $command,string $note,?int $clientId=null,#[SensitiveParameter] string $password=''):array {
    return staff_locked(fn()=>staff_transaction(function()use($id,$expectedEvent,$eventId,$command,$note,$clientId,$password):array{
        $actor=provider_force_ssl_requests_actor($clientId,$clientId===null?$password:null);$state=provider_force_ssl_requests_state();foreach([$id,$expectedEvent,$eventId] as $value)provider_force_ssl_requests_id($value);
        if($command!==($clientId===null?'decline':'cancel'))throw new RuntimeException('This HTTPS request action is unavailable.');
        $r=$state['requests'][$id]??null;if(!$r||($clientId!==null&&$r['request']['client_id']!==$clientId))throw new RuntimeException('Force SSL request unavailable.');$note=provider_force_ssl_requests_note($note);$payload=['previous_event'=>$expectedEvent,'note'=>$note];
        foreach($r['events'] as $event)if($event['id']===$eventId){if($event['kind']!==$command||provider_force_ssl_requests_json($event['actor'])!==provider_force_ssl_requests_json($actor)||provider_force_ssl_requests_json($event['payload'])!==provider_force_ssl_requests_json($payload))throw new RuntimeException('This review identifier was already used.');return provider_force_ssl_requests_item($r,$clientId);}
        if($r['state']!=='pending'||$r['latest_event']!==$expectedEvent)throw new RuntimeException('This HTTPS request changed. Reload its review.');
        if($clientId!==null)provider_force_ssl_requests_current($r);
        provider_force_ssl_requests_append($eventId,$id,$r['request']['client_id'],$command,$actor,$payload);return provider_force_ssl_requests_item(provider_force_ssl_requests_state()['requests'][$id],$clientId);
    }));
}
/** Internal admission check before the reciprocal dispatch event exists. */
function provider_force_ssl_requests_admission_origin(array $origin,string $linkId,bool $desired):void {
    provider_force_ssl_requests_guard(true);
    if(array_keys($origin)!==['id','request_sha256']||!is_string($origin['id'])||!is_string($origin['request_sha256']))throw new RuntimeException('Invalid client HTTPS origin.');
    $state=provider_force_ssl_requests_state();$r=$state['requests'][$origin['id']]??null;
    if(!$r||$r['state']!=='pending'||!hash_equals(provider_force_ssl_requests_event_sha($r['request']),$origin['request_sha256'])||$r['request']['payload']['resource']['binding']['link_id']!==$linkId||$r['request']['payload']['desired']!==$desired)throw new RuntimeException('The original client HTTPS request changed.');provider_force_ssl_requests_current($r);
}

function provider_force_ssl_requests_approval(array $r):?array {
    $resource=provider_force_ssl_requests_current($r);$link=$resource['binding']['link_id'];$latest=provider_force_ssl_latest($link);
    if(!$latest||$latest['state']!=='complete'||!service_observations_fresh($latest))return null;
    $anchor=provider_force_ssl_observation($latest);
    try{$binding=provider_force_ssl_binding($link);provider_force_ssl_controls_precondition($link,$anchor['id'],$binding->sha256);}catch(RuntimeException $e){
        if($e instanceof PDOException||!in_array($e->getMessage(),['Refresh hosting inventory for this exact assignment first.','Refresh hosting inventory after this assignment or reapproval.','Refresh and review the latest Force SSL flag before changing it.'],true))throw $e;
        return null;
    }
    if($anchor['value']===$r['request']['payload']['desired']||provider_force_ssl_controls_pending($binding->data['profile_id'],$binding->data['package_id']))return null;
    return ['request_id'=>bin2hex(random_bytes(16)),'anchor_id'=>$anchor['id'],'binding_sha256'=>$binding->sha256,'before_value'=>$anchor['value'],'desired'=>$r['request']['payload']['desired'],'observed_epoch'=>$anchor['observed_epoch']];
}

function provider_force_ssl_requests_approve(string $id,string $expectedEvent,string $requestSha,string $ownerRequestId,string $anchorId,string $bindingSha,bool $confirmed,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null):array {
    if(db()->inTransaction())throw new LogicException('HTTPS approval cannot run in a transaction.');
    return staff_locked(function()use($id,$expectedEvent,$requestSha,$ownerRequestId,$anchorId,$bindingSha,$confirmed,$password,$resolver,$perform):array{
        $fresh=staff_transaction(function()use($id,$expectedEvent,$requestSha,$ownerRequestId,$anchorId,$bindingSha,$confirmed,$password):bool{
            $actor=provider_force_ssl_requests_actor(null,$password);$state=provider_force_ssl_requests_state();foreach([$id,$expectedEvent,$ownerRequestId] as $value)provider_force_ssl_requests_id($value);
            if(!$confirmed)throw new InvalidArgumentException('Confirm this exact requested HTTPS change first.');$r=$state['requests'][$id]??null;
            if(!$r||!hash_equals(provider_force_ssl_requests_event_sha($r['request']),$requestSha))throw new RuntimeException('The original client request changed. Reload its review.');provider_force_ssl_requests_current($r);
            if($r['state']==='dispatched'){
                $event=$r['events'][array_key_last($r['events'])];$owner=provider_force_ssl_controls_request($event['payload']['owner_request_id']);
                if($event['id']!==$ownerRequestId||$event['payload']['previous_event']!==$expectedEvent||provider_force_ssl_requests_json($event['actor'])!==provider_force_ssl_requests_json($actor)||$owner['document']['anchor_id']!==$anchorId||!hash_equals($owner['binding']->sha256,$bindingSha))throw new RuntimeException('This approval identifier or reviewed request changed.');
                provider_force_ssl_controls_context($owner['binding']);if((int)$owner['row']['actor_id']!==$actor['id']||!hash_equals($owner['document']['actor_auth'],$actor['auth_sha256']))throw new RuntimeException('The original approval owner changed.');return false;
            }
            if($r['state']!=='pending'||$r['latest_event']!==$expectedEvent)throw new RuntimeException('This request was already reviewed or withdrawn.');$p=$r['request']['payload'];
            if(!provider_force_ssl_controls_admit_locked($ownerRequestId,$p['resource']['binding']['link_id'],$anchorId,$bindingSha,$p['desired'],true,$password,['id'=>$id,'request_sha256'=>$requestSha]))throw new RuntimeException('This approval identifier was already used.');
            $owner=one('SELECT document_sha256 FROM '.table('provider_force_ssl_changes').' WHERE id=?',[$ownerRequestId]);
            provider_force_ssl_requests_append($ownerRequestId,$id,$r['request']['client_id'],'dispatch',$actor,['previous_event'=>$expectedEvent,'owner_request_id'=>$ownerRequestId,'owner_request_sha256'=>$owner['document_sha256']]);
            provider_force_ssl_requests_state();return true;
        });
        if($fresh)provider_force_ssl_controls_send_locked($ownerRequestId,$resolver,$perform);
        return staff_transaction(fn()=>provider_force_ssl_requests_item(provider_force_ssl_requests_state()['requests'][$id],null));
    });
}
/** Closed client projection: receipt status only, never private paths, grants or credentials. */
function provider_force_ssl_requests_item(array $r,?int $clientId):array {
    $q=$r['request'];if($clientId!==null&&$q['client_id']!==$clientId)throw new RuntimeException('Force SSL request unavailable.');
    $client=null;if($clientId===null){$row=one('SELECT id,name FROM '.table('studio_clients').' WHERE id=?',[$q['client_id']]);if(!$row||(int)$row['id']!==$q['client_id']||!is_string($row['name']))throw new RuntimeException('The original request client record needs recovery.');$client=['id'=>$q['client_id'],'name'=>$row['name']];}
    $current=true;try{provider_force_ssl_requests_current($r);}catch(ForceSslRequestUnavailable){$current=false;}
    $state=$r['state'];$lastCheck=null;$ownerId=null;$dispatch=null;$approval=null;$unavailable='';$note='';
    foreach($r['events'] as $event)if($event['kind']==='decline')$note=$event['payload']['note'];
    if($state==='dispatched'){
        $event=$r['events'][array_key_last($r['events'])];$ownerId=$event['payload']['owner_request_id'];$owner=provider_force_ssl_controls_request($ownerId);$terminal=provider_force_ssl_controls_terminal($owner);
        $state=$terminal===null?'interrupted':($terminal['row']['disposition']==='not_sent'?'not_sent':'unknown');
        $check=one('SELECT c.* FROM '.table('provider_force_ssl_change_checks').' c JOIN '.table('provider_refreshes').' f ON f.id=c.receipt_id WHERE c.request_id=? ORDER BY f.seq DESC LIMIT 1',[$ownerId]);
        if($check){$checked=provider_force_ssl_controls_check_document($check,$owner);$lastCheck=['disposition'=>$checked['disposition'],'value'=>$checked['value'],'recorded_epoch'=>$checked['recorded_epoch']];if($checked['disposition']==='observed')$state='observed';}
        if($clientId===null){$dispatch=provider_force_ssl_controls_summary($ownerId);$dispatch['can_verify']=false;if($current&&$terminal&&$terminal['row']['disposition']==='attempted'&&$state!=='observed'){try{provider_force_ssl_controls_finished_ledger($owner,time());$dispatch['can_verify']=true;}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;}}if(!$current){$dispatch['can_verify']=false;$dispatch['recovery_ledger_sha256']=null;}}
    }elseif($state==='pending'&&$clientId===null&&$current){try{$approval=provider_force_ssl_requests_approval($r);}catch(ForceSslRequestUnavailable){$current=false;}if($approval===null&&$unavailable==='')$unavailable='Refresh the HTTPS flag in Website settings. Approval is unavailable when the requested flag is already saved or an earlier change is unresolved.';}
    return ['id'=>$q['id'],'client'=>$client,'package_id'=>$q['payload']['resource']['binding']['package_id'],'link_id'=>$q['payload']['resource']['binding']['link_id'],'package_name'=>provider_force_ssl_requests_retained($q['payload']['resource'])['name'],'desired'=>$q['payload']['desired'],'created_epoch'=>$q['recorded_epoch'],'state'=>$state,'latest_event'=>$r['latest_event'],'request_sha256'=>provider_force_ssl_requests_event_sha($q),'current_assignment'=>$current,'can_cancel'=>$clientId!==null&&$current&&$r['state']==='pending','can_decline'=>$clientId===null&&$r['state']==='pending','note'=>$note,'owner_request_id'=>$clientId===null?$ownerId:null,'last_check'=>$lastCheck,'approval'=>$approval,'approval_unavailable'=>$clientId===null?$unavailable:'','dispatch'=>$dispatch];
}
function provider_force_ssl_requests_screen(?int $clientId=null,int $page=1,?string $detailId=null):array {
    return staff_locked(fn()=>staff_transaction(function()use($clientId,$page,$detailId):array{
        $actor=provider_force_ssl_requests_actor($clientId);$page=max(1,min(500,$page));$out=['ready'=>false,'options'=>[],'items'=>[],'page'=>$page,'has_more'=>false];
        if(!provider_force_ssl_requests_ready())return $out;$state=provider_force_ssl_requests_state();$out['ready']=true;
        if($detailId!==null){provider_force_ssl_requests_id($detailId);$r=$state['requests'][$detailId]??null;if(!$r||($clientId!==null&&$r['request']['client_id']!==$clientId))throw new RuntimeException('Force SSL request unavailable.');$out['items'][]=provider_force_ssl_requests_item($r,$clientId);return $out;}
        if($clientId!==null)foreach(all('SELECT id FROM '.table('provider_package_links').' WHERE client_id=? ORDER BY id',[$clientId]) as $row){
            try{$resource=provider_force_ssl_requests_resource($row['id']);if($resource['binding']['client_id']!==$clientId)continue;$package=provider_force_ssl_requests_retained($resource);
                $permission=provider_force_ssl_permission($row['id']);$out['options'][]=['link_id'=>$row['id'],'package_id'=>$resource['binding']['package_id'],'package_name'=>$package['name'],'review_sha256'=>provider_force_ssl_requests_context($resource,$actor),'shared_value'=>$permission['anchor']['value'],'observed_epoch'=>$permission['anchor']['observed_epoch']];
            }catch(ForceSslRequestUnavailable){}
        }
        $items=array_reverse(array_values($state['requests']));if($clientId!==null)$items=array_values(array_filter($items,static fn($r)=>$r['request']['client_id']===$clientId));$offset=($page-1)*20;$out['has_more']=count($items)>$offset+20;
        foreach(array_slice($items,$offset,20) as $r)$out['items'][]=provider_force_ssl_requests_item($r,$clientId);return $out;
    }));
}
function provider_force_ssl_requests_dispatch(string $id,string $ownerRequestId,#[SensitiveParameter] string $password):array {
    provider_force_ssl_requests_actor(null,$password);$state=provider_force_ssl_requests_state();provider_force_ssl_requests_id($id);provider_force_ssl_requests_id($ownerRequestId);$r=$state['requests'][$id]??null;
    if(!$r||$r['state']!=='dispatched'||$r['latest_event']!==$ownerRequestId)throw new RuntimeException('This owner approval does not belong to the client request.');provider_force_ssl_requests_current($r);
    $owner=provider_force_ssl_controls_request($ownerRequestId);provider_force_ssl_requests_validate_origin($owner);return $owner;
}
/** Existing inventory recorder and quotas, with original client consent at each read boundary. */
function provider_force_ssl_requests_refresh(string $ownerRequestId,\CajunWebPro\Foundation\TwentyIPackageForceSsl\PackageForceSslBinding $binding,#[SensitiveParameter] string $password,?callable $resolver,?callable $transport):array {
    $authorize=static function()use($ownerRequestId,$binding):void{
        provider_force_ssl_authority($binding);$owner=provider_force_ssl_controls_request($ownerRequestId);provider_force_ssl_requests_origin_current($owner);
    };
    return provider_inventory_refresh_attempt($binding->data['profile_id'],$binding->data['profile_revision'],$password,$resolver,$transport,operation:\CajunWebPro\Foundation\TwentyIPackageForceSsl\PackageForceSsl::OPERATION,scopeAuthority:$authorize,forceSslContext:$binding);
}

function provider_force_ssl_requests_verify(string $id,string $ownerRequestId,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    if(db()->inTransaction())throw new LogicException('HTTPS verification cannot inherit a transaction.');
    staff_locked(fn()=>staff_transaction(fn()=>provider_force_ssl_requests_dispatch($id,$ownerRequestId,$password)));
    provider_force_ssl_controls_verify($ownerRequestId,$password,$resolver,$transport);return provider_force_ssl_requests_screen(null,1,$id)['items'][0];
}
function provider_force_ssl_requests_recover(string $id,string $ownerRequestId,string $ledgerSha,#[SensitiveParameter] string $password):array {
    if(db()->inTransaction())throw new LogicException('HTTPS recovery cannot inherit a transaction.');
    return staff_locked(function()use($id,$ownerRequestId,$ledgerSha,$password):array{
        staff_transaction(fn()=>provider_force_ssl_requests_dispatch($id,$ownerRequestId,$password));provider_force_ssl_controls_recover_finished($ownerRequestId,$ledgerSha,$password);
        return staff_transaction(fn()=>provider_force_ssl_requests_item(provider_force_ssl_requests_state()['requests'][$id],null));
    });
}
