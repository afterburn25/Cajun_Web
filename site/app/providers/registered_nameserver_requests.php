<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/registered_nameserver_changes.php';
require_once CWP_CODE_ROOT.'/app/providers/registered_nameserver_requests_storage.php';

/** Check the real session before parsing any client-supplied form fields. */
function registered_nameserver_requests_actor(?int $clientId,#[SensitiveParameter] ?string $password=null):array {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Sign in directly to manage nameserver requests.');
    if(array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION))throw new RuntimeException('Sign in to your primary client account.');
    if($clientId!==null){
        $client=studio_client();
        if(!$client||$clientId<1||(int)$client['id']!==$clientId||(int)($_SESSION['client_id']??0)!==$clientId||!hash_equals($client['auth_version'],(string)($_SESSION['client_auth']??'')))throw new RuntimeException('Sign in to your primary client account.');
        return ['kind'=>'client','id'=>$clientId,'auth_sha256'=>hash('sha256',$client['auth_version'])];
    }
    $owner=$password===null?provider_settings_manager():provider_settings_owner($password);
    return ['kind'=>'owner','id'=>(int)$owner['id'],'auth_sha256'=>hash('sha256',$owner['auth_version'])];
}

/** Local assignment provenance, independent of the age of a later provider read. */
function registered_nameserver_requests_resource(string $linkId):array {
    registered_nameservers_guard();registered_nameservers_id($linkId);
    $raw=one('SELECT revision FROM '.table('provider_registrar_links').' WHERE id=?',[$linkId]);
    if(!$raw)throw new RuntimeException('This registered-domain assignment is unavailable.');
    [$link,$event,$audit]=domain_contracts_link($linkId,(int)$raw['revision']);
    $profile=provider_settings_load($link['profile_id']);
    $client=one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$link['client_id']]);
    $owner=one('SELECT * FROM '.table('users').' WHERE id=?',[$event['actor_id']]);$owner=$owner?staff_profile($owner):null;
    if(!$client||!$owner||$owner['role']!=='owner'||(int)$profile['enabled']!==1||(int)$profile['revision']!==(int)$link['profile_revision']||$profile['credentials']==='')throw new RuntimeException('The studio must confirm this domain assignment again.');
    $source=domain_contracts_source($event['refresh_id'],(int)$link['domain_id']);
    if($source['profile_id']!==$link['profile_id']||$source['profile_revision']!==(int)$link['profile_revision']||$source['key_fingerprint']!==$link['key_fingerprint']||$source['domain']!==$link['domain'])throw new RuntimeException('The original domain assignment needs review.');
    $binding=new \CajunWebPro\Foundation\TwentyIRegistrarNameservers\RegisteredNameserverBinding([
        'profile_id'=>$link['profile_id'],'profile_revision'=>(int)$profile['revision'],'key_fingerprint'=>$link['key_fingerprint'],
        'domain_id'=>(int)$link['domain_id'],'domain'=>$link['domain'],'link_id'=>$link['id'],'link_revision'=>(int)$link['revision'],
        'client_id'=>(int)$link['client_id'],'link_event_id'=>$event['id'],'link_event_sha256'=>hash('sha256',registered_nameservers_json($event)),
        'link_audit_id'=>$audit['id'],'link_audit_sha256'=>hash('sha256',registered_nameservers_json($audit)),
        'source_refresh_id'=>$source['id'],'source_seq'=>$source['seq'],'source_sha256'=>hash('sha256',registered_nameservers_json($source))]);
    return ['binding'=>$binding->data,'credential_sha256'=>hash('sha256',$profile['credentials']),'owner_id'=>(int)$owner['id'],'owner_auth_sha256'=>hash('sha256',$owner['auth_version'])];
}
function registered_nameserver_requests_event_sha(array $event):string {return hash('sha256',registered_nameserver_requests_json($event));}
function registered_nameserver_requests_context(array $resource,array $actor):string {return provider_operations_scope('registered-nameserver-request.form',['resource'=>$resource,'actor'=>$actor]);}

/** Raw authenticated graph only: never calls owner-history validation recursively. */
function registered_nameserver_requests_graph():?array {
    $events=registered_nameserver_requests_events();return $events===null?null:registered_nameserver_requests_project($events);
}
function registered_nameserver_requests_state():array {
    registered_nameservers_guard();$state=registered_nameserver_requests_graph();
    if($state===null)throw new RuntimeException('The studio has not enabled nameserver requests yet.');
    if(!registered_nameserver_changes_ready())throw new RuntimeException('The studio must prepare nameserver changes first.');
    return $state;
}

