<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/cloud_sites.php';
require_once CWP_CODE_ROOT.'/app/providers/cloud_site_requests_storage.php';

use CajunWebPro\Foundation\TwentyI\Operations\CloudWebsiteCreate;
use CajunWebPro\Foundation\Provider\OperationResult;

/** Actual account authority; a support session never submits on a client's behalf. */
function provider_cloud_site_requests_actor(?int $clientId,#[SensitiveParameter] ?string $password=null):array {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Leave support access before managing website requests.');
    if(array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION))throw new RuntimeException('Sign in as the primary client to manage website requests.');
    if($clientId!==null){
        $client=studio_client();
        if(!$client||$clientId<1||(int)$client['id']!==$clientId)throw new RuntimeException('Sign in to your active client account.');
        $current=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]);
        if(!$current||!hash_equals($current['auth_version'],$client['auth_version']))throw new RuntimeException('Your client account changed. Sign in again.');
        return ['kind'=>'client','id'=>$clientId,'auth_sha256'=>hash('sha256',$current['auth_version'])];
    }
    $owner=$password===null?provider_settings_manager():provider_settings_owner($password);
    return ['kind'=>'owner','id'=>(int)$owner['id'],'auth_sha256'=>hash('sha256',$owner['auth_version'])];
}
function provider_cloud_site_requests_raw_state(bool $required=true):array {
    $events=provider_cloud_site_requests_events();
    if($events===null&&$required)throw new RuntimeException('The studio has not enabled cloud website requests yet.');
    return provider_cloud_site_requests_project($events??[]);
}
function provider_cloud_site_requests_hash(array $event):string {return provider_operations_scope('cloud-site-request.event',$event);}
function provider_cloud_site_requests_metadata(array $r):array {
    if($r['dispatch']===null)throw new RuntimeException('The original website dispatch is missing.');
    return ['request_id'=>$r['request']['id'],'request_hash'=>provider_cloud_site_requests_hash($r['request']),'dispatch_id'=>$r['dispatch']['id'],'dispatch_hash'=>provider_cloud_site_requests_hash($r['dispatch'])];
}
function provider_cloud_site_requests_grant_document(array $r):array {
    $p=$r['request']['payload'];$dispatch=$r['dispatch'];
    if($dispatch===null)throw new RuntimeException('The original website dispatch is missing.');
    return ['version'=>1,'binding'=>$dispatch['payload']['cloud_binding'],'params'=>CloudWebsiteCreate::params(['cloudServerId'=>$p['binding']['server_id'],'domain_name'=>$p['domain'],'extra_domain_names'=>$p['aliases']]),'reviewed'=>true,'client_request'=>provider_cloud_site_requests_metadata($r)];
}
/** Compare immutable grant facts directly. Never call the shared decoder from this graph. */
function provider_cloud_site_requests_check_grant(array $r,array $grant):void {
    $dispatch=$r['dispatch'];$doc=provider_cloud_site_requests_grant_document($r);$binding=$dispatch['payload']['binding'];
    if($grant['id']!==$dispatch['payload']['grant_id']||$grant['operation']!==CloudWebsiteCreate::OPERATION||$grant['profile_id']!==$binding['profile_id']||(int)$grant['profile_revision']!==$binding['profile_revision']||(int)$grant['granted_by']!==$dispatch['actor']['id']||$grant['consumed_key']!=='cloudsite:'.$grant['id']||$grant['consumed_at']===null||$grant['scope_params']!==provider_cloud_site_requests_json($doc)||!hash_equals($grant['scope_fingerprint'],provider_operations_scope(CloudWebsiteCreate::OPERATION,$doc)))throw new RuntimeException('The original website request and creation approval disagree. Restore their original history.');
    provider_cloud_assignment_audit('provider.cloudsite_started','Cloud website request '.$grant['id'].' client '.$binding['client_id'].' link '.$binding['link_id'],$dispatch['actor']['id']);
}
/** Cross-store integrity is historical, independent of current permission to send. */
function provider_cloud_site_requests_state(bool $required=true):array {
    provider_cloud_sites_approval_history_ready();
    $state=provider_cloud_site_requests_raw_state($required);$grants=[];
    foreach($state['requests'] as $r)if($r['dispatch']!==null){
        $id=$r['dispatch']['payload']['grant_id'];$grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$id]);
        if(!$grant)throw new RuntimeException('A website request lost its original creation approval. Restore the original history.');
        provider_cloud_site_requests_check_grant($r,$grant);$grants[$id]=true;
    }
    // An independently retained signed grant witnesses loss of the complete intake chain.
    foreach(all('SELECT id,scope_params FROM '.table('provider_operation_grants').' WHERE operation=?',[CloudWebsiteCreate::OPERATION]) as $grant){
        try{$doc=json_decode($grant['scope_params'],true,12,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('Cloud website approval history is inconsistent.');}
        if(!is_array($doc))throw new RuntimeException('Cloud website approval history is inconsistent.');
        if(array_key_exists('client_request',$doc)&&!isset($grants[$grant['id']]))throw new RuntimeException('A creation approval lost its original client request. Restore the original history.');
    }
    return $state;
}
/** Shared document/history paths validate signed origin even outside the request controller. */
function provider_cloud_site_requests_origin(array $grant,array $doc):array {
    return provider_cloud_assignment_inspect(function()use($grant,$doc):array {
        if(!is_array($doc['client_request']??null))throw new RuntimeException('Cloud website request origin is inconsistent.');
        provider_cloud_site_requests_meta($doc['client_request']);$state=provider_cloud_site_requests_raw_state();
        $r=$state['requests'][$doc['client_request']['request_id']]??null;
        if(!$r||$r['dispatch']===null||provider_cloud_site_requests_json(provider_cloud_site_requests_metadata($r))!==provider_cloud_site_requests_json($doc['client_request']))throw new RuntimeException('The original client request or dispatch is missing. Restore the original history.');
        provider_cloud_site_requests_check_grant($r,$grant);
        if(provider_cloud_site_requests_json($doc)!==provider_cloud_site_requests_json(provider_cloud_site_requests_grant_document($r)))throw new RuntimeException('Cloud website request origin changed.');
        return $r;
    });
}
/** Current original client authority, checked from the database during an owner operation. */
function provider_cloud_site_requests_original_client(array $r):void {
    $request=$r['request'];$client=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=? AND active=1',[$request['client_id']]);
    if(!$client||!hash_equals($request['actor']['auth_sha256'],hash('sha256',$client['auth_version'])))throw new RuntimeException('The original client account changed. Decline this request and ask the client to submit a new one.');
}
/** Visibility and replay use authenticated current assignment, without requiring a new observation. */
function provider_cloud_site_requests_static_current(array $r):void {
    provider_cloud_assignment_guard();provider_cloud_site_requests_original_client($r);
    if(!provider_cloud_assignment_ready())throw new RuntimeException('Cloud assignment history needs review.');
    $b=provider_cloud_site_requests_binding($r['request']['payload']['binding']);$link=one('SELECT * FROM '.table('provider_cloud_links').' WHERE id=?',[$b['link_id']]);
    if(!$link)throw new RuntimeException('This cloud server assignment changed.');
    $assignment=provider_cloud_assignment_assignment($link);$w=$assignment['witness'];$d=$w['document'];$profile=provider_settings_load($link['profile_id']);
    if($d['legacy']||(int)$profile['enabled']!==1||$profile['credentials']===''||$profile['id']!==$b['profile_id']||(int)$profile['revision']!==$b['profile_revision']||!hash_equals(hash('sha256',$profile['credentials']),$b['credential_sha256'])||$d['extra']['credentials']!==provider_cloud_assignment_profile_context($profile)||!hash_equals($d['extra']['owner_auth'],provider_cloud_assignment_owner_auth($d['actor_id'])))throw new RuntimeException('This request belongs to an earlier cloud account approval.');
    foreach(['client_id','server_id'] as $key)if((int)$link[$key]!==$b[$key])throw new RuntimeException('This cloud server assignment changed.');
    if((int)$link['revision']!==$b['link_revision']||(int)$link['profile_revision']!==$b['profile_revision']||$link['key_fingerprint']!==$b['key_fingerprint']||!hash_equals($b['link_sha256'],hash('sha256',provider_cloud_assignment_json(provider_cloud_assignment_link_fields($link))))||(int)$assignment['event']['id']!==$b['event_id']||!hash_equals($w['row']['proof'],$b['event_proof']))throw new RuntimeException('This cloud server assignment changed.');
}
function provider_cloud_site_requests_context(array $binding,array $actor,?array $r=null):string {
    return provider_operations_scope($r===null?'cloud-site-request.intake':'cloud-site-request.dispatch',['binding'=>provider_cloud_site_requests_binding($binding),'actor'=>$actor,'request_id'=>$r['request']['id']??null,'expected_event'=>$r['request']['id']??null]);
}
/** Future observation/origin IDs and owner hashes have fixed lengths; size the exact envelope now. */
function provider_cloud_site_requests_size(array $binding,string $domain,array $aliases):void {
    $b=provider_cloud_site_requests_binding($binding);$cloud=[];
    foreach(['link_id','link_revision','client_id','server_id','profile_revision','key_fingerprint'] as $key)$cloud[$key]=$b[$key];
    $cloud+=['refresh_id'=>$b['source_id'],'credentials_hash'=>$b['credential_sha256'],'actor_auth'=>str_repeat('0',64)];
    $doc=['version'=>1,'binding'=>$cloud,'params'=>CloudWebsiteCreate::params(['cloudServerId'=>$b['server_id'],'domain_name'=>$domain,'extra_domain_names'=>$aliases]),'reviewed'=>true,'client_request'=>['request_id'=>str_repeat('0',32),'request_hash'=>str_repeat('0',64),'dispatch_id'=>str_repeat('0',32),'dispatch_hash'=>str_repeat('0',64)]];
    if(strlen(provider_cloud_site_requests_json($doc))>4096)throw new InvalidArgumentException('These domains exceed the website request size limit. Reduce the additional domains before submitting.');
}
function provider_cloud_site_requests_fresh(array $r):array {
    provider_cloud_site_requests_static_current($r);$b=provider_cloud_assignment_binding($r['request']['payload']['binding']['link_id'])->data;
    if(!provider_cloud_site_requests_same_assignment($b,$r['request']['payload']['binding']))throw new RuntimeException('This cloud server assignment changed.');return $b;
}
function provider_cloud_site_requests_options(?int $clientId=null):array {
    return provider_cloud_assignment_inspect(function()use($clientId):array {
        $actor=provider_cloud_site_requests_actor($clientId);if(!provider_cloud_site_requests_ready())return [];
        provider_cloud_site_requests_state();$out=[];
        foreach(all('SELECT id FROM '.table('provider_cloud_links').($clientId!==null?' WHERE client_id=?':'').' ORDER BY profile_id,server_id',$clientId!==null?[$clientId]:[]) as $link){
            try{$b=provider_cloud_assignment_binding($link['id'])->data;}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;continue;}
            $out[]=['binding'=>$b,'context'=>provider_cloud_site_requests_context($b,$actor),'label'=>'Cloud server #'.$b['server_id']];
        }
        return $out;
    });
}
function provider_cloud_site_requests_create(string $id,int $clientId,string $linkId,string $context,string $domain,array $aliases,bool $confirmed):array {
    return provider_cloud_assignment_inspect(function()use($id,$clientId,$linkId,$context,$domain,$aliases,$confirmed):array {
        $actor=provider_cloud_site_requests_actor($clientId);provider_cloud_site_requests_id($id);provider_cloud_site_requests_id($linkId);$state=provider_cloud_site_requests_state();
        if(!$confirmed)throw new InvalidArgumentException('Confirm this website request for studio review.');
        $existing=$state['requests'][$id]??null;
        if($existing){
            $p=$existing['request']['payload'];provider_cloud_site_requests_static_current($existing);
            if($existing['request']['client_id']!==$clientId||provider_cloud_site_requests_json($existing['request']['actor'])!==provider_cloud_site_requests_json($actor)||$p['binding']['link_id']!==$linkId||$p['domain']!==$domain||$p['aliases']!==$aliases||!hash_equals(provider_cloud_site_requests_context($p['binding'],$actor),$context))throw new RuntimeException('This request identity was already used for different details.');
            return $existing;
        }
        $b=provider_cloud_assignment_binding($linkId)->data;
        if($b['client_id']!==$clientId||!hash_equals(provider_cloud_site_requests_context($b,$actor),$context))throw new RuntimeException('Your server assignment or observation changed. Reload this form.');
        provider_cloud_site_requests_size($b,$domain,$aliases);$recent=0;
        foreach($state['requests'] as $r){
            if($r['request']['client_id']===$clientId&&$r['request']['recorded_epoch']>=time()-86400)++$recent;
            $p=$r['request']['payload'];if($p['binding']['profile_id']!==$b['profile_id']||!array_intersect([$domain,...$aliases],[$p['domain'],...$p['aliases']]))continue;
            if($r['stage']==='pending')throw new RuntimeException('A request for one of these domains is already awaiting review.');
            if($r['dispatch']!==null){$grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$r['dispatch']['payload']['grant_id']]);$result=provider_cloud_sites_result($grant);if(($result['status']??null)!=='failed')throw new RuntimeException('A creation attempt for one of these domains needs review before another request.');}
        }
        if($recent>=10)throw new RuntimeException('You have reached the daily website request limit. Please contact the studio.');
        provider_cloud_site_requests_append($id,$id,$clientId,'request',$actor,['binding'=>$b,'domain'=>$domain,'aliases'=>$aliases,'confirmed'=>true]);return provider_cloud_site_requests_state()['requests'][$id];
    });
}
function provider_cloud_site_requests_change(string $id,string $expected,string $eventId,string $command,string $note,?int $clientId=null,#[SensitiveParameter] string $password=''):array {
    return provider_cloud_assignment_inspect(function()use($id,$expected,$eventId,$command,$note,$clientId,$password):array {
        $actor=provider_cloud_site_requests_actor($clientId,$clientId===null?$password:null);foreach([$id,$expected,$eventId] as $value)provider_cloud_site_requests_id($value);
        if($command!==($clientId===null?'decline':'cancel'))throw new RuntimeException('This website request action is unavailable.');
        $note=provider_cloud_site_requests_note($note);if(($command==='cancel'&&$note!=='')||($command==='decline'&&$note===''))throw new InvalidArgumentException('Add a review message when declining a request.');
        $state=provider_cloud_site_requests_state();$r=$state['requests'][$id]??null;
        if(!$r||($clientId!==null&&$r['request']['client_id']!==$clientId))throw new RuntimeException('Website request unavailable.');
        if($clientId!==null)provider_cloud_site_requests_static_current($r);
        $payload=['previous_event'=>$expected,'note'=>$note];$latest=$r['latest'];
        if($latest['id']===$eventId&&$latest['kind']===$command&&provider_cloud_site_requests_json($latest['actor'])===provider_cloud_site_requests_json($actor)&&provider_cloud_site_requests_json($latest['payload'])===provider_cloud_site_requests_json($payload))return $r;
        if($r['stage']!=='pending'||$r['latest_event']!==$expected)throw new RuntimeException('This request changed. Reload its current review.');
        provider_cloud_site_requests_append($eventId,$id,$r['request']['client_id'],$command,$actor,$payload);return provider_cloud_site_requests_state()['requests'][$id];
    });
}
/** Called only inside shared cloud creation admission: event and grant commit together. */
function provider_cloud_site_requests_admit(array $review,string $grantId,array $owner,array $profile,array $cloud,array $params,?array $existing):array {
    provider_cloud_assignment_guard();
    if(!provider_cloud_site_requests_keys($review,['request_id','expected_event','dispatch_id']))throw new InvalidArgumentException('Reload this website review.');
    foreach($review as $value){if(!is_string($value))throw new InvalidArgumentException('Reload this website review.');provider_cloud_site_requests_id($value);}
    $state=provider_cloud_site_requests_state();$r=$state['requests'][$review['request_id']]??null;if(!$r)throw new RuntimeException('The original client request is unavailable.');
    $actor=['kind'=>'owner','id'=>(int)$owner['id'],'auth_sha256'=>hash('sha256',$owner['auth_version'])];$b=provider_cloud_site_requests_fresh($r);$p=$r['request']['payload'];
    if($b['profile_id']!==$profile['id']||$b['source_id']!==$cloud['refresh_id']||$params!==CloudWebsiteCreate::params(['cloudServerId'=>$b['server_id'],'domain_name'=>$p['domain'],'extra_domain_names'=>$p['aliases']]))throw new RuntimeException('The reviewed website request changed.');
    if($existing){
        if($r['dispatch']===null||$r['dispatch']['id']!==$review['dispatch_id']||$r['dispatch']['payload']['grant_id']!==$grantId||$r['dispatch']['payload']['previous_event']!==$review['expected_event']||provider_cloud_site_requests_json($r['dispatch']['actor'])!==provider_cloud_site_requests_json($actor))throw new RuntimeException('This request was already used for different details.');
        provider_cloud_site_requests_check_grant($r,$existing);return provider_cloud_site_requests_metadata($r);
    }
    if($r['stage']!=='pending'||$r['latest_event']!==$review['expected_event'])throw new RuntimeException('This website request has already changed.');
    $event=provider_cloud_site_requests_append($review['dispatch_id'],$r['request']['id'],$r['request']['client_id'],'dispatch',$actor,['previous_event'=>$review['expected_event'],'grant_id'=>$grantId,'binding'=>$b,'cloud_binding'=>$cloud,'confirmed'=>true]);
    $r['dispatch']=$event;return provider_cloud_site_requests_metadata($r);
}
/** Fresh original-client authority at each existing credential and transport boundary. */
function provider_cloud_site_requests_authorize(array $grant,array $doc):void {
    provider_cloud_assignment_inspect(function()use($grant,$doc):void {
        $r=provider_cloud_site_requests_origin($grant,$doc);$b=provider_cloud_site_requests_fresh($r);
        if(provider_cloud_site_requests_json($b)!==provider_cloud_site_requests_json($r['dispatch']['payload']['binding']))throw new ProviderOperationAccessChanged('The original website request observation changed.');
    });
}
function provider_cloud_site_requests_dispatch(string $id,string $expected,string $dispatchId,string $grantId,string $context,bool $confirmed,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null):OperationResult {
    if(db()->inTransaction())throw new LogicException('Website dispatch cannot run inside a transaction.');
    $prepared=provider_cloud_assignment_inspect(function()use($id,$expected,$dispatchId,$grantId,$context,$confirmed,$password):array {
        $actor=provider_cloud_site_requests_actor(null,$password);foreach([$id,$expected,$dispatchId,$grantId] as $value)provider_cloud_site_requests_id($value);
        if(!$confirmed)throw new InvalidArgumentException('Review domain authority and server capacity before creating this website.');
        $state=provider_cloud_site_requests_state();$r=$state['requests'][$id]??null;if(!$r)throw new RuntimeException('Website request unavailable.');
        if($r['dispatch']!==null){
            provider_cloud_site_requests_static_current($r);$d=$r['dispatch'];$b=$d['payload']['binding'];
            if($d['id']!==$dispatchId||$d['payload']['grant_id']!==$grantId||$d['payload']['previous_event']!==$expected||provider_cloud_site_requests_json($d['actor'])!==provider_cloud_site_requests_json($actor)||!hash_equals(provider_cloud_site_requests_context($b,$actor,$r),$context))throw new RuntimeException('This request already has a different creation attempt.');
            $grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$grantId]);provider_cloud_site_requests_check_grant($r,$grant);
            return ['result'=>provider_cloud_sites_replay($grant)];
        }
        if($r['stage']!=='pending'||$r['latest_event']!==$expected)throw new RuntimeException('This request changed. Reload its review.');
        $b=provider_cloud_site_requests_fresh($r);
        if(!hash_equals(provider_cloud_site_requests_context($b,$actor,$r),$context))throw new RuntimeException('The cloud observation or review changed. Reload this form.');
        return ['binding'=>$b,'request'=>$r['request']['payload']];
    });
    if(isset($prepared['result']))return $prepared['result'];$b=$prepared['binding'];$p=$prepared['request'];
    return provider_cloud_sites_create($grantId,$b['link_id'],$b['link_revision'],$b['profile_revision'],$b['source_id'],$p['domain'],$p['aliases'],true,$password,$resolver,$perform,['request_id'=>$id,'expected_event'=>$expected,'dispatch_id'=>$dispatchId]);
}
/** Public DTOs contain no event documents, credentials, owner identities or client-visible grant IDs. */
function provider_cloud_site_requests_history(?int $clientId=null,int $page=1):array {
    return provider_cloud_assignment_inspect(function()use($clientId,$page):array {
        $actor=provider_cloud_site_requests_actor($clientId);if(!provider_cloud_site_requests_ready())return [];$state=provider_cloud_site_requests_state();$visible=[];
        foreach(array_reverse(array_values($state['requests'])) as $r){
            if($clientId!==null){if($r['request']['client_id']!==$clientId)continue;try{provider_cloud_site_requests_static_current($r);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;continue;}}
            $visible[]=$r;
        }
        $out=[];foreach(array_slice($visible,(max(1,min(10000,$page))-1)*20,20) as $r){
            $request=$r['request'];$p=$request['payload'];$status=$r['stage'];$reason='';$context='';$canDispatch=false;$grantId=null;$packageId=null;$confirmation=null;
            if($r['stage']==='pending'&&$clientId===null){try{$b=provider_cloud_site_requests_fresh($r);$context=provider_cloud_site_requests_context($b,$actor,$r);$canDispatch=true;}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$reason=$e->getMessage();}}
            if($r['dispatch']!==null){$grantId=$r['dispatch']['payload']['grant_id'];$grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$grantId]);$result=provider_cloud_sites_result($grant);$status=$result['status']??'uncertain';$packageId=$result['external_id']??null;$confirmation=provider_cloud_confirmation_projection($grantId);}
            $out[]=['id'=>$request['id'],'domain'=>$p['domain'],'aliases'=>$p['aliases'],'server_id'=>$p['binding']['server_id'],'server_label'=>'Cloud server #'.$p['binding']['server_id'],'created_at'=>gmdate('Y-m-d H:i:s',$request['recorded_epoch']).' UTC','stage'=>$r['stage'],'status'=>$status,'note'=>$r['stage']==='declined'?$r['latest']['payload']['note']:'','latest_event'=>$r['latest_event'],'can_cancel'=>$clientId!==null&&$r['stage']==='pending','can_decline'=>$clientId===null&&$r['stage']==='pending','can_dispatch'=>$canDispatch,'dispatch_context'=>$context,'grant_id'=>$clientId===null?$grantId:null,'package_id'=>$clientId===null?$packageId:null,'confirmation'=>$confirmation,'reason'=>$reason];
        }
        return $out;
    });
}
