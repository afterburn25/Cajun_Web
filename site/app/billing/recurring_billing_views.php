<?php
/** Current-authorized bounded views, after complete retained-family validation. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_invoices.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_forms.php';
use CajunWebPro\Foundation\RecurringBilling\{RecurringBillingTerms,RecurringBillingSchedule};

function recurring_ui_mode():bool {
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['recurring_billing_schema_version'])['value']??'';
    if(!in_array($version,['','1'],true))throw new RuntimeException('Recurring billing storage needs owner review. Existing records were preserved.');
    if($version==='')foreach(array_keys(recurring_billing_schema(config()['db']['prefix'])) as $name){
        if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Retained recurring records are not activated. Ask an owner to review storage.');
    }
    if($version==='1'&&!recurring_billing_ready())throw new RuntimeException('Established recurring billing history is missing. Restore the original database before continuing.');
    return $version==='1';
}
function recurring_ui_authority(?int $client):void {
    if($client===null)recurring_billing_staff(false);else service_plans_current_client($client);
}
function recurring_ui_slice(array $rows,int $page,int $size=20):array {
    return ['items'=>array_slice($rows,0,$size),'more'=>count($rows)>$size,'page'=>$page];
}
function recurring_ui_source_view(ServiceSubscriptionDocument $doc):array {
    $model=$doc->model();$data=$doc->data();
    return ['id'=>$data['id'],'client_id'=>$data['client_id'],'revision'=>$doc->revision(),'sha256'=>$doc->fingerprint(),
        'title'=>$model->plan->name,'state'=>$model->state(),'amount_cents'=>$model->agreedPrice,
        'currency'=>$model->plan->currency,'cadence'=>$model->plan->cadence,'plan'=>ServicePlanDocument::fromJson(service_plans_json($data['plan']))];
}
function recurring_ui_invoice(array $map,RecurringBillingSchedule $doc):array {
    $invoice=recurring_billing_invoice_validate($map,$doc);$balance=ledger_balance(ledger_invoice((int)$invoice['id']));
    return ['id'=>(int)$invoice['id'],'reference'=>$invoice['reference'],'period'=>json_decode($map['snapshot_json'],true,8,JSON_THROW_ON_ERROR)['period'],
        'paid_cents'=>$balance['paid_cents'],'due_cents'=>$balance['due_cents'],'status'=>$invoice['status']];
}
function recurring_ui_context(array $q,?int $client=null):array {
    return recurring_billing_touch(fn()=>recurring_billing_locked(function()use($q,$client):array{
        recurring_ui_authority($client);$ready=recurring_ui_mode();$out=['ready'=>$ready,'query'=>$q,'staff'=>$client===null];
        if(!$ready)return $out;
        recurring_billing_require();
        if(!$q['job']){
            $where=$client===null?'':' WHERE j.client_id=?';$rows=all('SELECT j.id,j.title,j.client_id,c.name AS client_name FROM '.table('studio_jobs').' j JOIN '.table('studio_clients').' c ON c.id=j.client_id'.$where.' ORDER BY j.id DESC LIMIT 21 OFFSET '.($q['p']*20),$client===null?[]:[$client]);
            return $out+['projects'=>recurring_ui_slice($rows,$q['p'])];
        }
        $job=recurring_billing_job($q['job'],$client);$owner=service_plans_client_row((int)$job['client_id'],false);
        $out+=['project'=>['id'=>(int)$job['id'],'title'=>$job['title']],'client'=>['id'=>(int)$owner['id'],'name'=>$owner['name'],'active'=>(int)$owner['active']===1]];
        if($q['source']!==''){
            $row=service_plans_row($q['source'],true,true);
            if((int)$row['client_id']!==(int)$owner['id'])throw new RuntimeException('Service agreement unavailable for this project.');
            $family=recurring_billing_family($q['source']);
            return $out+['source'=>recurring_ui_source_view($family['source']),'previous_id'=>$family['row']['id']??'',
                'previous_sha256'=>$family['document']?->fingerprint()??'','previous_phase'=>$family['document']?->phase(),'floor'=>$family['floor']];
        }
        if($q['schedule']!==''){
            $row=recurring_billing_row($q['schedule']);
            if((int)$row['job_id']!==$q['job']||(int)$row['client_id']!==(int)$owner['id'])throw new RuntimeException('Recurring agreement unavailable for this project.');
            $family=recurring_billing_family($row['subscription_id']);$doc=recurring_billing_validate($row,$family['source']);$data=$doc->data();$terms=RecurringBillingTerms::fromArray($data['terms']);
            $current=$terms->periodAt(time());$selected=$q['period_date']===''?($current['index']??0):recurring_ui_period($terms,$q['period_date']);
            $assessment=$doc->assessment($selected,time());$next=recurring_ui_next_dates($doc,time());
            $maps=all('SELECT * FROM '.table('recurring_billing_invoices').' WHERE schedule_id=? ORDER BY period_index DESC LIMIT 21 OFFSET '.($q['invoices_page']*20),[$q['schedule']]);
            $invoices=recurring_ui_slice($maps,$q['invoices_page']);$invoices['items']=array_map(fn($m)=>recurring_ui_invoice($m,$doc),$invoices['items']);
            $map=one('SELECT * FROM '.table('recurring_billing_invoices').' WHERE schedule_id=? AND period_index=?',[$q['schedule'],$selected]);
            $history=recurring_ui_slice(array_slice(array_reverse($data['events']),$q['history_page']*10,11),$q['history_page'],10);
            return $out+['document'=>$doc,'source'=>recurring_ui_source_view($family['source']),'current'=>$family['row']['id']===$row['id'],
                'history'=>$history,'invoices'=>$invoices,'assessment'=>$assessment,'selected_invoice'=>$map?recurring_ui_invoice($map,$doc):null]+$next;
        }
        $sources=[];
        if($client===null){
            $rows=all('SELECT * FROM '.table('service_subscriptions').' WHERE client_id=? ORDER BY created_epoch DESC,id DESC LIMIT 21 OFFSET '.($q['sources_page']*20),[$owner['id']]);
            $sources=recurring_ui_slice($rows,$q['sources_page']);$sources['items']=array_map(fn($r)=>recurring_ui_source_view(service_plans_validate($r,true)),$sources['items']);
        }
        $rows=all('SELECT id,subscription_id FROM '.table('recurring_billing_schedules').' WHERE job_id=? AND client_id=? ORDER BY created_epoch DESC,id DESC LIMIT 21 OFFSET '.($q['schedules_page']*20),[$job['id'],$owner['id']]);
        $schedules=recurring_ui_slice($rows,$q['schedules_page']);$items=[];$families=[];
        foreach($schedules['items'] as $r){
            $families[$r['subscription_id']]??=recurring_billing_family($r['subscription_id']);$f=$families[$r['subscription_id']];
            $doc=recurring_billing_validate(recurring_billing_row($r['id']),$f['source']);$t=$doc->data()['terms'];
            $items[]=['id'=>$t['id'],'title'=>$t['title'],'phase'=>$doc->phase(),'amount_cents'=>$t['amount_cents'],'cadence'=>$t['cadence']];
        }
        $schedules['items']=$items;return $out+['sources'=>$sources,'schedules'=>$schedules];
    }),false);
}
/** Exact retained source prefix supplies price; browser input can never set an amount. */
function recurring_ui_offer_source(array $a,int $job):array {
    recurring_ui_valid($a);$client=recurring_ui_number($a['client_id']);$source=recurring_ui_hex($a['source_id']);$revision=recurring_ui_number($a['source_revision'],256);$hash=recurring_ui_hex($a['source_sha256'],64);
    return recurring_billing_locked(function()use($client,$source,$revision,$hash,$job):array{
        recurring_billing_staff();recurring_billing_job($job,$client);$row=service_plans_row($source,true,true);
        if((int)$row['client_id']!==$client)throw new RuntimeException('Service agreement unavailable for this project.');
        $current=service_plans_validate($row,true);if($revision>$current->revision())throw new RuntimeException('The reviewed service version is unavailable.');
        $data=$current->data();$data['events']=array_slice($data['events'],0,$revision);$prefix=ServiceSubscriptionDocument::fromJson(service_plans_json($data));
        if($prefix->fingerprint()!==$hash||$prefix->model()->state()!=='active')throw new RuntimeException('The reviewed active service agreement changed.');
        return recurring_ui_source_view($prefix);
    });
}
