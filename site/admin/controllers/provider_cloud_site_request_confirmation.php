<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/cloud_site_requests.php';

/** Exact retained grant navigation, independent of the current profile and recent-40 list. */
function provider_cloud_site_request_confirmation_render():void {
    if(!array_key_exists('cloud_website_confirm',$_GET))return;
    provider_cloud_site_requests_actor(null);
    echo '<section class="workspace-card" id="cloud-website-confirmation"><h2>Requested website confirmation</h2>';
    try{
        $id=$_GET['cloud_website_confirm'];if(!is_string($id))throw new InvalidArgumentException('Choose a website request from its history.');provider_cloud_site_requests_id($id);
        $request=provider_cloud_assignment_inspect(function()use($id):array {
            if(!provider_operations_ready()||!provider_inventory_ready()||!provider_cloud_site_requests_ready())throw new RuntimeException('Prepare the original website request and hosting history before reviewing a package.');
            provider_cloud_site_requests_state();$grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$id]);
            if(!$grant)throw new RuntimeException('The original website approval is unavailable.');$doc=provider_cloud_sites_document($grant);
            if(!array_key_exists('client_request',$doc))throw new RuntimeException('Choose a client website request from its history.');
            $result=provider_cloud_sites_result($grant);
            if(($result['status']??null)!=='accepted_pending'||!is_string($result['external_id']??null))throw new RuntimeException('This request has no returned package available for confirmation.');
            return ['id'=>$id,'domain'=>$doc['params']['domain_name'],'package_id'=>$result['external_id'],'confirmation'=>provider_cloud_confirmation_projection($id)];
        });
        echo '<h3>'.esc($request['domain']).'</h3><p>Returned package #'.esc($request['package_id']).'. Refresh hosting inventory and review the exact returned package before assigning it.</p>';
        if(!provider_cloud_confirmation_ready()){
            echo '<form method="post" data-cloud-confirmation-prepare>';admin_action('provider_inventory_cloud_confirmation_prepare');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');echo '<button class="btn">Prepare confirmation history</button></form>';
        }else require CWP_CODE_ROOT.'/admin/views/provider_cloud_confirmation.php';
    }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="notice">'.esc($e->getMessage()).'</p>';}
    echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['section'=>'website']).'#cloud-website-requests').'">Back to client website requests</a></section>';
}
