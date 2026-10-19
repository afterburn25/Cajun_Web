<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/document_root_requests_views.php';

function provider_document_root_requests_client_action(string $action,array $client): never {
    $clientId = (int)$client['id']; provider_document_root_requests_actor($clientId);
    $id = provider_document_root_requests_post('request_id',32);
    if ($action==='folder_request_create') {
        provider_document_root_requests_create($id,$clientId,provider_document_root_requests_post('link_id',32),provider_document_root_requests_post('review_sha256',64),provider_document_root_requests_post('name',253),provider_document_root_requests_post('desired_path',1024),($_POST['confirmed']??null)==='1');
        flash('Your website folder request is saved for studio review.');
    } elseif ($action==='folder_request_cancel') {
        provider_document_root_requests_change($id,provider_document_root_requests_post('expected_event',32),provider_document_root_requests_post('event_id',32),'cancel','',$clientId);
        flash('Your request was withdrawn.');
    } else throw new InvalidArgumentException('Unknown website folder request action.');
    redirect(provider_document_root_requests_url(false,provider_document_root_requests_route(),$id));
}
function provider_document_root_requests_client_render(array $client,string $error): never {
    header('Cache-Control: no-store, private'); header('Referrer-Policy: no-referrer');
    try { provider_document_root_requests_actor((int)$client['id']); }
    catch (InvalidArgumentException|RuntimeException $e) { fail_page('Sign in directly to your primary client account to manage website folder requests.',403); }
    $route = provider_document_root_requests_route();
    site_head('Website folder requests','portal');
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR HOSTING</span><h1>Website folder requests</h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';
    show_flash(); if ($error!=='') echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    try { $screen = provider_document_root_requests_screen((int)$client['id'],$route['page'],$route['request']); require CWP_CODE_ROOT.'/portal/views/document_root_requests.php'; }
    catch (InvalidArgumentException|RuntimeException $e) { echo '<p class="notice error" role="alert">'.esc(provider_document_root_requests_error($e)).'</p><p><a href="'.esc(provider_document_root_requests_url(false,$route,$route['request'])).'">Reload current request details</a></p>'; }
    echo '</div></section>'; site_foot(false); exit;
}
