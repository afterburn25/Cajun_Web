<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/subdomain_requests.php';
require_once CWP_CODE_ROOT.'/app/providers/subdomain_requests_views.php';

function provider_subdomain_requests_admin_action(string $action): never {
    provider_subdomain_requests_actor(null);check_csrf();
    if(($_POST['action']??null)!==$action||!in_array($action,['provider_subdomain_request_prepare','provider_subdomain_request_decline','provider_subdomain_request_approve','provider_subdomain_request_verify','provider_subdomain_request_recover'],true))throw new InvalidArgumentException('Unknown subdomain request action.');
    $password=provider_subdomain_requests_ui_text($_POST,'current_password',200);
    if($action==='provider_subdomain_request_prepare'){
        provider_subdomain_requests_ui_confirm($_POST,'reviewed');provider_subdomain_requests_prepare($password);
        flash('Client subdomain requests are enabled.');redirect(provider_subdomain_requests_ui_url(true));
    }
    $id=provider_subdomain_requests_ui_id($_POST,'request_id');
    if($action==='provider_subdomain_request_verify'){
        provider_subdomain_requests_verify($id,provider_subdomain_requests_ui_id($_POST,'owner_request_id'),$password);
        flash('The follow-up check was recorded. Review the reported result below.');
    }elseif($action==='provider_subdomain_request_recover'){
        provider_subdomain_requests_ui_confirm($_POST,'reviewed');
        provider_subdomain_requests_recover($id,provider_subdomain_requests_ui_id($_POST,'owner_request_id'),provider_subdomain_requests_ui_id($_POST,'ledger_sha256',64),$password);
        flash('The saved local request record was restored. Check the reported website names separately.');
    }else{
        $event=provider_subdomain_requests_ui_id($_POST,'expected_event');provider_subdomain_requests_ui_confirm($_POST,'reviewed');
        if($action==='provider_subdomain_request_decline'){
            provider_subdomain_requests_change($id,$event,provider_subdomain_requests_ui_id($_POST,'event_id'),'decline',provider_subdomain_requests_ui_text($_POST,'note',1000),null,$password);
            flash('The client request was declined.');
        }else{
            provider_subdomain_requests_approve($id,$event,provider_subdomain_requests_ui_id($_POST,'request_sha256',64),provider_subdomain_requests_ui_id($_POST,'owner_request_id'),provider_subdomain_requests_ui_text($_POST,'review_proof',4096),true,$password);
            flash('The request was recorded. Review its saved outcome and check the reported website names separately.');
        }
    }
    redirect(provider_subdomain_requests_ui_url(true,$id));
}
function provider_subdomain_requests_admin_render(string $error): never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{
        if(support_current()||array_key_exists('support',$_GET)||array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION))throw new RuntimeException('Direct owner sign-in required.');
        $owner=provider_settings_manager(false);
    }catch(RuntimeException){fail_page('Sign in directly as the owner to review subdomain requests.',403);}
    $retry=provider_subdomain_requests_ui_retry($_POST,true);$screen=null;
    try{
        provider_subdomain_requests_actor(null);
        [$page,$detail]=provider_subdomain_requests_ui_query($_GET);
        if($retry&&preg_match('/\A[a-f0-9]{32}\z/',$retry['request_id']??''))$detail=$retry['request_id'];
        $screen=provider_subdomain_requests_screen(null,$page,$detail);
    }catch(InvalidArgumentException|RuntimeException $e){$error=$e instanceof PDOException?'Request records could not be loaded. Please try again later.':$e->getMessage();}
    admin_head('Client subdomain requests','subdomain-requests',$owner);
    if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    require CWP_CODE_ROOT.'/admin/views/subdomain_requests.php';admin_foot();exit;
}
