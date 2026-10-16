<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/app/providers/service_observations.php';
require_once CWP_CODE_ROOT.'/app/providers/directory_listing_storage.php';
use CajunWebPro\Foundation\TwentyIDirectoryListing\{DirectoryListingBinding,DirectoryListingObservation,DirectoryListingReader};
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;
foreach(['DirectoryListingBinding','DirectoryListingObservation','DirectoryListingReader'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-directory-listing/'.$class.'.php';

function provider_directory_indexing_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/',$id))throw new InvalidArgumentException('Reload the Directory listing observation form.');}
function provider_directory_indexing_row_hash(?array $row):string {if(!$row)throw new RuntimeException('Directory listing observation source history is missing.');return hash('sha256',provider_directory_indexing_json(array_map(static fn($v)=>$v===null?null:(string)$v,$row)));}
function provider_directory_indexing_hmac(string $kind,array $values):string {return hash_hmac('sha256','package.directory-indexing|'.$kind.'|'.provider_directory_indexing_json($values),provider_settings_vault_key());}
function provider_directory_indexing_intent_values(array $f):array {$out=[];foreach(['id','profile_id','profile_revision','actor_id','started_at','started_epoch','operation','seq'] as $key)$out[$key]=(string)$f[$key];return $out;}
/** Fixed-size actor/resource reconciliation includes later unlinks and reapprovals. */
function provider_directory_indexing_link_coverage(string $profileId,int $packageId):void {
    $e=table('provider_package_link_events');$a=table('audit');
    if(one("SELECT id FROM $e WHERE BINARY profile_id=? AND package_id=? AND BINARY event NOT IN ('link','relink','unlink') LIMIT 1",[$profileId,$packageId]))throw new RuntimeException('Directory listing package assignment history needs recovery.');
    foreach(['link'=>['provider.service_linked','Profile '.$profileId.' package '.$packageId.' client %'],'relink'=>['provider.service_relinked','Link % profile '.$profileId.' package '.$packageId.' revision %'],'unlink'=>['provider.service_unlinked','Link % profile '.$profileId.' package '.$packageId]] as $kind=>[$action,$pattern]){
        $event="BINARY e.profile_id=? AND e.package_id=? AND BINARY e.event=?";$audit='BINARY a.action=? AND BINARY a.detail LIKE ?';
        if(one("SELECT e.id FROM $e e WHERE $event AND (SELECT COUNT(*) FROM $e x WHERE BINARY x.profile_id=? AND x.package_id=? AND BINARY x.event=? AND x.actor_id=e.actor_id)<>(SELECT COUNT(*) FROM $a a WHERE $audit AND a.user_id=e.actor_id) LIMIT 1",[$profileId,$packageId,$kind,$profileId,$packageId,$kind,$action,$pattern])||one("SELECT a.id FROM $a a WHERE $audit AND (SELECT COUNT(*) FROM $a x WHERE BINARY x.action=? AND BINARY x.detail LIKE ? AND x.user_id=a.user_id)<>(SELECT COUNT(*) FROM $e e WHERE $event AND e.actor_id=a.user_id) LIMIT 1",[$action,$pattern,$action,$pattern,$profileId,$packageId,$kind]))throw new RuntimeException('Directory listing package assignment audits need recovery.');
    }
}
function provider_directory_indexing_assignment(string $linkId):array {
    provider_directory_indexing_id($linkId);$link=one('SELECT * FROM '.table('provider_package_links').' WHERE id=?',[$linkId]);if(!$link)throw new RuntimeException('This hosting assignment is unavailable.');provider_directory_indexing_link_coverage($link['profile_id'],(int)$link['package_id']);
    $b=service_observations_binding($linkId);$event=one('SELECT * FROM '.table('provider_package_link_events').' WHERE id=?',[$b['event']['id']]);$audit=one('SELECT * FROM '.table('audit').' WHERE id=?',[$b['event']['audit_id']]);
    return ['link'=>$link,'profile'=>$b['profile'],'event'=>$event,'audit'=>$audit,'link_sha256'=>provider_directory_indexing_row_hash($link),'event_sha256'=>provider_directory_indexing_row_hash($event),'audit_sha256'=>provider_directory_indexing_row_hash($audit)];
}
function provider_directory_indexing_package_source(string $id,int $packageId):array {
    provider_directory_indexing_id($id);$r=one('SELECT f.*,r.state,r.reason,r.scope,r.coverage,r.package_count,r.skipped,r.skip_reasons,r.finished_at,r.elapsed_ms,r.key_fingerprint FROM '.table('provider_refreshes').' f JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.id=?',[$id]);
    if(!$r||!in_array($r['operation'],[null,InventoryRead::PACKAGES_LIST],true)||$r['state']!=='complete'||(int)$r['skipped']!==0||$r['scope']!=='twenty-i|'.$r['profile_id'].'|'.InventoryRead::PACKAGES_LIST)throw new RuntimeException('The exact hosting observation needs recovery.');
    $source=service_observations_source($r,$packageId);if($source['package']===null)throw new RuntimeException('The assigned package is absent from the hosting observation.');
    $start=one('SELECT * FROM '.table('audit').' WHERE user_id=? AND BINARY action=? AND BINARY detail=?',[$r['actor_id'],'provider.refresh_started','Refresh '.$id.' profile '.$r['profile_id'].' revision '.$r['profile_revision']]);
    $finish=one('SELECT * FROM '.table('audit').' WHERE user_id=? AND BINARY action=? AND BINARY detail=?',[$r['actor_id'],'provider.refresh_finished','Refresh '.$id.' complete']);
    if(!$start||!$finish)throw new RuntimeException('The exact hosting observation audit needs recovery.');
    return ['row'=>$r,'start'=>$start,'sha256'=>hash('sha256',provider_directory_indexing_json(['row'=>provider_directory_indexing_row_hash($r),'package'=>$source['package'],'start'=>provider_directory_indexing_row_hash($start),'finish'=>provider_directory_indexing_row_hash($finish)]))];
}
/** A surviving common audit must never be ignored when a later package intent is lost. */
function provider_directory_indexing_common_history(string $profileId):void {
    $f=table('provider_refreshes');$a=table('audit');$pattern='Refresh % profile '.$profileId.' revision %';
    $intents=(int)query("SELECT COUNT(*) FROM $f WHERE BINARY profile_id=?",[$profileId])->fetchColumn();$audits=(int)query("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.refresh_started' AND BINARY detail LIKE ?",[$pattern])->fetchColumn();
    if($intents!==$audits)throw new RuntimeException('Hosting observation intent history needs recovery.');
    $detail="CONCAT(BINARY 'Refresh ',f.id,' profile ',f.profile_id,' revision ',f.profile_revision)";
    if(one("SELECT f.id FROM $f f WHERE BINARY f.profile_id=? AND ((SELECT COUNT(*) FROM $a a WHERE BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail AND a.user_id=f.actor_id)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail)<>1) LIMIT 1",[$profileId]))throw new RuntimeException('Hosting observation intent correspondence needs recovery.');
    // Common start-audit order independently witnesses which package intent came last.
    $package="BINARY f.profile_id=? AND (f.operation IS NULL OR BINARY f.operation=?)";
    $bySequence=one("SELECT f.id FROM $f f WHERE $package ORDER BY f.seq DESC LIMIT 1",[$profileId,InventoryRead::PACKAGES_LIST]);
    $byAudit=one("SELECT f.id FROM $f f JOIN $a a ON a.user_id=f.actor_id AND BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail WHERE $package ORDER BY a.id DESC LIMIT 1",[$profileId,InventoryRead::PACKAGES_LIST]);
    if(($bySequence['id']??null)!==($byAudit['id']??null))throw new RuntimeException('Hosting observation intent order needs recovery.');

}
function provider_directory_indexing_binding(string $linkId):DirectoryListingBinding {
    $a=provider_directory_indexing_assignment($linkId);$l=$a['link'];provider_directory_indexing_common_history($l['profile_id']);$latest=service_observations_reads($l['profile_id'])[0]??null;
    if(!$latest||!service_observations_fresh($latest)||!provider_inventory_link_context_matches($l,$a['profile'],$latest))throw new RuntimeException('Refresh hosting inventory for this exact assignment first.');
    $source=provider_directory_indexing_package_source($latest['id'],(int)$l['package_id']);if((int)$source['start']['id']<=(int)$a['audit']['id'])throw new RuntimeException('Refresh hosting inventory after this assignment or reapproval.');
    return new DirectoryListingBinding(['profile_id'=>$l['profile_id'],'profile_revision'=>(int)$l['profile_revision'],'key_fingerprint'=>$l['key_fingerprint'],'credential_sha256'=>hash('sha256',$a['profile']['credentials']),'package_id'=>(int)$l['package_id'],'client_id'=>(int)$l['client_id'],'link_id'=>$l['id'],'link_sha256'=>$a['link_sha256'],'event_id'=>(int)$a['event']['id'],'event_sha256'=>$a['event_sha256'],'audit_id'=>(int)$a['audit']['id'],'audit_sha256'=>$a['audit_sha256'],'source_id'=>$latest['id'],'source_seq'=>(int)$latest['seq'],'source_sha256'=>$source['sha256']]);
}
/** Retained original source validation does not require a mutable link still to have its old value. */
function provider_directory_indexing_retained_binding(DirectoryListingBinding $b):void {
    $b=$b->validated();$d=$b->data;$source=provider_directory_indexing_package_source($d['source_id'],$d['package_id']);$r=$source['row'];
    if($r['profile_id']!==$d['profile_id']||(int)$r['profile_revision']!==$d['profile_revision']||(int)$r['seq']!==$d['source_seq']||!hash_equals($d['key_fingerprint'],$r['key_fingerprint'])||!hash_equals($d['source_sha256'],$source['sha256'])||!hash_equals($d['event_sha256'],provider_directory_indexing_row_hash(one('SELECT * FROM '.table('provider_package_link_events').' WHERE id=?',[$d['event_id']])))||!hash_equals($d['audit_sha256'],provider_directory_indexing_row_hash(one('SELECT * FROM '.table('audit').' WHERE id=?',[$d['audit_id']]))))throw new RuntimeException('The original Directory listing observation authority needs recovery.');
}
function provider_directory_indexing_assignment_matches(DirectoryListingBinding $b):bool {
    $b=$b->validated();
    $a=provider_directory_indexing_assignment($b->data['link_id']);return hash_equals($b->data['credential_sha256'],hash('sha256',$a['profile']['credentials']))&&hash_equals($b->data['link_sha256'],$a['link_sha256'])&&$b->data['event_id']===(int)$a['event']['id']&&hash_equals($b->data['event_sha256'],$a['event_sha256'])&&$b->data['audit_id']===(int)$a['audit']['id']&&hash_equals($b->data['audit_sha256'],$a['audit_sha256']);
}
/** Must be invoked by the shared executor before secret/transport and after the response. */
function provider_directory_indexing_authority(DirectoryListingBinding $expected):void {
    $expected=$expected->validated();
    provider_settings_manager();if(!provider_directory_indexing_ready())throw new RuntimeException('Prepare Directory listing observation storage first.');$current=provider_directory_indexing_binding($expected->data['link_id']);if(!hash_equals($expected->sha256,$current->sha256))throw new RuntimeException('The Directory listing observation assignment or hosting read changed.');
}
function provider_directory_indexing_started(array $started,DirectoryListingBinding $binding):void {
    provider_directory_indexing_guard();$binding=$binding->validated();$f=one('SELECT * FROM '.table('provider_refreshes').' WHERE id=?',[$started['id']]);if(!$f||$f['operation']!==DirectoryListingReader::OPERATION||$f['profile_id']!==$binding->data['profile_id']||(int)$f['profile_revision']!==$binding->data['profile_revision'])throw new LogicException('Directory listing intent does not match its binding.');
    foreach(['id','profile_id','profile_revision','actor_id','started_at','started_epoch','operation'] as $key)if(!array_key_exists($key,$started)||(string)$f[$key]!==(string)$started[$key])throw new LogicException('Directory listing intent context changed.');
    $b=['id'=>$f['id'],'link_id'=>$binding->data['link_id'],'profile_id'=>$binding->data['profile_id'],'package_id'=>$binding->data['package_id'],'client_id'=>$binding->data['client_id'],'binding_json'=>$binding->json,'binding_sha256'=>$binding->sha256];$b['proof']=provider_directory_indexing_hmac('intent',[provider_directory_indexing_intent_values($f),$b]);
    query('INSERT INTO '.table('provider_directory_indexing_reads').' (id,link_id,profile_id,package_id,client_id,binding_json,binding_sha256,proof) VALUES (?,?,?,?,?,?,?,?)',array_values($b));provider_settings_audit((int)$f['actor_id'],'directory_indexing_started','Directory listing read '.$f['id'].' started '.provider_directory_indexing_digest(provider_directory_indexing_start_values($f,$b)));provider_directory_indexing_advance('started');
}
function provider_directory_indexing_read_binding(array $f,array $b):DirectoryListingBinding {
    if(strlen($b['binding_json'])>8192)throw new RuntimeException('Directory listing observation binding needs recovery.');
    try{$binding=new DirectoryListingBinding(json_decode($b['binding_json'],true,8,JSON_THROW_ON_ERROR));}catch(JsonException|TypeError|InvalidArgumentException $e){throw new RuntimeException('Directory listing observation binding needs recovery.',0,$e);}
    $identity=['id'=>$f['id'],'link_id'=>$binding->data['link_id'],'profile_id'=>$binding->data['profile_id'],'package_id'=>$binding->data['package_id'],'client_id'=>$binding->data['client_id'],'binding_json'=>$binding->json,'binding_sha256'=>$binding->sha256];
    if($binding->json!==$b['binding_json']||$binding->sha256!==$b['binding_sha256']||$b['link_id']!==$identity['link_id']||$b['profile_id']!==$identity['profile_id']||(int)$b['package_id']!==$identity['package_id']||(int)$b['client_id']!==$identity['client_id']||!hash_equals($b['proof'],provider_directory_indexing_hmac('intent',[provider_directory_indexing_intent_values($f),$identity])))throw new RuntimeException('Directory listing observation intent proof needs recovery.');
    provider_directory_indexing_retained_binding($binding);return $binding;
}
function provider_directory_indexing_source(string $id):?array {return one('SELECT f.*,r.state,r.reason,r.scope,r.coverage,r.package_count,r.skipped,r.skip_reasons,r.finished_at,r.elapsed_ms,r.key_fingerprint FROM '.table('provider_refreshes').' f LEFT JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.id=? AND BINARY f.operation=?',[$id,DirectoryListingReader::OPERATION]);}
/** The existing recorder owns the result transaction; never writes a provider-operation result. */
function provider_directory_indexing_record(array $started,?DirectoryListingObservation $observation,string $fingerprint):void {
    provider_directory_indexing_guard();$observation=$observation?->validated();$r=provider_directory_indexing_source($started['id']);$b=one('SELECT * FROM '.table('provider_directory_indexing_reads').' WHERE id=?',[$started['id']]);if(!$r||!$b||$r['state']===null)throw new LogicException('Directory listing result requires its exact retained intent.');$binding=provider_directory_indexing_read_binding($r,$b);$p=null;
    foreach(['id','profile_id','profile_revision','actor_id','started_at','started_epoch','operation'] as $key)if((string)$r[$key]!==(string)$started[$key])throw new LogicException('Directory listing result context changed.');
    if(!in_array($r['state'],['complete','unavailable','refused','access_changed','credential'],true)||$r['key_fingerprint']!==$fingerprint||$r['coverage']!=='unknown'||(int)$r['package_count']!==0||(int)$r['skipped']!==0||$r['skip_reasons']!=='[]')throw new LogicException('Directory listing result metadata is inconsistent.');
    if($r['state']==='complete'){
        if(!$observation||$observation->state!=='complete'||$r['scope']!==$binding->scope||$r['reason']!==''||!hash_equals($binding->sha256,$observation->binding->sha256)||!hash_equals($binding->data['key_fingerprint'],$fingerprint)||$observation->observedAt<(int)$r['started_epoch']||$observation->observedAt>time()+30)throw new LogicException('Directory listing result does not match its exact intent.');
        $p=['id'=>$r['id'],'value_json'=>json_encode($observation->value,JSON_THROW_ON_ERROR),'observed_epoch'=>$observation->observedAt];$p['proof']=provider_directory_indexing_hmac('value',[provider_directory_indexing_result_values($r),$b['proof'],$p]);query('INSERT INTO '.table('provider_directory_indexing_observations').' (id,value_json,observed_epoch,proof) VALUES (?,?,?,?)',array_values($p));
    }elseif($observation!==null)throw new LogicException('A failed Directory listing read cannot retain a value.');
    provider_settings_audit((int)$r['actor_id'],'directory_indexing_finished','Directory listing read '.$r['id'].' finished '.provider_directory_indexing_digest(provider_directory_indexing_finish_values($r,$b,$p)));provider_directory_indexing_advance('finished');
}
function provider_directory_indexing_refresh(string $linkId,DirectoryListingBinding $expected,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    $expected=$expected->validated();
    if($linkId!==$expected->data['link_id'])throw new InvalidArgumentException('Reload the Directory listing observation form.');
    return provider_inventory_refresh_attempt($expected->data['profile_id'],$expected->data['profile_revision'],$password,$resolver,$transport,operation:DirectoryListingReader::OPERATION,scopeAuthority:static fn()=>provider_directory_indexing_authority($expected),directoryIndexingContext:$expected);
}
function provider_directory_indexing_latest(string $linkId,bool $complete=false):?array {
    return one('SELECT f.*,r.state,r.reason,r.scope,r.coverage,r.package_count,r.skipped,r.skip_reasons,r.finished_at,r.elapsed_ms,r.key_fingerprint FROM '.table('provider_directory_indexing_reads').' b JOIN '.table('provider_refreshes').' f ON f.id=b.id LEFT JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE b.link_id=? AND BINARY f.operation=?'.($complete?' AND BINARY r.state=\'complete\'':'').' ORDER BY f.seq DESC LIMIT 1',[$linkId,DirectoryListingReader::OPERATION]);
}
function provider_directory_indexing_observation(array $r):array {
    $b=one('SELECT * FROM '.table('provider_directory_indexing_reads').' WHERE id=?',[$r['id']]);$p=one('SELECT * FROM '.table('provider_directory_indexing_observations').' WHERE id=?',[$r['id']]);if(!$b||!$p)throw new RuntimeException('The saved Directory listing observation is missing.');$binding=provider_directory_indexing_read_binding($r,$b);
    if($r['state']!=='complete'||$r['reason']!==''||$r['scope']!==$binding->scope||$r['coverage']!=='unknown'||(int)$r['package_count']!==0||(int)$r['skipped']!==0||$r['skip_reasons']!=='[]'||!hash_equals($binding->data['key_fingerprint'],$r['key_fingerprint']))throw new RuntimeException('The saved Directory listing observation context is inconsistent.');
    try{$value=json_decode($p['value_json'],false,4,JSON_THROW_ON_ERROR);if(!is_bool($value))throw new InvalidArgumentException('Expected a reported boolean.');}catch(JsonException|InvalidArgumentException $e){throw new RuntimeException('The saved Directory listing observation value is inconsistent.',0,$e);}
    $document=['id'=>$r['id'],'value_json'=>json_encode($value,JSON_THROW_ON_ERROR),'observed_epoch'=>(int)$p['observed_epoch']];
    if($document['value_json']!==$p['value_json']||$document['observed_epoch']<(int)$r['started_epoch']||!hash_equals($p['proof'],provider_directory_indexing_hmac('value',[provider_directory_indexing_result_values($r),$b['proof'],$document])))throw new RuntimeException('The saved Directory listing observation proof needs recovery.');
    return ['id'=>$r['id'],'seq'=>(int)$r['seq'],'value'=>$value,'observed_epoch'=>$document['observed_epoch'],'binding'=>$binding,'proof'=>$p['proof']];
}
function provider_directory_indexing_permission(string $linkId):?array {
    provider_directory_indexing_id($linkId);$row=one('SELECT * FROM '.table('provider_directory_indexing_permissions').' WHERE link_id=? ORDER BY revision DESC LIMIT 1',[$linkId]);if(!$row)return null;
    if(strlen($row['document_json'])>16384)throw new RuntimeException('Directory listing sharing document needs recovery.');
    try{$d=json_decode($row['document_json'],true,12,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new RuntimeException('Directory listing sharing document needs recovery.',0,$e);}
    $keys=['version','id','link_id','client_id','revision','actor_id','recorded_epoch','previous_sha256','enabled','binding','anchor_id','anchor_proof'];
    if(!is_array($d)||array_keys($d)!==$keys||$d['version']!==1||!is_bool($d['enabled'])||!is_int($d['recorded_epoch'])||$d['recorded_epoch']<0||provider_directory_indexing_json($d)!==$row['document_json'])throw new RuntimeException('Directory listing sharing document needs recovery.');
    foreach(['id','link_id','previous_sha256'] as $key)if($d[$key]!==$row[$key])throw new RuntimeException('Directory listing sharing identity needs recovery.');foreach(['client_id','revision','actor_id'] as $key)if(!is_int($d[$key])||$d[$key]!== (int)$row[$key])throw new RuntimeException('Directory listing sharing identity needs recovery.');
    try{$binding=new DirectoryListingBinding($d['binding']);}catch(TypeError|InvalidArgumentException $e){throw new RuntimeException('Directory listing sharing authority needs recovery.',0,$e);}
    if($binding->data['link_id']!==$linkId||$binding->data['client_id']!==$d['client_id'])throw new RuntimeException('Directory listing sharing assignment needs recovery.');provider_directory_indexing_id($d['anchor_id']);$anchor=provider_directory_indexing_source($d['anchor_id']);if(!$anchor)throw new RuntimeException('Original shared Directory listing observation is missing.');$observed=provider_directory_indexing_observation($anchor);
    if($observed['binding']->sha256!==$binding->sha256||!is_string($d['anchor_proof'])||!hash_equals($observed['proof'],$d['anchor_proof']))throw new RuntimeException('Original shared Directory listing observation changed.');return ['document'=>$d,'sha256'=>$row['document_sha256'],'binding'=>$binding,'anchor'=>$observed];
}
function provider_directory_indexing_share(string $linkId,string $expectedId,string $eventId,bool $enabled,string $anchorId,#[SensitiveParameter] string $password):array {
    return staff_locked(fn()=>staff_transaction(function()use($linkId,$expectedId,$eventId,$enabled,$anchorId,$password):array{
        $actor=provider_settings_owner($password);provider_directory_indexing_id($eventId);if(!provider_directory_indexing_ready())throw new RuntimeException('Prepare Directory listing observations first.');$old=provider_directory_indexing_permission($linkId);$previous=$old['document']??null;
        if(($previous['id']??'')===$eventId){if($previous['enabled']!==$enabled||($enabled&&$previous['anchor_id']!==$anchorId))throw new RuntimeException('This sharing identifier was already used.');return $old;}
        if(($previous['id']??'')!==$expectedId||one('SELECT id FROM '.table('provider_directory_indexing_permissions').' WHERE id=?',[$eventId]))throw new RuntimeException('Sharing changed. Reload the owner form.');
        if($enabled){$binding=provider_directory_indexing_binding($linkId);$r=provider_directory_indexing_latest($linkId);if(!$r||$r['id']!==$anchorId||!service_observations_fresh($r))throw new RuntimeException('Refresh and review this exact Directory listing observation before sharing.');$anchor=provider_directory_indexing_observation($r);if(!hash_equals($anchor['binding']->sha256,$binding->sha256))throw new RuntimeException('The Directory listing observation authority changed. Refresh before sharing.');}
        else{if(!$old)throw new RuntimeException('No Directory listing sharing permission exists.');$binding=$old['binding'];$anchor=$old['anchor'];}
        $d=['version'=>1,'id'=>$eventId,'link_id'=>$linkId,'client_id'=>$binding->data['client_id'],'revision'=>($previous['revision']??0)+1,'actor_id'=>(int)$actor['id'],'recorded_epoch'=>max(time(),$previous['recorded_epoch']??0),'previous_sha256'=>$old['sha256']??str_repeat('0',64),'enabled'=>$enabled,'binding'=>$binding->data,'anchor_id'=>$anchor['id'],'anchor_proof'=>$anchor['proof']];
        $json=provider_directory_indexing_json($d);$row=['id'=>$eventId,'link_id'=>$linkId,'client_id'=>$d['client_id'],'revision'=>$d['revision'],'actor_id'=>$d['actor_id'],'previous_sha256'=>$d['previous_sha256'],'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];
        query('INSERT INTO '.table('provider_directory_indexing_permissions').' (id,link_id,client_id,revision,actor_id,previous_sha256,document_json,document_sha256) VALUES (?,?,?,?,?,?,?,?)',array_values($row));provider_settings_audit($d['actor_id'],'directory_indexing_permission','Directory listing permission '.$eventId.' '.provider_directory_indexing_digest(provider_directory_indexing_permission_values($row)));provider_directory_indexing_advance('permission');return provider_directory_indexing_permission($linkId);
    }));
}
/** Internal saved projection. Caller owns client/owner authority, lock and transaction. */
function provider_directory_indexing_project(string $linkId,bool $clientVisible):array {
    provider_directory_indexing_guard();if(!provider_directory_indexing_ready())return ['status'=>'not_ready','rows'=>[],'permission'=>null];
    require_once CWP_CODE_ROOT.'/app/providers/directory_listing_controls.php';
    $controlCurrent=provider_directory_indexing_controls_current($linkId);$permission=provider_directory_indexing_permission($linkId);
    if($clientVisible&&(!$permission||!$permission['document']['enabled']))return ['status'=>'not_shared','rows'=>[],'permission'=>null];
    if($clientVisible&&!provider_directory_indexing_assignment_matches($permission['binding']))return ['status'=>'assignment_changed','rows'=>[],'permission'=>null];
    $latest=provider_directory_indexing_latest($linkId);$saved=provider_directory_indexing_latest($linkId,true);$observation=$saved?provider_directory_indexing_observation($saved):null;$status='not_observed';$rows=[];
    if($latest){$bindingRow=one('SELECT * FROM '.table('provider_directory_indexing_reads').' WHERE id=?',[$latest['id']]);if(!$bindingRow)throw new RuntimeException('Directory listing observation intent needs recovery.');provider_directory_indexing_read_binding($latest,$bindingRow);$status=$latest['state']??'unfinished';}
    if($observation){
        if(!provider_directory_indexing_assignment_matches($observation['binding']))return ['status'=>'assignment_changed','rows'=>[],'permission'=>$clientVisible?null:$permission];
        if(!$clientVisible||($observation['seq']>=$permission['anchor']['seq']&&$observation['binding']->data['link_sha256']===$permission['binding']->data['link_sha256']&&$observation['binding']->data['event_id']===$permission['binding']->data['event_id'])){
            $current=$controlCurrent&&$latest['id']===$saved['id']&&service_observations_fresh($saved)&&$observation['observed_epoch']<=time()+30;
            if($current){try{$now=provider_directory_indexing_binding($linkId);$current=$now->data['link_sha256']===$observation['binding']->data['link_sha256']&&$now->data['event_id']===$observation['binding']->data['event_id'];}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$current=false;}}
            if($latest['id']===$saved['id'])$status=$current?'observed':'stale';$rows[]=['value'=>$observation['value'],'observed_epoch'=>$observation['observed_epoch'],'current'=>$current];
        }
    }
    return ['status'=>$status,'rows'=>$rows,'permission'=>$clientVisible?null:$permission,'latest_id'=>$latest['id']??''];
}
function provider_directory_indexing_client(int $clientId,string $linkId):array {
    return staff_locked(fn()=>staff_transaction(function()use($clientId,$linkId):array{
        service_observations_actor($clientId);provider_directory_indexing_id($linkId);if(!provider_inventory_ready()||!one('SELECT id FROM '.table('provider_package_links').' WHERE id=? AND client_id=?',[$linkId,$clientId]))return ['status'=>'not_shared','rows'=>[]];$view=provider_directory_indexing_project($linkId,true);return ['status'=>$view['status'],'rows'=>$view['rows']];
    }));
}
