<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/client_stackcp_views.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_launch_http.php';

function provider_client_stackcp_requested(): bool {
    return array_key_exists('hosting_panel',$_GET) || ($_POST['action'] ?? null) === 'client_stackcp_open';
}

/** Run before studio_client/support normalization, including direct action posts. */
function provider_client_stackcp_route_preflight(): void {
    if (!provider_client_stackcp_requested()) return;
    header('Cache-Control: no-store, private, max-age=0');header('Referrer-Policy: no-referrer');
    if (provider_client_stackcp_session_blocked()) fail_page('Hosting control panel access requires an independent primary client session.',403);
}

function provider_client_stackcp_client_action(array $client): void {
    if (provider_client_stackcp_session_blocked()) throw new RuntimeException('Use an independent primary client session.');
    $expires = posted('expires_epoch',10);
    if (preg_match('/\A[1-9][0-9]{0,9}\z/',$expires) !== 1) throw new InvalidArgumentException('Reload the hosting control panel page.');
    $result = provider_client_stackcp_open(posted('admission_id',32),posted('permission_id',32),(int)$client['id'],(int)$expires,posted('review_proof',64),posted('confirmed',1) === '1','provider_stackcp_launch_redirect');
    throw new RuntimeException(provider_stackcp_launch_state_label((string)$result['state']));
}

function provider_client_stackcp_client_render(array $client,string $error): never {
    if (provider_client_stackcp_session_blocked()) fail_page('Use an independent primary client session.',403);
    provider_stackcp_launch_page_headers();
    $snapshot = null;$unavailable = false;$page = provider_client_stackcp_page_number('panel_page');
    $link = $_GET['service_link'] ?? null;
    try {
        if ($link !== null && (!is_string($link) || preg_match('/\A[a-f0-9]{32}\z/',$link) !== 1)) throw new InvalidArgumentException('Invalid service.');
        $snapshot = provider_client_stackcp_client_snapshot((int)$client['id'],$page,$link);
        if (!$snapshot['ready']) $unavailable = true;
    } catch (InvalidArgumentException|RuntimeException $failure) { $unavailable = true; }
    site_head('Hosting control panel','portal');
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR HOSTING</span><h1>Hosting <span class="gold-text">control panel</span></h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';
    if ($error !== '') echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    if ($unavailable) echo '<p role="alert">Hosting access is unavailable. Contact the studio to review your account.</p>';
    elseif (!$snapshot['items']) echo '<p>No hosting control panel access is enabled for this account. Contact the studio for help.</p>';
    else {
        echo '<div class="workspace-grid">';
        foreach ($snapshot['items'] as $item) {
            try {
                // Failed POSTs never call review or mint another approval during redisplay.
                $review = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
                    ? provider_client_stackcp_retry_review($item) : provider_client_stackcp_review((string)$item['permission_id'],(int)$client['id']);
                $history = provider_client_stackcp_history((string)$item['permission_id'],(int)$client['id']);
                provider_client_stackcp_launch_render($item,$review,$history,$page,$link);
            } catch (InvalidArgumentException|RuntimeException $failure) {
                echo '<p role="alert">Hosting access is unavailable. Contact the studio to review your account.</p>';
            }
        }
        echo '</div><nav class="button-row" aria-label="Hosting control panel pages">';
        $params = ['hosting_panel'=>1];if ($link !== null) $params['service_link']=$link;
        if ($page > 1) echo '<a class="btn outline" href="'.esc(studio_url($params+['panel_page'=>$page-1])).'">Previous</a>';
        if ($snapshot['has_more'] && $page < 500) echo '<a class="btn outline" href="'.esc(studio_url($params+['panel_page'=>$page+1])).'">More accounts</a>';
        echo '</nav>';
    }
    echo '</div></section>';site_foot(false);exit;
}
