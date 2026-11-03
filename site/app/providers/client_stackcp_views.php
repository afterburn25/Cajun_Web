<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/client_stackcp_launch.php';
require_once __DIR__.'/stackcp_launch_views.php';

/** Inspect raw identity before any helper can normalize or clear it. */
function provider_client_stackcp_session_blocked(): bool {
    foreach (['contact_id','contact_auth','uid'] as $key) if (array_key_exists($key,$_SESSION)) return true;
    return array_key_exists('support',$_GET) || !empty($GLOBALS['cwp_support']) || support_current() !== null;
}

function provider_client_stackcp_page_number(string $key): int {
    $raw = $_GET[$key] ?? '1';
    return is_string($raw) && preg_match('/\A[1-9][0-9]{0,3}\z/',$raw) === 1 ? min(500,(int)$raw) : 1;
}

/** Rejected submissions keep their captured proof; they cannot renew an approval. */
function provider_client_stackcp_form_values(string $action,array $defaults): array {
    if (($_POST['action'] ?? null) !== $action) return $defaults;
    $out = [];
    foreach ($defaults as $name=>$value) {
        $raw = provider_stackcp_post_value($name,$name === 'review_proof' ? 64 : 32);
        $pattern = match ($name) {
            'admission_id','permission_id','profile_id' => '/\A[a-f0-9]{32}\z/',
            'review_proof' => '/\A[a-f0-9]{64}\z/',
            'enabled' => '/\A[01]\z/',
            default => '/\A[1-9][0-9]{0,9}\z/',
        };
        $out[$name] = preg_match($pattern,$raw) === 1 ? $raw : '';
    }
    foreach (['permission_id','profile_id','package_id','enabled'] as $selector) {
        if (isset($defaults[$selector]) && $out[$selector] !== (string)$defaults[$selector]) $out['review_proof'] = '';
    }
    foreach ($out as $value) if ($value === '') $out['review_proof'] = '';
    return $out;
}

/** Redisplay captured fields only; this cannot create or renew a signed review. */
function provider_client_stackcp_retry_review(array $item): array {
    if (($_POST['action'] ?? null) !== 'client_stackcp_open') return ['available'=>false];
    $values = provider_client_stackcp_form_values('client_stackcp_open',[
        'permission_id'=>(string)$item['permission_id'],'admission_id'=>'','expires_epoch'=>'','review_proof'=>'',
    ]);
    if ($values['review_proof'] === '') return ['available'=>false];
    return ['available'=>true,'retry'=>true,'previous_unknown'=>false]+$values;
}

/** Safe owner projection only; permission covers the named whole Stack-user. */
function provider_client_stackcp_permission_render(array $item): void {
    echo '<article class="workspace-card" data-client-stackcp-permission><h3>'.esc((string)$item['package_name']).'</h3>';
    echo '<p>Client: <strong>'.esc((string)$item['client_name']).'</strong> · StackCP account: <strong>'.esc((string)$item['stack_identity']).'</strong></p>';
    echo '<p>'.(!empty($item['stale']) ? 'Access needs a new owner review.' : (!empty($item['enabled']) ? 'Client access is enabled.' : 'Client access is disabled.')).'</p>';
    $enabled = !empty($item['enabled']) ? '0' : '1';
    if ($enabled === '1' && empty($item['can_enable'])) {
        echo '<p>Review the client assignment and refresh the membership and grants checks above before enabling access.</p></article>';
        return;
    }
    $values = provider_client_stackcp_form_values('provider_inventory_client_stackcp_permission',[
        'profile_id'=>(string)$item['profile_id'],'package_id'=>(string)$item['package_id'],
        'review_proof'=>(string)$item['review_proof'],'enabled'=>$enabled,
    ]);
    echo '<form method="post" data-client-stackcp-permission-form>';
    admin_action('provider_inventory_client_stackcp_permission');provider_stackcp_hidden($values);
    admin_check('confirmed',$enabled === '1'
        ? 'Allow this primary client to open this whole StackCP account.'
        : 'Disable new client sessions. Existing provider sessions will stay signed in.',false);
    provider_stackcp_password();
    echo '<button class="btn '.($enabled === '1' ? 'gold' : 'outline').'"'.($values['review_proof'] === '' ? ' disabled' : '').'>'.($enabled === '1' ? 'Enable client access' : 'Disable client access').'</button></form></article>';
}