/** Both immutable directions must agree. This does not require current authority. */
function registered_nameserver_requests_validate_origin(array $ownerRequest):void {
    $d=$ownerRequest['document'];$origin=$d['client_origin']??null;
    if($d['version']!==2||!is_array($origin)||array_keys($origin)!==['id','request_sha256']||!is_string($origin['id'])||!is_string($origin['request_sha256']))throw new RuntimeException('Nameserver client origin needs recovery.');
    $state=registered_nameserver_requests_graph();$r=$state['requests'][$origin['id']]??null;
    if(!$r||$r['state']!=='dispatched'||!hash_equals(registered_nameserver_requests_event_sha($r['request']),$origin['request_sha256']))throw new RuntimeException('Nameserver client origin needs recovery.');
    $dispatch=$r['events'][array_key_last($r['events'])]??null;$request=$r['request'];
    if(!$dispatch||$dispatch['kind']!=='dispatch'||$dispatch['id']!==$ownerRequest['row']['id']||$dispatch['payload']['owner_request_id']!==$ownerRequest['row']['id']||!hash_equals($dispatch['payload']['owner_request_sha256'],$ownerRequest['row']['document_sha256'])||$dispatch['actor']['kind']!=='owner'||$dispatch['actor']['id']!==(int)$ownerRequest['row']['actor_id']||!hash_equals($dispatch['actor']['auth_sha256'],$d['actor_auth'])||$dispatch['recorded_epoch']<$d['approved_epoch']||$request['recorded_epoch']>$d['approved_epoch']||$request['payload']['desired_nameservers']!==$d['params']['ns'])throw new RuntimeException('Nameserver dispatch association needs recovery.');
    foreach($request['payload']['resource']['binding'] as $key=>$value)if(!str_starts_with($key,'source_')&&$d['binding'][$key]!==$value)throw new RuntimeException('Nameserver client assignment association needs recovery.');
}

/** Called after all owner history is authenticated, including legacy v1 requests. */
function registered_nameserver_requests_owner_history():void {
    $state=registered_nameserver_requests_graph();if($state===null)return;
    foreach($state['requests'] as $r)if($r['state']==='dispatched'){
        $event=$r['events'][array_key_last($r['events'])];$owner=registered_nameserver_changes_request($event['payload']['owner_request_id']);
        // A lost or downgraded client-origin approval cannot become a direct-owner request.
        registered_nameserver_requests_validate_origin($owner);
    }
}

/** Current consent is required for dispatch, not to read immutable historical facts. */
function registered_nameserver_requests_current(array $r):array {
    $request=$r['request'];$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1',[$request['client_id']]);
    if(!$client||!hash_equals($request['actor']['auth_sha256'],hash('sha256',$client['auth_version'])))throw new RuntimeException('The client account changed. Ask for a new nameserver request.');
    $resource=registered_nameserver_requests_resource($request['payload']['resource']['binding']['link_id']);
    if(registered_nameserver_requests_json($resource)!==registered_nameserver_requests_json($request['payload']['resource']))throw new RuntimeException('The request belongs to an earlier domain assignment.');
    return $resource;
}
function registered_nameserver_requests_origin_current(array $ownerRequest):void {
    registered_nameservers_guard(false);registered_nameserver_requests_validate_origin($ownerRequest);
    $state=registered_nameserver_requests_graph();$r=$state['requests'][$ownerRequest['document']['client_origin']['id']];
    // Credential boundaries run outside a transaction but inside the same owning mutex.
    if(db()->inTransaction())registered_nameserver_requests_current($r);
    else staff_transaction(fn()=>registered_nameserver_requests_current($r));
}

