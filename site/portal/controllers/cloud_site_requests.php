<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/cloud_site_requests.php';
require_once CWP_CODE_ROOT.'/app/providers/cloud_site_requests_views.php';

function provider_cloud_site_requests_client_action(string $action,array $client):never {
    check_csrf();$clientId=(int)$client['id'];provider_cloud_site_requests_actor($clientId);
    if(!in_array($action,['cloud_website_request_create','cloud_website_request_cancel'],true))throw new InvalidArgumentException('Unknown website request action.');
    if(!consume_rate('cloud-website-request-client-'.$clientId,20,3600))throw new RuntimeException('Please wait before making more website requests.');
    $id=provider_cloud_site_requests_input_id($_POST,'request_id');
    if($action==='cloud_website_request_create'){
        if(provider_cloud_site_requests_input($_POST,'request_confirm',1)!=='1')throw new InvalidArgumentException('Confirm that these domains should be submitted for studio review.');
        provider_cloud_site_requests_create($id,$clientId,provider_cloud_site_requests_input_id($_POST,'link_id'),provider_cloud_site_requests_input_id($_POST,'context',64),provider_cloud_site_requests_input($_POST,'domain',253),provider_cloud_site_requests_input_aliases($_POST),true);
        flash('Your website request is pending studio review. No website has been created.');
    }else{
        if(provider_cloud_site_requests_input($_POST,'confirm',1)!=='1')throw new InvalidArgumentException('Confirm that you want to cancel this request.');
        provider_cloud_site_requests_change($id,provider_cloud_site_requests_input_id($_POST,'expected_event'),provider_cloud_site_requests_input_id($_POST,'event_id'),'cancel','',$clientId);
        flash('Your website request was cancelled.');
    }
    redirect(studio_url(['cloud_websites'=>1]).'#cloud-website-request-'.$id);
}
function provider_cloud_site_requests_client_render(array $client,string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{provider_cloud_site_requests_actor((int)$client['id']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Website requests are unavailable for this session.',403);}
    $cloudWebsiteOptions=[];$cloudWebsiteHistory=[];$cloudWebsiteReady=false;$cloudWebsiteUnavailable=false;$cloudWebsitePage=provider_cloud_site_requests_page($_GET);
    try{
        $cloudWebsiteReady=provider_cloud_site_requests_ready();
        if($cloudWebsiteReady){$cloudWebsiteOptions=provider_cloud_site_requests_options((int)$client['id']);$cloudWebsiteHistory=provider_cloud_site_requests_history((int)$client['id'],$cloudWebsitePage);}
    }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$cloudWebsiteUnavailable=true;}
    site_head('Cloud website requests','portal');
    echo '<section class="page-hero compact"><div class="container"><h1>Cloud website requests</h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';
    show_flash();if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    require CWP_CODE_ROOT.'/portal/views/cloud_site_requests.php';
    echo '</div></section>';site_foot(false);exit;
}
