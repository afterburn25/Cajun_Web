<?php
/** Current owner/client authority and atomic saved terms/consent. No route, billing or renewal worker. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/plans/documents.php';
require_once CWP_CODE_ROOT.'/database/schema/service_plans_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/service_plans_migrate.php';

function service_plans_ready():bool {
    if((one('SELECT value FROM '.table('settings').' WHERE name=?',['service_plans_schema_version'])['value']??'')!=='1')return false;
    $names=array_map(fn($name)=>config()['db']['prefix'].$name,array_keys(service_plans_schema(config()['db']['prefix'])));
    return count(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN (?,?,?,?)',$names))===count($names);
}
function service_plans_require_ready():void {if(!service_plans_ready())throw new RuntimeException('An owner must prepare service-plan storage first.');}
function service_plans_owner(bool $ready=true):array {
    if(support_current() || array_key_exists('support',$_GET))throw new RuntimeException('Leave client support before managing service plans.');
    $actor=admin_user();
    if(!$actor || !staff_ready() || !studio_ready() || $actor['role']!=='owner' || !staff_can('staff.manage',$actor) || !staff_can('catalog.manage',$actor) || !staff_can('clients.manage',$actor))throw new RuntimeException('A currently authorized owner account is required.');
    if($ready)service_plans_require_ready();return $actor;
}
function service_plans_password(#[\SensitiveParameter] string $password,bool $ready=true):array {
    $actor=service_plans_owner($ready);if(!password_verify($password,$actor['password_hash']))throw new InvalidArgumentException('Your current password is not correct.');return $actor;
}
function service_plans_audit(int $actor,string $action,string $detail):void {query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'service_plans.'.$action,$detail,gmdate('Y-m-d H:i:s')]);}
function service_plans_client_row(int $id,bool $active=true):array {
    service_plans_actor($id);$row=one('SELECT * FROM '.table('studio_clients').' WHERE id=?'.(db()->inTransaction()?' FOR UPDATE':''),[$id]);
    if(!$row || ($active && (int)$row['active']!==1))throw new InvalidArgumentException('Client unavailable.');return $row;
}
/** Locking read after the shared guard; never treat a support session as client agreement. */
function service_plans_current_client(int $id):array {
    service_plans_require_ready();
    if(!studio_ready() || support_current() || array_key_exists('support',$_GET) || (int)($_SESSION['client_id']??0)!==$id)throw new RuntimeException('Sign in as the selected client outside support access.');
    $client=service_plans_client_row($id);$now=time();
    if($now-(int)($_SESSION['client_last']??0)>1800 || $now-(int)($_SESSION['client_login']??0)>28800 || !hash_equals($client['auth_version'],(string)($_SESSION['client_auth']??'')))throw new RuntimeException('Your client session is no longer current. Sign in again.');
    $_SESSION['client_last']=$now;
    // Contacts share the parent client session; recheck the live contact row here so a revoked or non-billing contact is denied inside this lock.
    if(!empty($_SESSION['contact_id'])){
        require_once CWP_CODE_ROOT.'/app/clients/contacts.php';
        $contact=contacts_ready()?one('SELECT enabled,auth_version,permissions FROM '.table('studio_contacts').' WHERE id=?',[(int)$_SESSION['contact_id']]):null;
        if(!$contact||!(int)$contact['enabled']||!hash_equals($contact['auth_version'],(string)($_SESSION['contact_auth']??''))||!in_array('billing',contact_perms_decode($contact['permissions']),true))
            throw new RuntimeException('Your contact permissions do not include billing.');
    }
    return $client;
}
function service_plans_row(string $id,bool $subscription=false,bool $lock=false):array {
    service_plans_id($id);$row=one('SELECT * FROM '.table($subscription?'service_subscriptions':'service_plans').' WHERE id=?'.($lock?' FOR UPDATE':''),[$id]);
    if(!$row)throw new InvalidArgumentException('Service plan unavailable.');return $row;
}
/** Compare creation, the bounded journal and every retained hash-linked event before use. */
function service_plans_validate(array $row,bool $subscription=false):ServicePlanDocument|ServiceSubscriptionDocument {
    $class=$subscription?ServiceSubscriptionDocument::class:ServicePlanDocument::class;
    $initial=$class::fromJson($row['initial_json']);$current=$class::fromJson($row['journal_json']);$start=$initial->data();$d=$current->data();$events=$d['events'];$d['events']=[];
    if($initial->revision()!==0 || $start!==$d || $d['id']!==$row['id'] || $d['created_by']!==(int)$row['created_by'] || $d['created_at']!==(int)$row['created_epoch'] || $current->revision()!==(int)$row['revision'] || !hash_equals($current->fingerprint(),$row['journal_sha256']))throw new RuntimeException('Saved service-plan records are inconsistent.');
    if($subscription){
        if($d['client_id']!==(int)$row['client_id'])throw new RuntimeException('Saved subscription client is inconsistent.');
        $plan=service_plans_validate(service_plans_row($row['plan_id']));$prefix=$plan->prefix((int)$row['plan_revision']);
        if($d['plan']!==$prefix->data() || !hash_equals($prefix->fingerprint(),$row['plan_sha256']))throw new RuntimeException('Saved subscription terms no longer match the original plan revision.');
    }
    $parent=$subscription?'subscription_id':'plan_id';$table=$subscription?'service_subscription_events':'service_plan_events';
    $retained=all('SELECT * FROM '.table($table).' WHERE '.$parent.'=? ORDER BY revision LIMIT '.($class::MAX_EVENTS+1),[$row['id']]);
    if(count($retained)!==count($events))throw new RuntimeException('A service-plan event is missing.');
    $before=$initial->fingerprint();$prefix=$start;
    foreach($events as $i=>$event){
        $saved=$retained[$i];$prefix['events'][]=$event;$after=hash('sha256',service_plans_json($prefix));
        $actor=$subscription?($saved['actor_staff_id']!==null?'staff:'.$saved['actor_staff_id']:'client:'.$saved['actor_client_id']):(int)$saved['actor_id'];
        if($subscription && (($saved['actor_staff_id']===null)===($saved['actor_client_id']===null)))throw new RuntimeException('A subscription event has ambiguous attribution.');
        $input=service_plans_json(['revision'=>$i,'command'=>$event['command'],'arguments'=>$event['arguments']]);
        if($saved['id']!==$event['id'] || (int)$saved['revision']!==$i+1 || $actor!==$event['actor'] || (int)$saved['recorded_epoch']!==$event['at'] || $saved['input_json']!==$input || !hash_equals($before,$saved['before_sha256']) || !hash_equals($after,$saved['after_sha256']))throw new RuntimeException('Saved service-plan event is inconsistent.');
        $before=$after;
    }
    return $current;
}
function service_plans_create(string $id,array $terms,#[\SensitiveParameter] string $password):ServicePlanDocument {
    return staff_locked(fn()=>staff_transaction(function()use($id,$terms,$password):ServicePlanDocument {
        $actor=service_plans_password($password);service_plans_id($id);$old=one('SELECT * FROM '.table('service_plans').' WHERE id=? FOR UPDATE',[$id]);
        $doc=ServicePlanDocument::create($id,(int)$actor['id'],$old?(int)$old['created_epoch']:time(),$terms);
        if($old){$current=service_plans_validate($old);if($old['initial_json']!==$doc->json())throw new InvalidArgumentException('This plan identity was already used.');return $current;}
        query('INSERT INTO '.table('service_plans').' (id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,?,?,?,?,?,0)',[$id,$actor['id'],$doc->data()['created_at'],$doc->json(),$doc->json(),$doc->fingerprint()]);
        service_plans_audit((int)$actor['id'],'created','Plan '.$id);return $doc;
    }));
}
/** Caller holds the shared guard, current authority and this record's transaction. */
function service_plans_append_locked(array $row,int $revision,string $event,string $command,int|string $actor,array $args,bool $subscription=false,?string $renewalBinding=null):ServicePlanDocument|ServiceSubscriptionDocument {
    if(!db()->inTransaction())throw new LogicException('Plan event needs its owning transaction.');service_plans_id($event);
    $doc=service_plans_validate($row,$subscription);$table=$subscription?'service_subscription_events':'service_plan_events';$parent=$subscription?'subscription_id':'plan_id';
    if($subscription){require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_agreements.php';domain_renewal_agreements_guard($doc,$command,(string)$actor,$renewalBinding);}
    if($revision<0 || $revision>=$doc::MAX_EVENTS)throw new InvalidArgumentException('Invalid plan revision.');
    $input=service_plans_json(['revision'=>$revision,'command'=>$command,'arguments'=>$args]);$old=one('SELECT * FROM '.table($table).' WHERE id=?',[$event]);
    if($old){
        $oldActor=$subscription?($old['actor_staff_id']!==null?'staff:'.$old['actor_staff_id']:'client:'.$old['actor_client_id']):(int)$old['actor_id'];
        if($old[$parent]!==$row['id'] || $oldActor!==$actor || $old['input_json']!==$input)throw new InvalidArgumentException('This plan event identity was already used.');return $doc;
    }
    if($doc->revision()!==$revision)throw new RuntimeException('This plan changed. Reload and review the current version.');
    $d=$doc->data();$events=$d['events'];$at=max(time(),$events?$events[array_key_last($events)]['at']:$d['created_at']);$next=$doc->append($event,$command,$actor,$at,$args);
    $tail=[$revision+1,$at,$input,$doc->fingerprint(),$next->fingerprint()];
    if($subscription){
        [$kind,$actorId]=explode(':',$actor,2);
        query('INSERT INTO '.table($table).' (id,subscription_id,actor_staff_id,actor_client_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$event,$row['id'],$kind==='staff'?(int)$actorId:null,$kind==='client'?(int)$actorId:null,...$tail]);$auditActor=$kind==='staff'?(int)$actorId:0;
    }else{
        query('INSERT INTO '.table($table).' (id,plan_id,actor_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?)',[$event,$row['id'],$actor,...$tail]);$auditActor=$actor;
    }
    $updated=query('UPDATE '.table($subscription?'service_subscriptions':'service_plans').' SET journal_json=?,journal_sha256=?,revision=? WHERE id=? AND revision=? AND journal_sha256=?',[$next->json(),$next->fingerprint(),$revision+1,$row['id'],$revision,$doc->fingerprint()]);
    if($updated->rowCount()!==1)throw new RuntimeException('This service-plan record changed.');
    service_plans_audit($auditActor,$subscription?'subscription_changed':'plan_changed','Record '.$row['id'].' event '.$event.' revision '.($revision+1).' '.$command.($subscription?' client '.$row['client_id']:''));return $next;
}
function service_plans_change(string $id,int $revision,string $event,string $command,array $args,#[\SensitiveParameter] string $password):ServicePlanDocument {
    return staff_locked(fn()=>staff_transaction(function()use($id,$revision,$event,$command,$args,$password):ServicePlanDocument {
        $actor=service_plans_password($password);
        if(!in_array($command,['replace_draft','activate','retire'],true))throw new InvalidArgumentException('Unknown plan action.');
        if($command==='replace_draft'){service_plans_keys($args,['terms']);if(!is_array($args['terms']))throw new InvalidArgumentException('Plan terms must be structured fields.');$args=['terms'=>service_plans_terms($args['terms'])];}
        return service_plans_append_locked(service_plans_row($id,false,true),$revision,$event,$command,(int)$actor['id'],$args);
    }));
}
/** An offer to a named client; never a payment mandate, invoice or charge. */
function service_plans_offer(string $id,int $client,string $planId,int $planRevision,string $planFingerprint,#[\SensitiveParameter] string $password):ServiceSubscriptionDocument {
    return staff_locked(fn()=>staff_transaction(function()use($id,$client,$planId,$planRevision,$planFingerprint,$password):ServiceSubscriptionDocument {
        $actor=service_plans_password($password);service_plans_client_row($client);service_plans_id($id);
        $plan=service_plans_validate(service_plans_row($planId,false,true));$old=one('SELECT * FROM '.table('service_subscriptions').' WHERE id=? FOR UPDATE',[$id]);
        $selected=$plan->prefix($planRevision);
        if(!hash_equals($selected->fingerprint(),$planFingerprint))throw new RuntimeException('The reviewed plan changed.');
        if(!$old && $plan->revision()!==$planRevision)throw new RuntimeException('Reload the current plan before offering it.');
        $planData=$selected->data();$events=$planData['events'];$at=$old?(int)$old['created_epoch']:max(time(),$events?$events[array_key_last($events)]['at']:$planData['created_at']);
        $doc=ServiceSubscriptionDocument::create($id,$client,(int)$actor['id'],$at,$selected);
        if($old){$current=service_plans_validate($old,true);if($old['initial_json']!==$doc->json())throw new InvalidArgumentException('This subscription identity was already used.');return $current;}
        query('INSERT INTO '.table('service_subscriptions').' (id,plan_id,plan_revision,plan_sha256,client_id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,?,?,?,?,?,?,?,?,?,0)',[$id,$planId,$planRevision,$planFingerprint,$client,$actor['id'],$at,$doc->json(),$doc->json(),$doc->fingerprint()]);
        service_plans_audit((int)$actor['id'],'offered','Subscription '.$id.' plan '.$planId.' revision '.$planRevision.' client '.$client);return $doc;
    }));
}
function service_plans_subscription_change(string $id,int $client,int $revision,string $event,string $command,array $args,#[\SensitiveParameter] string $password,?string $renewalBinding=null):ServiceSubscriptionDocument {
    return staff_locked(fn()=>staff_transaction(function()use($id,$client,$revision,$event,$command,$args,$password,$renewalBinding):ServiceSubscriptionDocument {
        $actor=service_plans_password($password);service_plans_client_row($client,!in_array($command,['pause','cancel','abandon'],true));
        if(!in_array($command,['activate','pause','resume','cancel','abandon'],true))throw new InvalidArgumentException('Owners cannot record client consent.');
        $row=service_plans_row($id,true,true);if((int)$row['client_id']!==$client)throw new InvalidArgumentException('Subscription unavailable.');
        return service_plans_append_locked($row,$revision,$event,$command,'staff:'.$actor['id'],$args,true,$renewalBinding);
    }));
}
function service_plans_client_change(string $id,int $client,int $revision,string $event,string $command,array $args,bool $confirmed,?string $renewalBinding=null):ServiceSubscriptionDocument {
    return staff_locked(fn()=>staff_transaction(function()use($id,$client,$revision,$event,$command,$args,$confirmed,$renewalBinding):ServiceSubscriptionDocument {
        service_plans_current_client($client);
        if(!$confirmed || !in_array($command,['consent','cancel','abandon'],true))throw new InvalidArgumentException('Review and explicitly confirm this client action.');
        $row=service_plans_row($id,true,true);if((int)$row['client_id']!==$client)throw new InvalidArgumentException('Subscription unavailable.');
        return service_plans_append_locked($row,$revision,$event,$command,'client:'.$client,$args,true,$renewalBinding);
    }));
}
function service_plans_get(string $id):ServicePlanDocument {
    if(db()->inTransaction())throw new LogicException('Plan reads cannot inherit a transaction.');
    return staff_locked(function()use($id):ServicePlanDocument {service_plans_owner();return service_plans_validate(service_plans_row($id));});
}
function service_plans_subscription_get(string $id,int $client,bool $asClient=false):ServiceSubscriptionDocument {
    if(db()->inTransaction())throw new LogicException('Subscription reads cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($id,$client,$asClient):ServiceSubscriptionDocument {
        if($asClient)service_plans_current_client($client);else{service_plans_owner();service_plans_client_row($client,false);}
        $row=service_plans_row($id,true);if((int)$row['client_id']!==$client)throw new InvalidArgumentException('Subscription unavailable.');return service_plans_validate($row,true);
    }));
}
