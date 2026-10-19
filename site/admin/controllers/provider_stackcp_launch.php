<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/stackcp_launch_views.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_launch_http.php';

/** The common administrator dispatcher checks CSRF before this dedicated action. */
function provider_stackcp_launch_admin_action(string $action): never {
    provider_settings_manager();
    $password = posted('current_password',200);
    if ($action === 'provider_inventory_stackcp_launch_prepare') {
        provider_stackcp_launch_prepare($password);
        flash('StackCP access history is ready. No provider session was created.');
    } elseif ($action === 'provider_inventory_stackcp_launch_open') {
        $package = posted('package_id',10);
        $expires = posted('expires_epoch',10);
        if (preg_match('/\A[1-9][0-9]{0,9}\z/',$package) !== 1 || (int)$package > 2147483647
            || preg_match('/\A[1-9][0-9]{0,9}\z/',$expires) !== 1) {
            throw new InvalidArgumentException('Reload the current StackCP access form.');
        }
        $result = provider_stackcp_launch_open(posted('admission_id',32),posted('profile_id',32),(int)$package,
            (int)$expires,posted('review_proof',64),posted('confirmed',1) === '1',$password,'provider_stackcp_launch_redirect');
        flash(provider_stackcp_launch_state_label((string)$result['state']),'error');
    } else {
        throw new InvalidArgumentException('Unknown StackCP access action.');
    }
    redirect(admin_url('provider-settings').'#stackcp-launch');
}
