<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/hosting/orders.php';
require_once CWP_CODE_ROOT.'/app/plans/forms.php';

/** Current authority is rechecked inside the same native guard used for actions. */
function service_orders_context(string $agreement,int $client,bool $asClient=false):array {
    return recurring_billing_locked(function()use($agreement,$client,$asClient):array{
        service_orders_require();
        if($asClient)service_plans_current_client($client);else service_orders_owner();
        $source=service_plans_validate(service_plans_row($agreement,true,true),true);
        if($source->data()['client_id']!==$client)throw new RuntimeException('Hosting order unavailable.');
        $row=one('SELECT * FROM '.table('service_orders').' WHERE agreement_id=?',[$agreement]);$choices=[];$more=false;
        if($row){
            $doc=service_orders_validate($row);$assessment=null;$warning=null;
            try{$assessment=service_orders_assess($doc,time());}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;$warning='The original agreement or invoice needs studio review before setup can continue.';}
            // Clients see safe status only, never staff policy reasons or receipt internals.
            if($asClient&&$assessment!==null)$assessment=['eligible'=>$assessment['eligible'],'blockers'=>$assessment['blockers'],'basis'=>$assessment['basis']];
            return ['document'=>$doc,'assessment'=>$assessment,'warning'=>$warning,'choices'=>[],'more'=>false,'source'=>$source];
        }
        if(one('SELECT id FROM '.table('audit').' WHERE action=? AND detail LIKE ? LIMIT 1',['service_orders.offered','% agreement '.$agreement.' invoice %']))throw new RuntimeException('Hosting order history is missing. Restore the original database.');
        if(!$asClient&&$source->model()->state()==='active'){
            // Explicit invoice selection is bounded and never infers provider ownership.
            $rows=all('SELECT i.id,i.reference,i.title FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON j.id=i.job_id WHERE j.client_id=? AND i.amount_cents=? AND i.currency=? AND i.status IN (?,?) ORDER BY i.id DESC LIMIT 51',[$client,$source->model()->agreedPrice,$source->model()->plan->currency,'issued','paid']);
            $more=count($rows)>50;
            foreach(array_slice($rows,0,50) as $i){
                if(one('SELECT id FROM '.table('service_orders').' WHERE invoice_id=?',[$i['id']]))continue;
                try{$binding=service_orders_invoice($source,(int)$i['id']);}
                catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;continue;}
                $choices[]=['id'=>(int)$i['id'],'reference'=>$i['reference'],'title'=>$i['title'],'sha256'=>$binding['sha256'],'amount_cents'=>$binding['amount_cents']];
            }
        }
        return ['document'=>null,'assessment'=>null,'warning'=>null,'choices'=>$choices,'more'=>$more,'source'=>$source];
    });
}
function service_orders_form_action(string $action,string $agreement,int $client,bool $owner):void {
    service_plans_form_confirm($_POST);service_plans_id($agreement);
    if(service_plans_form_id($_POST,'agreement_id')!==$agreement)throw new InvalidArgumentException('Reload the selected service agreement.');
    $prefix=$owner?'service_plans_order_':'service_agreements_order_';
    if(!str_starts_with($action,$prefix))throw new InvalidArgumentException('Unknown hosting order action.');
    $command=substr($action,strlen($prefix));$password=$owner?service_plans_form_text($_POST,'current_password',200):'';
    if($owner&&$command==='setup'){service_orders_migrate($password);return;}
    $id=service_plans_form_id($_POST,'order_id');
    if($owner&&$command==='offer'){
        $choice=service_plans_form_text($_POST,'invoice_choice',80);
        if(!preg_match('/\A([1-9][0-9]{0,9}):([a-f0-9]{64})\z/',$choice,$match)||(int)$match[1]>2147483647)throw new InvalidArgumentException('Choose the exact reviewed hosting invoice.');
        service_orders_offer($id,$agreement,$client,service_plans_form_number($_POST,'source_revision',255),service_plans_form_hash($_POST,'source_hash'),(int)$match[1],$match[2],$password);return;
    }
    // Bind route and order before the native action; native authority is rechecked there.
    $context=service_orders_context($agreement,$client,!$owner);
    if(!$context['document']||$context['document']->data()['id']!==$id)throw new RuntimeException('Hosting order unavailable.');
    $revision=service_plans_form_number($_POST,'order_revision',127);$event=service_plans_form_id($_POST,'event_id');
    $args=match($command){
        'accept'=>['domain_name'=>service_orders_domain(service_plans_form_text($_POST,'domain_name',254,true)),'review_sha256'=>service_plans_form_hash($_POST,'review_sha256')],
        'allow_manual'=>['reason'=>service_plans_form_text($_POST,'reason',500,true),'expires_at'=>service_plans_form_number($_POST,'expires_at',2147483647)],
        'cancel','withdraw','decline','revoke_manual'=>['reason'=>service_plans_form_text($_POST,'reason',500,true)],
        default=>throw new InvalidArgumentException('Unknown hosting order action.'),
    };
    if($owner)service_orders_change($id,$client,$revision,$event,$command,$args,$password);
    else service_orders_decide($id,$client,$revision,$event,$command,$args,true);
}
