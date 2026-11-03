<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/document_root_requests_views.php';

/** Shared admin dispatcher authenticates the owner and CSRF before this handler. */
function provider_document_root_requests_admin_action(string $action): never {
    provider_document_root_requests_actor(null);
    $password = provider_document_root_requests_post('current_password',200);
    if ($action === 'provider_folder_request_prepare') {
        provider_document_root_requests_prepare($password); flash('Client website folder request history is ready.');
        redirect(provider_document_root_requests_url(true,provider_document_root_requests_route()));
    }
    $id = provider_document_root_requests_post('request_id',32);
    if ($action === 'provider_folder_request_decline') {
        provider_document_root_requests_change($id,provider_document_root_requests_post('expected_event',32),provider_document_root_requests_post('event_id',32),'decline',provider_document_root_requests_post('note',1000),null,$password);
    } elseif ($action === 'provider_folder_request_approve') {
        provider_document_root_requests_approve($id,provider_document_root_requests_post('expected_event',32),provider_document_root_requests_post('request_sha256',64),provider_document_root_requests_post('owner_request_id',32),provider_document_root_requests_post('anchor_id',32),provider_document_root_requests_post('binding_sha256',64),($_POST['reviewed']??null)==='1',$password);
    } elseif ($action === 'provider_folder_request_verify') {
        provider_document_root_requests_verify($id,provider_document_root_requests_post('owner_request_id',32),$password);
    } elseif ($action === 'provider_folder_request_recover') {
        if (($_POST['confirmed']??null)!=='1') throw new InvalidArgumentException('Confirm that only local request history should be restored.');
        provider_document_root_requests_recover($id,provider_document_root_requests_post('owner_request_id',32),provider_document_root_requests_post('ledger_sha256',64),$password);
    } else throw new InvalidArgumentException('Unknown website folder request action.');
    flash('Request history updated. Review its recorded status below.');
    redirect(provider_document_root_requests_url(true,provider_document_root_requests_route(),$id));
}
function provider_document_root_requests_admin_render(string $error): never {
    header('Cache-Control: no-store, private'); header('Referrer-Policy: no-referrer');
    try {
        if (array_key_exists('contact_id',$_SESSION) || array_key_exists('contact_auth',$_SESSION) || array_key_exists('support',$_SESSION) || array_key_exists('support',$_GET) || array_key_exists('support',$_POST) || support_current()!==null) throw new RuntimeException('Sign in directly to manage folder requests.');
        $actor = provider_settings_manager(false);
    } catch (InvalidArgumentException|RuntimeException $e) { fail_page('Website folder requests are unavailable for this session.',403); }
    $route = provider_document_root_requests_route();
    admin_head('Client website folder requests','folder-requests',$actor);
    if ($error!=='') echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    try { provider_document_root_requests_render(provider_document_root_requests_screen(null,$route['page'],$route['request']),true,$route); }
    catch (InvalidArgumentException|RuntimeException $e) { echo '<p class="notice error" role="alert">'.esc(provider_document_root_requests_error($e)).'</p><p><a href="'.esc(admin_url('provider-settings')).'">Open 20i connection settings</a></p>'; }
    admin_foot(); exit;
}
