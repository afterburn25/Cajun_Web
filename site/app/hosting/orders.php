<?php
/** Agreed initial hosting orders reuse native clients, agreements, invoices and receipt history. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/hosting/order_document.php';
require_once CWP_CODE_ROOT.'/app/plans/records.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_invoices.php';
require_once CWP_CODE_ROOT.'/app/billing/ledger_fulfillment.php';
require_once CWP_CODE_ROOT.'/database/schema/service_orders_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/service_orders_migrate.php';

function service_orders_storage():array {
    $names=array_keys(service_orders_schema(config()['db']['prefix']));$existing=[];
    foreach($names as $name)if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))$existing[]=$name;
    return ['version'=>one('SELECT value FROM '.table('settings').' WHERE name=?',['service_orders_schema_version'])['value']??'','existing'=>$existing,'complete'=>count($existing)===count($names)];
}
function service_orders_ready():bool {$s=service_orders_storage();return $s['version']==='1'&&$s['complete'];}
function service_orders_require():void {
    if(service_orders_ready()&&service_plans_ready()&&ledger_ready()&&payments_ready())return;
    throw new RuntimeException('Order or payment history is unavailable. Prepare it before first use; restore the original database if it was previously prepared.');
}
function service_orders_owner(#[\SensitiveParameter] ?string $password=null,bool $ready=true):array {
    $actor=service_plans_owner();
    if(!staff_can('payments.manage',$actor))throw new RuntimeException('Current owner financial access is required.');
    if($password!==null&&!password_verify($password,$actor['password_hash']))throw new InvalidArgumentException('Your current password is not correct.');
    if(!ledger_ready()||!payments_ready())throw new RuntimeException('Prepare payments and receipt history before hosting orders.');
    if($ready)service_orders_require();return $actor;
}
function service_orders_audit(string $actor,string $action,string $detail):void {
    [$kind,$id]=explode(':',$actor,2);query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$kind==='staff'?(int)$id:0,'service_orders.'.$action,$actor.'; '.$detail,gmdate('Y-m-d H:i:s')]);
}
function service_orders_row(string $id):array {
    service_plans_id($id);$row=one('SELECT * FROM '.table('service_orders').' WHERE id=? FOR UPDATE',[$id]);
    if(!$row)throw new RuntimeException('Hosting order unavailable.');return $row;
}
function service_orders_agreement(string $id,int $client,int $revision,string $hash,?string $termsHash=null):array {
    $current=service_plans_validate(service_plans_row($id,true,true),true);$d=$current->data();
    if($d['client_id']!==$client||$revision<1||$revision>$current->revision())throw new RuntimeException('Hosting order agreement is inconsistent.');
    $d['events']=array_slice($d['events'],0,$revision);$prefix=ServiceSubscriptionDocument::fromJson(service_plans_json($d));$m=$prefix->model();
    if($m->state()!=='active'||$m->consent()===null||$prefix->fingerprint()!==$hash||($termsHash!==null&&$m->plan->termsHash()!==$termsHash))throw new RuntimeException('Hosting order does not match its original accepted service terms.');
    return ['current'=>$current,'prefix'=>$prefix];
}
/** Inspect a specific existing invoice; matching amounts alone never create an order. */
function service_orders_invoice(ServiceSubscriptionDocument $source,int $invoice,bool $active=true):array {
    if(!db()->inTransaction())throw new LogicException('Invoice binding needs its owning transaction.');
    $d=$source->data();$m=$source->model();
    $i=one('SELECT i.*,j.client_id FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON j.id=i.job_id WHERE i.id=? FOR UPDATE',[$invoice]);
    if(!$i||(int)$i['client_id']!==$d['client_id']||!in_array($i['status'],['issued','paid'],true)||(int)$i['amount_cents']!==$m->agreedPrice||$i['currency']!==$m->plan->currency||catalog_snapshot($invoice)!==null)throw new RuntimeException('Select this client’s separate invoice for the exact agreed hosting price.');
    recurring_billing_invoice_guard($i);
    $basis='one-time';$schedule=null;$period=null;$origin=recurring_billing_invoice_origin($invoice);
    if($m->plan->cadence==='one-time'){
        if($origin!==null)throw new RuntimeException('A one-time hosting order cannot consume a recurring invoice.');
    }else{
        recurring_billing_require();
        $map=one('SELECT * FROM '.table('recurring_billing_invoices').' WHERE invoice_id=?',[$invoice]);
        if(!$map||(int)$map['period_index']!==0)throw new RuntimeException('Use the first invoice of this hosting agreement’s recurring schedule.');
        $row=recurring_billing_row($map['schedule_id']);$family=recurring_billing_family($d['id']);
        if($row['subscription_id']!==$d['id']||(int)$row['client_id']!==$d['client_id']||($active&&($family['row']['id']??null)!==$row['id']))throw new RuntimeException('This invoice belongs to a different or replaced billing agreement.');
        $billing=recurring_billing_validate($row,$family['source']);recurring_billing_invoice_validate($map,$billing);
        if($active&&$billing->phase()!=='active')throw new RuntimeException('Activate the accepted billing schedule before offering initial hosting.');
        $basis='initial-recurring-period';$schedule=$map['schedule_id'];$period=0;
    }
    return ['id'=>$invoice,'project_id'=>(int)$i['job_id'],'sha256'=>ledger_invoice_hash($i),'amount_cents'=>(int)$i['amount_cents'],'currency'=>$i['currency'],'basis'=>$basis,'schedule_id'=>$schedule,'period_index'=>$period];
}
/** Validate original offer, every retained event and independent original offer audit. */
function service_orders_validate(array $row):ServiceOrderDocument {
    $initial=ServiceOrderDocument::fromJson($row['initial_json']);$doc=ServiceOrderDocument::fromJson($row['journal_json']);$d=$doc->data();$events=$d['events'];$d['events']=[];
    if($initial->revision()!==0||$initial->data()!==$d||$d['id']!==$row['id']||$d['agreement']['id']!==$row['agreement_id']||$d['invoice']['id']!==(int)$row['invoice_id']||$d['client_id']!==(int)$row['client_id']||$d['created_by']!==(int)$row['created_by']||$d['created_at']!==(int)$row['created_epoch']||$doc->revision()!==(int)$row['revision']||$doc->fingerprint()!==$row['journal_sha256'])throw new RuntimeException('Retained hosting order is inconsistent.');
    $pin=$d['agreement'];$source=service_orders_agreement($pin['id'],$d['client_id'],$pin['revision'],$pin['sha256'],$pin['terms_hash']);$model=$source['prefix']->model();
    $sourceEvents=$source['prefix']->data()['events'];
    if($sourceEvents[array_key_last($sourceEvents)]['at']>$d['created_at']||$d['invoice']['amount_cents']!==$model->agreedPrice||$d['invoice']['currency']!==$model->plan->currency||($model->plan->cadence==='one-time')!==($d['invoice']['basis']==='one-time'))throw new RuntimeException('Hosting order price or period differs from its agreed service.');
    $detail='staff:'.$d['created_by'].'; Order '.$d['id'].' agreement '.$pin['id'].' invoice '.$d['invoice']['id'].' client '.$d['client_id'].' sha256 '.$initial->fingerprint();
    $origin=all('SELECT user_id,detail FROM '.table('audit').' WHERE action=? AND detail=? LIMIT 2',['service_orders.offered',$detail]);
    if(count($origin)!==1||(int)$origin[0]['user_id']!==$d['created_by']||$origin[0]['detail']!==$detail)throw new RuntimeException('Original hosting order history is missing. Restore the original database.');
    $saved=all('SELECT * FROM '.table('service_order_events').' WHERE order_id=? ORDER BY revision LIMIT 129',[$row['id']]);
    if(count($saved)!==count($events))throw new RuntimeException('A hosting order event is missing.');
    $before=$initial->fingerprint();$prefix=$d;
    foreach($events as $i=>$event){
        $r=$saved[$i];$prefix['events'][]=$event;$after=hash('sha256',service_plans_json($prefix));
        if(($r['actor_staff_id']===null)===($r['actor_client_id']===null))throw new RuntimeException('Order attribution is ambiguous.');
        $actor=$r['actor_staff_id']!==null?'staff:'.$r['actor_staff_id']:'client:'.$r['actor_client_id'];
        if($r['id']!==$event['id']||$actor!==$event['actor']||(int)$r['revision']!==$i+1||(int)$r['recorded_epoch']!==$event['at']||$r['input_json']!==service_plans_json(['revision'=>$i,'command'=>$event['command'],'arguments'=>$event['arguments']])||$r['before_sha256']!==$before||$r['after_sha256']!==$after)throw new RuntimeException('Retained order event is inconsistent.');
        $before=$after;
    }
    return $doc;
}
function service_orders_offer(string $id,string $agreement,int $client,int $revision,string $hash,int $invoice,string $invoiceHash,#[\SensitiveParameter] string $password):ServiceOrderDocument {
    service_plans_id($id);service_orders_hash($hash);service_orders_hash($invoiceHash);
    return recurring_billing_locked(function()use($id,$agreement,$client,$revision,$hash,$invoice,$invoiceHash,$password):ServiceOrderDocument{
        $actor=service_orders_owner($password);service_plans_client_row($client);
        $source=service_orders_agreement($agreement,$client,$revision,$hash);
        $old=one('SELECT * FROM '.table('service_orders').' WHERE id=? FOR UPDATE',[$id]);
        if($old){
            $doc=service_orders_validate($old);$d=$doc->data();
            if($d['created_by']!==(int)$actor['id']||$d['client_id']!==$client||$d['agreement']['id']!==$agreement||$d['agreement']['revision']!==$revision||$d['agreement']['sha256']!==$hash||$d['invoice']['id']!==$invoice||$d['invoice']['sha256']!==$invoiceHash)throw new RuntimeException('This hosting offer identity was used with different details.');
            return $doc;
        }
        $current=$source['current'];
        if($current->model()->state()!=='active'||$current->revision()!==$revision||$current->fingerprint()!==$hash)throw new RuntimeException('Review the current active service agreement.');
        $binding=service_orders_invoice($current,$invoice);
        if($binding['sha256']!==$invoiceHash)throw new RuntimeException('Review the current invoice before offering hosting.');
        if(one('SELECT id FROM '.table('service_orders').' WHERE agreement_id=? OR invoice_id=? LIMIT 1',[$agreement,$invoice]))throw new RuntimeException('This service agreement or invoice already has an initial hosting order.');
        if(one('SELECT id FROM '.table('audit').' WHERE action=? AND (detail LIKE ? OR detail LIKE ?) LIMIT 1',['service_orders.offered','% agreement '.$agreement.' invoice %','% invoice '.$invoice.' client %']))throw new RuntimeException('Original hosting offer history exists without its order. Restore the original database.');
        $doc=ServiceOrderDocument::create($id,$client,(int)$actor['id'],time(),['id'=>$agreement,'revision'=>$revision,'sha256'=>$hash,'terms_hash'=>$current->model()->plan->termsHash()],$binding);
        query('INSERT INTO '.table('service_orders').' (id,agreement_id,invoice_id,client_id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,?,?,?,?,?,?,?,?,0)',[$id,$agreement,$invoice,$client,$actor['id'],$doc->data()['created_at'],$doc->json(),$doc->json(),$doc->fingerprint()]);
        service_orders_audit('staff:'.$actor['id'],'offered','Order '.$id.' agreement '.$agreement.' invoice '.$invoice.' client '.$client.' sha256 '.$doc->fingerprint());return $doc;
    });
}
/** Rechecked commercial evidence only. This does not grant provider purchase authority. */
function service_orders_assess(ServiceOrderDocument $doc,int $at):array {
    service_plans_time($at);$d=$doc->data();$pin=$d['agreement'];
    $source=service_orders_agreement($pin['id'],$d['client_id'],$pin['revision'],$pin['sha256'],$pin['terms_hash']);
    $blockers=[];$state=$doc->state();$manual=$doc->manualPolicy($at);
    if($state['phase']!=='accepted')$blockers[]='client-acceptance-required';
    if($source['current']->model()->state()!=='active')$blockers[]='agreement-inactive';
    $invoice=service_orders_invoice($source['current'],$d['invoice']['id']);
    if($invoice!==$d['invoice'])throw new RuntimeException('The hosting order’s original invoice binding changed.');
    $payment=ledger_fulfillment_evidence($invoice['id'],$d['client_id']);
    if(!$payment['payment_evidence_ready']){
        foreach($payment['blockers'] as $reason)if($manual===null||in_array($reason,['client-inactive','invoice-void','payment-review-required'],true))$blockers[]=$reason;
    }
    return ['eligible'=>$blockers===[],'blockers'=>$blockers,'basis'=>$payment['payment_evidence_ready']?'verified-payment':($manual!==null?'owner-manual-policy':null),'payment'=>$payment,'manual'=>$manual,'order_sha256'=>$doc->fingerprint()];
}
function service_orders_append(array $row,int $revision,string $event,string $command,string $actor,array $args):ServiceOrderDocument {
    if(!db()->inTransaction())throw new LogicException('Order action needs its owning transaction.');
    service_plans_id($event);$doc=service_orders_validate($row);
    if($revision<0||$revision>=ServiceOrderDocument::MAX_EVENTS)throw new InvalidArgumentException('Invalid order revision.');
    $fields=match($command){'accept'=>['domain_name','review_sha256'],'allow_manual'=>['reason','expires_at'],'cancel','withdraw','decline','revoke_manual'=>['reason'],default=>throw new InvalidArgumentException('Unknown order action.')};
    service_plans_keys($args,$fields);$ordered=[];foreach($fields as $key)$ordered[$key]=$args[$key];$args=$ordered;
    $input=service_plans_json(['revision'=>$revision,'command'=>$command,'arguments'=>$args]);
    $old=one('SELECT * FROM '.table('service_order_events').' WHERE id=?',[$event]);
    if($old){
        $oldActor=$old['actor_staff_id']!==null?'staff:'.$old['actor_staff_id']:'client:'.$old['actor_client_id'];
        if($old['order_id']!==$row['id']||$oldActor!==$actor||$old['input_json']!==$input)throw new RuntimeException('This order action identity was used with different details.');
        return $doc;
    }
    if($doc->revision()!==$revision)throw new RuntimeException('This hosting order changed. Reload and review the current version.');
    if(in_array($command,['accept','allow_manual'],true)){
        $d=$doc->data();$pin=$d['agreement'];$source=service_orders_agreement($pin['id'],$d['client_id'],$pin['revision'],$pin['sha256'],$pin['terms_hash']);
        if($source['current']->model()->state()!=='active'||service_orders_invoice($source['current'],$d['invoice']['id'])!==$d['invoice'])throw new RuntimeException('Review the current active agreement and original invoice.');
    }
    $d=$doc->data();$events=$d['events'];$at=max(time(),$events?$events[array_key_last($events)]['at']:$d['created_at']);
    $next=$doc->append($event,$command,$actor,$at,$args);[$kind,$id]=explode(':',$actor,2);
    query('INSERT INTO '.table('service_order_events').' (id,order_id,actor_staff_id,actor_client_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$event,$row['id'],$kind==='staff'?(int)$id:null,$kind==='client'?(int)$id:null,$revision+1,$at,$input,$doc->fingerprint(),$next->fingerprint()]);
    $changed=query('UPDATE '.table('service_orders').' SET journal_json=?,journal_sha256=?,revision=? WHERE id=? AND revision=? AND journal_sha256=?',[$next->json(),$next->fingerprint(),$revision+1,$row['id'],$revision,$doc->fingerprint()]);
    if($changed->rowCount()!==1)throw new RuntimeException('This hosting order changed.');
    service_orders_audit($actor,$command,'Order '.$row['id'].' event '.$event.' revision '.($revision+1));return $next;
}
function service_orders_change(string $id,int $client,int $revision,string $event,string $command,array $args,#[\SensitiveParameter] string $password):ServiceOrderDocument {
    if(!in_array($command,['withdraw','cancel','allow_manual','revoke_manual'],true))throw new InvalidArgumentException('Owners cannot record client acceptance.');
    return recurring_billing_locked(function()use($id,$client,$revision,$event,$command,$args,$password):ServiceOrderDocument{
        $actor=service_orders_owner($password);service_plans_client_row($client,$command==='allow_manual');$row=service_orders_row($id);
        if((int)$row['client_id']!==$client)throw new RuntimeException('Hosting order unavailable.');
        return service_orders_append($row,$revision,$event,$command,'staff:'.$actor['id'],$args);
    });
}
function service_orders_decide(string $id,int $client,int $revision,string $event,string $command,array $args,bool $confirmed):ServiceOrderDocument {
    if(!$confirmed||!in_array($command,['accept','decline','cancel'],true))throw new InvalidArgumentException('Review and explicitly confirm this client action.');
    return recurring_billing_locked(function()use($id,$client,$revision,$event,$command,$args):ServiceOrderDocument{
        service_orders_require();service_plans_current_client($client);$row=service_orders_row($id);
        if((int)$row['client_id']!==$client)throw new RuntimeException('Hosting order unavailable.');
        return service_orders_append($row,$revision,$event,$command,'client:'.$client,$args);
    });
}
