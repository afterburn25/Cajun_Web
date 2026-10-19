<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/registered_nameserver_requests.php';
require_once CWP_CODE_ROOT.'/app/providers/registered_nameserver_requests_views.php';
require_once CWP_CODE_ROOT.'/app/providers/registered_nameservers_views.php';

function registered_nameserver_requests_admin_action(string $action): never {
    registered_nameserver_requests_actor(null);
    check_csrf();
    $password = registered_nameserver_requests_ui_text($_POST, 'current_password', 200);
    if ($action === 'provider_nameserver_request_prepare') {
        registered_nameserver_requests_prepare($password);
        flash('Client nameserver requests are enabled.');
        redirect(registered_nameserver_requests_ui_url(true));
    }
    $id = registered_nameserver_requests_ui_id($_POST, 'request_id');
    $event = registered_nameserver_requests_ui_id($_POST, 'expected_event');
    registered_nameserver_requests_ui_confirm($_POST, 'reviewed');
    if ($action === 'provider_nameserver_request_decline') {
        registered_nameserver_requests_change($id, $event, registered_nameserver_requests_ui_id($_POST, 'event_id'), 'decline', registered_nameserver_requests_ui_text($_POST, 'note', 1000), null, $password);
        flash('The client request was declined.');
    } elseif ($action === 'provider_nameserver_request_approve') {
        registered_nameserver_requests_approve($id, $event, registered_nameserver_requests_ui_id($_POST, 'request_sha256', 64), registered_nameserver_requests_ui_id($_POST, 'dispatch_id'), registered_nameserver_requests_ui_id($_POST, 'approval_review_sha256', 64), true, $password);
        flash('The request was recorded. Review its saved outcome and check nameservers separately.');
    } else throw new InvalidArgumentException('Unknown nameserver request action.');
    redirect(registered_nameserver_requests_ui_url(true, $id));
}
function registered_nameserver_requests_admin_render(string $error): never {
    header('Cache-Control: no-store, private'); header('Referrer-Policy: no-referrer');
    registered_nameserver_requests_actor(null);
    $owner = provider_settings_manager();
    $retry = registered_nameserver_requests_ui_retry($_POST, true);
    $screen = null;
    try {
        [$page, $detail] = registered_nameserver_requests_ui_query($_GET);
        if ($retry && isset($retry['request_id']) && preg_match('/\A[a-f0-9]{32}\z/', $retry['request_id'])) $detail = $retry['request_id'];
        $screen = registered_nameserver_requests_screen(null, $page, $detail);
    } catch (InvalidArgumentException|RuntimeException $e) {
        $error = $e instanceof PDOException ? 'Request records could not be loaded. Please try again later.' : $e->getMessage();
    }
    admin_head('Client nameserver requests', 'nameserver-requests', $owner);
    show_flash();
    if ($error !== '') echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    require CWP_CODE_ROOT.'/admin/views/registered_nameserver_requests.php';
    admin_foot(); exit;
}