function registered_nameserver_requests_pending(array $state,array $resource):bool {
    $b=$resource['binding'];
    foreach($state['requests'] as $r){$old=$r['request']['payload']['resource']['binding'];if($r['state']==='pending'&&$old['profile_id']===$b['profile_id']&&$old['domain_id']===$b['domain_id'])return true;}
    return registered_nameserver_changes_pending($b['profile_id'],$b['domain_id'])!==null;
}
function registered_nameserver_requests_create(string $id,int $clientId,string $linkId,string $reviewSha,array $desired,bool $confirmed):array {
    return staff_locked(fn()=>staff_transaction(function()use($id,$clientId,$linkId,$reviewSha,$desired,$confirmed):array{
        $actor=registered_nameserver_requests_actor($clientId);$state=registered_nameserver_requests_state();registered_nameservers_id($id);registered_nameservers_id($linkId);
        if(!$confirmed)throw new InvalidArgumentException('Confirm that the studio must review this request.');
        $resource=registered_nameserver_requests_resource($linkId);
        if($resource['binding']['client_id']!==$clientId||!hash_equals(registered_nameserver_requests_context($resource,$actor),$reviewSha))throw new RuntimeException('The domain assignment changed. Reload the request form.');
        $desired=\CajunWebPro\Foundation\TwentyI\Operations\NameserverChange::observed($desired);
        $payload=['resource'=>$resource,'desired_nameservers'=>$desired,'confirmed'=>true];
        if(isset($state['requests'][$id])){$r=$state['requests'][$id];if($r['request']['client_id']!==$clientId||registered_nameserver_requests_json($r['request']['actor'])!==registered_nameserver_requests_json($actor)||registered_nameserver_requests_json($r['request']['payload'])!==registered_nameserver_requests_json($payload))throw new RuntimeException('This request identifier was already used.');return registered_nameserver_requests_item($r,$clientId);}
        if(registered_nameserver_requests_pending($state,$resource))throw new RuntimeException('A nameserver request for this domain still needs studio review.');
        $recent=0;foreach($state['requests'] as $r)if($r['request']['client_id']===$clientId&&$r['request']['recorded_epoch']>=time()-3600)$recent++;
        if($recent>=10)throw new RuntimeException('Please wait before submitting more nameserver requests.');
        registered_nameserver_requests_append($id,$id,$clientId,'request',$actor,$payload);
        return registered_nameserver_requests_item(registered_nameserver_requests_state()['requests'][$id],$clientId);
    }));
}

function registered_nameserver_requests_change(string $id,string $expectedEvent,string $eventId,string $command,string $note,?int $clientId=null,#[SensitiveParameter] string $password=''):array {
    return staff_locked(fn()=>staff_transaction(function()use($id,$expectedEvent,$eventId,$command,$note,$clientId,$password):array{
        $actor=registered_nameserver_requests_actor($clientId,$clientId===null?$password:null);$state=registered_nameserver_requests_state();
        registered_nameservers_id($id);registered_nameservers_id($expectedEvent);registered_nameservers_id($eventId);
        if($command!==($clientId===null?'decline':'cancel'))throw new RuntimeException('This nameserver request action is unavailable.');
        $r=$state['requests'][$id]??null;if(!$r||($clientId!==null&&$r['request']['client_id']!==$clientId))throw new RuntimeException('Nameserver request unavailable.');
        $payload=['previous_event'=>$expectedEvent,'note'=>$note];
        foreach($r['events'] as $event)if($event['id']===$eventId){if($event['kind']!==$command||registered_nameserver_requests_json($event['actor'])!==registered_nameserver_requests_json($actor)||registered_nameserver_requests_json($event['payload'])!==registered_nameserver_requests_json($payload))throw new RuntimeException('This review identifier was already used.');return registered_nameserver_requests_item($r,$clientId);}
        if($r['state']!=='pending'||$r['latest_event']!==$expectedEvent)throw new RuntimeException('This nameserver request changed. Reload its review.');
        if($clientId!==null)registered_nameserver_requests_current($r);
        registered_nameserver_requests_append($eventId,$id,$r['request']['client_id'],$command,$actor,$payload);
        return registered_nameserver_requests_item(registered_nameserver_requests_state()['requests'][$id],$clientId);
    }));
}

