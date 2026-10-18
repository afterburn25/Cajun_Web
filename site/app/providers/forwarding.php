<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/forwarding_storage.php';
require_once CWP_CODE_ROOT.'/app/providers/forwarding_document.php';
/** This workflow records requests only. It has no provider dispatch or fulfillment state. */
function provider_forwarding_state():array {$events=provider_forwarding_events();if($events===null)throw new RuntimeException('The studio has not enabled forwarding requests yet.');return provider_forwarding_project($events);}
function provider_forwarding_resource(string $linkId):array {
    $r=provider_mailbox_resource($linkId);$b=$r['binding'];
    $result=one('SELECT skipped,package_count,scope,coverage FROM '.table('provider_refresh_results').' WHERE id=?',[$r['observation_id']]);
    if(!$result||(int)$result['skipped']!==0||$result['scope']!=='twenty-i|'.$b['profile_id'].'|packages.list'||$result['coverage']!=='unknown'||(int)$result['package_count']!==(int)query('SELECT COUNT(*) FROM '.table('provider_observation_packages').' WHERE refresh_id=?',[$r['observation_id']])->fetchColumn())throw new RuntimeException('Refresh the complete hosting inventory before requesting forwarding.');
    provider_forwarding_link_coverage($b['profile_id'],$b['domain']);
    $event=one('SELECT * FROM '.table('provider_domain_link_events').' WHERE profile_id=? AND BINARY domain=? ORDER BY id DESC LIMIT 1',[$b['profile_id'],$b['domain']]);
    if(!$event||!in_array($event['event'],['link','relink'],true)||(int)$event['package_id']!==$b['package_id']||(int)$event['client_id']!==$b['client_id'])throw new RuntimeException('The domain assignment history needs review.');
    provider_forwarding_link_audit($event,$b);
    $resource=['binding'=>$b,'credential_sha256'=>hash('sha256',$r['profile']['credentials']),'refresh_id'=>$r['observation_id'],'link_event_id'=>(int)$event['id']];provider_forwarding_resource_document($resource);return $resource;
}
/** A later lost relink/unlink must not expose an older permission while its audit survives. */
function provider_forwarding_link_coverage(string $profileId,string $domain):void {
    $events=all('SELECT * FROM '.table('provider_domain_link_events').' WHERE profile_id=? AND BINARY domain=? ORDER BY id',[$profileId,$domain]);$expected=[];$actual=[];
    foreach($events as $e){if(!in_array($e['event'],['link','relink','unlink'],true))throw new RuntimeException('Domain assignment event history is inconsistent.');$key=$e['event'].':'.$e['actor_id'];$expected[$key]=($expected[$key]??0)+1;}
    foreach(['link'=>['provider.domain_linked','Profile '.$profileId.' domain '.$domain.' client %','Profile '.preg_quote($profileId,'/').' domain '.preg_quote($domain,'/').' client [1-9][0-9]{0,9}'],
        'relink'=>['provider.domain_relinked','Domain link % profile '.$profileId.' domain '.$domain.' revision %','Domain link [a-f0-9]{32} profile '.preg_quote($profileId,'/').' domain '.preg_quote($domain,'/').' revision [1-9][0-9]{0,9}'],
        'unlink'=>['provider.domain_unlinked','Domain link % profile '.$profileId.' domain '.$domain,'Domain link [a-f0-9]{32} profile '.preg_quote($profileId,'/').' domain '.preg_quote($domain,'/')]] as $kind=>[$action,$pattern,$shape]){
        foreach(all('SELECT user_id,detail FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ? ORDER BY id',[$action,$pattern]) as $audit){if(!preg_match('/\A'.$shape.'\z/',$audit['detail']))throw new RuntimeException('Domain assignment audit history is inconsistent.');$key=$kind.':'.$audit['user_id'];$actual[$key]=($actual[$key]??0)+1;}
    }
    ksort($expected);ksort($actual);if($expected!==$actual)throw new RuntimeException('Domain assignment history needs recovery before forwarding review.');
}
/** Pair legacy domain events and exact resource/actor audits by insertion order. */
function provider_forwarding_link_audit(array $event,array $binding):void {
    $action=$event['event']==='link'?'provider.domain_linked':'provider.domain_relinked';
    $detail=$event['event']==='link'?'Profile '.$event['profile_id'].' domain '.$event['domain'].' client '.$event['client_id']:'Domain link '.$binding['link_id'].' profile '.$event['profile_id'].' domain '.$event['domain'].' revision '.$binding['profile_revision'];
    if($event['event']==='link'){
        $events=all('SELECT id FROM '.table('provider_domain_link_events').' WHERE profile_id=? AND BINARY domain=? AND client_id=? AND event=? AND actor_id=? ORDER BY id',[$event['profile_id'],$event['domain'],$event['client_id'],'link',$event['actor_id']]);
        $audits=all('SELECT detail FROM '.table('audit').' WHERE BINARY action=? AND user_id=? AND BINARY detail=? ORDER BY id',[$action,$event['actor_id'],$detail]);
    }else{
        $events=all('SELECT id FROM '.table('provider_domain_link_events').' WHERE profile_id=? AND BINARY domain=? AND event=? AND actor_id=? ORDER BY id',[$event['profile_id'],$event['domain'],'relink',$event['actor_id']]);
        $audits=all('SELECT detail FROM '.table('audit').' WHERE BINARY action=? AND user_id=? AND BINARY detail LIKE ? ORDER BY id',[$action,$event['actor_id'],'Domain link % profile '.$event['profile_id'].' domain '.$event['domain'].' revision %']);
        foreach($audits as $audit)if(!preg_match('/\ADomain link [a-f0-9]{32} profile '.preg_quote($event['profile_id'],'/').' domain '.preg_quote($event['domain'],'/').' revision [1-9][0-9]{0,9}\z/',$audit['detail']))throw new RuntimeException('The domain assignment audit is inconsistent.');
    }
    if(count($events)!==count($audits)||$audits===[])throw new RuntimeException('The domain assignment audit needs recovery.');
    foreach($events as $index=>$row)if((int)$row['id']===(int)$event['id']){if($audits[$index]['detail']!==$detail)throw new RuntimeException('The domain assignment audit identity changed.');return;}
    throw new RuntimeException('The domain assignment audit is missing.');
}
function provider_forwarding_token(array $resource):string {return provider_operations_scope('forwarding.form-context',$resource);}
function provider_forwarding_actor(?int $clientId,#[SensitiveParameter] ?string $password=null):array {
    $a=provider_mailbox_actor($clientId,$password);if($clientId!==null&&!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]))throw new RuntimeException('Sign in to your active client account.');return $a;
}
function provider_forwarding_permission(array $state,string $linkId,?int $clientId=null):array {
    $p=$state['permissions'][$linkId]??null;
    if(!$p||!$p['payload']['enabled']||$p['payload']['expires_epoch']<=time()||($clientId!==null&&$p['client_id']!==$clientId))throw new RuntimeException('Forwarding request permission changed or expired. Ask the studio to review it.');
    $owner=one('SELECT * FROM '.table('users').' WHERE id=?',[$p['actor']['id']]);$owner=$owner?staff_profile($owner):null;
    if(!$owner||$owner['role']!=='owner'||!hash_equals($owner['auth_version'],$p['actor']['auth_version']))throw new RuntimeException('The forwarding request approval is no longer current.');
    $resource=provider_forwarding_resource($linkId);if(!provider_forwarding_same_resource($resource,$p['payload']['resource']))throw new RuntimeException('The approved forwarding assignment changed. Ask the studio to renew permission.');return [$p,$resource];
}
function provider_forwarding_configure(string $id,string $linkId,string $expected,string $context,bool $enabled,int $dailyLimit,int $hours,bool $confirmed,#[SensitiveParameter] string $password):void {
    staff_locked(fn()=>staff_transaction(function()use($id,$linkId,$expected,$context,$enabled,$dailyLimit,$hours,$confirmed,$password):void{
        $actor=provider_forwarding_actor(null,$password);$state=provider_forwarding_state();provider_forwarding_id($id);provider_forwarding_id($linkId);$old=$state['permissions'][$linkId]??null;
        if(($old['id']??'')!==$expected)throw new RuntimeException('Forwarding permission changed. Reload the form.');
        if($dailyLimit<1||$dailyLimit>20||$hours<1||$hours>720||($enabled&&!$confirmed))throw new InvalidArgumentException('Confirm this separate forwarding permission and choose 1–20 requests per day and 1–720 hours.');
        $resource=(!$enabled&&$old)?$old['payload']['resource']:provider_forwarding_resource($linkId);if($enabled&&!hash_equals(provider_forwarding_token($resource),$context))throw new RuntimeException('The hosting observation changed. Reload the permission form.');
        provider_forwarding_append($id,$linkId,$resource['binding']['client_id'],'permission',$actor,['previous_id'=>$expected,'enabled'=>$enabled,'daily_limit'=>$dailyLimit,'expires_epoch'=>time()+$hours*3600,'resource'=>$resource]);
    }));
}
function provider_forwarding_create(string $id,int $clientId,string $linkId,string $permissionId,string $context,string $local,string $remote,bool $confirmed):array {
    return staff_locked(fn()=>staff_transaction(function()use($id,$clientId,$linkId,$permissionId,$context,$local,$remote,$confirmed):array{
        $actor=provider_forwarding_actor($clientId);$state=provider_forwarding_state();provider_forwarding_id($id);provider_forwarding_id($linkId);provider_forwarding_id($permissionId);
        if(!$confirmed)throw new InvalidArgumentException('Confirm that this is a request for studio review.');
        [$p,$resource]=provider_forwarding_permission($state,$linkId,$clientId);
        if($p['id']!==$permissionId||!hash_equals(provider_forwarding_token($resource),$context))throw new RuntimeException('The forwarding permission or observation changed. Reload the form.');
        $recent=0;foreach($state['requests'] as $r)if($r['request']['payload']['resource']['binding']['link_id']===$linkId&&$r['request']['recorded_epoch']>=time()-86400)$recent++;
        if($recent>=$p['payload']['daily_limit'])throw new RuntimeException('This domain has reached its daily request limit. Please contact the studio.');
        provider_forwarding_append($id,$id,$clientId,'request',$actor,['permission_id'=>$permissionId,'local'=>$local,'remote'=>$remote,'resource'=>$resource]);return provider_forwarding_state()['requests'][$id];
    }));
}
function provider_forwarding_change(string $id,string $expected,string $eventId,string $command,string $note,?int $clientId=null,#[SensitiveParameter] string $password=''):array {
    return staff_locked(fn()=>staff_transaction(function()use($id,$expected,$eventId,$command,$note,$clientId,$password):array{
        $actor=provider_forwarding_actor($clientId,$clientId===null?$password:null);$state=provider_forwarding_state();provider_forwarding_id($id);provider_forwarding_id($expected);provider_forwarding_id($eventId);
        if(!in_array($command,$clientId===null?['review','decline']:['cancel'],true))throw new RuntimeException('This action is not available for your account.');
        $r=$state['requests'][$id]??null;if(!$r||($clientId!==null&&$r['request']['client_id']!==$clientId))throw new RuntimeException('Forwarding request unavailable.');
        if($r['state']!=='pending_review'||$r['latest_event']!==$expected)throw new RuntimeException('This request changed. Reload its review.');$resource=null;
        if($command==='review'){$original=$r['request']['payload'];[$p,$resource]=provider_forwarding_permission($state,$original['resource']['binding']['link_id']);if($p['id']!==$original['permission_id']||!provider_forwarding_same_resource($resource,$original['resource']))throw new RuntimeException('This request belongs to an earlier permission or assignment. Decline it and ask for a new request.');}
        provider_forwarding_append($eventId,$id,$r['request']['client_id'],$command,$actor,['previous_event'=>$expected,'note'=>provider_forwarding_note($note),'resource'=>$resource]);return provider_forwarding_state()['requests'][$id];
    }));
}
function provider_forwarding_options(?int $clientId=null):array {
    provider_forwarding_actor($clientId);if(!provider_forwarding_ready())return [];$state=provider_forwarding_state();$out=[];$seen=[];
    foreach(all('SELECT id FROM '.table('provider_domain_links').($clientId!==null?' WHERE client_id=?':'').' ORDER BY domain',$clientId!==null?[$clientId]:[]) as $row){
        try{if($clientId!==null){[$p,$resource]=provider_forwarding_permission($state,$row['id'],$clientId);}else{$resource=provider_forwarding_resource($row['id']);$p=$state['permissions'][$row['id']]??null;}}
        catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;continue;}
        $seen[$row['id']]=true;$out[]=['resource'=>$resource,'permission'=>$p,'context'=>provider_forwarding_token($resource),'current'=>true];
    }if($clientId===null)foreach($state['permissions'] as $linkId=>$p)if(!isset($seen[$linkId]))$out[]=['resource'=>$p['payload']['resource'],'permission'=>$p,'context'=>'','current'=>false];return $out;
}
function provider_forwarding_history(?int $clientId=null,int $page=1):array {
    provider_forwarding_actor($clientId);if(!provider_forwarding_ready())return [];$state=provider_forwarding_state();$requests=array_reverse(array_values($state['requests']));if($clientId!==null)$requests=array_values(array_filter($requests,static fn($r)=>$r['request']['client_id']===$clientId));return array_slice($requests,(max(1,min(10000,$page))-1)*20,20);
}
