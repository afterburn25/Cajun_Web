<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/force_ssl_requests_views.php';

function provider_force_ssl_requests_client_action(string $action, array $client): never {
    $clientId = (int)$client['id'];
    provider_force_ssl_requests_actor($clientId);
    $id = provider_force_ssl_requests_post('request_id', 32);
    if ($action === 'https_request_create') {
        $desired = provider_force_ssl_requests_post('desired', 5);
        if (!in_array($desired, ['true','false'], true)) throw new InvalidArgumentException('Choose On or Off for HTTPS redirection.');
        provider_force_ssl_requests_create($id, $clientId, provider_force_ssl_requests_post('link_id',32), provider_force_ssl_requests_post('review_sha256',64), $desired === 'true', ($_POST['confirmed'] ?? null) === '1');
        flash('Your HTTPS request is saved for studio review.');
    } elseif ($action === 'https_request_cancel') {
        provider_force_ssl_requests_change($id, provider_force_ssl_requests_post('expected_event',32), provider_force_ssl_requests_post('event_id',32), 'cancel', '', $clientId);
        flash('Your request was withdrawn.');
    } else throw new InvalidArgumentException('Unknown HTTPS request action.');
    redirect(provider_force_ssl_requests_url(false, provider_force_ssl_requests_route(), $id));
}
function provider_force_ssl_requests_client_render(array $client, string $error): never {
    header('Cache-Control: no-store, private'); header('Referrer-Policy: no-referrer');
    try { provider_force_ssl_requests_actor((int)$client['id']); }
    catch (InvalidArgumentException|RuntimeException $e) { if ($e instanceof PDOException) throw $e; fail_page('Sign in directly to your primary client account to manage HTTPS requests.',403); }
    $route = provider_force_ssl_requests_route();
    site_head('HTTPS redirect requests','portal');
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR HOSTING</span><h1>HTTPS redirect requests</h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';
    show_flash(); if ($error !== '') echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    try { provider_force_ssl_requests_render(provider_force_ssl_requests_screen((int)$client['id'],$route['page'],$route['request']),false,$route); }
    catch (InvalidArgumentException|RuntimeException $e) { if ($e instanceof PDOException) throw $e; echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>'; }
    echo '</div></section>'; site_foot(false); exit;
}
