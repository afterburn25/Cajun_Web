<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/app/providers/service_observations.php';
require_once CWP_CODE_ROOT.'/app/providers/package_wordpress_installed_storage.php';
use CajunWebPro\Foundation\TwentyIPackageWordpressInstalled\{PackageWordpressInstalledBinding,PackageWordpressInstalledObservation,PackageWordpressInstalled};
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;
foreach(['PackageWordpressInstalledBinding','PackageWordpressInstalledObservation','PackageWordpressInstalled'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-package-wordpress-installed/'.$class.'.php';

function provider_wp_installed_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/',$id))throw new InvalidArgumentException('Reload the WordPress installation observation form.');}
function provider_wp_installed_row_hash(?array $row):string {if(!$row)throw new RuntimeException('WordPress installation observation source history is missing.');return hash('sha256',provider_wp_installed_json(array_map(static fn($v)=>$v===null?null:(string)$v,$row)));}
function provider_wp_installed_hmac(string $kind,array $values):string {return hash_hmac('sha256','package.wordpress-installed|'.$kind.'|'.provider_wp_installed_json($values),provider_settings_vault_key());}
function provider_wp_installed_intent_values(array $f):array {$out=[];foreach(['id','profile_id','profile_revision','actor_id','started_at','started_epoch','operation','seq'] as $key)$out[$key]=(string)$f[$key];return $out;}
/** Fixed-size actor/resource reconciliation includes later unlinks and reapprovals. */
function provider_wp_installed_link_coverage(string $profileId,int $packageId):void {
    $e=table('provider_package_link_events');$a=table('audit');
    if(one("SELECT id FROM $e WHERE BINARY profile_id=? AND package_id=? AND BINARY event NOT IN ('link','relink','unlink') LIMIT 1",[$profileId,$packageId]))throw new RuntimeException('WordPress installation package assignment history needs recovery.');
    foreach(['link'=>['provider.service_linked','Profile '.$profileId.' package '.$packageId.' client %'],'relink'=>['provider.service_relinked','Link % profile '.$profileId.' package '.$packageId.' revision %'],'unlink'=>['provider.service_unlinked','Link % profile '.$profileId.' package '.$packageId]] as $kind=>[$action,$pattern]){
        $event="BINARY e.profile_id=? AND e.package_id=? AND BINARY e.event=?";$audit='BINARY a.action=? AND BINARY a.detail LIKE ?';
        if(one("SELECT e.id FROM $e e WHERE $event AND (SELECT COUNT(*) FROM $e x WHERE BINARY x.profile_id=? AND x.package_id=? AND BINARY x.event=? AND x.actor_id=e.actor_id)<>(SELECT COUNT(*) FROM $a a WHERE $audit AND a.user_id=e.actor_id) LIMIT 1",[$profileId,$packageId,$kind,$profileId,$packageId,$kind,$action,$pattern])||one("SELECT a.id FROM $a a WHERE $audit AND (SELECT COUNT(*) FROM $a x WHERE BINARY x.action=? AND BINARY x.detail LIKE ? AND x.user_id=a.user_id)<>(SELECT COUNT(*) FROM $e e WHERE $event AND e.actor_id=a.user_id) LIMIT 1",[$action,$pattern,$action,$pattern,$profileId,$packageId,$kind]))throw new RuntimeException('WordPress installation package assignment audits need recovery.');
    }
}
function provider_wp_installed_assignment(string $linkId):array {
    provider_wp_installed_id($linkId);$link=one('SELECT * FROM '.table('provider_package_links').' WHERE id=?',[$linkId]);if(!$link)throw new RuntimeException('This hosting assignment is unavailable.');provider_wp_installed_link_coverage($link['profile_id'],(int)$link['package_id']);
    $b=service_observations_binding($linkId);$event=one('SELECT * FROM '.table('provider_package_link_events').' WHERE id=?',[$b['event']['id']]);$audit=one('SELECT * FROM '.table('audit').' WHERE id=?',[$b['event']['audit_id']]);
    return ['link'=>$link,'profile'=>$b['profile'],'event'=>$event,'audit'=>$audit,'link_sha256'=>provider_wp_installed_row_hash($link),'event_sha256'=>provider_wp_installed_row_hash($event),'audit_sha256'=>provider_wp_installed_row_hash($audit)];
}
function provider_wp_installed_package_source(string $id,int $packageId):array {
    provider_wp_installed_id($id);$r=one('SELECT f.*,r.state,r.reason,r.scope,r.coverage,r.package_count,r.skipped,r.skip_reasons,r.finished_at,r.elapsed_ms,r.key_fingerprint FROM '.table('provider_refreshes').' f JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.id=?',[$id]);
    if(!$r||!in_array($r['operation'],[null,InventoryRead::PACKAGES_LIST],true)||$r['state']!=='complete'||(int)$r['skipped']!==0||$r['scope']!=='twenty-i|'.$r['profile_id'].'|'.InventoryRead::PACKAGES_LIST)throw new RuntimeException('The exact hosting observation needs recovery.');
    $source=service_observations_source($r,$packageId);if($source['package']===null)throw new RuntimeException('The assigned package is absent from the hosting observation.');
    $start=one('SELECT * FROM '.table('audit').' WHERE user_id=? AND BINARY action=? AND BINARY detail=?',[$r['actor_id'],'provider.refresh_started','Refresh '.$id.' profile '.$r['profile_id'].' revision '.$r['profile_revision']]);
    $finish=one('SELECT * FROM '.table('audit').' WHERE user_id=? AND BINARY action=? AND BINARY detail=?',[$r['actor_id'],'provider.refresh_finished','Refresh '.$id.' complete']);
    if(!$start||!$finish)throw new RuntimeException('The exact hosting observation audit needs recovery.');
    return ['row'=>$r,'start'=>$start,'sha256'=>hash('sha256',provider_wp_installed_json(['row'=>provider_wp_installed_row_hash($r),'package'=>$source['package'],'start'=>provider_wp_installed_row_hash($start),'finish'=>provider_wp_installed_row_hash($finish)]))];
}
/** A surviving common audit must never be ignored when a later package intent is lost. */
function provider_wp_installed_common_history(string $profileId):void {
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
function provider_wp_installed_binding(string $linkId):PackageWordpressInstalledBinding {
    $a=provider_wp_installed_assignment($linkId);$l=$a['link'];provider_wp_installed_common_history($l['profile_id']);$latest=service_observations_reads($l['profile_id'])[0]??null;
    if(!$latest||!service_observations_fresh($latest)||!provider_inventory_link_context_matches($l,$a['profile'],$latest))throw new RuntimeException('Refresh hosting inventory for this exact assignment first.');
    $source=provider_wp_installed_package_source($latest['id'],(int)$l['package_id']);if((int)$source['start']['id']<=(int)$a['audit']['id'])throw new RuntimeException('Refresh hosting inventory after this assignment or reapproval.');
    return new PackageWordpressInstalledBinding(['profile_id'=>$l['profile_id'],'profile_revision'=>(int)$l['profile_revision'],'key_fingerprint'=>$l['key_fingerprint'],'credential_sha256'=>hash('sha256',$a['profile']['credentials']),'package_id'=>(int)$l['package_id'],'client_id'=>(int)$l['client_id'],'link_id'=>$l['id'],'link_sha256'=>$a['link_sha256'],'event_id'=>(int)$a['event']['id'],'event_sha256'=>$a['event_sha256'],'audit_id'=>(int)$a['audit']['id'],'audit_sha256'=>$a['audit_sha256'],'source_id'=>$latest['id'],'source_seq'=>(int)$latest['seq'],'source_sha256'=>$source['sha256']]);
}
/** Retained original source validation does not require a mutable link still to have its old value. */
function provider_wp_installed_retained_binding(PackageWordpressInstalledBinding $b):void {
    $b=$b->validated();$d=$b->data;$source=provider_wp_installed_package_source($d['source_id'],$d['package_id']);$r=$source['row'];
    if($r['profile_id']!==$d['profile_id']||(int)$r['profile_revision']!==$d['profile_revision']||(int)$r['seq']!==$d['source_seq']||!hash_equals($d['key_fingerprint'],$r['key_fingerprint'])||!hash_equals($d['source_sha256'],$source['sha256'])||!hash_equals($d['event_sha256'],provider_wp_installed_row_hash(one('SELECT * FROM '.table('provider_package_link_events').' WHERE id=?',[$d['event_id']])))||!hash_equals($d['audit_sha256'],provider_wp_installed_row_hash(one('SELECT * FROM '.table('audit').' WHERE id=?',[$d['audit_id']]))))throw new RuntimeException('The original WordPress installation observation authority needs recovery.');
}
function provider_wp_installed_assignment_matches(PackageWordpressInstalledBinding $b):bool {
    $b=$b->validated();
    $a=provider_wp_installed_assignment($b->data['link_id']);return hash_equals($b->data['credential_sha256'],hash('sha256',$a['profile']['credentials']))&&hash_equals($b->data['link_sha256'],$a['link_sha256'])&&$b->data['event_id']===(int)$a['event']['id']&&hash_equals($b->data['event_sha256'],$a['event_sha256'])&&$b->data['audit_id']===(int)$a['audit']['id']&&hash_equals($b->data['audit_sha256'],$a['audit_sha256']);
}
/** Must be invoked by the shared executor before secret/transport and after the response. */
function provider_wp_installed_authority(PackageWordpressInstalledBinding $expected):void {
    $expected=$expected->validated();
    provider_settings_manager();if(!provider_wp_installed_ready())throw new RuntimeException('Prepare WordPress installation observation storage first.');$current=provider_wp_installed_binding($expected->data['link_id']);if(!hash_equals($expected->sha256,$current->sha256))throw new RuntimeException('The WordPress installation observation assignment or hosting read changed.');
}
function provider_wp_installed_started(array $started,PackageWordpressInstalledBinding $binding):void {
    provider_wp_installed_guard();$binding=$binding->validated();$f=one('SELECT * FROM '.table('provider_refreshes').' WHERE id=?',[$started['id']]);if(!$f||$f['operation']!==PackageWordpressInstalled::OPERATION||$f['profile_id']!==$binding->data['profile_id']||(int)$f['profile_revision']!==$binding->data['profile_revision'])throw new LogicException('WordPress installation intent does not match its binding.');
    foreach(['id','profile_id','profile_revision','actor_id','started_at','started_epoch','operation'] as $key)if(!array_key_exists($key,$started)||(string)$f[$key]!==(string)$started[$key])throw new LogicException('WordPress installation intent context changed.');
    $b=['id'=>$f['id'],'link_id'=>$binding->data['link_id'],'profile_id'=>$binding->data['profile_id'],'package_id'=>$binding->data['package_id'],'client_id'=>$binding->data['client_id'],'binding_json'=>$binding->json,'binding_sha256'=>$binding->sha256];$b['proof']=provider_wp_installed_hmac('intent',[provider_wp_installed_intent_values($f),$b]);
    query('INSERT INTO '.table('provider_wp_installed_reads').' (id,link_id,profile_id,package_id,client_id,binding_json,binding_sha256,proof) VALUES (?,?,?,?,?,?,?,?)',array_values($b));provider_settings_audit((int)$f['actor_id'],'wp_installed_started','WordPress installation read '.$f['id'].' started '.provider_wp_installed_digest(provider_wp_installed_start_values($f,$b)));provider_wp_installed_advance('started');
}
function provider_wp_installed_read_binding(array $f,array $b):PackageWordpressInstalledBinding {
    if(strlen($b['binding_json'])>8192)throw new RuntimeException('WordPress installation observation binding needs recovery.');
    try{$binding=new PackageWordpressInstalledBinding(json_decode($b['binding_json'],true,8,JSON_THROW_ON_ERROR));}catch(JsonException|TypeError|InvalidArgumentException $e){throw new RuntimeException('WordPress installation observation binding needs recovery.',0,$e);}
    $identity=['id'=>$f['id'],'link_id'=>$binding->data['link_id'],'profile_id'=>$binding->data['profile_id'],'package_id'=>$binding->data['package_id'],'client_id'=>$binding->data['client_id'],'binding_json'=>$binding->json,'binding_sha256'=>$binding->sha256];
    if($binding->json!==$b['binding_json']||$binding->sha256!==$b['binding_sha256']||$b['link_id']!==$identity['link_id']||$b['profile_id']!==$identity['profile_id']||(int)$b['package_id']!==$identity['package_id']||(int)$b['client_id']!==$identity['client_id']||!hash_equals($b['proof'],provider_wp_installed_hmac('intent',[provider_wp_installed_intent_values($f),$identity])))throw new RuntimeException('WordPress installation observation intent proof needs recovery.');
    provider_wp_installed_retained_binding($binding);return $binding;
}
function provider_wp_installed_source(string $id):?array {return one('SELECT f.*,r.state,r.reason,r.scope,r.coverage,r.package_count,r.skipped,r.skip_reasons,r.finished_at,r.elapsed_ms,r.key_fingerprint FROM '.table('provider_refreshes').' f LEFT JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.id=? AND BINARY f.operation=?',[$id,PackageWordpressInstalled::OPERATION]);}
/** The existing recorder owns the result transaction; never writes a provider-operation result. */
function provider_wp_installed_record(array $started,?PackageWordpressInstalledObservation $observation,string $fingerprint):void {
    provider_wp_installed_guard();$observation=$observation?->validated();$r=provider_wp_installed_source($started['id']);$b=one('SELECT * FROM '.table('provider_wp_installed_reads').' WHERE id=?',[$started['id']]);if(!$r||!$b||$r['state']===null)throw new LogicException('WordPress installation result requires its exact retained intent.');$binding=provider_wp_installed_read_binding($r,$b);$p=null;
    foreach(['id','profile_id','profile_revision','actor_id','started_at','started_epoch','operation'] as $key)if((string)$r[$key]!==(string)$started[$key])throw new LogicException('WordPress installation result context changed.');
    if(!in_array($r['state'],['complete','unavailable','refused','access_changed','credential'],true)||$r['key_fingerprint']!==$fingerprint||$r['coverage']!=='unknown'||(int)$r['package_count']!==0||(int)$r['skipped']!==0||$r['skip_reasons']!=='[]')throw new LogicException('WordPress installation result metadata is inconsistent.');
    if($r['state']==='complete'){
        if(!$observation||$observation->state!=='complete'||$r['scope']!==$binding->scope||$r['reason']!==''||!hash_equals($binding->sha256,$observation->binding->sha256)||!hash_equals($binding->data['key_fingerprint'],$fingerprint)||$observation->observedAt<(int)$r['started_epoch']||$observation->observedAt>time()+30)throw new LogicException('WordPress installation result does not match its exact intent.');
        $p=['id'=>$r['id'],'value_json'=>json_encode($observation->value,JSON_THROW_ON_ERROR),'observed_epoch'=>$observation->observedAt];$p['proof']=provider_wp_installed_hmac('value',[provider_wp_installed_result_values($r),$b['proof'],$p]);query('INSERT INTO '.table('provider_wp_installed_observations').' (id,value_json,observed_epoch,proof) VALUES (?,?,?,?)',array_values($p));
    }elseif($observation!==null)throw new LogicException('A failed WordPress installation read cannot retain a value.');
    provider_settings_audit((int)$r['actor_id'],'wp_installed_finished','WordPress installation read '.$r['id'].' finished '.provider_wp_installed_digest(provider_wp_installed_finish_values($r,$b,$p)));provider_wp_installed_advance('finished');
}
function provider_wp_installed_refresh(string $linkId,PackageWordpressInstalledBinding $expected,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    $expected=$expected->validated();
    if($linkId!==$expected->data['link_id'])throw new InvalidArgumentException('Reload the WordPress installation observation form.');
    return provider_inventory_refresh_attempt($expected->data['profile_id'],$expected->data['profile_revision'],$password,$resolver,$transport,operation:PackageWordpressInstalled::OPERATION,scopeAuthority:static fn()=>provider_wp_installed_authority($expected),wordpressInstalledContext:$expected);
}
function provider_wp_installed_latest(string $linkId,bool $complete=false):?array {
    return one('SELECT f.*,r.state,r.reason,r.scope,r.coverage,r.package_count,r.skipped,r.skip_reasons,r.finished_at,r.elapsed_ms,r.key_fingerprint FROM '.table('provider_wp_installed_reads').' b JOIN '.table('provider_refreshes').' f ON f.id=b.id LEFT JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE b.link_id=? AND BINARY f.operation=?'.($complete?' AND BINARY r.state=\'complete\'':'').' ORDER BY f.seq DESC LIMIT 1',[$linkId,PackageWordpressInstalled::OPERATION]);
}
function provider_wp_installed_observation(array $r):array {
    $b=one('SELECT * FROM '.table('provider_wp_installed_reads').' WHERE id=?',[$r['id']]);$p=one('SELECT * FROM '.table('provider_wp_installed_observations').' WHERE id=?',[$r['id']]);if(!$b||!$p)throw new RuntimeException('The saved WordPress installation observation is missing.');$binding=provider_wp_installed_read_binding($r,$b);
    if($r['state']!=='complete'||$r['reason']!==''||$r['scope']!==$binding->scope||$r['coverage']!=='unknown'||(int)$r['package_count']!==0||(int)$r['skipped']!==0||$r['skip_reasons']!=='[]'||!hash_equals($binding->data['key_fingerprint'],$r['key_fingerprint']))throw new RuntimeException('The saved WordPress installation observation context is inconsistent.');
    try{$value=json_decode($p['value_json'],false,4,JSON_THROW_ON_ERROR);if(!is_bool($value))throw new InvalidArgumentException('Expected a reported boolean.');}catch(JsonException|InvalidArgumentException $e){throw new RuntimeException('The saved WordPress installation observation value is inconsistent.',0,$e);}
    $document=['id'=>$r['id'],'value_json'=>json_encode($value,JSON_THROW_ON_ERROR),'observed_epoch'=>(int)$p['observed_epoch']];
    if($document['value_json']!==$p['value_json']||$document['observed_epoch']<(int)$r['started_epoch']||!hash_equals($p['proof'],provider_wp_installed_hmac('value',[provider_wp_installed_result_values($r),$b['proof'],$document])))throw new RuntimeException('The saved WordPress installation observation proof needs recovery.');
    return ['id'=>$r['id'],'seq'=>(int)$r['seq'],'value'=>$value,'observed_epoch'=>$document['observed_epoch'],'binding'=>$binding,'proof'=>$p['proof']];
}
function provider_wp_installed_permission(string $linkId):?array {
    provider_wp_installed_id($linkId);$row=one('SELECT * FROM '.table('provider_wp_installed_permissions').' WHERE link_id=? ORDER BY revision DESC LIMIT 1',[$linkId]);if(!$row)return null;
    if(strlen($row['document_json'])>16384)throw new RuntimeException('WordPress installation sharing document needs recovery.');
    try{$d=json_decode($row['document_json'],true,12,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new RuntimeException('WordPress installation sharing document needs recovery.',0,$e);}
    $keys=['version','id','link_id','client_id','revision','actor_id','recorded_epoch','previous_sha256','enabled','binding','anchor_id','anchor_proof'];
    if(!is_array($d)||array_keys($d)!==$keys||$d['version']!==1||!is_bool($d['enabled'])||!is_int($d['recorded_epoch'])||$d['recorded_epoch']<0||provider_wp_installed_json($d)!==$row['document_json'])throw new RuntimeException('WordPress installation sharing document needs recovery.');
    foreach(['id','link_id','previous_sha256'] as $key)if($d[$key]!==$row[$key])throw new RuntimeException('WordPress installation sharing identity needs recovery.');foreach(['client_id','revision','actor_id'] as $key)if(!is_int($d[$key])||$d[$key]!== (int)$row[$key])throw new RuntimeException('WordPress installation sharing identity needs recovery.');
    try{$binding=new PackageWordpressInstalledBinding($d['binding']);}catch(TypeError|InvalidArgumentException $e){throw new RuntimeException('WordPress installation sharing authority needs recovery.',0,$e);}
    if($binding->data['link_id']!==$linkId||$binding->data['client_id']!==$d['client_id'])throw new RuntimeException('WordPress installation sharing assignment needs recovery.');provider_wp_installed_id($d['anchor_id']);$anchor=provider_wp_installed_source($d['anchor_id']);if(!$anchor)throw new RuntimeException('Original shared WordPress installation observation is missing.');$observed=provider_wp_installed_observation($anchor);
    if($observed['binding']->sha256!==$binding->sha256||!is_string($d['anchor_proof'])||!hash_equals($observed['proof'],$d['anchor_proof']))throw new RuntimeException('Original shared WordPress installation observation changed.');return ['document'=>$d,'sha256'=>$row['document_sha256'],'binding'=>$binding,'anchor'=>$observed];
}
function provider_wp_installed_share(string $linkId,string $expectedId,string $eventId,bool $enabled,string $anchorId,#[SensitiveParameter] string $password):array {
    return staff_locked(fn()=>staff_transaction(function()use($linkId,$expectedId,$eventId,$enabled,$anchorId,$password):array{
        $actor=provider_settings_owner($password);provider_wp_installed_id($eventId);if(!provider_wp_installed_ready())throw new RuntimeException('Prepare WordPress installation observations first.');$old=provider_wp_installed_permission($linkId);$previous=$old['document']??null;
        if(($previous['id']??'')===$eventId){if($previous['enabled']!==$enabled||($enabled&&$previous['anchor_id']!==$anchorId))throw new RuntimeException('This sharing identifier was already used.');return $old;}
        if(($previous['id']??'')!==$expectedId||one('SELECT id FROM '.table('provider_wp_installed_permissions').' WHERE id=?',[$eventId]))throw new RuntimeException('Sharing changed. Reload the owner form.');
        if($enabled){$binding=provider_wp_installed_binding($linkId);$r=provider_wp_installed_latest($linkId);if(!$r||$r['id']!==$anchorId||!service_observations_fresh($r))throw new RuntimeException('Refresh and review this exact WordPress installation observation before sharing.');$anchor=provider_wp_installed_observation($r);if(!hash_equals($anchor['binding']->sha256,$binding->sha256))throw new RuntimeException('The WordPress installation observation authority changed. Refresh before sharing.');}
        else{if(!$old)throw new RuntimeException('No WordPress installation sharing permission exists.');$binding=$old['binding'];$anchor=$old['anchor'];}
        $d=['version'=>1,'id'=>$eventId,'link_id'=>$linkId,'client_id'=>$binding->data['client_id'],'revision'=>($previous['revision']??0)+1,'actor_id'=>(int)$actor['id'],'recorded_epoch'=>max(time(),$previous['recorded_epoch']??0),'previous_sha256'=>$old['sha256']??str_repeat('0',64),'enabled'=>$enabled,'binding'=>$binding->data,'anchor_id'=>$anchor['id'],'anchor_proof'=>$anchor['proof']];
        $json=provider_wp_installed_json($d);$row=['id'=>$eventId,'link_id'=>$linkId,'client_id'=>$d['client_id'],'revision'=>$d['revision'],'actor_id'=>$d['actor_id'],'previous_sha256'=>$d['previous_sha256'],'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];
        query('INSERT INTO '.table('provider_wp_installed_permissions').' (id,link_id,client_id,revision,actor_id,previous_sha256,document_json,document_sha256) VALUES (?,?,?,?,?,?,?,?)',array_values($row));provider_settings_audit($d['actor_id'],'wp_installed_permission','WordPress installation permission '.$eventId.' '.provider_wp_installed_digest(provider_wp_installed_permission_values($row)));provider_wp_installed_advance('permission');return provider_wp_installed_permission($linkId);
    }));
}
/** Internal saved projection. Caller owns client/owner authority, lock and transaction. */
function provider_wp_installed_project(string $linkId,bool $clientVisible):array {
    provider_wp_installed_guard();if(!provider_wp_installed_ready())return ['status'=>'not_ready','rows'=>[],'permission'=>null];$permission=provider_wp_installed_permission($linkId);
    if($clientVisible&&(!$permission||!$permission['document']['enabled']))return ['status'=>'not_shared','rows'=>[],'permission'=>null];
    if($clientVisible&&!provider_wp_installed_assignment_matches($permission['binding']))return ['status'=>'assignment_changed','rows'=>[],'permission'=>null];
    $latest=provider_wp_installed_latest($linkId);$saved=provider_wp_installed_latest($linkId,true);$observation=$saved?provider_wp_installed_observation($saved):null;$status='not_observed';$rows=[];
    if($latest){$bindingRow=one('SELECT * FROM '.table('provider_wp_installed_reads').' WHERE id=?',[$latest['id']]);if(!$bindingRow)throw new RuntimeException('WordPress installation observation intent needs recovery.');provider_wp_installed_read_binding($latest,$bindingRow);$status=$latest['state']??'unfinished';}
    if($observation){
        if(!provider_wp_installed_assignment_matches($observation['binding']))return ['status'=>'assignment_changed','rows'=>[],'permission'=>$clientVisible?null:$permission];
        if(!$clientVisible||($observation['seq']>=$permission['anchor']['seq']&&$observation['binding']->data['link_sha256']===$permission['binding']->data['link_sha256']&&$observation['binding']->data['event_id']===$permission['binding']->data['event_id'])){
            $current=$latest['id']===$saved['id']&&service_observations_fresh($saved)&&$observation['observed_epoch']<=time()+30;
            if($current){try{$now=provider_wp_installed_binding($linkId);$current=$now->data['link_sha256']===$observation['binding']->data['link_sha256']&&$now->data['event_id']===$observation['binding']->data['event_id'];}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$current=false;}}
            if($latest['id']===$saved['id'])$status=$current?'observed':'stale';$rows[]=['value'=>$observation['value'],'observed_epoch'=>$observation['observed_epoch'],'current'=>$current];
        }
    }
    return ['status'=>$status,'rows'=>$rows,'permission'=>$clientVisible?null:$permission,'latest_id'=>$latest['id']??''];
}
function provider_wp_installed_client(int $clientId,string $linkId):array {
    return staff_locked(fn()=>staff_transaction(function()use($clientId,$linkId):array{
        service_observations_actor($clientId);provider_wp_installed_id($linkId);if(!provider_inventory_ready()||!one('SELECT id FROM '.table('provider_package_links').' WHERE id=? AND client_id=?',[$linkId,$clientId]))return ['status'=>'not_shared','rows'=>[]];return provider_wp_installed_project($linkId,true);
    }));
}
