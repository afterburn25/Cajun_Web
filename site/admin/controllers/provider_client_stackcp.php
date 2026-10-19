<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/client_stackcp_views.php';

/** Called only after the common administrator CSRF gate. */
function provider_client_stackcp_admin_action(string $action): never {
    provider_settings_manager();$password = posted('current_password',200);
    if ($action === 'provider_inventory_client_stackcp_prepare') {
        provider_client_stackcp_prepare($password);flash('Client hosting access history is ready.');
    } elseif ($action === 'provider_inventory_client_stackcp_permission') {
        $package = posted('package_id',10);$enabled = posted('enabled',1);
        if (preg_match('/\A[1-9][0-9]{0,9}\z/',$package) !== 1 || (int)$package > 2147483647 || !in_array($enabled,['0','1'],true)) throw new InvalidArgumentException('Reload the current client access form.');
        provider_client_stackcp_permission_change(posted('profile_id',32),(int)$package,posted('review_proof',64),$enabled === '1',posted('confirmed',1) === '1',$password);
        flash($enabled === '1' ? 'Client hosting control panel access is enabled.' : 'New client launches are disabled. Existing provider sessions remain signed in.');
    } else throw new InvalidArgumentException('Unknown client hosting access action.');
    redirect(admin_url('provider-settings').'#client-stackcp');
}
