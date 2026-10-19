<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/registered_nameserver_requests.php';
require_once CWP_CODE_ROOT.'/app/providers/registered_nameserver_requests_views.php';

/** Invoke before studio_client() can normalize away contact provenance. */
function registered_nameserver_requests_portal_guard(): void {
    if (support_current() || array_key_exists('support', $_GET) || array_key_exists('contact_id', $_SESSION) || array_key_exists('contact_auth', $_SESSION)) throw new RuntimeException('Sign in directly as the primary client to review nameserver requests.');
}
function registered_nameserver_requests_client_action(string $action, array $client): never {
    registered_nameserver_requests_portal_guard();
    registered_nameserver_requests_actor((int)$client['id']);
    check_csrf();
    if (!consume_rate('nameserver-request-client-'.(int)$client['id'], 20, 3600)) throw new RuntimeException('Please wait before making more request changes.');
    $id = registered_nameserver_requests_ui_id($_POST, 'request_id');
    registered_nameserver_requests_ui_confirm($_POST, 'confirmed');
    if ($action === 'nameserver_request_create') {
        registered_nameserver_requests_create($id, (int)$client['id'], registered_nameserver_requests_ui_id($_POST, 'link_id'), registered_nameserver_requests_ui_id($_POST, 'review_sha256', 64), registered_nameserver_requests_ui_desired($_POST), true);
        flash('Your nameserver request is awaiting studio review. No change was sent.');
    } elseif ($action === 'nameserver_request_cancel') {
        registered_nameserver_requests_change($id, registered_nameserver_requests_ui_id($_POST, 'expected_event'), registered_nameserver_requests_ui_id($_POST, 'event_id'), 'cancel', '', (int)$client['id']);
        flash('Your pending request was withdrawn.');
    } else throw new InvalidArgumentException('Unknown nameserver request action.');
    redirect(registered_nameserver_requests_ui_url(false, $id));
}
function registered_nameserver_requests_client_render(array $client, string $error): never {
    registered_nameserver_requests_portal_guard();
    registered_nameserver_requests_actor((int)$client['id']);
    header('Cache-Control: no-store, private'); header('Referrer-Policy: no-referrer');
    $retry = registered_nameserver_requests_ui_retry($_POST, false);
    $screen = null;
    try {
        [$page, $detail] = registered_nameserver_requests_ui_query($_GET);
        if (($retry['action'] ?? '') === 'nameserver_request_cancel' && isset($retry['request_id']) && preg_match('/\A[a-f0-9]{32}\z/', $retry['request_id'])) $detail = $retry['request_id'];
        $screen = registered_nameserver_requests_screen((int)$client['id'], $page, $detail);
    } catch (InvalidArgumentException|RuntimeException $e) {
        $error = $e instanceof PDOException ? 'Request records could not be loaded. Please try again later.' : $e->getMessage();
    }
    site_head('Nameserver requests', 'portal');
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR DOMAINS</span><h1>Nameserver <span class="gold-text">requests</span></h1><a class="btn outline" href="'.esc(studio_url(['services' => 1])).'">Hosting services</a></div></section><section class="section"><div class="container">';
    show_flash();
    if ($error !== '') echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    require CWP_CODE_ROOT.'/portal/views/registered_nameserver_requests.php';
    echo '</div></section>'; site_foot(false); exit;
}
