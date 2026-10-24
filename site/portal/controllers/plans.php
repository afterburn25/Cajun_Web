<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/plans/views.php';
require_once CWP_CODE_ROOT.'/includes/layout/plans.php';
require_once CWP_CODE_ROOT.'/includes/layout/service_orders.php';

function service_plans_portal_action(string $action,array $client):never {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new InvalidArgumentException('Submit the reviewed form to continue.');
    if(str_starts_with($action,'service_agreements_order_')){
        $id=service_plans_form_id($_GET,'agreement');
        service_orders_form_action($action,$id,(int)$client['id'],false);
        flash('Your hosting request was updated.');redirect(studio_url(['agreement'=>$id]));
    }
    $intent=service_plans_form_intent($_POST);service_plans_form_confirm($_POST);
    if(!is_string($_GET['agreement']??null) || $_GET['agreement']!==$intent['record_id'])throw new InvalidArgumentException('Reload the agreement you intend to change.');
    $command=match($action){'service_agreements_consent'=>'consent','service_agreements_cancel'=>'cancel','service_agreements_abandon'=>'abandon',default=>throw new InvalidArgumentException('Unknown agreement action.')};
    $args=match($command){'consent'=>['terms_hash'=>service_plans_form_hash($_POST,'terms_hash')],'cancel'=>['reason'=>service_plans_form_text($_POST,'reason',1000,true)],default=>[]};
    service_plans_client_change($intent['record_id'],(int)$client['id'],$intent['revision'],$intent['event_id'],$command,$args,true,array_key_exists('renewal_binding',$_POST)?service_plans_form_hash($_POST,'renewal_binding'):null);
    flash($command==='consent'?'Your agreement to these service terms was recorded.':'Your service agreement was updated.');redirect(studio_url(['agreement'=>$intent['record_id']]));
}
function service_plans_portal_render(array $client,string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try {staff_locked(function()use($client):void {service_plans_current_client((int)$client['id']);});}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page($error!==''?$error:'Service agreements are unavailable for this session.',403);}
    site_head('Your service agreements','portal');echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR CLIENT WORKSPACE</span><h1>Service <span class="gold-text">agreements</span></h1><div class="button-row"><a class="btn outline" href="'.esc(studio_url()).'">All projects</a><a class="btn outline" href="'.esc(studio_url(['agreements'=>1])).'">All agreements</a></div></div></section><section class="section"><div class="container">';show_flash();
    if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    try {require CWP_CODE_ROOT.'/portal/views/plans.php';}
    catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;echo '<div class="notice error" role="alert">'.esc($e->getMessage()).'</div>';}
    echo '</div></section>';site_foot(false);exit;
}