function registered_nameserver_requests_approval_hash(array $r,string $anchorId,string $bindingSha,array $before):string {
    return provider_operations_scope('registered-nameserver-request.approval',['id'=>$r['request']['id'],'request_sha256'=>registered_nameserver_requests_event_sha($r['request']),'anchor_id'=>$anchorId,'binding_sha256'=>$bindingSha,'old_nameservers'=>$before,'desired_nameservers'=>$r['request']['payload']['desired_nameservers']]);
}
function registered_nameserver_requests_approval(array $r):array {
    $resource=registered_nameserver_requests_current($r);$b=$resource['binding'];
    $form=registered_nameserver_changes_form($b['link_id'],$b['link_revision']);
    if(!$form['eligible'])throw new RuntimeException('Refresh and review this domain before approving the client request.');
    // Apply the provider-supported complete/disjoint-set rule before offering approval.
    \CajunWebPro\Foundation\TwentyI\Operations\RegistrarNameserverChange::params(['domainId'=>$b['domain_id'],'ns'=>$r['request']['payload']['desired_nameservers'],'old-ns'=>$form['old_nameservers']]);
    return ['form'=>$form,'review_sha256'=>registered_nameserver_requests_approval_hash($r,$form['anchor_id'],$form['binding_sha256'],$form['old_nameservers'])];
}
function registered_nameserver_requests_approve(string $id,string $expectedEvent,string $requestSha,string $dispatchId,string $reviewSha,bool $reviewed,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null):array {
    if(db()->inTransaction())throw new LogicException('Nameserver approval cannot run in a transaction.');
    return staff_locked(function()use($id,$expectedEvent,$requestSha,$dispatchId,$reviewSha,$reviewed,$password,$resolver,$perform):array{
        $fresh=staff_transaction(function()use($id,$expectedEvent,$requestSha,$dispatchId,$reviewSha,$reviewed,$password):bool{
            $actor=registered_nameserver_requests_actor(null,$password);$state=registered_nameserver_requests_state();
            registered_nameservers_id($id);registered_nameservers_id($expectedEvent);registered_nameservers_id($dispatchId);
            if(!$reviewed)throw new InvalidArgumentException('Review the complete requested nameserver replacement first.');
            $r=$state['requests'][$id]??null;if(!$r||!hash_equals(registered_nameserver_requests_event_sha($r['request']),$requestSha))throw new RuntimeException('The original client request changed. Reload its review.');
            registered_nameserver_requests_current($r);
            if($r['state']==='dispatched'){
                $event=$r['events'][array_key_last($r['events'])];$owner=registered_nameserver_changes_request($event['payload']['owner_request_id']);$d=$owner['document'];
                if($event['id']!==$dispatchId||$event['payload']['previous_event']!==$expectedEvent||registered_nameserver_requests_json($event['actor'])!==registered_nameserver_requests_json($actor)||!hash_equals(registered_nameserver_requests_approval_hash($r,$d['anchor_id'],$owner['binding']->sha256,$d['before_nameservers']),$reviewSha))throw new RuntimeException('This approval identifier or reviewed request changed.');
                registered_nameserver_changes_context($owner['binding']);return false;
            }
            if($r['state']!=='pending'||$r['latest_event']!==$expectedEvent)throw new RuntimeException('This request was already reviewed or withdrawn.');
            $plan=registered_nameserver_requests_approval($r);if(!hash_equals($plan['review_sha256'],$reviewSha))throw new RuntimeException('The reviewed nameserver observation changed. Reload the approval.');
            $form=$plan['form'];
            if(!registered_nameserver_changes_admit_locked($dispatchId,$form['link_id'],$form['anchor_id'],$form['binding_sha256'],$r['request']['payload']['desired_nameservers'],$password,['id'=>$id,'request_sha256'=>$requestSha]))throw new RuntimeException('This approval identifier was already used.');
            $ownerRow=one('SELECT document_sha256 FROM '.table('provider_nameserver_change_requests').' WHERE id=?',[$dispatchId]);
            registered_nameserver_requests_append($dispatchId,$id,$r['request']['client_id'],'dispatch',$actor,['previous_event'=>$expectedEvent,'owner_request_id'=>$dispatchId,'owner_request_sha256'=>$ownerRow['document_sha256']]);
            // The immutable request, consumed grant and reciprocal association commit together.
            registered_nameserver_requests_state();return true;
        });
        if($fresh)registered_nameserver_changes_send_locked($dispatchId,$resolver,$perform);
        return staff_transaction(fn()=>registered_nameserver_requests_item(registered_nameserver_requests_state()['requests'][$id],null));
    });
}

