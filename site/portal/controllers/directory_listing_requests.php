<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/directory_listing_requests_views.php';

function provider_directory_indexing_requests_client_action(string $action, array $client): never {
    $clientId = (int)$client['id'];
    provider_directory_indexing_requests_actor($clientId);
    $id = provider_directory_indexing_requests_post('request_id', 32);
    if ($action === 'directory_request_create') {
        $desired = provider_directory_indexing_requests_post('desired', 5);
        if (!in_array($desired, ['true','false'], true)) throw new InvalidArgumentException('Choose Enabled or Disabled for directory listing.');
        provider_directory_indexing_requests_create($id, $clientId, provider_directory_indexing_requests_post('link_id',32), provider_directory_indexing_requests_post('review_sha256',64), $desired === 'true', ($_POST['confirmed'] ?? null) === '1');
        flash('Your directory-listing request is saved for studio review.');
    } elseif ($action === 'directory_request_cancel') {
        provider_directory_indexing_requests_change($id, provider_directory_indexing_requests_post('expected_event',32), provider_directory_indexing_requests_post('event_id',32), 'cancel', '', $clientId);
        flash('Your request was withdrawn.');
    } else throw new InvalidArgumentException('Unknown directory-listing request action.');
    redirect(provider_directory_indexing_requests_url(false, provider_directory_indexing_requests_route(), $id));
}
function provider_directory_indexing_requests_client_render(array $client, string $error): never {
    header('Cache-Control: no-store, private'); header('Referrer-Policy: no-referrer');
    try { provider_directory_indexing_requests_actor((int)$client['id']); }
    catch (InvalidArgumentException|RuntimeException $e) { if ($e instanceof PDOException) throw $e; fail_page('Sign in directly to your primary client account to manage directory-listing requests.',403); }
    $route = provider_directory_indexing_requests_route();
    site_head('Directory listing requests','portal');
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR HOSTING</span><h1>Directory listing requests</h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';
    show_flash(); if ($error !== '') echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    try { provider_directory_indexing_requests_render(provider_directory_indexing_requests_screen((int)$client['id'],$route['page'],$route['request']),false,$route); }
    catch (InvalidArgumentException|RuntimeException $e) { if ($e instanceof PDOException) throw $e; echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p><p><a href="'.esc(provider_directory_indexing_requests_url(false,$route,$route['request'])).'">Reload current request details</a></p>'; }
    echo '</div></section>'; site_foot(false); exit;
}
