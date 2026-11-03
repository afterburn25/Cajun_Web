<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/directory_listing_requests_views.php';

function provider_directory_indexing_requests_admin_action(string $action): never {
    provider_directory_indexing_requests_actor(null);
    $password = provider_directory_indexing_requests_post('current_password',200);
    if ($action === 'provider_directory_request_prepare') {
        provider_directory_indexing_requests_prepare($password); flash('Client directory-listing request history is ready.');
        redirect(provider_directory_indexing_requests_url(true,provider_directory_indexing_requests_route()));
    }
    $id = provider_directory_indexing_requests_post('request_id',32);
    if ($action === 'provider_directory_request_decline') {
        provider_directory_indexing_requests_change($id,provider_directory_indexing_requests_post('expected_event',32),provider_directory_indexing_requests_post('event_id',32),'decline',provider_directory_indexing_requests_post('note',1000),null,$password);
    } elseif ($action === 'provider_directory_request_approve') {
        provider_directory_indexing_requests_approve($id,provider_directory_indexing_requests_post('expected_event',32),provider_directory_indexing_requests_post('request_sha256',64),provider_directory_indexing_requests_post('owner_request_id',32),provider_directory_indexing_requests_post('anchor_id',32),provider_directory_indexing_requests_post('binding_sha256',64),($_POST['confirmed']??null)==='1',$password);
    } elseif ($action === 'provider_directory_request_verify') {
        provider_directory_indexing_requests_verify($id,provider_directory_indexing_requests_post('owner_request_id',32),$password);
    } elseif ($action === 'provider_directory_request_recover') {
        if (($_POST['confirmed']??null)!=='1') throw new InvalidArgumentException('Confirm that only the saved request history should be restored.');
        provider_directory_indexing_requests_recover($id,provider_directory_indexing_requests_post('owner_request_id',32),provider_directory_indexing_requests_post('ledger_sha256',64),$password);
    } else throw new InvalidArgumentException('Unknown directory-listing request action.');
    flash('The request history is updated. Review its recorded status below.');
    redirect(provider_directory_indexing_requests_url(true,provider_directory_indexing_requests_route(),$id));
}
function provider_directory_indexing_requests_admin_render(string $error): never {
    header('Cache-Control: no-store, private'); header('Referrer-Policy: no-referrer');
    try {
        if (array_key_exists('contact_id',$_SESSION) || array_key_exists('contact_auth',$_SESSION) || array_key_exists('support',$_SESSION) || array_key_exists('support',$_GET) || array_key_exists('support',$_POST) || support_current() !== null) throw new RuntimeException('Sign in directly to manage directory-listing requests.');
        $actor = provider_settings_manager(false);
    }
    catch (InvalidArgumentException|RuntimeException $e) { if ($e instanceof PDOException) throw $e; fail_page('Directory listing requests are unavailable for this session.',403); }
    $route = provider_directory_indexing_requests_route();
    admin_head('Client directory-listing requests','directory-requests',$actor); show_flash();
    if ($error !== '') echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    try { provider_directory_indexing_requests_render(provider_directory_indexing_requests_screen(null,$route['page'],$route['request']),true,$route); }
    catch (InvalidArgumentException|RuntimeException $e) { if ($e instanceof PDOException) throw $e; echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p><p><a href="'.esc(admin_url('provider-settings')).'">Open 20i connection settings</a></p>'; }
    admin_foot(); exit;
}