function provider_client_stackcp_owner_render(): void {
    provider_settings_manager();
    echo '<section class="workspace-card" id="client-stackcp"><h2>Client hosting control panel</h2><p>Enable access for the primary client assigned to each hosting package. StackCP opens the selected user’s whole account. Clients must confirm each launch, and current provider permissions are checked before a session is created.</p><p>Disabling local access prevents new launches; it does not sign out existing provider sessions.</p>';
    try {
        $page = provider_client_stackcp_page_number('client_stackcp_page');
        $snapshot = provider_client_stackcp_owner_snapshot($page);
        if (!$snapshot['ready']) {
            echo '<p>Complete the StackCP setup and observations above, then prepare client access.</p><form method="post">';
            admin_action('provider_inventory_client_stackcp_prepare');provider_stackcp_password();
            echo '<button class="btn gold">Prepare client access</button></form></section>';return;
        }
        if (!$snapshot['items']) echo '<p>No assigned hosting accounts are available for client access.</p>';
        foreach ($snapshot['items'] as $item) provider_client_stackcp_permission_render($item);
        echo '<nav class="button-row" aria-label="Client hosting access pages">';
        if ($page > 1) echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['client_stackcp_page'=>$page-1])).'#client-stackcp">Previous</a>';
        if ($snapshot['has_more'] && $page < 500) echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['client_stackcp_page'=>$page+1])).'#client-stackcp">More accounts</a>';
        echo '</nav>';
    } catch (InvalidArgumentException|RuntimeException $error) {
        if ($error instanceof PDOException) throw $error;
        echo '<p class="notice error" role="alert">Client access is unavailable. Review the saved StackCP settings and access history.</p>';
    }
    echo '</section>';
}

/** No provider URL, credentials or untrusted error details enter this renderer. */
function provider_client_stackcp_launch_render(array $item,array $review,array $history,int $page=1,?string $link=null): void {
    if ($page < 1 || $page > 500) throw new InvalidArgumentException('Invalid hosting access page.');
    $route=['hosting_panel'=>1];if ($page > 1) $route['panel_page']=$page;
    if ($link !== null) {
        if (preg_match('/\A[a-f0-9]{32}\z/',$link) !== 1 || $link !== ($item['link_id'] ?? null)) throw new InvalidArgumentException('Invalid hosting access service.');
        $route['service_link']=$link;
    }
    echo '<article class="workspace-card" data-client-stackcp-launch><h2>'.esc((string)$item['package_name']).'</h2>';
    if (!empty($review['available'])) {
        echo '<p>This opens your assigned StackCP user’s whole account. Its current hosting permissions are checked before creating a session.</p><p>Removing access here does not sign out an existing provider session.</p>';
        if (!empty($review['previous_unknown'])) echo '<p class="notice error" role="alert">The previous attempt has an unknown remote outcome. A provider session may already exist. A new session does not cancel or recover that earlier session.</p>';
        $values = provider_client_stackcp_form_values('client_stackcp_open',[
            'permission_id'=>(string)$item['permission_id'],'admission_id'=>(string)$review['admission_id'],
            'expires_epoch'=>(string)$review['expires_epoch'],'review_proof'=>(string)$review['review_proof'],
        ]);
        echo '<form method="post" action="'.esc(studio_url($route)).'" target="_blank" rel="noopener noreferrer" data-client-stackcp-open>';
        echo csrf_field();provider_stackcp_hidden(['action'=>'client_stackcp_open']+$values);
        admin_check('confirmed',!empty($review['retry'])
            ? 'Continue this original whole-account access attempt; an earlier session may already exist.'
            : (!empty($review['previous_unknown']) ? 'Create a new session even though an earlier session may exist.' : 'Open my assigned whole StackCP account in a new tab.'),false);
        echo '<button class="btn gold"'.($values['review_proof'] === '' ? ' disabled' : '').'>Open hosting control panel</button><p class="hint">This approval expires after five minutes and can be used only once.</p></form>';
    } else echo '<p>Hosting control panel access is unavailable. Contact the studio to review your account.</p>';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') echo '<p class="hint">This attempt keeps its original approval. Submitting it again cannot repeat a completed exchange. Reload the page to review a new attempt.</p>';
    if (!empty($history['items'])) {
        echo '<details><summary>Recent access attempts</summary><ul>';
        foreach ($history['items'] as $row) echo '<li><time>'.esc(gmdate('Y-m-d H:i:s',(int)$row['created_epoch'])).' UTC</time> — '.esc(provider_stackcp_launch_state_label((string)$row['state'])).'</li>';
        echo '</ul></details>';
    }
    echo '</article>';
}