/** A closed projection: no account secrets, binding proofs, grants or provider errors. */
function registered_nameserver_requests_item(array $r,?int $clientId):array {
    $q=$r['request'];if($clientId!==null&&$q['client_id']!==$clientId)throw new RuntimeException('Nameserver request unavailable.');
    $current=true;try{registered_nameserver_requests_current($r);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$current=false;}
    $state=$r['state'];$lastCheck=null;$ownerId=null;$dispatch=null;$approval=null;$note='';
    foreach($r['events'] as $event)if($event['kind']==='decline')$note=$event['payload']['note'];
    if($state==='dispatched'){
        $event=$r['events'][array_key_last($r['events'])];$ownerId=$event['payload']['owner_request_id'];$owner=registered_nameserver_changes_request($ownerId);$terminal=registered_nameserver_changes_terminal($owner);
        $state=$terminal===null?'interrupted':($terminal['row']['disposition']==='not_sent'?'not_sent':'uncertain');
        // Receipt UUID order is not chronology; use the source sequence exactly as owner history.
        $check=one('SELECT c.* FROM '.table('provider_nameserver_change_checks').' c JOIN '.table('provider_refreshes').' f ON f.id=c.receipt_id WHERE c.request_id=? ORDER BY f.seq DESC LIMIT 1',[$ownerId]);
        if($check){$checked=registered_nameserver_changes_check_document($check,$owner);$lastCheck=['disposition'=>$check['disposition'],'nameservers'=>$checked['nameservers'],'recorded_epoch'=>$checked['recorded_epoch']];if($check['disposition']==='observed')$state='observed';}
        if($clientId===null)$dispatch=registered_nameserver_changes_summary($ownerId);
    }elseif($state==='pending'&&$clientId===null&&$current){
        try{$plan=registered_nameserver_requests_approval($r);$approval=['review_sha256'=>$plan['review_sha256'],'old_nameservers'=>$plan['form']['old_nameservers'],'observed_epoch'=>$plan['form']['observed_epoch']];}
        catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}
    }
    return ['id'=>$q['id'],'domain'=>$q['payload']['resource']['binding']['domain'],'desired_nameservers'=>$q['payload']['desired_nameservers'],'created_epoch'=>$q['recorded_epoch'],'state'=>$state,'latest_event'=>$r['latest_event'],'request_sha256'=>registered_nameserver_requests_event_sha($q),'current_assignment'=>$current,'can_cancel'=>$clientId!==null&&$current&&$r['state']==='pending','can_decline'=>$clientId===null&&$r['state']==='pending','note'=>$note,'owner_request_id'=>$clientId===null?$ownerId:null,'last_check'=>$lastCheck,'approval'=>$approval,'dispatch'=>$dispatch];
}

function registered_nameserver_requests_screen(?int $clientId=null,int $page=1,?string $detailId=null):array {
    return staff_locked(fn()=>staff_transaction(function()use($clientId,$page,$detailId):array{
        $actor=registered_nameserver_requests_actor($clientId);$page=max(1,min(10000,$page));
        $screen=['ready'=>false,'options'=>[],'items'=>[],'page'=>$page,'has_more'=>false];
        if(!registered_nameserver_requests_ready())return $screen;$state=registered_nameserver_requests_state();$screen['ready']=true;
        if($detailId!==null){registered_nameservers_id($detailId);$r=$state['requests'][$detailId]??null;if(!$r||($clientId!==null&&$r['request']['client_id']!==$clientId))throw new RuntimeException('Nameserver request unavailable.');$screen['items'][]=registered_nameserver_requests_item($r,$clientId);return $screen;}
        if($clientId!==null)foreach(all('SELECT id FROM '.table('provider_registrar_links').' WHERE client_id=? ORDER BY domain',[$clientId]) as $row){
            try{$resource=registered_nameserver_requests_resource($row['id']);if($resource['binding']['client_id']!==$clientId||registered_nameserver_requests_pending($state,$resource))continue;$screen['options'][]=['link_id'=>$row['id'],'domain'=>$resource['binding']['domain'],'review_sha256'=>registered_nameserver_requests_context($resource,$actor)];}
            catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}
        }
        $items=array_reverse(array_values($state['requests']));if($clientId!==null)$items=array_values(array_filter($items,static fn($r)=>$r['request']['client_id']===$clientId));
        $offset=($page-1)*20;$screen['has_more']=count($items)>$offset+20;foreach(array_slice($items,$offset,20) as $r)$screen['items'][]=registered_nameserver_requests_item($r,$clientId);
        return $screen;
    }));
}
