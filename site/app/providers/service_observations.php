<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/app/providers/service_observations_document.php';
require_once CWP_CODE_ROOT.'/app/providers/service_observations_storage.php';
use CajunWebPro\Foundation\TwentyIInventory\{InventoryPackage,InventoryRead};
function service_observations_actor(int $clientId):void {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Sign in directly to view service history.');$client=studio_client();if(!$client||(int)$client['id']!==$clientId||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]))throw new RuntimeException('Sign in to view your service history.');
}
function service_observations_load(string $linkId):?array {
    service_observations_id($linkId);if(!service_observations_ready())return null;$last=null;$revision=0;$previous=str_repeat('0',64);$time=0;
    foreach(all('SELECT * FROM '.table('service_observation_permissions').' WHERE link_id=? ORDER BY revision',[$linkId]) as $r){
        try{$d=service_observations_document(json_decode($r['document_json'],true,8,JSON_THROW_ON_ERROR));}catch(JsonException|TypeError $e){throw new RuntimeException('Service history permission needs recovery.',0,$e);}$json=service_observations_json($d);$hash=hash('sha256',$json);
        if($json!==$r['document_json']||!hash_equals($r['document_sha256'],$hash)||$d['id']!==$r['id']||$d['link_id']!==$linkId||$d['client_id']!==(int)$r['client_id']||$d['revision']!==++$revision||(int)$r['revision']!==$revision||!hash_equals($d['previous_sha256'],$previous)||$d['recorded_epoch']<$time)throw new RuntimeException('Service history permission chain needs recovery.');
        if($last&&$d['client_id']!==$last['document']['client_id'])throw new RuntimeException('Service history assignment is inconsistent.');service_observations_validate_anchor($d);
        if((int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE user_id=? AND BINARY action=? AND BINARY detail=?',[$d['actor_id'],'service_observations.permission','Permission '.$d['id'].' link '.$linkId.' revision '.$revision.' sha256 '.$hash])->fetchColumn()!==1)throw new RuntimeException('Service history permission audit needs recovery.');$last=['document'=>$d,'sha256'=>$hash];$previous=$hash;$time=$d['recorded_epoch'];
    }return $last;
}
/** Canonical local ownership, separate from provider stack-user references. */
function service_observations_binding(string $linkId):array {
    service_observations_id($linkId);if(!provider_inventory_ready())throw new RuntimeException('Hosting inventory is unavailable.');$l=one('SELECT * FROM '.table('provider_package_links').' WHERE id=?',[$linkId]);if(!$l)throw new RuntimeException('This hosting assignment is unavailable.');
    $p=provider_settings_load($l['profile_id']);if((int)$p['enabled']!==1||$p['credentials']===''||(int)$l['profile_revision']!==(int)$p['revision']||!preg_match('/\A[a-f0-9]{64}\z/',$l['key_fingerprint'])||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$l['client_id']]))throw new RuntimeException('This hosting account or assignment needs owner confirmation.');
    $e=one('SELECT * FROM '.table('provider_package_link_events').' WHERE profile_id=? AND package_id=? ORDER BY id DESC LIMIT 1',[$l['profile_id'],$l['package_id']]);
    if(!$e||!in_array($e['event'],['link','relink'],true)||(int)$e['client_id']!==(int)$l['client_id'])throw new RuntimeException('Hosting assignment history needs recovery.');
    foreach(['link'=>['service_linked','Profile '.$l['profile_id'].' package '.$l['package_id'].' client %'],'relink'=>['service_relinked','Link % profile '.$l['profile_id'].' package '.$l['package_id'].' revision %'],'unlink'=>['service_unlinked','Link % profile '.$l['profile_id'].' package '.$l['package_id']]] as $eventKind=>[$auditAction,$pattern]){if((int)query('SELECT COUNT(*) FROM '.table('provider_package_link_events').' WHERE profile_id=? AND package_id=? AND event=?',[$l['profile_id'],$l['package_id'],$eventKind])->fetchColumn()!==(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=? AND detail LIKE ?',['provider.'.$auditAction,$pattern])->fetchColumn())throw new RuntimeException('Hosting assignment audit history needs recovery.');}
    // Events and their audit rows are appended atomically under the same mutex.
    // Pair the last event of its kind with the last resource audit, then verify
    // its exact current actor/resource identity. Timestamps cannot order these.
    $pattern=$e['event']==='link'?'Profile '.$l['profile_id'].' package '.$l['package_id'].' client %':'Link % profile '.$l['profile_id'].' package '.$l['package_id'].' revision %';$action=$e['event']==='link'?'provider.service_linked':'provider.service_relinked';
    $a=one('SELECT * FROM '.table('audit').' WHERE BINARY action=? AND detail LIKE ? ORDER BY id DESC LIMIT 1',[$action,$pattern]);$detail=$e['event']==='link'?'Profile '.$l['profile_id'].' package '.$l['package_id'].' client '.$l['client_id']:'Link '.$linkId.' profile '.$l['profile_id'].' package '.$l['package_id'].' revision '.$l['profile_revision'];
    if(!$a||(int)$a['user_id']!==(int)$e['actor_id']||$a['detail']!==$detail||($e['event']==='link'&&(int)$l['linked_by']!==(int)$e['actor_id']))throw new RuntimeException('Hosting assignment audit identity needs recovery.');
    $link=['id'=>$l['id'],'profile_id'=>$l['profile_id'],'package_id'=>(int)$l['package_id'],'client_id'=>(int)$l['client_id'],'linked_by'=>(int)$l['linked_by'],'linked_at'=>$l['linked_at'],'key_fingerprint'=>$l['key_fingerprint'],'profile_revision'=>(int)$l['profile_revision']];
    $event=['id'=>(int)$e['id'],'profile_id'=>$e['profile_id'],'package_id'=>(int)$e['package_id'],'client_id'=>(int)$e['client_id'],'event'=>$e['event'],'actor_id'=>(int)$e['actor_id'],'recorded_at'=>$e['recorded_at'],'audit_id'=>(int)$a['id']];
    return ['link'=>$link,'profile'=>$p,'link_sha256'=>hash('sha256',service_observations_json($link)),'event'=>$event,'link_event_sha256'=>hash('sha256',service_observations_json($event))];
}
function service_observations_reads(string $profileId,int $anchorSeq=0,int $before=0):array {
    return all('SELECT f.*,r.state,r.reason,r.coverage,r.package_count,r.skipped,r.key_fingerprint,r.finished_at FROM '.table('provider_refreshes').' f LEFT JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.profile_id=? AND (f.operation=? OR f.operation IS NULL) AND f.seq>=?'.($before>0?' AND f.seq<?':'').' ORDER BY f.seq DESC LIMIT 21',$before>0?[$profileId,InventoryRead::PACKAGES_LIST,$anchorSeq,$before]:[$profileId,InventoryRead::PACKAGES_LIST,$anchorSeq]);
}
/** Validate the retained source, not provider liveness. Never return sibling rows. */
function service_observations_intent_audit(array $r):array {
    $detail='Refresh '.$r['id'].' profile '.$r['profile_id'].' revision '.$r['profile_revision'];
    $rows=all('SELECT id FROM '.table('audit').' WHERE user_id=? AND BINARY action=? AND BINARY detail=?',[$r['actor_id'],'provider.refresh_started',$detail]);if(count($rows)!==1)throw new RuntimeException('Hosting observation intent history needs recovery.');return $rows[0];
}
function service_observations_source(array $r,int $packageId):array {
    service_observations_intent_audit($r);
    if($r['state']===null){if(one('SELECT id FROM '.table('audit').' WHERE BINARY action=? AND detail LIKE ?',['provider.refresh_finished','Refresh '.$r['id'].' %']))throw new RuntimeException('Hosting observation result needs recovery.');return ['id'=>$r['id'],'seq'=>(int)$r['seq'],'observed_epoch'=>(int)$r['started_epoch'],'state'=>'unfinished','package'=>null];}
    if(!in_array($r['state'],['complete','partial','unavailable','refused','access_changed','credential'],true))throw new RuntimeException('Hosting observation result needs recovery.');
    $detail='Refresh '.$r['id'].' '.$r['state'].($r['reason']!==''?' '.$r['reason']:'');
    if((int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE user_id=? AND BINARY action=? AND BINARY detail=?',[$r['actor_id'],'provider.refresh_finished',$detail])->fetchColumn()!==1)throw new RuntimeException('Hosting observation result history needs recovery.');
    $count=(int)query('SELECT COUNT(*) FROM '.table('provider_observation_packages').' WHERE refresh_id=?',[$r['id']])->fetchColumn();$observed=in_array($r['state'],['complete','partial'],true);
    if($count!==(int)$r['package_count']||(!$observed&&$count!==0)||($r['state']==='complete'&&((int)$r['skipped']!==0||$r['coverage']!=='unknown')))throw new RuntimeException('Hosting observation rows need recovery.');
    $p=one('SELECT * FROM '.table('provider_observation_packages').' WHERE refresh_id=? AND package_id=?',[$r['id'],$packageId]);$safe=null;
    if($p){
        try{if(!in_array((string)$p['enabled'],['0','1'],true))throw new RuntimeException('Invalid enabled state.');$names=json_decode($p['names'],true,8,JSON_THROW_ON_ERROR);$package=InventoryPackage::fromItem((object)['id'=>(int)$p['package_id'],'name'=>$p['name'],'names'=>$names,'enabled'=>(int)$p['enabled']===1,'created'=>$p['created_at'],'typeRef'=>(int)$p['type_ref'],'packageTypeName'=>$p['package_type_name']]);if($package->createdEpoch!==(int)$p['created_epoch'])throw new RuntimeException('Invalid created time.');}
        catch(JsonException|InvalidArgumentException|TypeError $e){throw new RuntimeException('Hosting observation fields need recovery.',0,$e);}
        $safe=['name'=>$package->name,'names'=>$package->names,'enabled'=>$package->enabled,'type_ref'=>$package->typeRef,'package_type_name'=>$package->packageTypeName];
    }
    return ['id'=>$r['id'],'seq'=>(int)$r['seq'],'observed_epoch'=>(int)$r['started_epoch'],'state'=>$r['state'],'package'=>$safe];
}
function service_observations_anchor_hash(array $r,array $source):string {return hash('sha256',service_observations_json(['profile_id'=>$r['profile_id'],'profile_revision'=>(int)$r['profile_revision'],'key_fingerprint'=>$r['key_fingerprint'],'intent_audit_id'=>(int)service_observations_intent_audit($r)['id'],'source'=>$source]));}
function service_observations_validate_anchor(array $d):void {
    if(!provider_inventory_ready())throw new RuntimeException('Hosting observation storage needs recovery.');$anchor=one('SELECT f.*,r.state,r.reason,r.coverage,r.package_count,r.skipped,r.key_fingerprint,r.finished_at FROM '.table('provider_refreshes').' f LEFT JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.id=?',[$d['anchor_id']]);
    if(!$anchor||(int)$anchor['seq']!==$d['anchor_seq']||$anchor['profile_id']!==$d['profile_id']||(int)$anchor['profile_revision']!==$d['profile_revision']||!in_array($anchor['operation'],[null,InventoryRead::PACKAGES_LIST],true)||!hash_equals($d['anchor_sha256'],service_observations_anchor_hash($anchor,service_observations_source($anchor,$d['package_id']))))throw new RuntimeException('The original shared hosting observation needs recovery.');
}
function service_observations_fresh(array $r):bool {return $r['state']==='complete'&&(int)$r['started_epoch']>=time()-900&&(int)$r['started_epoch']<=time()+30;}
function service_observations_change(string $linkId,string $expectedId,string $eventId,bool $enabled,string $refreshId,#[SensitiveParameter] string $password):array {
    return staff_locked(fn()=>staff_transaction(function()use($linkId,$expectedId,$eventId,$enabled,$refreshId,$password):array{
        $actor=provider_settings_owner($password);service_observations_id($eventId);if(!service_observations_ready())throw new RuntimeException('Enable service history storage first.');$old=service_observations_load($linkId);$previous=$old['document']??null;
        if($previous&&$previous['id']===$eventId){if($previous['enabled']!==$enabled||($enabled&&$previous['anchor_id']!==$refreshId))throw new RuntimeException('This permission identifier was already used.');return $old;}
        if(($previous['id']??'')!==$expectedId)throw new RuntimeException('This permission changed. Reload the owner form.');
        if($enabled){$b=service_observations_binding($linkId);$l=$b['link'];$r=service_observations_reads($l['profile_id'])[0]??null;if(!$r||$r['id']!==$refreshId||!service_observations_fresh($r)||!provider_inventory_link_context_matches($l,$b['profile'],$r))throw new RuntimeException('Refresh hosting inventory and review the current assignment before sharing its history.');if((int)service_observations_intent_audit($r)['id']<=$b['event']['audit_id'])throw new RuntimeException('Refresh hosting inventory after this assignment or reapproval before sharing its history.');$source=service_observations_source($r,$l['package_id']);if($source['package']===null)throw new RuntimeException('The assigned package is absent from the current observation.');
            $binding=['client_id'=>$l['client_id'],'profile_id'=>$l['profile_id'],'profile_revision'=>$l['profile_revision'],'package_id'=>$l['package_id'],'key_fingerprint'=>$l['key_fingerprint'],'link_sha256'=>$b['link_sha256'],'link_event_id'=>$b['event']['id'],'link_event_sha256'=>$b['link_event_sha256'],'anchor_id'=>$r['id'],'anchor_seq'=>(int)$r['seq'],'anchor_sha256'=>service_observations_anchor_hash($r,$source)];
        }else{if(!$previous)throw new RuntimeException('No sharing permission exists for this assignment.');$binding=array_slice($previous,8,null,true);}
        $d=service_observations_document(array_merge(['version'=>1,'id'=>$eventId,'link_id'=>$linkId,'revision'=>($previous['revision']??0)+1,'enabled'=>$enabled,'actor_id'=>(int)$actor['id'],'recorded_epoch'=>max(time(),$previous['recorded_epoch']??0),'previous_sha256'=>$old['sha256']??str_repeat('0',64)],$binding));$json=service_observations_json($d);$hash=hash('sha256',$json);
        query('INSERT INTO '.table('service_observation_permissions').' (id,link_id,client_id,revision,document_json,document_sha256) VALUES (?,?,?,?,?,?)',[$eventId,$linkId,$d['client_id'],$d['revision'],$json,$hash]);service_observations_audit((int)$actor['id'],'permission','Permission '.$eventId.' link '.$linkId.' revision '.$d['revision'].' sha256 '.$hash);$count=(int)one('SELECT value FROM '.table('settings').' WHERE name=?',['service_observations_count'])['value'];save_setting('service_observations_count',(string)($count+1));return service_observations_load($linkId);
    }));
}
/** Caller holds the shared lock/transaction and checks client or owner identity. */
function service_observations_project(string $linkId,int $before=0):array {
    $policy=service_observations_load($linkId);if(!$policy||!$policy['document']['enabled'])return ['status'=>'not_shared','rows'=>[],'next'=>0];$d=$policy['document'];
    try{$b=service_observations_binding($linkId);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;return ['status'=>'assignment_changed','rows'=>[],'next'=>0];}
    if(!hash_equals($d['link_sha256'],$b['link_sha256'])||$d['link_event_id']!==$b['event']['id']||!hash_equals($d['link_event_sha256'],$b['link_event_sha256']))return ['status'=>'assignment_changed','rows'=>[],'next'=>0];
    $intentCount=(int)query('SELECT COUNT(*) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$d['profile_id']])->fetchColumn();$auditCount=(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=? AND detail LIKE ?',['provider.refresh_started','Refresh % profile '.$d['profile_id'].' revision %'])->fetchColumn();if($intentCount!==$auditCount)throw new RuntimeException('Hosting observation intent history needs recovery.');
    $latest=service_observations_reads($d['profile_id'])[0]??null;if(!$latest||(int)$latest['seq']<$d['anchor_seq'])throw new RuntimeException('Hosting observation sequence needs recovery.');
    if((int)$latest['profile_revision']!==$d['profile_revision']||($latest['key_fingerprint']!==null&&$latest['key_fingerprint']!==''&&!hash_equals($d['key_fingerprint'],$latest['key_fingerprint'])))return ['status'=>'assignment_changed','rows'=>[],'next'=>0];
    $current=service_observations_source($latest,$d['package_id']);$status=$current['state'];if($status==='complete')$status=$current['package']===null?'absent':(service_observations_fresh($latest)?'observed':'stale');
    $raw=service_observations_reads($d['profile_id'],$d['anchor_seq'],max(0,$before));$rows=[];$next=0;
    foreach($raw as $i=>$r){if($i===20){$next=(int)$raw[19]['seq'];break;}if((int)$r['profile_revision']!==$d['profile_revision']||($r['key_fingerprint']!==null&&$r['key_fingerprint']!==''&&!hash_equals($d['key_fingerprint'],$r['key_fingerprint'])))throw new RuntimeException('Hosting observation context needs recovery.');$rows[]=service_observations_source($r,$d['package_id']);}
    return ['status'=>$status,'rows'=>$rows,'next'=>$next,'latest_epoch'=>$current['observed_epoch']];
}
function service_observations_client(int $clientId,string $linkId='',int $before=0):array {
    return staff_locked(fn()=>staff_transaction(function()use($clientId,$linkId,$before):array{service_observations_actor($clientId);if(!service_observations_ready()||!provider_inventory_ready())return [];$out=[];
        foreach(all('SELECT id FROM '.table('provider_package_links').' WHERE client_id=?'.($linkId!==''?' AND id=?':'').' ORDER BY id',$linkId!==''?[$clientId,$linkId]:[$clientId]) as $l){$p=service_observations_load($l['id']);if(!$p||!$p['document']['enabled']||$p['document']['client_id']!==$clientId)continue;$out[]=['link_id'=>$l['id'],'history'=>service_observations_project($l['id'],$before)];}return $out;
    }));
}
