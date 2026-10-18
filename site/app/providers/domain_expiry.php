<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_contracts.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_expiry_storage.php';
foreach(['DomainExpiryBinding','DomainExpiryObservation','DomainExpiry','DomainExpiryReader'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-domain-expiry/'.$class.'.php';
use CajunWebPro\Foundation\TwentyIDomainExpiry\{DomainExpiryBinding,DomainExpiryObservation,DomainExpiry};
use CajunWebPro\Foundation\TwentyIDomainContracts\DomainContractBinding;
function domain_expiry_json(array $data):string {return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);}
function domain_expiry_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/',$id))throw new InvalidArgumentException('Reload the registrar expiry form.');}
function domain_expiry_audit_row(int $actor,string $action,string $detail):array {
    $rows=all('SELECT id,user_id,action,detail FROM '.table('audit').' WHERE user_id=? AND BINARY action=? AND BINARY detail=?',[$actor,$action,$detail]);if(count($rows)!==1)throw new RuntimeException('Registrar expiry authority audit needs recovery.');$r=$rows[0];return ['id'=>(int)$r['id'],'actor_id'=>(int)$r['user_id'],'action'=>$r['action'],'detail'=>$r['detail']];
}
/** Reuse exact registrar event/source witnesses without requiring renewal-date storage. */
function domain_expiry_link(string $id,int $revision):array {return domain_contracts_link($id,$revision);}
function domain_expiry_source(string $id,int $domain):array {return domain_contracts_source($id,$domain);}
/** Independent common-start audit order must agree before any retained latest intent is authority. */
function domain_expiry_intent_order(string $profileId,string $linkId):void {
    $f=table('provider_refreshes');$a=table('audit');$b=table('domain_expiry_bindings');
    $intents=(int)query("SELECT COUNT(*) FROM $f WHERE BINARY profile_id=?",[$profileId])->fetchColumn();$audits=(int)query("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.refresh_started' AND BINARY detail LIKE ?",['Refresh % profile '.$profileId.' revision %'])->fetchColumn();
    if($intents!==$audits)throw new RuntimeException('Provider observation intent history needs recovery.');
    $detail="CONCAT(BINARY 'Refresh ',f.id,BINARY ' profile ',f.profile_id,BINARY ' revision ',f.profile_revision)";
    if(one("SELECT f.id FROM $f f WHERE BINARY f.profile_id=? AND ((SELECT COUNT(*) FROM $a a WHERE BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail AND a.user_id=f.actor_id)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail)<>1) LIMIT 1",[$profileId]))throw new RuntimeException('Provider observation intent correspondence needs recovery.');
    $paired="JOIN $a a ON a.user_id=f.actor_id AND BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail";
    $registrar="BINARY f.profile_id=? AND BINARY f.operation='domains.list'";
    $bySequence=one("SELECT f.id FROM $f f WHERE $registrar ORDER BY f.seq DESC LIMIT 1",[$profileId]);$byAudit=one("SELECT f.id FROM $f f $paired WHERE $registrar ORDER BY a.id DESC LIMIT 1",[$profileId]);
    if(($bySequence['id']??null)!==($byAudit['id']??null))throw new RuntimeException('Registrar observation intent order needs recovery.');
    $date="JOIN $b b ON b.id=f.id WHERE BINARY f.profile_id=? AND BINARY f.operation='domain.upstream_expiry_date' AND BINARY b.link_id=?";
    $bySequence=one("SELECT f.id FROM $f f $date ORDER BY f.seq DESC LIMIT 1",[$profileId,$linkId]);$byAudit=one("SELECT f.id FROM $f f $paired $date ORDER BY a.id DESC LIMIT 1",[$profileId,$linkId]);
    if(($bySequence['id']??null)!==($byAudit['id']??null))throw new RuntimeException('Registrar expiry intent order needs recovery.');
}
/** Current assignment/account/identity, with an exact complete fresh registrar read. */
function domain_expiry_binding(string $linkId,int $linkRevision):DomainExpiryBinding {
    domain_expiry_id($linkId);if(!domain_expiry_ready())throw new RuntimeException('Enable registrar expiry observations first.');[$l,$event,$audit]=domain_expiry_link($linkId,$linkRevision);domain_expiry_intent_order($l['profile_id'],$linkId);$profile=provider_settings_load($l['profile_id']);$latest=provider_registrar_latest($l['profile_id']);
    if(!provider_registrar_fresh($profile,$latest)||!provider_inventory_link_context_matches($l,$profile,$latest)||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$l['client_id']]))throw new RuntimeException('Refresh registered domains and confirm this assignment first.');
    $source=domain_expiry_source($latest['id'],(int)$l['domain_id']);if($source['domain']!==$l['domain'])throw new RuntimeException('Registered-domain identity changed.');
    return new DomainExpiryBinding(new DomainContractBinding(['profile_id'=>$l['profile_id'],'profile_revision'=>(int)$profile['revision'],'key_fingerprint'=>$l['key_fingerprint'],'domain_id'=>(int)$l['domain_id'],'domain'=>$l['domain'],'link_id'=>$l['id'],'link_revision'=>(int)$l['revision'],'client_id'=>(int)$l['client_id'],'link_event_id'=>$event['id'],'link_event_sha256'=>hash('sha256',domain_expiry_json($event)),'link_audit_id'=>$audit['id'],'link_audit_sha256'=>hash('sha256',domain_expiry_json($audit)),'source_refresh_id'=>$source['id'],'source_seq'=>$source['seq'],'source_sha256'=>hash('sha256',domain_expiry_json($source))]),hash('sha256',$profile['credentials']));
}
function domain_expiry_authority(DomainExpiryBinding $binding):void {
    provider_settings_manager();$current=domain_expiry_binding($binding->registrar->data['link_id'],$binding->registrar->data['link_revision']);if(!hash_equals($binding->sha256,$current->sha256))throw new RuntimeException('Registered-domain read permission changed.');
}
/** Internal shared-executor hook, in its existing intent transaction. */
function domain_expiry_start(array $started,DomainExpiryBinding $binding):void {
    if(!db()->inTransaction()||$started['operation']!==DomainExpiry::OPERATION||$started['profile_id']!==$binding->registrar->data['profile_id']||$started['profile_revision']!==$binding->registrar->data['profile_revision'])throw new LogicException('Invalid domain read intent.');
    query('INSERT INTO '.table('domain_expiry_bindings').' (id,link_id,client_id,actor_id,started_epoch,binding_json,binding_sha256) VALUES (?,?,?,?,?,?,?)',[$started['id'],$binding->registrar->data['link_id'],$binding->registrar->data['client_id'],$started['actor_id'],$started['started_epoch'],$binding->json,$binding->sha256]);domain_expiry_audit((int)$started['actor_id'],'bound','Read '.$started['id'].' binding '.$binding->sha256);$count=(int)one('SELECT value FROM '.table('settings').' WHERE name=?',['domain_expiry_intent_count'])['value'];save_setting('domain_expiry_intent_count',(string)($count+1));
}
function domain_expiry_original(string $id):array {
    domain_expiry_id($id);$row=one('SELECT b.*,f.seq,f.profile_id,f.profile_revision,f.actor_id AS intent_actor,f.started_epoch AS intent_epoch,f.started_at,f.operation FROM '.table('domain_expiry_bindings').' b JOIN '.table('provider_refreshes').' f ON f.id=b.id WHERE b.id=?',[$id]);if(!$row)throw new RuntimeException('Original registrar expiry binding needs recovery.');
    try{$document=json_decode($row['binding_json'],true,8,JSON_THROW_ON_ERROR);if(!is_array($document)||array_keys($document)!==['operation','registrar','credential_sha256']||$document['operation']!==DomainExpiry::OPERATION)throw new InvalidArgumentException('Invalid expiry document.');$b=new DomainExpiryBinding(new DomainContractBinding($document['registrar']),$document['credential_sha256']);}catch(JsonException|InvalidArgumentException|TypeError $e){throw new RuntimeException('Registrar expiry binding needs recovery.',0,$e);}
    $d=$b->registrar->data;if($row['binding_json']!==$b->json||!hash_equals($row['binding_sha256'],$b->sha256)||$row['operation']!==DomainExpiry::OPERATION||$row['profile_id']!==$d['profile_id']||(int)$row['profile_revision']!==$d['profile_revision']||$row['link_id']!==$d['link_id']||(int)$row['client_id']!==$d['client_id']||(int)$row['actor_id']!==(int)$row['intent_actor']||(int)$row['started_epoch']!==(int)$row['intent_epoch'])throw new RuntimeException('Registrar expiry binding identity needs recovery.');
    $a=domain_expiry_audit_row((int)$row['actor_id'],'provider.refresh_started','Refresh '.$id.' profile '.$d['profile_id'].' revision '.$d['profile_revision']);domain_expiry_audit_row((int)$row['actor_id'],'domain_expiry.bound','Read '.$id.' binding '.$b->sha256);if($a['id']<=$d['link_audit_id'])throw new RuntimeException('Registrar expiry read precedes its assignment.');
    $source=domain_expiry_source($d['source_refresh_id'],$d['domain_id']);if($source['seq']!==$d['source_seq']||!hash_equals($d['source_sha256'],hash('sha256',domain_expiry_json($source))))throw new RuntimeException('Original registrar source needs recovery.');
    // Retain and verify the original assignment even after a legitimate later reapproval/unlink.
    $event=one('SELECT * FROM '.table('provider_registrar_events').' WHERE id=?',[$d['link_event_id']]);if(!$event)throw new RuntimeException('Original domain assignment needs recovery.');$safe=[];foreach(['id','link_id','profile_id','domain_id','domain','client_id','revision','event','actor_id','recorded_at','key_fingerprint','profile_revision','refresh_id'] as $k)$safe[$k]=in_array($k,['id','domain_id','client_id','revision','actor_id','profile_revision'],true)?(int)$event[$k]:$event[$k];
    $audit=domain_expiry_audit_row((int)$event['actor_id'],'provider.registrar_'.$event['event'],'Registered-domain link '.$d['link_id'].' revision '.$d['link_revision']);if(!hash_equals($d['link_event_sha256'],hash('sha256',domain_expiry_json($safe)))||$audit['id']!==$d['link_audit_id']||!hash_equals($d['link_audit_sha256'],hash('sha256',domain_expiry_json($audit))))throw new RuntimeException('Original domain assignment audit needs recovery.');
    return [$b,$row];
}
/** Called only inside the shared result writer transaction, never a second execution path. */
function domain_expiry_record(array $started,string $state,?DomainExpiryObservation $snapshot,string $fingerprint):void {
    if(!db()->inTransaction())throw new LogicException('Domain result requires the shared transaction.');[$b,$row]=domain_expiry_original($started['id']);
    if($state==='complete'){
        if(!$snapshot||$snapshot->state!=='complete'||$snapshot->scope!==$b->scope||$snapshot->domainId!==$b->registrar->data['domain_id']||$snapshot->domainName!==$b->registrar->data['domain']||!hash_equals($b->sha256,$snapshot->binding->sha256)||!hash_equals($b->registrar->data['key_fingerprint'],$fingerprint))throw new LogicException('Domain result does not match the exact intent.');
        $date=['id'=>$started['id'],'binding_sha256'=>$b->sha256,'expiry_timestamp'=>$snapshot->upstreamExpiryDate,'observed_epoch'=>$snapshot->observedAt];$hash=hash('sha256',domain_expiry_json($date));query('INSERT INTO '.table('domain_expiry_timestamps').' (id,expiry_timestamp,observed_epoch,document_sha256) VALUES (?,?,?,?)',[$started['id'],$snapshot->upstreamExpiryDate,$snapshot->observedAt,$hash]);domain_expiry_audit((int)$started['actor_id'],'observed','Read '.$started['id'].' timestamp '.$hash);
    }elseif($snapshot!==null)throw new LogicException('Unavailable domain result cannot persist a timestamp.');
    $count=(int)one('SELECT value FROM '.table('settings').' WHERE name=?',['domain_expiry_result_count'])['value'];save_setting('domain_expiry_result_count',(string)($count+1));
}
function domain_expiry_refresh(string $linkId,int $revision,int $profileRevision,string $sourceId,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    $binding=staff_locked(fn()=>staff_transaction(function()use($linkId,$revision,$profileRevision,$sourceId,$password):DomainExpiryBinding{provider_settings_owner($password);$b=domain_expiry_binding($linkId,$revision);if($b->registrar->data['profile_revision']!==$profileRevision||$b->registrar->data['source_refresh_id']!==$sourceId)throw new RuntimeException('Reload the current registrar expiry form.');return $b;}));
    return provider_inventory_refresh_attempt($binding->registrar->data['profile_id'],$profileRevision,$password,$resolver,$transport,DomainExpiry::OPERATION,expiryContext:$binding);
}
function domain_expiry_read_row(string $id):array {
    [$b,$row]=domain_expiry_original($id);$r=one('SELECT * FROM '.table('provider_refresh_results').' WHERE id=?',[$id]);$date=one('SELECT * FROM '.table('domain_expiry_timestamps').' WHERE id=?',[$id]);$state=$r['state']??'unfinished';$saved=null;
    if(!$r){if($date||one('SELECT id FROM '.table('audit').' WHERE BINARY action=? AND detail LIKE ?',['provider.refresh_finished','Refresh '.$id.' %']))throw new RuntimeException('Registrar expiry outcome needs recovery.');}
    else{
        if(!in_array($state,['complete','unavailable','refused','access_changed','credential'],true)||(int)$r['skipped']!==0||$r['skip_reasons']!=='[]'||$r['coverage']!=='unknown'||(int)$r['package_count']!==0)throw new RuntimeException('Registrar expiry outcome fields need recovery.');
        domain_expiry_audit_row((int)$row['actor_id'],'provider.refresh_finished','Refresh '.$id.' '.$state.($r['reason']!==''?' '.$r['reason']:''));
        if($state==='complete'){
            if(!$date||!DomainExpiryObservation::validTimestamp($date['expiry_timestamp'])||$r['reason']!==''||$r['scope']!==$b->scope||!hash_equals($b->registrar->data['key_fingerprint'],$r['key_fingerprint']))throw new RuntimeException('Saved registrar expiry needs recovery.');
            $safe=['id'=>$id,'binding_sha256'=>$b->sha256,'expiry_timestamp'=>$date['expiry_timestamp'],'observed_epoch'=>(int)$date['observed_epoch']];$hash=hash('sha256',domain_expiry_json($safe));if(!hash_equals($date['document_sha256'],$hash))throw new RuntimeException('Saved registrar expiry digest needs recovery.');domain_expiry_audit_row((int)$row['actor_id'],'domain_expiry.observed','Read '.$id.' timestamp '.$hash);$saved=$date['expiry_timestamp'];
        }elseif($date)throw new RuntimeException('Failed registrar expiry read has unexpected data.');
    }
    return ['id'=>$id,'seq'=>(int)$row['seq'],'binding'=>$b,'state'=>$state,'timestamp'=>$saved,'observed_epoch'=>$date?(int)$date['observed_epoch']:(int)$row['started_epoch']];
}
function domain_expiry_project(string $linkId,int $revision):array {
    $current=domain_expiry_binding($linkId,$revision);$rows=[];$status='not_observed';$date=null;$at=null;
    foreach(all('SELECT b.id FROM '.table('domain_expiry_bindings').' b JOIN '.table('provider_refreshes').' f ON f.id=b.id WHERE b.link_id=? ORDER BY f.seq DESC LIMIT 20',[$linkId]) as $i=>$raw){$r=domain_expiry_read_row($raw['id']);$same=hash_equals($current->credentialSha256,$r['binding']->credentialSha256);foreach($current->registrar->data as $k=>$v){if(str_starts_with($k,'source_'))continue;if($r['binding']->registrar->data[$k]!==$v)$same=false;}
        if($i===0){$status=$same?$r['state']:'assignment_changed';if($same&&$r['state']==='complete'){$fresh=$r['observed_epoch']>=time()-900&&$r['observed_epoch']<=time()+30;$status=$fresh?'observed':'stale';if($fresh){$date=$r['timestamp'];$at=$r['observed_epoch'];}}}
        if($same)$rows[]=['state'=>$r['state'],'timestamp'=>$r['timestamp'],'observed_epoch'=>$r['observed_epoch']];
    }
    return ['domain'=>$current->registrar->data['domain'],'status'=>$status,'timestamp'=>$date,'observed_epoch'=>$at,'history'=>$rows];
}
function domain_expiry_actor(int $id):void {if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Sign in directly to view registrar expiry observations.');$client=studio_client();if(!$client||(int)$client['id']!==$id||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$id]))throw new RuntimeException('Sign in to view registrar expiry observations.');}
function domain_expiry_client(int $id):array {
    return staff_locked(fn()=>staff_transaction(function()use($id):array{domain_expiry_actor($id);if(!provider_inventory_ready()||!provider_registrar_ready()||!domain_expiry_ready())return [];$out=[];foreach(all('SELECT id,revision FROM '.table('provider_registrar_links').' WHERE client_id=? ORDER BY domain',[$id]) as $l){try{$out[]=domain_expiry_project($l['id'],(int)$l['revision']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$out[]=['domain'=>null,'status'=>'unavailable','timestamp'=>null,'observed_epoch'=>null,'history'=>[]];}}return $out;}));
}
