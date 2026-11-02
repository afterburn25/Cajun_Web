<?php
/** Native agreed invoice schedules. No scheduler, collection or provider action. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/payments.php';
require_once CWP_CODE_ROOT.'/app/plans/records.php';
require_once CWP_CODE_ROOT.'/includes/foundation/recurring-billing/RecurringBillingSchedule.php';
require_once CWP_CODE_ROOT.'/database/schema/recurring_billing_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/recurring_billing_migrate.php';
require_once CWP_CODE_ROOT.'/database/migrations/recurring_billing_checkout_migrate.php';
use CajunWebPro\Foundation\RecurringBilling\{RecurringBillingTerms,RecurringBillingSchedule};
const RECURRING_BILLING_MAX_SCHEDULES=256;
function recurring_billing_json(array $data):string{return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
/** Reuse the existing global staff mutex and transaction for consent/invoice atomicity. */
function recurring_billing_locked(callable $work):mixed {
    if(db()->inTransaction())throw new LogicException('Recurring billing cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction($work));
}
/** Read-only projections must not keep an idle session alive indefinitely. */
function recurring_billing_touch(callable $work,bool $touch):mixed {
    $saved=array_intersect_key($_SESSION,array_flip(['last_activity','client_last']));
    try{return $work();}finally{if(!$touch)foreach(['last_activity','client_last'] as $key){if(array_key_exists($key,$saved))$_SESSION[$key]=$saved[$key];else unset($_SESSION[$key]);}}
}
/** Existing project ownership only; unrelated project-history features are not required. */
function recurring_billing_job(int $id,?int $client=null):array {
    service_plans_actor($id);$row=one('SELECT * FROM '.table('studio_jobs').' WHERE id=? FOR UPDATE',[$id]);
    if(!$row||($client!==null&&(int)$row['client_id']!==$client))throw new RuntimeException('Project unavailable.');return $row;
}
function recurring_billing_ready():bool{
    if((one('SELECT value FROM '.table('settings').' WHERE name=?',['recurring_billing_schema_version'])['value']??'')!=='1')return false;
    $names=array_map(fn($name)=>config()['db']['prefix'].$name,array_keys(recurring_billing_schema(config()['db']['prefix'])));
    if(count(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN (?,?,?,?)',$names))!==count($names))return false;
    $checkout=recurring_billing_checkout_schema_enabled();
    if(recurring_billing_checkout_schema_stage()!==($checkout?2:0))throw new RuntimeException('Recurring schema differs from its explicit checkout preparation marker.');
    return true;
}
function recurring_billing_require():void {if(!recurring_billing_ready()||!service_plans_ready()||!ledger_ready()||!payments_ready())throw new RuntimeException('An owner must prepare recurring billing first.');}
function recurring_billing_staff(bool $ready=true):string {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Recurring billing changes are unavailable during support.');
    $actor=studio_client_manager();
    if(!staff_ready()||!payments_ready()||!staff_can('payments.manage',$actor))throw new RuntimeException('Current financial and client-management access is required.');
    if($ready)recurring_billing_require();return 'staff:'.$actor['id'];
}
function recurring_billing_client(int $client):string {recurring_billing_require();service_plans_current_client($client);return 'client:'.$client;}
function recurring_billing_audit(string $actor,string $action,string $detail):void {
    [$kind,$id]=explode(':',$actor,2);query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$kind==='staff'?(int)$id:0,'recurring_billing.'.$action,$actor.'; '.$detail,gmdate('Y-m-d H:i:s')]);
}
function recurring_billing_row(string $id):array {
    service_plans_id($id);$row=one('SELECT * FROM '.table('recurring_billing_schedules').' WHERE id=?',[$id]);
    if(!$row)throw new InvalidArgumentException('Recurring agreement unavailable.');return $row;
}
function recurring_billing_finance_managed(string $id):bool{
    service_plans_id($id);
    $table=config()['db']['prefix'].'license_finance_offers';
    if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$table]))return false;
    return (bool)one('SELECT id FROM '.table('license_finance_offers').' WHERE schedule_id=? AND status IN (?,?) LIMIT 1',[$id,'offered','signed']);
}
/** Verify today's source and the exact historical active prefix separately. */
function recurring_billing_source(RecurringBillingTerms $terms,bool $active=false,bool $latest=false,?ServiceSubscriptionDocument $current=null,?int $createdAt=null):ServiceSubscriptionDocument {
    $t=$terms->data();$pin=$t['source_subscription'];
    $current??=service_plans_validate(service_plans_row($pin['id'],true,true),true);
    $d=$current->data();
    if($d['id']!==$pin['id']||$d['client_id']!==$t['client_id']||$pin['revision']>$current->revision())throw new RuntimeException('Recurring terms do not match the retained service agreement.');
    $d['events']=array_slice($d['events'],0,$pin['revision']);$prefix=ServiceSubscriptionDocument::fromJson(service_plans_json($d));$model=$prefix->model();
    if($createdAt!==null&&$d['events'][array_key_last($d['events'])]['at']>$createdAt)throw new RuntimeException('Recurring billing cannot precede the agreed active service.');
    if($prefix->fingerprint()!==$pin['sha256']||$model->state()!=='active'||$model->agreedPrice!==$t['amount_cents']||$model->plan->cadence!==$t['cadence']||$model->plan->currency!==$t['currency'])throw new RuntimeException('Recurring terms differ from the agreed service price or cadence.');
    if($t['format']===2){
        require_once CWP_CODE_ROOT.'/app/hosting/catalog_checkout_billing_document.php';
        hosting_checkout_billing_assert($terms,$current);
    }elseif($current->data()['format']!==1)throw new RuntimeException('Legacy billing cannot substitute for checkout-origin terms.');
    if($active&&$current->model()->state()!=='active')throw new RuntimeException('This service agreement is not currently active.');
    if($latest&&($current->revision()!==$pin['revision']||$current->fingerprint()!==$pin['sha256']))throw new RuntimeException('Review the current service agreement before offering billing.');
    return $current;
}
/** Full native history validation; never use a displayed page as integrity evidence. */
function recurring_billing_validate(array $row,?ServiceSubscriptionDocument $source=null):RecurringBillingSchedule {
    $initial=RecurringBillingSchedule::fromJson($row['initial_json']);$doc=RecurringBillingSchedule::fromJson($row['journal_json']);$data=$doc->data();$events=$data['events'];$data['events']=[];$terms=RecurringBillingTerms::fromArray($data['terms']);$t=$terms->data();
    $checkout=$data['format']===2;$start=$data;
    if($checkout){
        if(!recurring_billing_checkout_schema_enabled())throw new RuntimeException('Checkout recurring attribution has not been prepared.');
        $start['events']=array_slice($events,0,1);
        $actorValid=$row['created_by']===null&&($row['created_by_client']??null)!==null&&$data['created_by_client']===(int)$row['created_by_client']&&$data['created_by_client']===$t['client_id'];
    }else $actorValid=$row['created_by']!==null&&($row['created_by_client']??null)===null&&$data['created_by']===(int)$row['created_by'];
    if($initial->revision()!==($checkout?1:0)||$initial->data()!==$start||!$actorValid||$t['id']!==$row['id']||$t['source_subscription']['id']!==$row['subscription_id']||$t['client_id']!==(int)$row['client_id']||$t['project_id']!==(int)$row['job_id']||$data['created_at']!==(int)$row['created_epoch']||$doc->revision()!==(int)$row['revision']||$doc->fingerprint()!==$row['journal_sha256'])throw new RuntimeException('Retained recurring agreement is inconsistent.');
    recurring_billing_source($terms,false,false,$source,$data['created_at']);
    $job=recurring_billing_job($t['project_id'],$t['client_id']);
    $saved=all('SELECT * FROM '.table('recurring_billing_events').' WHERE schedule_id=? ORDER BY revision LIMIT 257',[$row['id']]);
    if(count($events)!==count($saved))throw new RuntimeException('A recurring agreement event is missing.');
    // The v2 zero-event skeleton is only a hash-chain witness, never a parseable schedule.
    $before=$checkout?hash('sha256',recurring_billing_json($data)):$initial->fingerprint();$prefix=$data;
    foreach($events as $i=>$event){
        $r=$saved[$i];$prefix['events'][]=$event;$after=hash('sha256',recurring_billing_json($prefix));
        if(($r['actor_staff_id']===null)===($r['actor_client_id']===null))throw new RuntimeException('Recurring event attribution is ambiguous.');
        $actor=$r['actor_staff_id']!==null?'staff:'.$r['actor_staff_id']:'client:'.$r['actor_client_id'];
        $input=recurring_billing_json(['revision'=>$i,'command'=>$event['command'],'arguments'=>$event['arguments']]);
        if($r['id']!==$event['id']||$actor!==$event['actor']||(int)$r['revision']!==$i+1||(int)$r['recorded_epoch']!==$event['at']||$r['input_json']!==$input||$r['before_sha256']!==$before||$r['after_sha256']!==$after)throw new RuntimeException('Retained recurring event does not match its journal.');
        $before=$after;
    }
    if($checkout){
        require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_invoices.php';
        $first=one('SELECT * FROM '.table('recurring_billing_invoices').' WHERE schedule_id=? AND period_index=0',[$row['id']]);
        if(!$first)throw new RuntimeException('Checkout first-invoice history is missing. Restore the original atomic checkout records.');
        recurring_billing_invoice_validate($first,$doc); // Already parsed graph: never recurse into family/guard here.
    }
    return $doc;
}
function recurring_billing_replacement(RecurringBillingSchedule $previous,RecurringBillingTerms $next):void {
    if(!in_array($previous->phase(),['declined','withdrawn','ended'],true))throw new RuntimeException('End or withdraw the previous billing agreement before replacing it.');
    if($previous->phase()==='ended'){
        $p=$previous->data();$event=$p['events'][array_key_last($p['events'])];$terms=RecurringBillingTerms::fromArray($p['terms']);$cutoff=$terms->period($event['arguments']['period_index'])['start'];$n=$next->data();
        if($n['timezone']!==$p['terms']['timezone']||$n['anchor']<$cutoff)throw new RuntimeException('Replacement billing must use the same timezone and begin after the previous agreement ends.');
    }
}
function recurring_billing_check_floor(?array $floor,RecurringBillingTerms $terms):void {
    $t=$terms->data();if($floor&&($t['timezone']!==$floor['timezone']||$t['anchor']<$floor['date']))throw new RuntimeException('Replacement billing overlaps an earlier retained agreement.');
}
/** Return only the current document, streaming bounded predecessors to limit memory. */
function recurring_billing_family(string $sourceId):array {
    service_plans_id($sourceId);$source=service_plans_validate(service_plans_row($sourceId,true,true),true);
    $head=one('SELECT * FROM '.table('recurring_billing_sources').' WHERE id=? FOR UPDATE',[$sourceId]);
    $ids=all('SELECT id,generation FROM '.table('recurring_billing_schedules').' WHERE subscription_id=? ORDER BY generation LIMIT 257',[$sourceId]);
    if(count($ids)>RECURRING_BILLING_MAX_SCHEDULES||($head===null&&$ids)||($head!==null&&((int)$head['generations']!==count($ids)||!$ids)))throw new RuntimeException('Recurring agreement sequence no longer matches its saved head.');
    $previous=null;$lastRow=null;$floor=null;
    foreach($ids as $i=>$item){
        $row=recurring_billing_row($item['id']);$doc=recurring_billing_validate($row,$source);
        if((int)$row['generation']!==$i+1||$row['previous_id']!==($lastRow['id']??null)||$row['previous_sha256']!==$previous?->fingerprint())throw new RuntimeException('Recurring replacement history is inconsistent.');
        if($previous)recurring_billing_replacement($previous,RecurringBillingTerms::fromArray($doc->data()['terms']));
        $terms=RecurringBillingTerms::fromArray($doc->data()['terms']);recurring_billing_check_floor($floor,$terms);
        if($doc->phase()==='ended'){$d=$doc->data();$end=$d['events'][array_key_last($d['events'])];$date=$terms->period($end['arguments']['period_index'])['start'];$floor=['timezone'=>$d['terms']['timezone'],'date'=>max($floor['date']??$date,$date)];}
        $previous=$doc;$lastRow=$row;
    }
    if($head&&($head['latest_id']!==$lastRow['id']||$head['latest_sha256']!==$previous->fingerprint()))throw new RuntimeException('The current recurring agreement does not match its saved head.');
    return ['source'=>$source,'head'=>$head,'row'=>$lastRow,'document'=>$previous,'count'=>count($ids),'floor'=>$floor];
}
function recurring_billing_offer(array $fields,?string $previousId=null,?string $previousHash=null):RecurringBillingSchedule {
    $terms=RecurringBillingTerms::fromArray($fields);
    if($terms->data()['format']!==1)throw new InvalidArgumentException('Checkout schedules require their atomic client confirmation path.');
    return recurring_billing_locked(function()use($terms,$previousId,$previousHash):RecurringBillingSchedule{
        $actor=recurring_billing_staff();$t=$terms->data();service_plans_client_row($t['client_id']);recurring_billing_job($t['project_id'],$t['client_id']);
        $family=recurring_billing_family($t['source_subscription']['id']);
        $old=one('SELECT * FROM '.table('recurring_billing_schedules').' WHERE id=?',[$t['id']]);
        if($old){
            $doc=recurring_billing_validate($old);$d=$doc->data();
            if($d['terms']!==$t||'staff:'.$d['created_by']!==$actor||$old['previous_id']!==$previousId||$old['previous_sha256']!==$previousHash)throw new RuntimeException('This recurring offer identity was used with different details.');
            return $doc;
        }
        recurring_billing_source($terms,true,true,$family['source'],time());
        if($family['count']>=RECURRING_BILLING_MAX_SCHEDULES||$previousId!==($family['row']['id']??null)||$previousHash!==$family['document']?->fingerprint())throw new RuntimeException('Review the current preceding recurring agreement.');
        if($family['document'])recurring_billing_replacement($family['document'],$terms);
        recurring_billing_check_floor($family['floor'],$terms);
        $doc=RecurringBillingSchedule::create($terms,(int)substr($actor,6),time());$id=$t['id'];$source=$t['source_subscription']['id'];$generation=$family['count']+1;
        if(!$family['head'])query('INSERT INTO '.table('recurring_billing_sources').' (id,latest_id,latest_sha256,generations) VALUES (?,NULL,NULL,0)',[$source]);
        query('INSERT INTO '.table('recurring_billing_schedules').' (id,subscription_id,generation,client_id,job_id,created_by,created_epoch,previous_id,previous_sha256,initial_json,journal_json,journal_sha256,revision) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0)',[$id,$source,$generation,$t['client_id'],$t['project_id'],$doc->data()['created_by'],$doc->data()['created_at'],$previousId,$previousHash,$doc->json(),$doc->json(),$doc->fingerprint()]);
        query('UPDATE '.table('recurring_billing_sources').' SET latest_id=?,latest_sha256=?,generations=? WHERE id=?',[$id,$doc->fingerprint(),$generation,$source]);
        recurring_billing_audit($actor,'offered','Schedule '.$id.' source '.$source.' generation '.$generation);return $doc;
    });
}
/** Caller owns current authority, the shared guard and native transaction. */
function recurring_billing_append(array $row,int $revision,string $event,string $command,string $actor,array $args):RecurringBillingSchedule {
    if(!db()->inTransaction())throw new LogicException('Recurring changes need their owning transaction.');
    service_plans_id($event);$family=recurring_billing_family($row['subscription_id']);$doc=recurring_billing_validate($row,$family['source']);
    if($revision<0||$revision>=RecurringBillingSchedule::MAX_EVENTS)throw new InvalidArgumentException('Invalid recurring agreement revision.');
    $fields=match($command){'accept'=>['terms_sha256'],'decline','withdraw'=>['reason'],'activate'=>['period_index'],'pause','resume','end'=>['period_index','reason'],default=>throw new InvalidArgumentException('Unknown recurring action.')};
    service_plans_keys($args,$fields);$canonical=[];foreach($fields as $field)$canonical[$field]=$args[$field];$args=$canonical;
    $input=recurring_billing_json(['revision'=>$revision,'command'=>$command,'arguments'=>$args]);
    $old=one('SELECT * FROM '.table('recurring_billing_events').' WHERE id=?',[$event]);
    if($old){
        $oldActor=$old['actor_staff_id']!==null?'staff:'.$old['actor_staff_id']:'client:'.$old['actor_client_id'];
        if($old['schedule_id']!==$row['id']||$oldActor!==$actor||$old['input_json']!==$input)throw new RuntimeException('This recurring action identity was used with different details.');return $doc;
    }
    if($family['row']['id']!==$row['id']||$doc->revision()!==$revision)throw new RuntimeException('This recurring agreement changed. Reload the current version.');
    if(in_array($command,['accept','activate','resume'],true))recurring_billing_source(RecurringBillingTerms::fromArray($doc->data()['terms']),true,false,$family['source']);
    $next=$doc->append($event,$command,$actor,time(),$args);[$kind,$actorId]=explode(':',$actor,2);$at=$next->data()['events'][$revision]['at'];
    query('INSERT INTO '.table('recurring_billing_events').' (id,schedule_id,actor_staff_id,actor_client_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$event,$row['id'],$kind==='staff'?(int)$actorId:null,$kind==='client'?(int)$actorId:null,$revision+1,$at,$input,$doc->fingerprint(),$next->fingerprint()]);
    $changed=query('UPDATE '.table('recurring_billing_schedules').' SET journal_json=?,journal_sha256=?,revision=? WHERE id=? AND revision=? AND journal_sha256=?',[$next->json(),$next->fingerprint(),$revision+1,$row['id'],$revision,$doc->fingerprint()]);
    if($changed->rowCount()!==1)throw new RuntimeException('The recurring agreement changed.');
    $headChanged=query('UPDATE '.table('recurring_billing_sources').' SET latest_sha256=? WHERE id=? AND latest_id=?',[$next->fingerprint(),$row['subscription_id'],$row['id']]);
    if($headChanged->rowCount()!==1)throw new RuntimeException('The recurring sequence head changed.');
    recurring_billing_audit($actor,$command,'Schedule '.$row['id'].' event '.$event.' revision '.($revision+1));return $next;
}
function recurring_billing_change(string $id,int $client,int $revision,string $event,string $command,array $args):RecurringBillingSchedule {
    if(!in_array($command,['withdraw','activate','pause','resume','end'],true))throw new InvalidArgumentException('Choose a staff recurring action.');
    return recurring_billing_locked(function()use($id,$client,$revision,$event,$command,$args):RecurringBillingSchedule{
        $actor=recurring_billing_staff();service_plans_client_row($client,!in_array($command,['withdraw','pause','end'],true));$row=recurring_billing_row($id);
        if((int)$row['client_id']!==$client)throw new RuntimeException('Recurring agreement unavailable.');
        if(recurring_billing_finance_managed($id))throw new RuntimeException('This schedule is controlled by a customer financing agreement. Manage it from Website Licenses.');
        return recurring_billing_append($row,$revision,$event,$command,$actor,$args);
    });
}
function recurring_billing_decide(string $id,int $client,int $revision,string $event,string $command,array $args,bool $confirmed):RecurringBillingSchedule {
    if(!$confirmed||!in_array($command,['accept','decline','end'],true))throw new InvalidArgumentException('Review and explicitly confirm this client action.');
    return recurring_billing_locked(function()use($id,$client,$revision,$event,$command,$args):RecurringBillingSchedule{
        $actor=recurring_billing_client($client);$row=recurring_billing_row($id);
        if((int)$row['client_id']!==$client)throw new RuntimeException('Recurring agreement unavailable.');
        if(recurring_billing_finance_managed($id))throw new RuntimeException('Review and sign or decline this schedule from your Financing agreement page.');
        return recurring_billing_append($row,$revision,$event,$command,$actor,$args);
    });
}
function recurring_billing_get(string $id,int $client,bool $asClient=false):RecurringBillingSchedule {
    return recurring_billing_locked(function()use($id,$client,$asClient):RecurringBillingSchedule{
        if($asClient)recurring_billing_client($client);else{recurring_billing_staff();service_plans_client_row($client,false);}
        $row=recurring_billing_row($id);if((int)$row['client_id']!==$client)throw new RuntimeException('Recurring agreement unavailable.');
        $family=recurring_billing_family($row['subscription_id']);return recurring_billing_validate($row,$family['source']);
    });
}
