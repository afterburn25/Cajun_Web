<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/plans/views.php';
require_once CWP_CODE_ROOT.'/includes/layout/plans.php';
require_once CWP_CODE_ROOT.'/includes/layout/service_orders.php';
require_once CWP_CODE_ROOT.'/includes/layout/provider_plan_catalog.php';

function service_plans_admin_action(string $action):void {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new InvalidArgumentException('Submit the reviewed form to continue.');
    if(str_starts_with($action,'service_plans_catalog_')){
        $id=service_plans_form_id($_GET,'record');provider_plan_catalog_form($action,$id);flash('Provider mapping saved.');redirect(admin_url('service-plan',['record'=>$id]));
    }
    if(str_starts_with($action,'service_plans_order_')){
        $id=service_plans_form_id($_GET,'record');$item=service_plans_owner_agreement($id);
        service_orders_form_action($action,$id,$item['document']->data()['client_id'],true);
        flash('Hosting order updated.');redirect(admin_url('service-agreement',['record'=>$id]));
    }
    if($action==='service_plans_setup'){service_plans_migrate(posted('current_password',200));flash('Service agreements are ready.');redirect(admin_url('service-plans'));}
    service_plans_owner();$password=posted('current_password',200);
    if($action==='service_plans_create'){
        $id=service_plans_form_id($_POST,'record_id');service_plans_create($id,service_plans_form_terms($_POST),$password);
        flash('Draft plan saved.');redirect(admin_url('service-plan',['record'=>$id]));
    }
    $intent=service_plans_form_intent($_POST);$id=$intent['record_id'];
    if(!is_string($_GET['record']??null) || $_GET['record']!==$id)throw new InvalidArgumentException('Reload the agreement or plan you intend to change.');
    $revision=$intent['revision'];$event=$intent['event_id'];
    if($action==='service_plans_edit')service_plans_change($id,$revision,$event,'replace_draft',['terms'=>service_plans_form_terms($_POST)],$password);
    else {
        service_plans_form_confirm($_POST);
        if($action==='service_plans_publish' || $action==='service_plans_retire')service_plans_change($id,$revision,$event,$action==='service_plans_publish'?'activate':'retire',[],$password);
        elseif($action==='service_plans_offer'){
            $client=service_plans_form_number($_POST,'client_id',2147483647);$hash=service_plans_form_hash($_POST,'plan_hash');
            $doc=service_plans_offer($event,$client,$id,$revision,$hash,$password);
            flash('Service agreement offered in the client portal.');redirect(admin_url('service-agreement',['record'=>$doc->data()['id']]));
        }else{
            $command=match($action){'service_plans_activate'=>'activate','service_plans_pause'=>'pause','service_plans_resume'=>'resume','service_plans_cancel'=>'cancel','service_plans_abandon'=>'abandon',default=>throw new InvalidArgumentException('Unknown service-plan action.')};
            $client=service_plans_form_number($_POST,'client_id',2147483647);$args=$command==='cancel'?['reason'=>service_plans_form_text($_POST,'reason',1000,true)]:[];
            service_plans_subscription_change($id,$client,$revision,$event,$command,$args,$password);
            flash('Service agreement updated.');redirect(admin_url('service-agreement',['record'=>$id]));
        }
    }
    flash('Plan saved.');redirect(admin_url('service-plan',['record'=>$id]));
}
function service_plans_admin_render(string $view,string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{$actor=service_plans_owner(false);}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;http_response_code(403);admin_head('Service agreements unavailable','service-plans',require_admin());echo '<div class="notice error">'.esc($e->getMessage()).'</div>';admin_foot();exit;}
    admin_head(in_array($view,['service-agreement','service-agreements'],true)?'Service agreements':'Service plans','service-plans',$actor);
    if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    if(!service_plans_ready()){
        if((one('SELECT value FROM '.table('settings').' WHERE name=?',['service_plans_schema_version'])['value']??'')==='1'){
            echo '<div class="notice error">Existing agreement history is unavailable. Restore the original database before continuing; saved terms and client consent must be preserved.</div>';admin_foot();exit;
        }
        echo '<section class="workspace-card"><h2>Prepare service agreements</h2><p>Save reviewed service terms and let clients accept them in their portal. Existing projects and invoices are preserved.</p><form method="post">';admin_action('service_plans_setup');admin_input('current_password','Your current password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Enable service agreements</button></form></section>';admin_foot();exit;
    }
    try {require CWP_CODE_ROOT.'/admin/views/plans.php';}
    catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;echo '<div class="notice error" role="alert">'.esc($e->getMessage()).'</div>';}
    admin_foot();exit;
}
