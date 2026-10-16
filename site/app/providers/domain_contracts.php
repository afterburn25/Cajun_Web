<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/registrar.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_contracts_storage.php';
foreach(['DomainContractBinding','DomainContractObservation','DomainContract'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-domain-contracts/'.$class.'.php';
use CajunWebPro\Foundation\TwentyIDomainContracts\{DomainContractBinding,DomainContractObservation,DomainContract};
function domain_contracts_json(array $data):string {return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);}
function domain_contracts_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/',$id))throw new InvalidArgumentException('Reload the renewal date form.');}
function domain_contracts_audit_row(int $actor,string $action,string $detail):array {
    $rows=all('SELECT id,user_id,action,detail FROM '.table('audit').' WHERE user_id=? AND BINARY action=? AND BINARY detail=?',[$actor,$action,$detail]);if(count($rows)!==1)throw new RuntimeException('Renewal date authority audit needs recovery.');$r=$rows[0];return ['id'=>(int)$r['id'],'actor_id'=>(int)$r['user_id'],'action'=>$r['action'],'detail'=>$r['detail']];
}
/** Preserve the entire current registrar event/audit identity, including later unlinks. */
function domain_contracts_link(string $id,int $revision):array {
    $link=provider_registrar_current($id,$revision);$events=all('SELECT * FROM '.table('provider_registrar_events').' WHERE link_id=? ORDER BY id',[$id]);$last=null;$lastAudit=null;$rev=0;$auditId=0;$counts=['link'=>0,'relink'=>0,'unlink'=>0];
    foreach($events as $e){
        if(!array_key_exists($e['event'],$counts))throw new RuntimeException('Registered-domain assignment event needs recovery.');$counts[$e['event']]++;
        if(($last===null&&$e['event']!=='link')||($last!==null&&($e['event']==='link'||$last['event']==='unlink'))||(int)$e['revision']!==($e['event']==='unlink'?$rev:$rev+1))throw new RuntimeException('Registered-domain assignment sequence needs recovery.');
        foreach(['profile_id','domain_id','domain','client_id'] as $field)if((string)$e[$field]!== (string)$link[$field])throw new RuntimeException('Registered-domain assignment identity needs recovery.');
        $a=domain_contracts_audit_row((int)$e['actor_id'],'provider.registrar_'.$e['event'],'Registered-domain link '.$id.' revision '.$e['revision']);if($a['id']<=$auditId)throw new RuntimeException('Registered-domain assignment audit order needs recovery.');$auditId=$a['id'];$rev=(int)$e['revision'];$last=$e;$lastAudit=$a;
    }
    foreach($counts as $kind=>$count)if((int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=? AND detail LIKE ?',['provider.registrar_'.$kind,'Registered-domain link '.$id.' revision %'])->fetchColumn()!==$count)throw new RuntimeException('Registered-domain assignment audit history needs recovery.');
    if(!$last||!in_array($last['event'],['link','relink'],true)||(int)$last['revision']!==$revision)throw new RuntimeException('Registered-domain assignment changed.');
    $event=[];foreach(['id','link_id','profile_id','domain_id','domain','client_id','revision','event','actor_id','recorded_at','key_fingerprint','profile_revision','refresh_id'] as $k)$event[$k]=in_array($k,['id','domain_id','client_id','revision','actor_id','profile_revision'],true)?(int)$last[$k]:$last[$k];
    return [$link,$event,$lastAudit];
}
/** Validate immutable original source fields without requiring that an old source remain latest. */
function domain_contracts_source(string $refreshId,int $domainId):array {
    domain_contracts_id($refreshId);$r=one('SELECT f.*,r.state,r.reason,r.scope,r.coverage,r.package_count,r.skipped,r.key_fingerprint FROM '.table('provider_refreshes').' f JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.id=?',[$refreshId]);
    if(!$r||$r['operation']!=='domains.list'||$r['state']!=='complete'||$r['reason']!==''||$r['scope']!=='twenty-i|'.$r['profile_id'].'|domains.list'||$r['coverage']!=='unknown'||(int)$r['skipped']!==0)throw new RuntimeException('Original registrar observation needs recovery.');
    $start=domain_contracts_audit_row((int)$r['actor_id'],'provider.refresh_started','Refresh '.$r['id'].' profile '.$r['profile_id'].' revision '.$r['profile_revision']);$finish=domain_contracts_audit_row((int)$r['actor_id'],'provider.refresh_finished','Refresh '.$r['id'].' complete');if($finish['id']<=$start['id'])throw new RuntimeException('Registrar observation audit order needs recovery.');
    $rows=provider_registrar_rows($refreshId);if(count($rows)!==(int)$r['package_count'])throw new RuntimeException('Original registrar observation rows need recovery.');$domain=null;foreach($rows as $row)if((int)$row['domain_id']===$domainId)$domain=$row;
    if(!$domain)throw new RuntimeException('Original registered domain is missing.');
    $safe=['id'=>$r['id'],'seq'=>(int)$r['seq'],'profile_id'=>$r['profile_id'],'profile_revision'=>(int)$r['profile_revision'],'actor_id'=>(int)$r['actor_id'],'started_at'=>$r['started_at'],'started_epoch'=>(int)$r['started_epoch'],'key_fingerprint'=>$r['key_fingerprint'],'domain_id'=>(int)$domain['domain_id'],'domain'=>$domain['domain'],'unmapped_count'=>(int)$domain['unmapped_count'],'package_count'=>count($rows),'start_audit'=>$start,'finish_audit'=>$finish];return $safe;
}
/** Independent common-start audit order must agree before any retained latest intent is authority. */
function domain_contracts_intent_order(string $profileId,string $linkId):void {
    $f=table('provider_refreshes');$a=table('audit');$b=table('domain_contract_bindings');
    $intents=(int)query("SELECT COUNT(*) FROM $f WHERE BINARY profile_id=?",[$profileId])->fetchColumn();$audits=(int)query("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.refresh_started' AND BINARY detail LIKE ?",['Refresh % profile '.$profileId.' revision %'])->fetchColumn();
    if($intents!==$audits)throw new RuntimeException('Provider observation intent history needs recovery.');
    $detail="CONCAT(BINARY 'Refresh ',f.id,BINARY ' profile ',f.profile_id,BINARY ' revision ',f.profile_revision)";
    if(one("SELECT f.id FROM $f f WHERE BINARY f.profile_id=? AND ((SELECT COUNT(*) FROM $a a WHERE BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail AND a.user_id=f.actor_id)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail)<>1) LIMIT 1",[$profileId]))throw new RuntimeException('Provider observation intent correspondence needs recovery.');
    $paired="JOIN $a a ON a.user_id=f.actor_id AND BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail";
    $registrar="BINARY f.profile_id=? AND BINARY f.operation='domains.list'";
    $bySequence=one("SELECT f.id FROM $f f WHERE $registrar ORDER BY f.seq DESC LIMIT 1",[$profileId]);$byAudit=one("SELECT f.id FROM $f f $paired WHERE $registrar ORDER BY a.id DESC LIMIT 1",[$profileId]);
    if(($bySequence['id']??null)!==($byAudit['id']??null))throw new RuntimeException('Registrar observation intent order needs recovery.');
    $date="JOIN $b b ON b.id=f.id WHERE BINARY f.profile_id=? AND BINARY f.operation='domain.contract' AND BINARY b.link_id=?";
    $bySequence=one("SELECT f.id FROM $f f $date ORDER BY f.seq DESC LIMIT 1",[$profileId,$linkId]);$byAudit=one("SELECT f.id FROM $f f $paired $date ORDER BY a.id DESC LIMIT 1",[$profileId,$linkId]);
    if(($bySequence['id']??null)!==($byAudit['id']??null))throw new RuntimeException('Renewal date intent order needs recovery.');
}
/** Current assignment/account/identity, with an exact complete fresh registrar read. */
function domain_contracts_binding(string $linkId,int $linkRevision):DomainContractBinding {
    domain_contracts_id($linkId);if(!domain_contracts_ready())throw new RuntimeException('Enable renewal date observations first.');[$l,$event,$audit]=domain_contracts_link($linkId,$linkRevision);domain_contracts_intent_order($l['profile_id'],$linkId);$profile=provider_settings_load($l['profile_id']);$latest=provider_registrar_latest($l['profile_id']);
    if(!provider_registrar_fresh($profile,$latest)||!provider_inventory_link_context_matches($l,$profile,$latest)||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$l['client_id']]))throw new RuntimeException('Refresh registered domains and confirm this assignment first.');
    $source=domain_contracts_source($latest['id'],(int)$l['domain_id']);if($source['domain']!==$l['domain'])throw new RuntimeException('Registered-domain identity changed.');
    return new DomainContractBinding(['profile_id'=>$l['profile_id'],'profile_revision'=>(int)$profile['revision'],'key_fingerprint'=>$l['key_fingerprint'],'domain_id'=>(int)$l['domain_id'],'domain'=>$l['domain'],'link_id'=>$l['id'],'link_revision'=>(int)$l['revision'],'client_id'=>(int)$l['client_id'],'link_event_id'=>$event['id'],'link_event_sha256'=>hash('sha256',domain_contracts_json($event)),'link_audit_id'=>$audit['id'],'link_audit_sha256'=>hash('sha256',domain_contracts_json($audit)),'source_refresh_id'=>$source['id'],'source_seq'=>$source['seq'],'source_sha256'=>hash('sha256',domain_contracts_json($source))]);
}
function domain_contracts_authority(DomainContractBinding $binding):void {
    provider_settings_manager();$current=domain_contracts_binding($binding->data['link_id'],$binding->data['link_revision']);if(!hash_equals($binding->sha256,$current->sha256))throw new RuntimeException('Registered-domain read permission changed.');
}
/** Internal shared-executor hook, in its existing intent transaction. */
function domain_contracts_start(array $started,DomainContractBinding $binding):void {
    if(!db()->inTransaction()||$started['operation']!==DomainContract::OPERATION||$started['profile_id']!==$binding->data['profile_id']||$started['profile_revision']!==$binding->data['profile_revision'])throw new LogicException('Invalid domain read intent.');
    query('INSERT INTO '.table('domain_contract_bindings').' (id,link_id,client_id,actor_id,started_epoch,binding_json,binding_sha256) VALUES (?,?,?,?,?,?,?)',[$started['id'],$binding->data['link_id'],$binding->data['client_id'],$started['actor_id'],$started['started_epoch'],$binding->json,$binding->sha256]);domain_contracts_audit((int)$started['actor_id'],'bound','Read '.$started['id'].' binding '.$binding->sha256);$count=(int)one('SELECT value FROM '.table('settings').' WHERE name=?',['domain_contracts_intent_count'])['value'];save_setting('domain_contracts_intent_count',(string)($count+1));
}
function domain_contracts_original(string $id):array {
    domain_contracts_id($id);$row=one('SELECT b.*,f.seq,f.profile_id,f.profile_revision,f.actor_id AS intent_actor,f.started_epoch AS intent_epoch,f.started_at,f.operation FROM '.table('domain_contract_bindings').' b JOIN '.table('provider_refreshes').' f ON f.id=b.id WHERE b.id=?',[$id]);if(!$row)throw new RuntimeException('Original renewal date binding needs recovery.');
    try{$b=new DomainContractBinding(json_decode($row['binding_json'],true,8,JSON_THROW_ON_ERROR));}catch(JsonException|InvalidArgumentException|TypeError $e){throw new RuntimeException('Renewal date binding needs recovery.',0,$e);}
    $d=$b->data;if($row['binding_json']!==$b->json||!hash_equals($row['binding_sha256'],$b->sha256)||$row['operation']!==DomainContract::OPERATION||$row['profile_id']!==$d['profile_id']||(int)$row['profile_revision']!==$d['profile_revision']||$row['link_id']!==$d['link_id']||(int)$row['client_id']!==$d['client_id']||(int)$row['actor_id']!==(int)$row['intent_actor']||(int)$row['started_epoch']!==(int)$row['intent_epoch'])throw new RuntimeException('Renewal date binding identity needs recovery.');
    $a=domain_contracts_audit_row((int)$row['actor_id'],'provider.refresh_started','Refresh '.$id.' profile '.$d['profile_id'].' revision '.$d['profile_revision']);domain_contracts_audit_row((int)$row['actor_id'],'domain_contracts.bound','Read '.$id.' binding '.$b->sha256);if($a['id']<=$d['link_audit_id'])throw new RuntimeException('Renewal date read precedes its assignment.');
    $source=domain_contracts_source($d['source_refresh_id'],$d['domain_id']);if($source['seq']!==$d['source_seq']||!hash_equals($d['source_sha256'],hash('sha256',domain_contracts_json($source))))throw new RuntimeException('Original registrar source needs recovery.');
    // Retain and verify the original assignment even after a legitimate later reapproval/unlink.
    $event=one('SELECT * FROM '.table('provider_registrar_events').' WHERE id=?',[$d['link_event_id']]);if(!$event)throw new RuntimeException('Original domain assignment needs recovery.');$safe=[];foreach(['id','link_id','profile_id','domain_id','domain','client_id','revision','event','actor_id','recorded_at','key_fingerprint','profile_revision','refresh_id'] as $k)$safe[$k]=in_array($k,['id','domain_id','client_id','revision','actor_id','profile_revision'],true)?(int)$event[$k]:$event[$k];
    $audit=domain_contracts_audit_row((int)$event['actor_id'],'provider.registrar_'.$event['event'],'Registered-domain link '.$d['link_id'].' revision '.$d['link_revision']);if(!hash_equals($d['link_event_sha256'],hash('sha256',domain_contracts_json($safe)))||$audit['id']!==$d['link_audit_id']||!hash_equals($d['link_audit_sha256'],hash('sha256',domain_contracts_json($audit))))throw new RuntimeException('Original domain assignment audit needs recovery.');
    return [$b,$row];
}
/** Called only inside the shared result writer transaction, never a second execution path. */
function domain_contracts_record(array $started,string $state,?DomainContractObservation $snapshot,string $fingerprint):void {
    if(!db()->inTransaction())throw new LogicException('Domain result requires the shared transaction.');[$b,$row]=domain_contracts_original($started['id']);
    if($state==='complete'){
        if(!$snapshot||$snapshot->state!=='complete'||$snapshot->scope!==$b->scope||$snapshot->domainId!==$b->data['domain_id']||$snapshot->domainName!==$b->data['domain']||!hash_equals($b->sha256,$snapshot->binding->sha256)||!hash_equals($b->data['key_fingerprint'],$fingerprint))throw new LogicException('Domain result does not match the exact intent.');
        $date=['id'=>$started['id'],'binding_sha256'=>$b->sha256,'renewal_date'=>$snapshot->renewalDate,'observed_epoch'=>$snapshot->observedAt];$hash=hash('sha256',domain_contracts_json($date));query('INSERT INTO '.table('domain_contract_dates').' (id,renewal_date,observed_epoch,document_sha256) VALUES (?,?,?,?)',[$started['id'],$snapshot->renewalDate,$snapshot->observedAt,$hash]);domain_contracts_audit((int)$started['actor_id'],'observed','Read '.$started['id'].' date '.$hash);
    }elseif($snapshot!==null)throw new LogicException('Unavailable domain result cannot persist a date.');
    $count=(int)one('SELECT value FROM '.table('settings').' WHERE name=?',['domain_contracts_result_count'])['value'];save_setting('domain_contracts_result_count',(string)($count+1));
}
function domain_contracts_refresh(string $linkId,int $revision,int $profileRevision,string $sourceId,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    $binding=staff_locked(fn()=>staff_transaction(function()use($linkId,$revision,$profileRevision,$sourceId,$password):DomainContractBinding{provider_settings_owner($password);$b=domain_contracts_binding($linkId,$revision);if($b->data['profile_revision']!==$profileRevision||$b->data['source_refresh_id']!==$sourceId)throw new RuntimeException('Reload the current renewal date form.');return $b;}));
    return provider_inventory_refresh_attempt($binding->data['profile_id'],$profileRevision,$password,$resolver,$transport,DomainContract::OPERATION,null,$binding);
}
function domain_contracts_read_row(string $id):array {
    [$b,$row]=domain_contracts_original($id);$r=one('SELECT * FROM '.table('provider_refresh_results').' WHERE id=?',[$id]);$date=one('SELECT * FROM '.table('domain_contract_dates').' WHERE id=?',[$id]);$state=$r['state']??'unfinished';$saved=null;
    if(!$r){if($date||one('SELECT id FROM '.table('audit').' WHERE BINARY action=? AND detail LIKE ?',['provider.refresh_finished','Refresh '.$id.' %']))throw new RuntimeException('Renewal date outcome needs recovery.');}
    else{
        if(!in_array($state,['complete','unavailable','refused','access_changed','credential'],true)||(int)$r['skipped']!==0||$r['skip_reasons']!=='[]'||$r['coverage']!=='unknown'||(int)$r['package_count']!==0)throw new RuntimeException('Renewal date outcome fields need recovery.');
        domain_contracts_audit_row((int)$row['actor_id'],'provider.refresh_finished','Refresh '.$id.' '.$state.($r['reason']!==''?' '.$r['reason']:''));
        if($state==='complete'){
            if(!$date||!DomainContractObservation::validDate($date['renewal_date'])||$r['reason']!==''||$r['scope']!==$b->scope||!hash_equals($b->data['key_fingerprint'],$r['key_fingerprint']))throw new RuntimeException('Saved renewal date needs recovery.');
            $safe=['id'=>$id,'binding_sha256'=>$b->sha256,'renewal_date'=>$date['renewal_date'],'observed_epoch'=>(int)$date['observed_epoch']];$hash=hash('sha256',domain_contracts_json($safe));if(!hash_equals($date['document_sha256'],$hash))throw new RuntimeException('Saved renewal date digest needs recovery.');domain_contracts_audit_row((int)$row['actor_id'],'domain_contracts.observed','Read '.$id.' date '.$hash);$saved=$date['renewal_date'];
        }elseif($date)throw new RuntimeException('Failed renewal date read has unexpected data.');
    }
    return ['id'=>$id,'seq'=>(int)$row['seq'],'binding'=>$b,'state'=>$state,'date'=>$saved,'observed_epoch'=>$date?(int)$date['observed_epoch']:(int)$row['started_epoch']];
}
function domain_contracts_project(string $linkId,int $revision):array {
    $current=domain_contracts_binding($linkId,$revision);$rows=[];$status='not_observed';$date=null;$at=null;
    foreach(all('SELECT b.id FROM '.table('domain_contract_bindings').' b JOIN '.table('provider_refreshes').' f ON f.id=b.id WHERE b.link_id=? ORDER BY f.seq DESC LIMIT 20',[$linkId]) as $i=>$raw){$r=domain_contracts_read_row($raw['id']);$same=true;foreach($current->data as $k=>$v){if(str_starts_with($k,'source_'))continue;if($r['binding']->data[$k]!==$v)$same=false;}
        if($i===0){$status=$same?$r['state']:'assignment_changed';if($same&&$r['state']==='complete'){$fresh=$r['observed_epoch']>=time()-900&&$r['observed_epoch']<=time()+30;$status=$fresh?'observed':'stale';if($fresh){$date=$r['date'];$at=$r['observed_epoch'];}}}
        if($same)$rows[]=['state'=>$r['state'],'date'=>$r['date'],'observed_epoch'=>$r['observed_epoch']];
    }
    return ['domain'=>$current->data['domain'],'status'=>$status,'date'=>$date,'observed_epoch'=>$at,'history'=>$rows];
}
function domain_contracts_actor(int $id):void {if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Sign in directly to view renewal dates.');$client=studio_client();if(!$client||(int)$client['id']!==$id||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$id]))throw new RuntimeException('Sign in to view renewal dates.');}
function domain_contracts_client(int $id):array {
    return staff_locked(fn()=>staff_transaction(function()use($id):array{domain_contracts_actor($id);if(!provider_inventory_ready()||!provider_registrar_ready()||!domain_contracts_ready())return [];$out=[];foreach(all('SELECT id,revision FROM '.table('provider_registrar_links').' WHERE client_id=? ORDER BY domain',[$id]) as $l){try{$out[]=domain_contracts_project($l['id'],(int)$l['revision']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$out[]=['domain'=>null,'status'=>'unavailable','date'=>null,'observed_epoch'=>null,'history'=>[]];}}return $out;}));
}
