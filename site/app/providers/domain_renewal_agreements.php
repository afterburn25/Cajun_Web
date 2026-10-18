<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_renewals.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_contracts.php';
require_once CWP_CODE_ROOT.'/app/plans/records.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_agreements_document.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_agreements_storage.php';

function domain_renewal_agreements_require():void {if(!domain_renewal_agreements_ready())throw new RuntimeException('Enable renewal agreements first.');}
function domain_renewal_agreements_locked():void {
    $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(!db()->inTransaction()||(int)query('SELECT IS_USED_LOCK(?)',[$key])->fetchColumn()!==(int)query('SELECT CONNECTION_ID()')->fetchColumn())throw new LogicException('Renewal agreements need the owning staff lock and transaction.');
}
/** This source-only guard never requires renewal-date or expiry storage. */
function domain_renewal_agreements_source_order(string $profileId):void {
    $f=table('provider_refreshes');$a=table('audit');
    $intents=(int)query("SELECT COUNT(*) FROM $f WHERE BINARY profile_id=?",[$profileId])->fetchColumn();$audits=(int)query("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.refresh_started' AND BINARY detail LIKE ?",['Refresh % profile '.$profileId.' revision %'])->fetchColumn();
    if($intents!==$audits)throw new RuntimeException('Provider observation intent history needs recovery.');
    $detail="CONCAT(BINARY 'Refresh ',f.id,BINARY ' profile ',f.profile_id,BINARY ' revision ',f.profile_revision)";
    if(one("SELECT f.id FROM $f f WHERE BINARY f.profile_id=? AND ((SELECT COUNT(*) FROM $a a WHERE BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail AND a.user_id=f.actor_id)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail)<>1) LIMIT 1",[$profileId]))throw new RuntimeException('Provider observation intent correspondence needs recovery.');
    $where="BINARY f.profile_id=? AND BINARY f.operation='domains.list'";
    $seq=one("SELECT f.id FROM $f f WHERE $where ORDER BY f.seq DESC LIMIT 1",[$profileId]);
    $audit=one("SELECT f.id FROM $f f JOIN $a a ON a.user_id=f.actor_id AND BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail WHERE $where ORDER BY a.id DESC LIMIT 1",[$profileId]);
    if(($seq['id']??null)!==($audit['id']??null))throw new RuntimeException('Registrar observation intent order needs recovery.');
}
function domain_renewal_agreements_authority(array $request):array {
    domain_renewal_agreements_locked();$d=$request['request'];
    if($request['state']!=='pending_review')throw new RuntimeException('This renewal request is no longer awaiting review.');
    [$l,$event,$audit]=domain_contracts_link($d['link_id'],$d['link_revision']);domain_renewal_agreements_source_order($l['profile_id']);
    foreach(['client_id','profile_id','profile_revision','key_fingerprint','domain_id','domain'] as $key)if((string)$d[$key]!== (string)$l[$key])throw new RuntimeException('The renewal request belongs to an earlier assignment or account.');
    $profile=provider_settings_load($l['profile_id']);$latest=provider_registrar_latest($l['profile_id']);
    if(!provider_registrar_fresh($profile,$latest)||!provider_inventory_link_context_matches($l,$profile,$latest)||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$l['client_id']]))throw new RuntimeException('Refresh registered domains and confirm the current assignment before agreeing to renewal terms.');
    $source=domain_contracts_source($latest['id'],(int)$l['domain_id']);if($source['domain']!==$l['domain'])throw new RuntimeException('Registered-domain identity changed.');
    return ['registrar'=>['profile_id'=>$l['profile_id'],'profile_revision'=>(int)$profile['revision'],'key_fingerprint'=>$l['key_fingerprint'],'domain_id'=>(int)$l['domain_id'],'domain'=>$l['domain'],'link_id'=>$l['id'],'link_revision'=>(int)$l['revision'],'client_id'=>(int)$l['client_id'],'link_event_id'=>$event['id'],'link_event_sha256'=>hash('sha256',domain_contracts_json($event)),'link_audit_id'=>$audit['id'],'link_audit_sha256'=>hash('sha256',domain_contracts_json($audit)),'source_refresh_id'=>$source['id'],'source_seq'=>$source['seq'],'source_sha256'=>hash('sha256',domain_contracts_json($source))],'credential_sha256'=>hash('sha256',$profile['credentials'])];
}
function domain_renewal_agreements_request_prefix(array $request,int $revision):string {
    if($revision<0||$revision>$request['revision'])throw new RuntimeException('Original renewal request revision needs recovery.');
    foreach(array_slice($request['events'],0,$revision) as $event)if($event['command']!=='reviewed')throw new RuntimeException('Renewal offer was bound after its request ended.');
    return $revision===0?hash('sha256',domain_renewals_json($request['request'])):hash('sha256',domain_renewals_json($request['events'][$revision-1]));
}
/** Historical bindings verify original source/event witnesses without asserting they remain current. */
function domain_renewal_agreements_original(DomainRenewalAgreementDocument $binding,array $request):void {
    $d=$binding->data();$r=$request['request'];$b=$d['registrar'];
    if($d['request_id']!==$r['id']||!hash_equals($d['request_document_sha256'],hash('sha256',domain_renewals_json($r)))||!hash_equals($d['request_sha256'],domain_renewal_agreements_request_prefix($request,$d['request_revision']))||$d['requested_years']!==$r['requested_years']||$d['requested_privacy']!==$r['renew_privacy'])throw new RuntimeException('Original renewal request terms need recovery.');
    $requestAt=$d['request_revision']===0?$r['created_epoch']:$request['events'][$d['request_revision']-1]['recorded_epoch'];if($d['created_epoch']<$requestAt)throw new RuntimeException('Renewal offer precedes its request revision.');
    foreach(['client_id','link_id','link_revision','profile_id','profile_revision','key_fingerprint','domain_id','domain'] as $key)if($b[$key]!==$r[$key])throw new RuntimeException('Original renewal agreement assignment needs recovery.');
    $source=domain_contracts_source($b['source_refresh_id'],$b['domain_id']);if($source['seq']!==$b['source_seq']||!hash_equals($b['source_sha256'],hash('sha256',domain_contracts_json($source)))||$source['profile_id']!==$b['profile_id']||$source['profile_revision']!==$b['profile_revision']||$source['key_fingerprint']!==$b['key_fingerprint']||$source['domain']!==$b['domain'])throw new RuntimeException('Original renewal agreement source needs recovery.');
    $event=one('SELECT * FROM '.table('provider_registrar_events').' WHERE id=?',[$b['link_event_id']]);if(!$event)throw new RuntimeException('Original renewal assignment event needs recovery.');
    $safe=[];foreach(['id','link_id','profile_id','domain_id','domain','client_id','revision','event','actor_id','recorded_at','key_fingerprint','profile_revision','refresh_id'] as $k)$safe[$k]=in_array($k,['id','domain_id','client_id','revision','actor_id','profile_revision'],true)?(int)$event[$k]:$event[$k];
    foreach(['link_id','profile_id','domain_id','domain','client_id','key_fingerprint','profile_revision'] as $k)if($safe[$k]!==$b[$k])throw new RuntimeException('Original renewal assignment identity needs recovery.');if($safe['revision']!==$b['link_revision'])throw new RuntimeException('Original renewal assignment revision needs recovery.');
    $audit=domain_contracts_audit_row((int)$event['actor_id'],'provider.registrar_'.$event['event'],'Registered-domain link '.$b['link_id'].' revision '.$b['link_revision']);
    if(!in_array($event['event'],['link','relink'],true)||!hash_equals($b['link_event_sha256'],hash('sha256',domain_contracts_json($safe)))||$audit['id']!==$b['link_audit_id']||!hash_equals($b['link_audit_sha256'],hash('sha256',domain_contracts_json($audit))))throw new RuntimeException('Original renewal assignment audit needs recovery.');
}
function domain_renewal_agreements_agreement(DomainRenewalAgreementDocument $binding,int $bindingAudit):array {
    $d=$binding->data();$row=service_plans_row($d['agreement_id'],true,true);$doc=service_plans_validate($row,true);$data=$doc->data();$initial=$data;$initial['events']=[];$original=ServiceSubscriptionDocument::fromJson(service_plans_json($initial));$model=$doc->model();
    if($data['client_id']!==$d['registrar']['client_id']||!hash_equals($d['agreement_sha256'],$original->fingerprint())||!hash_equals($d['agreement_terms_sha256'],$model->plan->termsHash())||$model->agreedPrice!==$d['price_minor']||$model->plan->currency!==$d['currency']||$model->plan->cadence!=='one-time'||$data['created_at']>$d['created_epoch'])throw new RuntimeException('Original renewal service agreement needs recovery.');
    $offered=domain_contracts_audit_row($data['created_by'],'service_plans.offered','Subscription '.$data['id'].' plan '.$row['plan_id'].' revision '.$row['plan_revision'].' client '.$data['client_id']);if($offered['id']>=$bindingAudit)throw new RuntimeException('Renewal binding precedes its service agreement.');
    $last=$bindingAudit;$events=$data['events'];
    if((int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',['service_plans.subscription_changed','Record '.$data['id'].' event %'])->fetchColumn()!==count($events))throw new RuntimeException('Renewal agreement event history needs recovery.');
    foreach($events as $i=>$event){[$kind,$actor]=explode(':',$event['actor'],2);$a=domain_contracts_audit_row($kind==='staff'?(int)$actor:0,'service_plans.subscription_changed','Record '.$data['id'].' event '.$event['id'].' revision '.($i+1).' '.$event['command'].' client '.$data['client_id']);if($a['id']<=$last)throw new RuntimeException('Renewal agreement event order needs recovery.');$last=$a['id'];}
    return ['document'=>$doc,'last_audit_id'=>$last];
}
function domain_renewal_agreements_history_locked(string $requestId):array {
    domain_renewal_agreements_locked();domain_renewal_agreements_require();$request=domain_renewals_load($requestId);$out=[];$previous=null;$previousTime=0;$lastAudit=0;
    foreach(all('SELECT * FROM '.table('domain_renewal_agreements').' WHERE request_id=? ORDER BY revision LIMIT 129',[$requestId]) as $row){
        try{$binding=DomainRenewalAgreementDocument::fromJson($row['document_json']);}catch(JsonException|InvalidArgumentException|TypeError $e){throw new RuntimeException('Renewal agreement binding needs recovery.',0,$e);}$d=$binding->data();
        if($d['id']!==$row['id']||$d['request_id']!==$requestId||$d['agreement_id']!==$row['agreement_id']||$d['registrar']['client_id']!==(int)$row['client_id']||$d['actor_id']!==(int)$row['actor_id']||$d['revision']!==(int)$row['revision']||$d['revision']!==count($out)+1||$d['created_epoch']!==(int)$row['created_epoch']||$d['created_epoch']<$previousTime||$d['previous_sha256']!==$previous||!hash_equals($binding->fingerprint(),$row['document_sha256']))throw new RuntimeException('Renewal agreement binding sequence needs recovery.');
        $audit=domain_contracts_audit_row($d['actor_id'],'domain_renewal_agreements.bound','Binding '.$d['id'].' request '.$requestId.' revision '.$d['revision'].' sha256 '.$binding->fingerprint());if($audit['id']<=$lastAudit)throw new RuntimeException('Renewal offer order needs recovery.');
        if($out){$old=$out[array_key_last($out)];$oldEvents=$old['agreement']->data()['events'];$endTime=$oldEvents?$oldEvents[array_key_last($oldEvents)]['at']:0;if(!in_array($old['agreement']->model()->state(),['abandoned','cancelled'],true)||$old['last_audit_id']>=$audit['id']||$d['created_epoch']<$endTime)throw new RuntimeException('An earlier renewal agreement was not ended before reoffering.');}
        domain_renewal_agreements_original($binding,$request);$agreement=domain_renewal_agreements_agreement($binding,$audit['id']);$out[]=['binding'=>$binding,'agreement'=>$agreement['document'],'last_audit_id'=>$agreement['last_audit_id']];$previous=$binding->fingerprint();$previousTime=$d['created_epoch'];$lastAudit=$audit['id'];
    }
    return ['request'=>$request,'offers'=>$out];
}
function domain_renewal_agreements_current(array $history,DomainRenewalAgreementDocument $binding,bool $newConsent):void {
    $offers=$history['offers'];$d=$binding->data();$last=$offers?end($offers):null;$request=$history['request'];
    if(!$last||$last['binding']->fingerprint()!==$binding->fingerprint()||$request['revision']!==$d['request_revision']||!hash_equals($request['sha256'],$d['request_sha256']))throw new RuntimeException('The renewal request or offer changed. Review its current terms.');
    $current=domain_renewal_agreements_authority($request);
    if(!hash_equals($d['credential_sha256'],$current['credential_sha256']))throw new RuntimeException('The renewal account credentials changed.');
    foreach($d['registrar'] as $k=>$v)if(!str_starts_with($k,'source_')&&$current['registrar'][$k]!==$v)throw new RuntimeException('The renewal assignment changed.');
    if($newConsent&&time()>=$d['expires_epoch'])throw new RuntimeException('This renewal offer expired. Ask the studio for new terms.');
}
function domain_renewal_agreements_bind(string $id,string $requestId,int $requestRevision,string $requestHash,string $agreementId,string $agreementHash,?string $previousHash,int $expires,bool $privacy,#[SensitiveParameter] string $password):DomainRenewalAgreementDocument {
    if(db()->inTransaction())throw new LogicException('Renewal offer cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($id,$requestId,$requestRevision,$requestHash,$agreementId,$agreementHash,$previousHash,$expires,$privacy,$password):DomainRenewalAgreementDocument{
        $actor=provider_settings_owner($password);service_plans_password($password);domain_renewals_id($id);$history=domain_renewal_agreements_history_locked($requestId);$request=$history['request'];$offers=$history['offers'];$last=$offers?end($offers):null;
        foreach($offers as $offer){$b=$offer['binding'];$d=$b->data();if($d['id']!==$id)continue;if($d['actor_id']!==(int)$actor['id']||$d['request_revision']!==$requestRevision||$d['request_sha256']!==$requestHash||$d['agreement_id']!==$agreementId||$d['agreement_sha256']!==$agreementHash||$d['previous_sha256']!==$previousHash||$d['expires_epoch']!==$expires||$d['renew_privacy']!==$privacy)throw new RuntimeException('This renewal offer identifier was already used.');return $b;}
        if($request['revision']!==$requestRevision||!hash_equals($request['sha256'],$requestHash)||($last?$last['binding']->fingerprint():null)!==$previousHash)throw new RuntimeException('The renewal request or offer changed. Reload before offering terms.');
        if($last&&!in_array($last['agreement']->model()->state(),['abandoned','cancelled'],true))throw new RuntimeException('End the earlier renewal agreement before offering new terms.');
        $authority=domain_renewal_agreements_authority($request);$agreement=service_plans_validate(service_plans_row($agreementId,true,true),true);$ad=$agreement->data();$model=$agreement->model();
        if($ad['client_id']!==$request['request']['client_id']||$agreement->revision()!==0||$model->state()!=='pending-consent'||$model->consent()!==null||!hash_equals($agreement->fingerprint(),$agreementHash)||$model->plan->cadence!=='one-time'||$model->plan->currency!=='USD')throw new RuntimeException('Choose this client’s pending one-time USD agreement before any consent.');
        if(one('SELECT id FROM '.table('domain_renewal_agreements').' WHERE agreement_id=?',[$agreementId]))throw new RuntimeException('This agreement already belongs to renewal history.');
        // Do not reinterpret an existing commercial hosting record as a domain agreement.
        $orders=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'service_orders']);if($orders&&one('SELECT id FROM '.table('service_orders').' WHERE agreement_id=?',[$agreementId]))throw new RuntimeException('This agreement already belongs to a hosting order.');
        $priorEvents=$last?$last['agreement']->data()['events']:[];$requestEvents=$request['events'];
        $at=max(time(),$ad['created_at'],$request['request']['created_epoch'],$requestEvents?$requestEvents[array_key_last($requestEvents)]['recorded_epoch']:0,$last?$last['binding']->data()['created_epoch']:0,$priorEvents?$priorEvents[array_key_last($priorEvents)]['at']:0);$r=$request['request'];
        $binding=DomainRenewalAgreementDocument::fromArray(['version'=>1,'id'=>$id,'request_id'=>$requestId,'revision'=>count($offers)+1,'previous_sha256'=>$previousHash,'request_revision'=>$requestRevision,'request_sha256'=>$requestHash,'request_document_sha256'=>hash('sha256',domain_renewals_json($r)),'agreement_id'=>$agreementId,'agreement_sha256'=>$agreementHash,'agreement_terms_sha256'=>$model->plan->termsHash(),'price_minor'=>$model->agreedPrice,'currency'=>'USD','requested_years'=>$r['requested_years'],'requested_privacy'=>$r['renew_privacy'],'renew_privacy'=>$privacy,'registrar'=>$authority['registrar'],'credential_sha256'=>$authority['credential_sha256'],'actor_id'=>(int)$actor['id'],'created_epoch'=>$at,'expires_epoch'=>$expires]);$d=$binding->data();
        query('INSERT INTO '.table('domain_renewal_agreements').' (id,request_id,agreement_id,client_id,actor_id,revision,created_epoch,document_json,document_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$id,$requestId,$agreementId,$r['client_id'],$actor['id'],$d['revision'],$at,$binding->json(),$binding->fingerprint()]);
        if($last){$changed=query('UPDATE '.table('domain_renewal_agreement_heads').' SET binding_count=?,current_id=?,current_sha256=? WHERE id=? AND binding_count=? AND current_sha256=?',[$d['revision'],$id,$binding->fingerprint(),$requestId,$d['revision']-1,$previousHash]);if($changed->rowCount()!==1)throw new RuntimeException('Renewal agreement head changed.');}
        else query('INSERT INTO '.table('domain_renewal_agreement_heads').' (id,binding_count,current_id,current_sha256) VALUES (?,1,?,?)',[$requestId,$id,$binding->fingerprint()]);
        domain_renewal_agreements_audit((int)$actor['id'],'bound','Binding '.$id.' request '.$requestId.' revision '.$d['revision'].' sha256 '.$binding->fingerprint());$count=(int)one('SELECT value FROM '.table('settings').' WHERE name=?',['domain_renewal_agreements_count'])['value'];save_setting('domain_renewal_agreements_count',(string)($count+1));domain_renewal_agreements_history_locked($requestId);return $binding;
    }));
}
/** Proposed common agreement append hook; existing event arguments/journals stay unchanged. */
function domain_renewal_agreements_guard(ServiceSubscriptionDocument $doc,string $command,string $actor,?string $reviewedBinding=null):void {
    domain_renewal_agreements_locked();if(!domain_renewal_agreements_ready())return;$id=$doc->data()['id'];$row=one('SELECT request_id FROM '.table('domain_renewal_agreements').' WHERE agreement_id=?',[$id]);if(!$row)return;
    $client=$doc->data()['client_id'];if($actor==='client:'.$client)service_plans_current_client($client);else{$staff=service_plans_owner();if($actor!=='staff:'.$staff['id'])throw new RuntimeException('Current renewal agreement actor is required.');}
    $history=domain_renewal_agreements_history_locked($row['request_id']);$offer=null;foreach($history['offers'] as $saved)if($saved['binding']->data()['agreement_id']===$id)$offer=$saved;if(!$offer||$offer['agreement']->fingerprint()!==$doc->fingerprint())throw new RuntimeException('Renewal agreement history changed.');
    if(in_array($command,['abandon','cancel','pause'],true))return;
    if(!in_array($command,['consent','activate','resume'],true)||!is_string($reviewedBinding)||!hash_equals($offer['binding']->fingerprint(),$reviewedBinding))throw new RuntimeException('Review the linked renewal terms before this agreement action.');
    domain_renewal_agreements_current($history,$offer['binding'],$command==='consent');
}
/** Proposed generic hosting-offer hook, including old captured hosting forms. */
function domain_renewal_agreements_exclude_hosting(string $agreementId):void {
    domain_renewal_agreements_locked();if(domain_renewal_agreements_ready()&&one('SELECT id FROM '.table('domain_renewal_agreements').' WHERE agreement_id=?',[$agreementId]))throw new RuntimeException('This agreement is reserved for a domain renewal request.');
}
function domain_renewal_agreements_context(string $agreementId,int $clientId,bool $asClient):?array {
    if(db()->inTransaction())throw new LogicException('Renewal agreement display cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($agreementId,$clientId,$asClient):?array{
        if($asClient)service_plans_current_client($clientId);else{service_plans_owner();service_plans_client_row($clientId,false);}if(!domain_renewal_agreements_ready())return null;
        $row=one('SELECT request_id FROM '.table('domain_renewal_agreements').' WHERE agreement_id=? AND client_id=?',[$agreementId,$clientId]);if(!$row)return null;$history=domain_renewal_agreements_history_locked($row['request_id']);
        foreach($history['offers'] as $offer){$b=$offer['binding'];$d=$b->data();if($d['agreement_id']!==$agreementId)continue;$model=$offer['agreement']->model();$actionable=true;
            try{domain_renewal_agreements_current($history,$b,$model->consent()===null);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$actionable=false;}
            return ['request_id'=>$d['request_id'],'binding_sha256'=>$b->fingerprint(),'domain'=>$d['registrar']['domain'],'years'=>$d['requested_years'],'requested_privacy'=>$d['requested_privacy'],'renew_privacy'=>$d['renew_privacy'],'price_minor'=>$d['price_minor'],'currency'=>'USD','expires_epoch'=>$d['expires_epoch'],'state'=>$model->state(),'consented'=>$model->consent()!==null,'actionable'=>$actionable&&!in_array($model->state(),['abandoned','cancelled'],true)];
        }throw new RuntimeException('Renewal agreement display needs recovery.');
    }));
}
/** Native owner offer screen; every candidate remains subject to the locked bind checks. */
function domain_renewal_agreements_owner_request(string $requestId,int $page=1):array {
    if(db()->inTransaction())throw new LogicException('Renewal offer display cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($requestId,$page):array{
        provider_settings_manager();service_plans_owner();$history=domain_renewal_agreements_history_locked($requestId);$request=$history['request'];$offers=$history['offers'];$last=$offers?end($offers):null;$available=true;
        try{domain_renewal_agreements_authority($request);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$available=false;}
        if($last&&!in_array($last['agreement']->model()->state(),['abandoned','cancelled'],true))$available=false;
        $page=max(1,min(10000,$page));$offset=($page-1)*10;$candidates=[];
        if($available)foreach(all('SELECT s.id FROM '.table('service_subscriptions').' s LEFT JOIN '.table('domain_renewal_agreements').' b ON b.agreement_id=s.id WHERE s.client_id=? AND s.revision=0 AND b.id IS NULL ORDER BY s.created_epoch DESC,s.id DESC LIMIT 10 OFFSET '.$offset,[$request['request']['client_id']]) as $row){$doc=service_plans_validate(service_plans_row($row['id'],true,true),true);$m=$doc->model();if($m->state()==='pending-consent'&&$m->consent()===null&&$m->plan->cadence==='one-time'&&$m->plan->currency==='USD')$candidates[]=$doc;}
        return ['history'=>$history,'can_offer'=>$available,'candidates'=>$candidates,'page'=>$page,'previous_sha256'=>$last?$last['binding']->fingerprint():null];
    }));
}

/** Own-request links for the existing request history, including ended historical agreements. */
function domain_renewal_agreements_request_links(string $requestId,int $clientId,bool $asClient):array {
    if(db()->inTransaction())throw new LogicException('Renewal offer links cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($requestId,$clientId,$asClient):array{
        if($asClient)domain_renewals_actor($clientId);else provider_settings_manager();
        if(!domain_renewal_agreements_ready())return [];
        if($asClient)service_plans_current_client($clientId);else service_plans_owner();
        if(!one('SELECT id FROM '.table('domain_renewal_requests').' WHERE id=? AND client_id=?',[$requestId,$clientId]))throw new RuntimeException('Renewal request unavailable.');
        $history=domain_renewal_agreements_history_locked($requestId);$out=[];foreach($history['offers'] as $offer){$d=$offer['binding']->data();$out[]=['agreement_id'=>$d['agreement_id'],'revision'=>$d['revision'],'price_minor'=>$d['price_minor'],'state'=>$offer['agreement']->model()->state(),'consented'=>$offer['agreement']->model()->consent()!==null];}return $out;
    }));
}
