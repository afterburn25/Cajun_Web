<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/stackcp_launch.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_prerequisite_views.php';

function provider_stackcp_launch_state_label(string $state): string {
    return match ($state) {
        'handoff_attempted' => 'Handoff prepared; browser delivery and remote sign-in are not confirmed',
        'unknown' => 'Remote outcome unknown; this attempt will not be repeated',
        'not_issued' => 'No browser handoff issued',
        'interrupted' => 'Attempt interrupted; it will not be resumed',
        default => 'Outcome unavailable',
    };
}

/** A rejected POST retains its original approval; rendering never renews it. */
function provider_stackcp_launch_form_values(array $review): array {
    $values = [];
    foreach (['admission_id','profile_id','package_id','expires_epoch','review_proof'] as $name) {
        $values[$name] = (string)($review[$name] ?? '');
    }
    if (($_POST['action'] ?? null) !== 'provider_inventory_stackcp_launch_open') return $values;
    $original = [];
    foreach ($values as $name => $value) {
        $raw = provider_stackcp_post_value($name, $name === 'review_proof' ? 64 : 32);
        $pattern = match ($name) {
            'admission_id','profile_id' => '/\A[a-f0-9]{32}\z/',
            'review_proof' => '/\A[a-f0-9]{64}\z/',
            default => '/\A[1-9][0-9]{0,9}\z/',
        };
        $original[$name] = preg_match($pattern, $raw) === 1 ? $raw : '';
    }
    if ($original['profile_id'] !== $values['profile_id'] || $original['package_id'] !== $values['package_id']) {
        $original['review_proof'] = '';
    }
    return $original;
}

/** Safe native projection only: no credential, response body or destination URL. */
function provider_stackcp_launch_render(array $review, array $history): void {
    echo '<article class="workspace-card" data-stackcp-launch-package="'.(int)$review['package_id'].'">';
    echo '<h3>Package '.(int)$review['package_id'].'</h3>';
    if (!empty($review['available'])) {
        echo '<p>Open the selected StackCP account: <strong>'.esc((string)$review['stack_identity']).'</strong>.</p>';
        echo '<p>This opens that user’s whole account. Remote permissions can change after the saved checks. Removing local access does not sign out an existing provider session.</p>';
        if (!empty($review['previous_unknown'])) {
            echo '<p class="notice error" role="alert">The previous attempt has an unknown remote outcome. A provider session may already exist. Creating another session does not cancel or recover that earlier session.</p>';
        }
        $values = provider_stackcp_launch_form_values($review);
        echo '<form method="post" target="_blank" rel="noopener noreferrer" data-stackcp-launch-open>';
        admin_action('provider_inventory_stackcp_launch_open');
        provider_stackcp_hidden($values);
        admin_check('confirmed',!empty($review['previous_unknown'])
            ? 'Create a NEW StackCP session even though the previous session may exist.'
            : 'Create a new StackCP session for this selected user.',false);
        provider_stackcp_password();
        echo '<button class="btn gold"'.($values['review_proof'] === '' ? ' disabled' : '').'>Open StackCP</button>';
        echo '<p class="hint">Opens in a new tab. This approval expires after five minutes and can be used only once.</p></form>';
        if (($_POST['action'] ?? null) === 'provider_inventory_stackcp_launch_open') {
            echo '<p class="hint">This form keeps the original approval. Reload this page to review a new attempt.</p>';
        }
    } else {
        echo ($review['reason'] ?? '') === 'capacity'
            ? '<p>Access history has reached its capacity. Retain the existing history and request an application update before creating another session.</p>'
            : '<p>Refresh the package membership and whole-user grants after reviewing this assignment and its authentication settings.</p>';
    }
    if (!empty($history['items'])) {
        echo '<details><summary>Recent access attempts</summary><ul>';
        foreach ($history['items'] as $attempt) {
            echo '<li><time>'.esc(gmdate('Y-m-d H:i:s',(int)$attempt['created_epoch'])).' UTC</time> — '.esc(provider_stackcp_launch_state_label((string)$attempt['state'])).'</li>';
        }
        echo '</ul></details>';
    }
    echo '</article>';
}

function provider_stackcp_launch_admin_render(): void {
    provider_settings_manager();
    echo '<section class="workspace-card" id="stackcp-launch"><h2>Open StackCP</h2>';
    echo '<p>Owners can open an approved StackCP account after checking its current assignment. This handoff supports the default StackCP address; custom control-panel domains are not supported here.</p>';
    try {
        $status = provider_stackcp_launch_status();
        if (!$status['ready']) {
            echo '<p>Prepare the protected access history after setting up StackCP authentication, assignments and observations above.</p><form method="post">';
            admin_action('provider_inventory_stackcp_launch_prepare');
            provider_stackcp_password();
            echo '<button class="btn gold">Prepare StackCP access</button></form></section>';
            return;
        }
        $raw = $_GET['stackcp_access_page'] ?? '1';
        $page = is_string($raw) && preg_match('/\A[1-9][0-9]{0,3}\z/',$raw) === 1 ? min(500,(int)$raw) : 1;
        $snapshot = ProviderStackCpReadProjection::ownerAccessSnapshot($page);
        if ($snapshot['error'] !== null) throw new RuntimeException('Review the StackCP settings and observations before opening access.');
        if (!$snapshot['packages']) echo '<p>Save a current StackCP assignment, then check membership and grants above.</p>';
        foreach ($snapshot['packages'] as $package) {
            $profile = (string)$package['profile_id'];
            $packageId = (int)$package['package_id'];
            provider_stackcp_launch_render(provider_stackcp_launch_review($profile,$packageId),provider_stackcp_launch_history($profile,$packageId));
        }
        echo '<p class="hint">Package pages follow the assignment checks above.</p>';
    } catch (InvalidArgumentException|RuntimeException $error) {
        if ($error instanceof PDOException) throw $error;
        echo '<p class="notice error" role="alert">StackCP access is unavailable. Review the current settings and retained access history before continuing.</p>';
    }
    echo '</section>';
}
