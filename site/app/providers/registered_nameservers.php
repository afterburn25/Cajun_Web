<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_contracts.php';
require_once CWP_CODE_ROOT.'/app/providers/registered_nameservers_storage.php';
foreach(['RegisteredNameserverBinding','RegisteredNameserverObservation','RegisteredNameservers'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-registrar-nameservers/'.$class.'.php';
use CajunWebPro\Foundation\TwentyIRegistrarNameservers\{RegisteredNameserverBinding,RegisteredNameserverObservation,RegisteredNameservers};
function registered_nameservers_json(array $data):string {return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);}
function registered_nameservers_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/',$id))throw new InvalidArgumentException('Reload the nameserver form.');}
/** Independent common-start audit order must agree before any retained latest intent is authority. */
function registered_nameservers_intent_order(string $profileId,string $linkId):void {
    $f=table('provider_refreshes');$a=table('audit');$b=table('registered_nameserver_bindings');
    $intents=(int)query("SELECT COUNT(*) FROM $f WHERE BINARY profile_id=?",[$profileId])->fetchColumn();$audits=(int)query("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.refresh_started' AND BINARY detail LIKE ?",['Refresh % profile '.$profileId.' revision %'])->fetchColumn();
    if($intents!==$audits)throw new RuntimeException('Provider observation intent history needs recovery.');
    $detail="CONCAT(BINARY 'Refresh ',f.id,BINARY ' profile ',f.profile_id,BINARY ' revision ',f.profile_revision)";
    if(one("SELECT f.id FROM $f f WHERE BINARY f.profile_id=? AND ((SELECT COUNT(*) FROM $a a WHERE BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail AND a.user_id=f.actor_id)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail)<>1) LIMIT 1",[$profileId]))throw new RuntimeException('Provider observation intent correspondence needs recovery.');
    $paired="JOIN $a a ON a.user_id=f.actor_id AND BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail";
    $registrar="BINARY f.profile_id=? AND BINARY f.operation='domains.list'";
    $bySequence=one("SELECT f.id FROM $f f WHERE $registrar ORDER BY f.seq DESC LIMIT 1",[$profileId]);$byAudit=one("SELECT f.id FROM $f f $paired WHERE $registrar ORDER BY a.id DESC LIMIT 1",[$profileId]);
    if(($bySequence['id']??null)!==($byAudit['id']??null))throw new RuntimeException('Registrar observation intent order needs recovery.');
    $date="JOIN $b b ON b.id=f.id WHERE BINARY f.profile_id=? AND BINARY f.operation='registrar.nameservers.read' AND BINARY b.link_id=?";
    $bySequence=one("SELECT f.id FROM $f f $date ORDER BY f.seq DESC LIMIT 1",[$profileId,$linkId]);$byAudit=one("SELECT f.id FROM $f f $paired $date ORDER BY a.id DESC LIMIT 1",[$profileId,$linkId]);
    if(($bySequence['id']??null)!==($byAudit['id']??null))throw new RuntimeException('Nameserver intent order needs recovery.');
}
/** Current assignment/account/identity, with an exact complete fresh registrar read. */
function registered_nameservers_binding(string $linkId,int $linkRevision):RegisteredNameserverBinding {
    provider_settings_manager();
    registered_nameservers_id($linkId);if(!registered_nameservers_ready())throw new RuntimeException('Enable nameserver observations first.');[$l,$event,$audit]=domain_contracts_link($linkId,$linkRevision);registered_nameservers_intent_order($l['profile_id'],$linkId);$profile=provider_settings_load($l['profile_id']);$latest=provider_registrar_latest($l['profile_id']);
    if(!provider_registrar_fresh($profile,$latest)||!provider_inventory_link_context_matches($l,$profile,$latest)||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$l['client_id']]))throw new RuntimeException('Refresh registered domains and confirm this assignment first.');
    $source=domain_contracts_source($latest['id'],(int)$l['domain_id']);if($source['domain']!==$l['domain'])throw new RuntimeException('Registered-domain identity changed.');
    return new RegisteredNameserverBinding(['profile_id'=>$l['profile_id'],'profile_revision'=>(int)$profile['revision'],'key_fingerprint'=>$l['key_fingerprint'],'domain_id'=>(int)$l['domain_id'],'domain'=>$l['domain'],'link_id'=>$l['id'],'link_revision'=>(int)$l['revision'],'client_id'=>(int)$l['client_id'],'link_event_id'=>$event['id'],'link_event_sha256'=>hash('sha256',registered_nameservers_json($event)),'link_audit_id'=>$audit['id'],'link_audit_sha256'=>hash('sha256',registered_nameservers_json($audit)),'source_refresh_id'=>$source['id'],'source_seq'=>$source['seq'],'source_sha256'=>hash('sha256',registered_nameservers_json($source))]);
}
function registered_nameservers_authority(RegisteredNameserverBinding $binding):void {
    provider_settings_manager();$current=registered_nameservers_binding($binding->data['link_id'],$binding->data['link_revision']);if(!hash_equals($binding->sha256,$current->sha256))throw new RuntimeException('Registered-domain read permission changed.');
}
/** Internal shared-executor hook, in its existing intent transaction. */
function registered_nameservers_start(array $started,RegisteredNameserverBinding $binding):void {
    registered_nameservers_guard();
    if(!db()->inTransaction()||$started['operation']!==RegisteredNameservers::OPERATION||$started['profile_id']!==$binding->data['profile_id']||$started['profile_revision']!==$binding->data['profile_revision'])throw new LogicException('Invalid domain read intent.');
    query('INSERT INTO '.table('registered_nameserver_bindings').' (id,link_id,client_id,actor_id,started_epoch,binding_json,binding_sha256) VALUES (?,?,?,?,?,?,?)',[$started['id'],$binding->data['link_id'],$binding->data['client_id'],$started['actor_id'],$started['started_epoch'],$binding->json,$binding->sha256]);registered_nameservers_audit((int)$started['actor_id'],'bound','Read '.$started['id'].' binding '.$binding->sha256);$count=(int)one('SELECT value FROM '.table('settings').' WHERE name=?',['registered_nameservers_intent_count'])['value'];save_setting('registered_nameservers_intent_count',(string)($count+1));
}
function registered_nameservers_original(string $id):array {
    registered_nameservers_id($id);$row=one('SELECT b.*,f.seq,f.profile_id,f.profile_revision,f.actor_id AS intent_actor,f.started_epoch AS intent_epoch,f.started_at,f.operation FROM '.table('registered_nameserver_bindings').' b JOIN '.table('provider_refreshes').' f ON f.id=b.id WHERE b.id=?',[$id]);if(!$row)throw new RuntimeException('Original nameserver binding needs recovery.');
    try{$b=new RegisteredNameserverBinding(json_decode($row['binding_json'],true,8,JSON_THROW_ON_ERROR));}catch(JsonException|InvalidArgumentException|TypeError $e){throw new RuntimeException('Nameserver binding needs recovery.',0,$e);}
    $d=$b->data;if($row['binding_json']!==$b->json||!hash_equals($row['binding_sha256'],$b->sha256)||$row['operation']!==RegisteredNameservers::OPERATION||$row['profile_id']!==$d['profile_id']||(int)$row['profile_revision']!==$d['profile_revision']||$row['link_id']!==$d['link_id']||(int)$row['client_id']!==$d['client_id']||(int)$row['actor_id']!==(int)$row['intent_actor']||(int)$row['started_epoch']!==(int)$row['intent_epoch'])throw new RuntimeException('Nameserver binding identity needs recovery.');
    $a=domain_contracts_audit_row((int)$row['actor_id'],'provider.refresh_started','Refresh '.$id.' profile '.$d['profile_id'].' revision '.$d['profile_revision']);$bound=domain_contracts_audit_row((int)$row['actor_id'],'registered_nameservers.bound','Read '.$id.' binding '.$b->sha256);if($a['id']<=$d['link_audit_id']||$bound['id']<=$a['id'])throw new RuntimeException('Nameserver read audit order needs recovery.');
    $source=domain_contracts_source($d['source_refresh_id'],$d['domain_id']);if($source['seq']!==$d['source_seq']||!hash_equals($d['source_sha256'],hash('sha256',registered_nameservers_json($source))))throw new RuntimeException('Original registrar source needs recovery.');
    // Retain and verify the original assignment even after a legitimate later reapproval/unlink.
    $event=one('SELECT * FROM '.table('provider_registrar_events').' WHERE id=?',[$d['link_event_id']]);if(!$event)throw new RuntimeException('Original domain assignment needs recovery.');$safe=[];foreach(['id','link_id','profile_id','domain_id','domain','client_id','revision','event','actor_id','recorded_at','key_fingerprint','profile_revision','refresh_id'] as $k)$safe[$k]=in_array($k,['id','domain_id','client_id','revision','actor_id','profile_revision'],true)?(int)$event[$k]:$event[$k];
    $audit=domain_contracts_audit_row((int)$event['actor_id'],'provider.registrar_'.$event['event'],'Registered-domain link '.$d['link_id'].' revision '.$d['link_revision']);if(!hash_equals($d['link_event_sha256'],hash('sha256',registered_nameservers_json($safe)))||$audit['id']!==$d['link_audit_id']||!hash_equals($d['link_audit_sha256'],hash('sha256',registered_nameservers_json($audit))))throw new RuntimeException('Original domain assignment audit needs recovery.');
    return [$b,$row];
}
/** Called only inside the shared result writer transaction, never a second execution path. */
function registered_nameservers_record(array $started,string $state,?RegisteredNameserverObservation $snapshot,string $fingerprint):void {
    registered_nameservers_guard();
    if(!db()->inTransaction())throw new LogicException('Domain result requires the shared transaction.');[$b,$row]=registered_nameservers_original($started['id']);
    if($state==='complete'){
        if(!$snapshot||$snapshot->state!=='complete'||$snapshot->scope!==$b->scope||$snapshot->domainId!==$b->data['domain_id']||$snapshot->domainName!==$b->data['domain']||!hash_equals($b->sha256,$snapshot->binding->sha256)||!hash_equals($b->data['key_fingerprint'],$fingerprint))throw new LogicException('Domain result does not match the exact intent.');
        $set=['id'=>$started['id'],'binding_sha256'=>$b->sha256,'nameservers'=>$snapshot->nameservers,'observed_epoch'=>$snapshot->observedAt];$hash=hash('sha256',registered_nameservers_json($set));query('INSERT INTO '.table('registered_nameserver_sets').' (id,nameservers_json,observed_epoch,document_sha256) VALUES (?,?,?,?)',[$started['id'],registered_nameservers_json($snapshot->nameservers),$snapshot->observedAt,$hash]);registered_nameservers_audit((int)$started['actor_id'],'observed','Read '.$started['id'].' set '.$hash);
    }elseif($snapshot!==null)throw new LogicException('Unavailable nameserver result cannot persist a set.');
    $count=(int)one('SELECT value FROM '.table('settings').' WHERE name=?',['registered_nameservers_result_count'])['value'];save_setting('registered_nameservers_result_count',(string)($count+1));
}
function registered_nameservers_refresh(string $linkId,int $revision,int $profileRevision,string $sourceId,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    if(db()->inTransaction())throw new LogicException('Nameserver refresh cannot run inside a transaction.');
    $binding=staff_locked(fn()=>staff_transaction(function()use($linkId,$revision,$profileRevision,$sourceId,$password):RegisteredNameserverBinding{provider_settings_owner($password);$b=registered_nameservers_binding($linkId,$revision);if($b->data['profile_revision']!==$profileRevision||$b->data['source_refresh_id']!==$sourceId)throw new RuntimeException('Reload the current nameserver form.');return $b;}));
    return provider_inventory_refresh_attempt($binding->data['profile_id'],$profileRevision,$password,$resolver,$transport,RegisteredNameservers::OPERATION,nameserverContext:$binding);
}
function registered_nameservers_read_row(string $id):array {
    [$b,$row]=registered_nameservers_original($id);$r=one('SELECT * FROM '.table('provider_refresh_results').' WHERE id=?',[$id]);$set=one('SELECT * FROM '.table('registered_nameserver_sets').' WHERE id=?',[$id]);$state=$r['state']??'unfinished';$saved=null;
    $bound=domain_contracts_audit_row((int)$row['actor_id'],'registered_nameservers.bound','Read '.$id.' binding '.$b->sha256);
    if(!$r){if($set||one('SELECT id FROM '.table('audit').' WHERE BINARY action=? AND detail LIKE ?',['provider.refresh_finished','Refresh '.$id.' %']))throw new RuntimeException('Nameserver outcome needs recovery.');}
    else{
        if(!in_array($state,['complete','unavailable','refused','access_changed','credential'],true)||(int)$r['skipped']!==0||$r['skip_reasons']!=='[]'||$r['coverage']!=='unknown'||(int)$r['package_count']!==0)throw new RuntimeException('Nameserver outcome fields need recovery.');
        $finish=domain_contracts_audit_row((int)$row['actor_id'],'provider.refresh_finished','Refresh '.$id.' '.$state.($r['reason']!==''?' '.$r['reason']:''));if($finish['id']<=$bound['id'])throw new RuntimeException('Nameserver result audit order needs recovery.');
        if($state==='complete'){
            if(!$set||$r['reason']!==''||$r['scope']!==$b->scope||!hash_equals($b->data['key_fingerprint'],$r['key_fingerprint']))throw new RuntimeException('Saved nameserver needs recovery.');
            try{$o=new RegisteredNameserverObservation($b,'complete',json_decode($set['nameservers_json'],true,8,JSON_THROW_ON_ERROR),'',(int)$set['observed_epoch']);}catch(JsonException|InvalidArgumentException|TypeError $e){throw new RuntimeException('Saved nameserver set needs recovery.',0,$e);}
            if(registered_nameservers_json($o->nameservers)!==$set['nameservers_json'])throw new RuntimeException('Saved nameserver encoding needs recovery.');
            $safe=['id'=>$id,'binding_sha256'=>$b->sha256,'nameservers'=>$o->nameservers,'observed_epoch'=>$o->observedAt];$hash=hash('sha256',registered_nameservers_json($safe));if(!hash_equals($set['document_sha256'],$hash))throw new RuntimeException('Saved nameserver digest needs recovery.');$observed=domain_contracts_audit_row((int)$row['actor_id'],'registered_nameservers.observed','Read '.$id.' set '.$hash);if($observed['id']<=$bound['id']||$observed['id']>=$finish['id'])throw new RuntimeException('Nameserver observation audit order needs recovery.');$saved=$o->nameservers;
        }else{
            if($set||!in_array($r['scope'],['',$b->scope],true)||($r['key_fingerprint']!==''&&!preg_match('/\A[a-f0-9]{64}\z/',$r['key_fingerprint'])))throw new RuntimeException('Failed nameserver read has unexpected data.');
            $reasons=$state==='unavailable'?array_merge(['unavailable'],RegisteredNameserverObservation::REASONS):[$state];if(!in_array($r['reason'],$reasons,true))throw new RuntimeException('Nameserver failure reason needs recovery.');
        }
    }
    return ['id'=>$id,'seq'=>(int)$row['seq'],'binding'=>$b,'state'=>$state,'nameservers'=>$saved,'observed_epoch'=>$set?(int)$set['observed_epoch']:(int)$row['started_epoch']];
}
function registered_nameservers_project(string $linkId,int $revision,int $page=1):array {
    if(db()->inTransaction())throw new LogicException('Nameserver projection requires a fresh transaction.');
    return staff_locked(fn()=>staff_transaction(fn()=>registered_nameservers_project_locked($linkId,$revision,$page)));
}
/** Local saved-data projection; no credential resolution or provider request. */
function registered_nameservers_project_locked(string $linkId,int $revision,int $page=1):array {
    if($page<1||$page>1000000)throw new InvalidArgumentException('Invalid nameserver history page.');
    registered_nameservers_guard();$current=registered_nameservers_binding($linkId,$revision);$rows=[];$status='not_observed';$set=null;$at=null;
    $sameContext=static function(RegisteredNameserverBinding $old)use($current):bool{foreach($current->data as $k=>$v){if(str_starts_with($k,'source_'))continue;if($old->data[$k]!==$v)return false;}return true;};
    $from=' FROM '.table('registered_nameserver_bindings').' b JOIN '.table('provider_refreshes').' f ON f.id=b.id WHERE b.link_id=?';
    $latest=one('SELECT b.id'.$from.' ORDER BY f.seq DESC LIMIT 1',[$linkId]);
    if($latest){$r=registered_nameservers_read_row($latest['id']);$same=$sameContext($r['binding']);$status=$same?$r['state']:'assignment_changed';if($same&&$r['state']==='complete'){$fresh=$r['observed_epoch']>=time()-900&&$r['observed_epoch']<=time()+30;$status=$fresh?'observed':'stale';if($fresh){$set=$r['nameservers'];$at=$r['observed_epoch'];}}}
    $total=(int)query('SELECT COUNT(*)'.$from,[$linkId])->fetchColumn();$pages=max(1,(int)ceil($total/20));$page=min($page,$pages);$offset=($page-1)*20;
    foreach(all('SELECT b.id'.$from.' ORDER BY f.seq DESC LIMIT 20 OFFSET '.$offset,[$linkId]) as $raw){$r=registered_nameservers_read_row($raw['id']);$rows[]=['id'=>$r['id'],'state'=>$r['state'],'nameservers'=>$r['nameservers'],'observed_epoch'=>$r['observed_epoch'],'current_assignment'=>$sameContext($r['binding'])];
    }
    require_once CWP_CODE_ROOT.'/app/providers/registered_nameserver_changes.php';
    if(!registered_nameserver_changes_current($linkId)){$status='change_pending';$set=null;$at=null;}
    return ['domain'=>$current->data['domain'],'status'=>$status,'nameservers'=>$set,'observed_epoch'=>$at,'history'=>$rows,'pagination'=>['page'=>$page,'pages'=>$pages,'total'=>$total]];
}
