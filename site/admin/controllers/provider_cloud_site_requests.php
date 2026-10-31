<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/cloud_site_requests.php';
require_once CWP_CODE_ROOT.'/app/providers/cloud_site_requests_views.php';

function provider_cloud_site_requests_admin_action(string $action):void {
    check_csrf();
    if(!in_array($action,['provider_inventory_cloud_request_prepare','provider_inventory_cloud_request_decline','provider_inventory_cloud_request_dispatch'],true))throw new InvalidArgumentException('Unknown website request action.');
    $password=provider_cloud_site_requests_input($_POST,'current_password',200);
    provider_cloud_site_requests_actor(null,$password);
    if($action==='provider_inventory_cloud_request_prepare'){
        provider_cloud_site_requests_prepare($password);flash('Website request history is ready.');
    }else{
        $id=provider_cloud_site_requests_input_id($_POST,'request_id');$expected=provider_cloud_site_requests_input_id($_POST,'expected_event');
        if($action==='provider_inventory_cloud_request_decline'){
            if(provider_cloud_site_requests_input($_POST,'confirm',1)!=='1')throw new InvalidArgumentException('Confirm that you want to decline this request.');
            provider_cloud_site_requests_change($id,$expected,provider_cloud_site_requests_input_id($_POST,'event_id'),'decline',provider_cloud_site_requests_input($_POST,'note',1000),null,$password);
            flash('The request was declined. No website creation was submitted.');
        }else{
            if(provider_cloud_site_requests_input($_POST,'reviewed',1)!=='1')throw new InvalidArgumentException('Review domain authority and server capacity before submitting.');
            provider_cloud_site_requests_dispatch($id,$expected,provider_cloud_site_requests_input_id($_POST,'dispatch_id'),provider_cloud_site_requests_input_id($_POST,'grant_id'),provider_cloud_site_requests_input_id($_POST,'dispatch_context',64),true,$password);
            flash('The creation attempt was recorded. Review its outcome below; package confirmation is a separate step.');
        }
    }
    redirect(admin_url('provider-settings',['section'=>'website']).'#cloud-website-requests');
}
function provider_cloud_site_requests_admin_render():void {
    provider_cloud_site_requests_actor(null);
    try{
        $ready=provider_cloud_site_requests_ready();$page=provider_cloud_site_requests_page($_GET);
        $history=$ready?provider_cloud_site_requests_history(null,$page):[];
        provider_cloud_site_requests_render([],$history,true,$ready,$page,provider_cloud_site_requests_retry($_POST));
    }catch(InvalidArgumentException|RuntimeException $error){
        if($error instanceof PDOException)throw $error;
        echo '<section id="cloud-website-requests"><h2>Client website requests</h2><p class="notice">Request history needs review before more actions are available. Restore the original records; preparing again will not replace them.</p></section>';
    }
}
