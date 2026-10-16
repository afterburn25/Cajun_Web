<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/cloud_sites.php';
require_once CWP_CODE_ROOT.'/app/providers/cloud_confirmation_storage.php';

use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;

/** Stable across PDO numeric-fetch modes; no raw provider response or credential is retained. */
function provider_cloud_confirmation_source(?array $row):string {
    if($row===null)throw new RuntimeException('Original cloud request or observation history is missing. Restore the original database.');
    return provider_operations_scope('cloudconfirmation.source',array_map(static fn($v)=>$v===null?null:(string)$v,$row));
}
function provider_cloud_confirmation_request(string $id):array {
    if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('Reload the cloud confirmation form.');
    provider_cloud_sites_history_ready();$grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$id]);
    if(!$grant)throw new RuntimeException('The original cloud website approval is missing.');
    $doc=provider_cloud_sites_document($grant);$result=provider_cloud_sites_result($grant);
    if(!$result||$result['status']!=='accepted_pending'||!is_string($result['external_id'])||!preg_match('/^[1-9][0-9]{0,17}$/D',$result['external_id'])||$result['resolved_epoch']===null)throw new RuntimeException('Only an acknowledged request with an exact returned package ID can be confirmed. Unknown requests require investigation and cannot be retried here.');
    foreach(['provider.cloudsite_started','provider.cloudsite_result'] as $action)if((int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=? AND detail LIKE ?',[$action,'Cloud website request '.$id.' %'])->fetchColumn()!==1)throw new RuntimeException('Original cloud website request audits need recovery.');
    return [$grant,$doc,$result];
}

/** Evidence references retained immutable sources, never current server package membership. */
function provider_cloud_confirmation_sources(array $grant,array $doc,array $result,string $refreshId,int $packageId,int $linkEvent,string $packageLinkId=''):array {
    $b=$doc['binding'];
    $cloudIntent=one('SELECT * FROM '.table('provider_refreshes').' WHERE id=?',[$b['refresh_id']]);$cloudResult=one('SELECT * FROM '.table('provider_refresh_results').' WHERE id=?',[$b['refresh_id']]);$cloudRecord=one('SELECT * FROM '.table('provider_observation_cloud').' WHERE refresh_id=? AND server_id=?',[$b['refresh_id'],$b['server_id']]);
    if(!$cloudIntent||!$cloudResult||!$cloudRecord||$cloudIntent['profile_id']!==$grant['profile_id']||(int)$cloudIntent['profile_revision']!==$b['profile_revision']||$cloudIntent['operation']!==InventoryRead::CLOUD_SERVERS_LIST||$cloudResult['state']!=='complete'||(int)$cloudResult['skipped']!==0||$cloudResult['key_fingerprint']!==$b['key_fingerprint']||$cloudResult['scope']!=='twenty-i|'.$grant['profile_id'].'|'.InventoryRead::CLOUD_SERVERS_LIST||(int)query('SELECT COUNT(*) FROM '.table('provider_observation_cloud').' WHERE refresh_id=?',[$b['refresh_id']])->fetchColumn()!==(int)$cloudResult['package_count'])throw new RuntimeException('The original cloud observation is incomplete or belongs to different provider settings.');
    try{$server=\CajunWebPro\Foundation\TwentyIInventory\InventoryCloudServer::fromItem(json_decode($cloudRecord['metadata'],false,16,JSON_THROW_ON_ERROR));}catch(JsonException|InvalidArgumentException){throw new RuntimeException('The original cloud server observation is inconsistent.');}
    if($server->id!==$b['server_id'])throw new RuntimeException('The original cloud server observation is inconsistent.');
    $cloudEvent=one('SELECT * FROM '.table('provider_cloud_link_events').' WHERE link_id=? AND revision=? AND event IN (?,?) ORDER BY id DESC LIMIT 1',[$b['link_id'],$b['link_revision'],'link','relink']);
    if(!$cloudEvent||(int)$cloudEvent['server_id']!==$b['server_id']||(int)$cloudEvent['client_id']!==$b['client_id']||$cloudEvent['profile_id']!==$grant['profile_id']||(int)$cloudEvent['profile_revision']!==$b['profile_revision']||$cloudEvent['key_fingerprint']!==$b['key_fingerprint'])throw new RuntimeException('The original cloud assignment history is missing or inconsistent.');
    $cloudAudit='Cloud link '.$b['link_id'].' profile '.$grant['profile_id'].' server '.$b['server_id'].' client '.$b['client_id'].' revision '.$b['link_revision'];
    if((int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=? AND user_id=? AND detail=?',['provider.cloud_'.$cloudEvent['event'],$cloudEvent['actor_id'],$cloudAudit])->fetchColumn()!==1)throw new RuntimeException('The original cloud assignment audit needs recovery.');
    $intent=one('SELECT * FROM '.table('provider_refreshes').' WHERE id=?',[$refreshId]);$observation=one('SELECT * FROM '.table('provider_refresh_results').' WHERE id=?',[$refreshId]);
    foreach([$refreshId,$b['refresh_id']] as $sourceRefresh)foreach(['provider.refresh_started','provider.refresh_finished'] as $action)if((int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=? AND detail LIKE ?',[$action,'Refresh '.$sourceRefresh.' %'])->fetchColumn()!==1)throw new RuntimeException('Original hosting or cloud observation audits need recovery.');
    return [
        'request'=>$grant['scope_fingerprint'],
        'result'=>provider_cloud_confirmation_source(array_intersect_key($result,array_flip(['id','idempotency_key','fingerprint','operation','status','external_id','resolved_epoch']))),
        'cloud_assignment'=>provider_cloud_confirmation_source($cloudEvent),
        'cloud_intent'=>provider_cloud_confirmation_source($cloudIntent),
        'cloud_result'=>provider_cloud_confirmation_source($cloudResult),
        'cloud_observation'=>provider_cloud_confirmation_source($cloudRecord),
        'hosting_intent'=>provider_cloud_confirmation_source($intent),
        'hosting_result'=>provider_cloud_confirmation_source($observation),
        'package'=>provider_cloud_confirmation_source(one('SELECT * FROM '.table('provider_observation_packages').' WHERE refresh_id=? AND package_id=?',[$refreshId,$packageId])),
        'link_event'=>$linkEvent===0?'':provider_cloud_confirmation_source(one('SELECT * FROM '.table('provider_package_link_events').' WHERE id=?',[$linkEvent])),
        'link_audit'=>$linkEvent===0?'':provider_cloud_confirmation_source(provider_cloud_confirmation_link_audit(one('SELECT * FROM '.table('provider_package_link_events').' WHERE id=?',[$linkEvent]),$packageLinkId,$b['profile_revision'])),
    ];
}

/** Retained link events have no audit ID. Pair exact actor/target groups in insertion order; ambiguous histories fail closed. */
function provider_cloud_confirmation_link_audit(?array $event,string $linkId,int $revision):array {
    if(!$event||!in_array($event['event'],['link','relink'],true))throw new RuntimeException('The package assignment event needs recovery.');
    $detail=$event['event']==='link'?'Profile '.$event['profile_id'].' package '.$event['package_id'].' client '.$event['client_id']:'Link '.$linkId.' profile '.$event['profile_id'].' package '.$event['package_id'].' revision '.$revision;
    if($event['event']==='link'){
        $events=all('SELECT id FROM '.table('provider_package_link_events').' WHERE profile_id=? AND package_id=? AND client_id=? AND event=? AND actor_id=? ORDER BY id',[$event['profile_id'],$event['package_id'],$event['client_id'],'link',$event['actor_id']]);
        $audits=all('SELECT * FROM '.table('audit').' WHERE action=? AND user_id=? AND detail=? ORDER BY id',['provider.service_linked',$event['actor_id'],$detail]);
    }else{
        // Legacy relink events omit link ID/revision/client from the audit. Pair the entire
        // exact resource/actor group, then validate the selected immutable identity below.
        $events=all('SELECT id FROM '.table('provider_package_link_events').' WHERE profile_id=? AND package_id=? AND event=? AND actor_id=? ORDER BY id',[$event['profile_id'],$event['package_id'],'relink',$event['actor_id']]);
        $audits=all('SELECT * FROM '.table('audit').' WHERE action=? AND user_id=? AND detail LIKE ? ORDER BY id',['provider.service_relinked',$event['actor_id'],'Link % profile '.$event['profile_id'].' package '.$event['package_id'].' revision %']);
        foreach($audits as $audit)if(!preg_match('/^Link [a-f0-9]{32} profile '.preg_quote($event['profile_id'],'/').' package '.(int)$event['package_id'].' revision [1-9][0-9]{0,9}$/D',$audit['detail']))throw new RuntimeException('The original package assignment audit is inconsistent.');
    }
    if(count($events)!==count($audits)||$audits===[])throw new RuntimeException('The original package assignment audit is missing or ambiguous. Restore the original history.');
    foreach($events as $i=>$candidate)if((int)$candidate['id']===(int)$event['id']){if($audits[$i]['detail']!==$detail)throw new RuntimeException('The original package assignment audit identity changed.');return $audits[$i];}
    throw new RuntimeException('The original package assignment audit is missing.');
}
function provider_cloud_confirmation_link_event(array $link):array {
    $event=one('SELECT * FROM '.table('provider_package_link_events').' WHERE profile_id=? AND package_id=? ORDER BY id DESC LIMIT 1',[$link['profile_id'],$link['package_id']]);
    if(!$event||!in_array($event['event'],['link','relink'],true)||(int)$event['client_id']!==(int)$link['client_id'])throw new RuntimeException('The package assignment history needs recovery.');
    provider_cloud_confirmation_link_audit($event,$link['id'],(int)$link['profile_revision']);
    return $event;
}

/** Current review authority. Complete means every returned item validated, not account-wide coverage. */
function provider_cloud_confirmation_review(string $id):array {
    $actor=provider_settings_manager();if(!provider_cloud_confirmation_ready())throw new RuntimeException('Prepare cloud confirmation storage first.');
    [$grant,$doc,$result]=provider_cloud_confirmation_request($id);$b=$doc['binding'];
    if($grant['revoked_at']!==null)throw new RuntimeException('The original request approval was revoked.');
    [, $profile]=provider_checks_authority($grant['profile_id'],$b['profile_revision']);
    $cloudLink=provider_cloud_link_current($b['link_id'],$b['link_revision']);
    if($cloudLink['profile_id']!==$grant['profile_id']||(int)$cloudLink['client_id']!==$b['client_id']||(int)$cloudLink['server_id']!==$b['server_id']||(int)$cloudLink['profile_revision']!==$b['profile_revision']||$cloudLink['key_fingerprint']!==$b['key_fingerprint']||!hash_equals(hash('sha256',$profile['credentials']),$b['credentials_hash']))throw new RuntimeException('The original client, cloud assignment or provider configuration changed.');
    if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$b['client_id']]))throw new RuntimeException('The original client is unavailable.');
    // Intent sequence is authority even when clocks move backwards; never fall back after a later failed/unfinished refresh.
    $refresh=one('SELECT f.*,r.state,r.key_fingerprint,r.skipped,r.package_count,r.scope FROM '.table('provider_refreshes').' f LEFT JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.profile_id=? AND f.operation=? ORDER BY f.seq DESC LIMIT 1',[$grant['profile_id'],InventoryRead::PACKAGES_LIST]);
    if(!$refresh||$refresh['state']!=='complete'||(int)$refresh['skipped']!==0||(int)$refresh['profile_revision']!==$b['profile_revision']||$refresh['key_fingerprint']!==$b['key_fingerprint']||$refresh['scope']!=='twenty-i|'.$grant['profile_id'].'|'.InventoryRead::PACKAGES_LIST||(int)$refresh['started_epoch']<= (int)$result['resolved_epoch']||(int)$refresh['started_epoch']<time()-900||(int)$refresh['started_epoch']>time()+30)throw new RuntimeException('Refresh hosting inventory after the request, then review again. A complete response from the last 15 minutes under the original provider settings is required.');
    if((int)query('SELECT COUNT(*) FROM '.table('provider_observation_packages').' WHERE refresh_id=?',[$refresh['id']])->fetchColumn()!==(int)$refresh['package_count'])throw new RuntimeException('The saved hosting observation is incomplete. Restore its original records.');
    if((provider_inventory_observation($grant['profile_id'])['id']??null)!==$refresh['id'])throw new RuntimeException('The hosting observation order changed. Refresh and review again.');
    $packageId=(int)$result['external_id'];$package=one('SELECT * FROM '.table('provider_observation_packages').' WHERE refresh_id=? AND package_id=?',[$refresh['id'],$packageId]);
    if(!$package)throw new RuntimeException('The exact allocated package is absent from this hosting observation. No assignment was inferred.');
    try{$names=json_decode($package['names'],true,8,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('The allocated package names are inconsistent.');}
    $expected=[$doc['params']['domain_name'],...$doc['params']['extra_domain_names']];sort($expected);
    if(!is_array($names)||!array_is_list($names))throw new RuntimeException('The allocated package names are inconsistent.');sort($names);
    if($package['name']!==$doc['params']['domain_name']||$names!==$expected)throw new RuntimeException('The allocated package domain names do not exactly match the reviewed request.');
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=?',[$grant['profile_id'],$packageId]);$event=null;
    if($link){if((int)$link['client_id']!==$b['client_id']||(int)$link['profile_revision']!==$b['profile_revision']||$link['key_fingerprint']!==$b['key_fingerprint'])throw new RuntimeException('The allocated package has a different or stale client assignment.');$event=provider_cloud_confirmation_link_event($link);}
    $sources=provider_cloud_confirmation_sources($grant,$doc,$result,$refresh['id'],$packageId,(int)($event['id']??0),$link['id']??'');
    $review=['id'=>$id,'refresh_id'=>$refresh['id'],'actor_id'=>(int)$actor['id'],'actor_auth'=>hash('sha256',$actor['auth_version']),'cloud_link'=>provider_cloud_confirmation_source($cloudLink),'package_link'=>$link?provider_cloud_confirmation_source($link):null,'sources'=>$sources];
    return ['grant'=>$grant,'request'=>$doc,'result'=>$result,'refresh'=>$refresh,'package'=>$package,'link'=>$link,'event'=>$event,'review'=>provider_operations_scope('cloudconfirmation.review',$review)];
}

/** Local, explicit owner assignment only. No provider transport, retry, billing or health activation. */
function provider_cloud_confirmation_confirm(string $id,string $refreshId,string $review,bool $reviewed,#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Cloud confirmation requires its own transaction.');
    if(!$reviewed||!preg_match('/^[a-f0-9]{64}$/D',$review))throw new InvalidArgumentException('Review the observed package and original client before confirming.');
    staff_locked(fn()=>staff_transaction(function()use($id,$refreshId,$review,$password):void{
        $actor=provider_settings_owner($password);$context=provider_cloud_confirmation_review($id);
        if($refreshId!==$context['refresh']['id']||!hash_equals($context['review'],$review))throw new RuntimeException('The confirmation form is stale. Reload and review the current details.');
        if(one('SELECT id FROM '.table('provider_cloud_confirmations').' WHERE id=?',[$id]))throw new RuntimeException('This cloud package already has a recorded confirmation. It cannot be confirmed again.');
        $grant=$context['grant'];$request=$context['request'];$b=$request['binding'];$packageId=(int)$context['result']['external_id'];
        if(one('SELECT id FROM '.table('provider_cloud_confirmations').' WHERE profile_id=? AND package_id=?',[$grant['profile_id'],$packageId]))throw new RuntimeException('This allocated package already belongs to a different recorded confirmation.');
        $link=$context['link']??provider_inventory_link_in_transaction($grant['profile_id'],$packageId,$b['client_id'],$b['profile_revision'],$password,$refreshId);
        $event=provider_cloud_confirmation_link_event($link);$head=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_confirmation_head'])['value'];[$count,$previous]=explode(':',$head,2);
        $d=['version'=>1,'ordinal'=>(int)$count+1,'previous'=>$previous,'id'=>$id,'profile_id'=>$grant['profile_id'],'package_id'=>$packageId,'client_id'=>$b['client_id'],'refresh_id'=>$refreshId,'link_id'=>$link['id'],'actor_id'=>(int)$actor['id'],'confirmed_at'=>gmdate('Y-m-d H:i:s'),'requested_server_id'=>$b['server_id'],'link_event_id'=>(int)$event['id'],'link_snapshot'=>provider_cloud_confirmation_source($link),'sources'=>provider_cloud_confirmation_sources($grant,$request,$context['result'],$refreshId,$packageId,(int)$event['id'],$link['id'])];
        $json=provider_cloud_confirmation_json($d);if(strlen($json)>16384)throw new RuntimeException('Cloud confirmation evidence exceeds its local limit.');$fingerprint=provider_operations_scope('cloudconfirmation.evidence',$d);
        query('INSERT INTO '.table('provider_cloud_confirmations').' (id,profile_id,package_id,client_id,refresh_id,link_id,actor_id,confirmed_at,document,fingerprint) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,$grant['profile_id'],$packageId,$b['client_id'],$refreshId,$link['id'],(int)$actor['id'],$d['confirmed_at'],$json,$fingerprint]);
        provider_settings_audit((int)$actor['id'],'cloud_confirmed','Cloud confirmation '.$id.' '.$fingerprint);save_setting('provider_cloud_confirmation_head',$d['ordinal'].':'.$fingerprint);
    }));
}

/** Historical confirmation remains evidence; removing/revising its exact link removes the current assignment claim. */
function provider_cloud_confirmation_projection(string $id):?array {
    if(!provider_cloud_confirmation_ready())return null;
    $row=one('SELECT * FROM '.table('provider_cloud_confirmations').' WHERE id=?',[$id]);if(!$row)return null;
    $d=provider_cloud_confirmation_document($row);[$grant,$request,$result]=provider_cloud_confirmation_request($id);
    $sources=provider_cloud_confirmation_sources($grant,$request,$result,$row['refresh_id'],(int)$row['package_id'],$d['link_event_id'],$row['link_id']);
    if(!hash_equals(provider_operations_scope('cloudconfirmation.sources',$sources),provider_operations_scope('cloudconfirmation.sources',$d['sources'])))throw new RuntimeException('Original cloud confirmation sources changed or were lost. Restore the original history.');
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE id=?',[$row['link_id']]);
    $current=$link!==null&&hash_equals($d['link_snapshot'],provider_cloud_confirmation_source($link));
    if($current){$event=provider_cloud_confirmation_link_event($link);$current=(int)$event['id']===$d['link_event_id'];}
    $b=$request['binding'];$profile=provider_settings_load($grant['profile_id']);$cloud=one('SELECT * FROM '.table('provider_cloud_links').' WHERE id=?',[$b['link_id']]);
    $current=$current&&$grant['revoked_at']===null&&(int)$profile['enabled']===1&&(int)$profile['revision']===$b['profile_revision']&&hash_equals(hash('sha256',$profile['credentials']),$b['credentials_hash'])&&$cloud!==null&&(int)$cloud['revision']===$b['link_revision']&&(int)$cloud['client_id']===$b['client_id']&&(int)$cloud['server_id']===$b['server_id']&&$cloud['key_fingerprint']===$b['key_fingerprint']&&one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$b['client_id']])!==null;
    return ['confirmed_at'=>$row['confirmed_at'],'assigned'=>$current,'status'=>$current?'Allocated package observed and assigned':'Package was observed; assignment changed'];
}

function provider_cloud_confirmation_integrity(array $row,array $d):void {
    [$grant,$request,$result]=provider_cloud_confirmation_request($row['id']);
    $sources=provider_cloud_confirmation_sources($grant,$request,$result,$row['refresh_id'],(int)$row['package_id'],$d['link_event_id'],$row['link_id']);
    if(!hash_equals(provider_operations_scope('cloudconfirmation.sources',$sources),provider_operations_scope('cloudconfirmation.sources',$d['sources'])))throw new RuntimeException('Original cloud confirmation sources changed or were lost. Restore the original history.');
}
