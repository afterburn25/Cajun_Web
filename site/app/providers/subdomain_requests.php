<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/subdomain_add.php';
require_once CWP_CODE_ROOT.'/app/providers/subdomain_requests_storage.php';

/** Eligibility alone may be hidden in a screen. Corrupt history and SQL errors must propagate. */
class SubdomainRequestUnavailable extends RuntimeException {}

function provider_subdomain_requests_actor(?int $clientId,#[SensitiveParameter] ?string $password=null):array {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Sign in directly to manage subdomain requests.');
    if(array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION))throw new RuntimeException('Sign in to your primary client account.');
    if($clientId!==null){
        $client=studio_client();
        if(!$client||$clientId<1||(int)$client['id']!==$clientId||(int)($_SESSION['client_id']??0)!==$clientId||!hash_equals($client['auth_version'],(string)($_SESSION['client_auth']??'')))throw new RuntimeException('Sign in to your primary client account.');
        return ['kind'=>'client','id'=>$clientId,'auth_sha256'=>hash('sha256',$client['auth_version'])];
    }
    $owner=provider_subdomain_add_actor($password);return ['kind'=>'owner','id'=>(int)$owner['id'],'auth_sha256'=>hash('sha256',$owner['auth_version'])];
}
/** Static assignment authority. No key is opened and no provider request is made. */
function provider_subdomain_requests_static(string $linkId):array {
    provider_subdomain_requests_guard();provider_subdomain_requests_id($linkId);
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE id=?',[$linkId]);
    if(!$link)throw new SubdomainRequestUnavailable('This hosting assignment is unavailable.');
    $profile=provider_settings_load($link['profile_id']);
    if((int)$profile['enabled']!==1||$profile['credentials']===''||(int)$profile['revision']!==(int)$link['profile_revision']||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$link['client_id']]))throw new SubdomainRequestUnavailable('This hosting account or assignment needs owner confirmation.');
    $a=provider_document_roots_assignment($linkId);$owner=one('SELECT * FROM '.table('users').' WHERE id=?',[$a['event']['actor_id']]);$owner=$owner?staff_profile($owner):null;
    if(!$owner||$owner['role']!=='owner'||(int)$owner['enabled']!==1)throw new SubdomainRequestUnavailable('The studio must confirm this hosting assignment again.');
    return ['binding'=>['profile_id'=>$link['profile_id'],'profile_revision'=>(int)$link['profile_revision'],'key_fingerprint'=>$link['key_fingerprint'],'credential_sha256'=>hash('sha256',$profile['credentials']),
        'package_id'=>(int)$link['package_id'],'client_id'=>(int)$link['client_id'],'link_id'=>$linkId,'link_sha256'=>$a['link_sha256'],'event_id'=>(int)$a['event']['id'],'event_sha256'=>$a['event_sha256'],'audit_id'=>(int)$a['audit']['id'],'audit_sha256'=>$a['audit_sha256']],
        'owner_id'=>(int)$owner['id'],'owner_auth_sha256'=>hash('sha256',$owner['auth_version'])];
}
/** Latest complete source only, without an age cutoff. Failed or unfinished latest reads mask older names. */
function provider_subdomain_requests_resource(string $linkId):array {
    $resource=provider_subdomain_requests_static($linkId);$p=$resource['binding'];provider_document_roots_common_history($p['profile_id']);
    $latest=service_observations_reads($p['profile_id'])[0]??null;
    if(!$latest)throw new SubdomainRequestUnavailable('The studio must save a hosting inventory before accepting requests.');
    $source=service_observations_source($latest,$p['package_id']);
    if($source['state']!=='complete'||$source['package']===null||!$source['package']['enabled']||(int)$latest['profile_revision']!==$p['profile_revision']||$latest['key_fingerprint']!==$p['key_fingerprint'])throw new SubdomainRequestUnavailable('The latest hosting inventory is not available for new requests.');
    $retained=provider_document_roots_package_source($latest['id'],$p['package_id']);
    if((int)$retained['start']['id']<=$p['audit_id'])throw new SubdomainRequestUnavailable('The studio must save a hosting inventory after assigning this service.');
    $resource['binding']+=['source_id'=>$latest['id'],'source_seq'=>(int)$latest['seq'],'source_sha256'=>$retained['sha256']];
    provider_subdomain_requests_resource_document($resource);return $resource;
}
function provider_subdomain_requests_retained(array $resource):array {
    provider_subdomain_requests_resource_document($resource);$binding=new \CajunWebPro\Foundation\TwentyIDocumentRoots\DocumentRootsBinding(new \CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpVersionBinding($resource['binding']));
    provider_document_roots_retained_binding($binding);return provider_subdomain_add_package($binding);
}
function provider_subdomain_requests_event_sha(array $event):string {return hash('sha256',provider_subdomain_requests_json($event));}
function provider_subdomain_requests_context(array $resource,array $actor,string $parent):string {return provider_operations_scope('subdomain-requests.form',['resource'=>$resource,'actor'=>$actor,'parent_domain'=>$parent]);}
function provider_subdomain_requests_graph():?array {$events=provider_subdomain_requests_events();return $events===null?null:provider_subdomain_requests_project($events);}
function provider_subdomain_requests_state():array {
    provider_subdomain_requests_guard();$state=provider_subdomain_requests_graph();
    if($state===null)throw new RuntimeException('The studio has not enabled subdomain requests yet.');
    if(!provider_subdomain_add_ready())throw new RuntimeException('The studio must prepare subdomain additions first.');return $state;
}
/** Historical cross-link only. This never re-enters owner readiness. */
function provider_subdomain_requests_validate_origin(array $owner):void {
    $d=$owner['document'];$origin=$d['client_origin']??null;
    if($d['version']!==2||!is_array($origin)||array_keys($origin)!==['id','request_sha256']||!is_string($origin['id'])||!is_string($origin['request_sha256']))throw new RuntimeException('Subdomain client origin needs recovery.');
    $state=provider_subdomain_requests_graph();$r=$state['requests'][$origin['id']]??null;
    if(!$r||$r['state']!=='dispatched'||!hash_equals(provider_subdomain_requests_event_sha($r['request']),$origin['request_sha256']))throw new RuntimeException('Subdomain client origin needs recovery.');
    $event=$r['events'][array_key_last($r['events'])];$q=$r['request'];$p=$q['payload'];
    if($event['kind']!=='dispatch'||$event['id']!==$owner['row']['id']||$event['payload']['owner_request_id']!==$owner['row']['id']||!hash_equals($event['payload']['owner_request_sha256'],$owner['row']['document_sha256'])
        ||$event['actor']['kind']!=='owner'||$event['actor']['id']!==(int)$owner['row']['actor_id']||!hash_equals($event['actor']['auth_sha256'],$d['actor_auth'])||$event['recorded_epoch']<$d['approved_epoch']||$q['recorded_epoch']>$d['approved_epoch']
        ||$p['parent_domain']!==$d['params']['parentDomain']||$p['subdomain']!==$d['params']['subdomain'])throw new RuntimeException('Subdomain dispatch association needs recovery.');
    provider_subdomain_requests_retained($p['resource']);
    foreach($p['resource']['binding'] as $key=>$value)if(!str_starts_with($key,'source_')&&$d['binding']['package'][$key]!==$value)throw new RuntimeException('Subdomain client assignment association needs recovery.');
}
function provider_subdomain_requests_owner_history():void {
    $state=provider_subdomain_requests_graph();if($state===null)return;
    foreach($state['requests'] as $r){
        $package=provider_subdomain_requests_retained($r['request']['payload']['resource']);
        if(!$package['enabled']||!in_array($r['request']['payload']['parent_domain'],$package['names'],true)||in_array($r['request']['payload']['subdomain'],$package['names'],true))throw new RuntimeException('The original requested parent observation needs recovery.');
        if($r['state']==='dispatched'){$event=$r['events'][array_key_last($r['events'])];provider_subdomain_requests_validate_origin(provider_subdomain_add_request($event['payload']['owner_request_id']));}
    }
}
/** Ordinary later inventory does not change local consent; exact original source remains authenticated. */
function provider_subdomain_requests_current(array $r):array {
    $q=$r['request'];$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1',[$q['client_id']]);
    if(!$client||!hash_equals($q['actor']['auth_sha256'],hash('sha256',$client['auth_version'])))throw new SubdomainRequestUnavailable('The client account changed. Ask for a new subdomain request.');
    $original=$q['payload']['resource'];provider_subdomain_requests_retained($original);$current=provider_subdomain_requests_static($original['binding']['link_id']);$static=$original;
    foreach(['source_id','source_seq','source_sha256'] as $key)unset($static['binding'][$key]);
    if(provider_subdomain_requests_json($static)!==provider_subdomain_requests_json($current))throw new SubdomainRequestUnavailable('The request belongs to an earlier hosting assignment.');return $original;
}
function provider_subdomain_requests_origin_current(array $owner):void {
    provider_subdomain_requests_guard();provider_subdomain_requests_validate_origin($owner);$state=provider_subdomain_requests_graph();$r=$state['requests'][$owner['document']['client_origin']['id']];
    if(db()->inTransaction())provider_subdomain_requests_current($r);else staff_transaction(fn()=>provider_subdomain_requests_current($r));
}
function provider_subdomain_requests_pending(array $state,array $resource,string $child):bool {
    $b=$resource['binding'];foreach($state['requests'] as $r){$old=$r['request']['payload']['resource']['binding'];if($r['state']==='pending'&&$old['profile_id']===$b['profile_id']&&$old['package_id']===$b['package_id']&&$r['request']['payload']['subdomain']===$child)return true;}
    return provider_subdomain_add_pending($b['profile_id'],$b['package_id'],$child)!==null;
}
function provider_subdomain_requests_create(string $id,int $clientId,string $linkId,string $reviewSha,string $parentDomain,string $subdomain,bool $confirmed):array {
    return staff_locked(fn()=>staff_transaction(function()use($id,$clientId,$linkId,$reviewSha,$parentDomain,$subdomain,$confirmed):array{
        $actor=provider_subdomain_requests_actor($clientId);$state=provider_subdomain_requests_state();provider_subdomain_requests_id($id);provider_subdomain_requests_id($linkId);
        if(!$confirmed)throw new InvalidArgumentException('Confirm that the studio must review this request.');
        $old=$state['requests'][$id]??null;if($old&&$old['request']['client_id']!==$clientId)throw new RuntimeException('This request identifier was already used.');$resource=$old?provider_subdomain_requests_current($old):provider_subdomain_requests_resource($linkId);
        if($resource['binding']['client_id']!==$clientId||$resource['binding']['link_id']!==$linkId||!hash_equals(provider_subdomain_requests_context($resource,$actor,$parentDomain),$reviewSha))throw new RuntimeException('The hosting assignment or reviewed parent changed. Reload the request form.');
        $params=\CajunWebPro\Foundation\TwentyI\Operations\SubdomainAdd::params(['packageId'=>$resource['binding']['package_id'],'parentDomain'=>$parentDomain,'subdomain'=>$subdomain]);$package=provider_subdomain_requests_retained($resource);
        if(!in_array($parentDomain,$package['names'],true)||in_array($subdomain,$package['names'],true))throw new InvalidArgumentException('Select an attached parent and a new child hostname.');
        $payload=['resource'=>$resource,'parent_domain'=>$params['parentDomain'],'subdomain'=>$params['subdomain'],'confirmed'=>true];
        if($old){if($old['request']['client_id']!==$clientId||provider_subdomain_requests_json($old['request']['actor'])!==provider_subdomain_requests_json($actor)||provider_subdomain_requests_json($old['request']['payload'])!==provider_subdomain_requests_json($payload))throw new RuntimeException('This request identifier was already used.');return provider_subdomain_requests_item($old,$clientId);}
        if(provider_subdomain_requests_pending($state,$resource,$subdomain))throw new RuntimeException('A request for this hostname still needs studio review.');
        $recent=0;foreach($state['requests'] as $r)if($r['request']['client_id']===$clientId&&$r['request']['recorded_epoch']>=time()-86400)$recent++;
        if($recent>=10)throw new RuntimeException('The daily subdomain request limit has been reached. Please try later.');
        provider_subdomain_requests_append($id,$id,$clientId,'request',$actor,$payload);return provider_subdomain_requests_item(provider_subdomain_requests_state()['requests'][$id],$clientId);
    }));
}
function provider_subdomain_requests_change(string $id,string $expectedEvent,string $eventId,string $command,string $note,?int $clientId=null,#[SensitiveParameter] string $password=''):array {
    return staff_locked(fn()=>staff_transaction(function()use($id,$expectedEvent,$eventId,$command,$note,$clientId,$password):array{
        $actor=provider_subdomain_requests_actor($clientId,$clientId===null?$password:null);$state=provider_subdomain_requests_state();foreach([$id,$expectedEvent,$eventId] as $value)provider_subdomain_requests_id($value);
        if($command!==($clientId===null?'decline':'cancel'))throw new RuntimeException('This subdomain request action is unavailable.');
        $r=$state['requests'][$id]??null;if(!$r||($clientId!==null&&$r['request']['client_id']!==$clientId))throw new RuntimeException('Subdomain request unavailable.');$note=provider_subdomain_requests_note($note);$payload=['previous_event'=>$expectedEvent,'note'=>$note];
        foreach($r['events'] as $event)if($event['id']===$eventId){if($event['kind']!==$command||provider_subdomain_requests_json($event['actor'])!==provider_subdomain_requests_json($actor)||provider_subdomain_requests_json($event['payload'])!==provider_subdomain_requests_json($payload))throw new RuntimeException('This review identifier was already used.');return provider_subdomain_requests_item($r,$clientId);}
        if($r['state']!=='pending'||$r['latest_event']!==$expectedEvent)throw new RuntimeException('This subdomain request changed. Reload its review.');
        if($clientId!==null)provider_subdomain_requests_current($r);
        provider_subdomain_requests_append($eventId,$id,$r['request']['client_id'],$command,$actor,$payload);return provider_subdomain_requests_item(provider_subdomain_requests_state()['requests'][$id],$clientId);
    }));
}
/** Internal admission check before the reciprocal dispatch event exists. */
function provider_subdomain_requests_admission_origin(array $origin,string $linkId,string $parent,string $child):void {
    provider_subdomain_requests_guard(true);
    if(array_keys($origin)!==['id','request_sha256']||!is_string($origin['id'])||!is_string($origin['request_sha256']))throw new RuntimeException('Invalid client subdomain origin.');
    $state=provider_subdomain_requests_state();$r=$state['requests'][$origin['id']]??null;
    if(!$r||$r['state']!=='pending'||!hash_equals(provider_subdomain_requests_event_sha($r['request']),$origin['request_sha256'])||$r['request']['payload']['resource']['binding']['link_id']!==$linkId||$r['request']['payload']['parent_domain']!==$parent||$r['request']['payload']['subdomain']!==$child)throw new RuntimeException('The original client subdomain request changed.');provider_subdomain_requests_current($r);
}
function provider_subdomain_requests_approval(array $r):?array {
    $resource=provider_subdomain_requests_current($r);$form=provider_subdomain_add_review_locked($resource['binding']['link_id']);
    if(!$form['ready']||!in_array($r['request']['payload']['parent_domain'],$form['parent_domains'],true))return null;
    $proof=provider_subdomain_add_review_document($form['review_proof']);[$binding,$anchor]=provider_subdomain_add_precondition($form['link_id'],$proof['anchor_id'],$proof['binding_sha256']);
    try{provider_subdomain_add_target($binding,$anchor,$r['request']['payload']['parent_domain'],$r['request']['payload']['subdomain']);}catch(InvalidArgumentException){return null;}
    if(provider_subdomain_add_pending($form['profile_id'],$form['package_id'],$r['request']['payload']['subdomain']))return null;
    return ['request_id'=>$form['request_id'],'review_proof'=>$form['review_proof'],'expires_epoch'=>$form['expires_epoch'],'observed_epoch'=>$form['observed_epoch']];
}
function provider_subdomain_requests_approve(string $id,string $expectedEvent,string $requestSha,string $ownerRequestId,string $reviewProof,bool $confirmed,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null):array {
    if(db()->inTransaction())throw new LogicException('Subdomain approval cannot run in a transaction.');
    return staff_locked(function()use($id,$expectedEvent,$requestSha,$ownerRequestId,$reviewProof,$confirmed,$password,$resolver,$perform):array{
        $fresh=staff_transaction(function()use($id,$expectedEvent,$requestSha,$ownerRequestId,$reviewProof,$confirmed,$password):bool{
            $actor=provider_subdomain_requests_actor(null,$password);$state=provider_subdomain_requests_state();foreach([$id,$expectedEvent,$ownerRequestId] as $value)provider_subdomain_requests_id($value);
            if(!$confirmed)throw new InvalidArgumentException('Confirm this exact requested subdomain addition first.');$r=$state['requests'][$id]??null;
            if(!$r||!hash_equals(provider_subdomain_requests_event_sha($r['request']),$requestSha))throw new RuntimeException('The original client request changed. Reload its review.');provider_subdomain_requests_current($r);
            if($r['state']==='dispatched'){
                $event=$r['events'][array_key_last($r['events'])];$owner=provider_subdomain_add_request($event['payload']['owner_request_id']);
                if($event['id']!==$ownerRequestId||$event['payload']['previous_event']!==$expectedEvent||provider_subdomain_requests_json($event['actor'])!==provider_subdomain_requests_json($actor)||$owner['document']['review_proof']!==$reviewProof)throw new RuntimeException('This approval identifier or reviewed request changed.');
                provider_subdomain_add_context($owner['binding'],false);provider_subdomain_add_original_actor($owner,provider_subdomain_add_actor());return false;
            }
            if($r['state']!=='pending'||$r['latest_event']!==$expectedEvent)throw new RuntimeException('This request was already reviewed or withdrawn.');$p=$r['request']['payload'];
            if(!provider_subdomain_add_admit_locked($ownerRequestId,$p['resource']['binding']['link_id'],$p['parent_domain'],$p['subdomain'],$reviewProof,true,$password,['id'=>$id,'request_sha256'=>$requestSha]))throw new RuntimeException('This approval identifier was already used.');
            $owner=one('SELECT document_sha256 FROM '.table('provider_subdomain_add_requests').' WHERE id=?',[$ownerRequestId]);
            provider_subdomain_requests_append($ownerRequestId,$id,$r['request']['client_id'],'dispatch',$actor,['previous_event'=>$expectedEvent,'owner_request_id'=>$ownerRequestId,'owner_request_sha256'=>$owner['document_sha256']]);
            provider_subdomain_requests_state();return true;
        });
        if($fresh)provider_subdomain_add_send_locked($ownerRequestId,$resolver,$perform);
        return staff_transaction(fn()=>provider_subdomain_requests_item(provider_subdomain_requests_state()['requests'][$id],null));
    });
}
/** Closed client projection: receipt status only, never private paths, grants or credentials. */
function provider_subdomain_requests_item(array $r,?int $clientId):array {
    $q=$r['request'];if($clientId!==null&&$q['client_id']!==$clientId)throw new RuntimeException('Subdomain request unavailable.');
    $client=null;if($clientId===null){$row=one('SELECT id,name FROM '.table('studio_clients').' WHERE id=?',[$q['client_id']]);if(!$row||(int)$row['id']!==$q['client_id']||!is_string($row['name']))throw new RuntimeException('The original request client record needs recovery.');$client=['id'=>$q['client_id'],'name'=>$row['name']];}
    $current=true;try{provider_subdomain_requests_current($r);}catch(SubdomainRequestUnavailable){$current=false;}
    $state=$r['state'];$lastCheck=null;$ownerId=null;$dispatch=null;$approval=null;$note='';
    foreach($r['events'] as $event)if($event['kind']==='decline')$note=$event['payload']['note'];
    if($state==='dispatched'){
        $event=$r['events'][array_key_last($r['events'])];$ownerId=$event['payload']['owner_request_id'];$owner=provider_subdomain_add_request($ownerId);$terminal=provider_subdomain_add_terminal($owner);
        $state=$terminal===null?'interrupted':($terminal['row']['disposition']==='not_sent'?'not_sent':'unknown');
        $check=one('SELECT c.* FROM '.table('provider_subdomain_add_checks').' c JOIN '.table('provider_refreshes').' f ON f.id=c.receipt_id WHERE c.request_id=? ORDER BY f.seq DESC LIMIT 1',[$ownerId]);
        if($check){$checked=provider_subdomain_add_check_document($check,$owner);$lastCheck=['disposition'=>$checked['disposition'],'recorded_epoch'=>$checked['recorded_epoch']];if($checked['disposition']==='observed')$state='observed';}
        if($clientId===null){$dispatch=provider_subdomain_add_summary($ownerId);if(!$current){$dispatch['can_verify']=false;$dispatch['recovery_ledger_sha256']=null;}}
    }elseif($state==='pending'&&$clientId===null&&$current){$approval=provider_subdomain_requests_approval($r);}
    return ['id'=>$q['id'],'client'=>$client,'package_id'=>$q['payload']['resource']['binding']['package_id'],'parent_domain'=>$q['payload']['parent_domain'],'subdomain'=>$q['payload']['subdomain'],'created_epoch'=>$q['recorded_epoch'],'state'=>$state,'latest_event'=>$r['latest_event'],'request_sha256'=>provider_subdomain_requests_event_sha($q),'current_assignment'=>$current,'can_cancel'=>$clientId!==null&&$current&&$r['state']==='pending','can_decline'=>$clientId===null&&$r['state']==='pending','note'=>$note,'owner_request_id'=>$clientId===null?$ownerId:null,'last_check'=>$lastCheck,'approval'=>$approval,'dispatch'=>$dispatch];
}
function provider_subdomain_requests_screen(?int $clientId=null,int $page=1,?string $detailId=null):array {
    return staff_locked(fn()=>staff_transaction(function()use($clientId,$page,$detailId):array{
        $actor=provider_subdomain_requests_actor($clientId);$page=max(1,min(10000,$page));$out=['ready'=>false,'options'=>[],'items'=>[],'page'=>$page,'has_more'=>false];
        if(!provider_subdomain_requests_ready())return $out;$state=provider_subdomain_requests_state();$out['ready']=true;
        if($detailId!==null){provider_subdomain_requests_id($detailId);$r=$state['requests'][$detailId]??null;if(!$r||($clientId!==null&&$r['request']['client_id']!==$clientId))throw new RuntimeException('Subdomain request unavailable.');$out['items'][]=provider_subdomain_requests_item($r,$clientId);return $out;}
        if($clientId!==null)foreach(all('SELECT id FROM '.table('provider_package_links').' WHERE client_id=? ORDER BY id',[$clientId]) as $row){
            try{$resource=provider_subdomain_requests_resource($row['id']);if($resource['binding']['client_id']!==$clientId)continue;$package=provider_subdomain_requests_retained($resource);
                foreach($package['names'] as $parent)if(\CajunWebPro\Foundation\TwentyIDocumentRoots\DocumentRootsObservation::validName($parent))$out['options'][]=['link_id'=>$row['id'],'package_id'=>$resource['binding']['package_id'],'parent_domain'=>$parent,'review_sha256'=>provider_subdomain_requests_context($resource,$actor,$parent)];
            }catch(SubdomainRequestUnavailable){}
        }
        $items=array_reverse(array_values($state['requests']));if($clientId!==null)$items=array_values(array_filter($items,static fn($r)=>$r['request']['client_id']===$clientId));$offset=($page-1)*20;$out['has_more']=count($items)>$offset+20;
        foreach(array_slice($items,$offset,20) as $r)$out['items'][]=provider_subdomain_requests_item($r,$clientId);return $out;
    }));
}
function provider_subdomain_requests_dispatch(string $id,string $ownerRequestId,#[SensitiveParameter] string $password):array {
    provider_subdomain_requests_actor(null,$password);$state=provider_subdomain_requests_state();provider_subdomain_requests_id($id);provider_subdomain_requests_id($ownerRequestId);$r=$state['requests'][$id]??null;
    if(!$r||$r['state']!=='dispatched'||$r['latest_event']!==$ownerRequestId)throw new RuntimeException('This owner approval does not belong to the client request.');provider_subdomain_requests_current($r);
    $owner=provider_subdomain_add_request($ownerRequestId);provider_subdomain_requests_validate_origin($owner);return $owner;
}
/** Existing inventory recorder and quotas, with original client consent at each read boundary. */
function provider_subdomain_requests_refresh(string $ownerRequestId,\CajunWebPro\Foundation\TwentyIDocumentRoots\DocumentRootsBinding $binding,#[SensitiveParameter] string $password,?callable $resolver,?callable $transport):array {
    $authorize=static function()use($ownerRequestId,$binding):void{
        provider_document_roots_authority($binding);$owner=provider_subdomain_add_request($ownerRequestId);provider_subdomain_requests_origin_current($owner);
    };
    $p=$binding->data['package'];
    return provider_inventory_refresh_attempt($p['profile_id'],$p['profile_revision'],$password,$resolver,$transport,operation:\CajunWebPro\Foundation\TwentyIDocumentRoots\DocumentRootsBinding::OPERATION,scopeAuthority:$authorize,documentRootsContext:$binding);
}
function provider_subdomain_requests_verify(string $id,string $ownerRequestId,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    if(db()->inTransaction())throw new LogicException('Subdomain verification cannot inherit a transaction.');
    staff_locked(fn()=>staff_transaction(fn()=>provider_subdomain_requests_dispatch($id,$ownerRequestId,$password)));
    provider_subdomain_add_verify($ownerRequestId,$password,$resolver,$transport);return provider_subdomain_requests_screen(null,1,$id)['items'][0];
}
function provider_subdomain_requests_recover(string $id,string $ownerRequestId,string $ledgerSha,#[SensitiveParameter] string $password):array {
    if(db()->inTransaction())throw new LogicException('Subdomain recovery cannot inherit a transaction.');
    return staff_locked(function()use($id,$ownerRequestId,$ledgerSha,$password):array{
        staff_transaction(fn()=>provider_subdomain_requests_dispatch($id,$ownerRequestId,$password));provider_subdomain_add_recover_finished($ownerRequestId,$ledgerSha,$password);
        return staff_transaction(fn()=>provider_subdomain_requests_item(provider_subdomain_requests_state()['requests'][$id],null));
    });
}
