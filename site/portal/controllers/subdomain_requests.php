<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/subdomain_requests.php';
require_once CWP_CODE_ROOT.'/app/providers/subdomain_requests_views.php';

/** Run before studio_client() can resolve support or normalize session provenance. */
function provider_subdomain_requests_portal_guard(): void {
    if(support_current()||array_key_exists('support',$_GET)||array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION))throw new RuntimeException('Sign in directly as the primary client to use subdomain requests.');
}
function provider_subdomain_requests_client_action(string $action,array $client): never {
    provider_subdomain_requests_portal_guard();
    provider_subdomain_requests_actor((int)$client['id']);
    check_csrf();
    if(($_POST['action']??null)!==$action||!in_array($action,['subdomain_request_create','subdomain_request_cancel'],true))throw new InvalidArgumentException('Unknown subdomain request action.');
    if(!consume_rate('subdomain-request-client-'.(int)$client['id'],20,3600))throw new RuntimeException('Please wait before making more request changes.');
    $id=provider_subdomain_requests_ui_id($_POST,'request_id');
    provider_subdomain_requests_ui_confirm($_POST,'confirmed');
    if($action==='subdomain_request_create'){
        provider_subdomain_requests_create($id,(int)$client['id'],provider_subdomain_requests_ui_id($_POST,'link_id'),provider_subdomain_requests_ui_id($_POST,'review_sha256',64),provider_subdomain_requests_ui_text($_POST,'parent_domain',253),provider_subdomain_requests_ui_text($_POST,'subdomain',253),true);
        flash('Your subdomain request is awaiting studio review. No provider change was sent.');
    }else{
        provider_subdomain_requests_change($id,provider_subdomain_requests_ui_id($_POST,'expected_event'),provider_subdomain_requests_ui_id($_POST,'event_id'),'cancel','',(int)$client['id']);
        flash('Your pending request was withdrawn.');
    }
    redirect(provider_subdomain_requests_ui_url(false,$id));
}
function provider_subdomain_requests_client_render(array $client,string $error): never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{provider_subdomain_requests_portal_guard();provider_subdomain_requests_actor((int)$client['id']);}
    catch(RuntimeException){fail_page('Sign in directly as the primary client to use subdomain requests.',403);}
    $retry=provider_subdomain_requests_ui_retry($_POST,false);$screen=null;
    try{
        [$page,$detail]=provider_subdomain_requests_ui_query($_GET);
        if(($retry['action']??'')==='subdomain_request_cancel'&&preg_match('/\A[a-f0-9]{32}\z/',$retry['request_id']??''))$detail=$retry['request_id'];
        $screen=provider_subdomain_requests_screen((int)$client['id'],$page,$detail);
    }catch(InvalidArgumentException|RuntimeException $e){$error=$e instanceof PDOException?'Request records could not be loaded. Please try again later.':$e->getMessage();}
    site_head('Subdomain requests','portal');
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR HOSTING</span><h1>Subdomain <span class="gold-text">requests</span></h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';
    show_flash();if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    require CWP_CODE_ROOT.'/portal/views/subdomain_requests.php';
    echo '</div></section>';site_foot(false);exit;
}
