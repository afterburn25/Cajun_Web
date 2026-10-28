<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/stackcp_prerequisite_views.php';
/** Called only after admin_actions() checks CSRF, under the dedicated inventory_stackcp prefix. */
function provider_stackcp_admin_action(string $action): never {
    provider_settings_manager();$password=posted('current_password',200);
    if ($action==='provider_inventory_stackcp_auth_prepare') {provider_stackcp_auth_prepare($password);flash('Separate StackCP authentication storage is ready. No provider session was created.');}
    elseif ($action==='provider_inventory_stackcp_assignment_prepare') {provider_stackcp_assignment_prepare($password);flash('Local StackCP assignment storage is ready. Assignments remain unverified.');}
    elseif (in_array($action,['provider_inventory_stackcp_auth_save','provider_inventory_stackcp_auth_disable'],true)) {
        $secret=$_POST['auth_client_key']??'';if (!is_string($secret)||strlen($secret)>4096) throw new InvalidArgumentException('Enter the separate OAuth client key up to 4,096 bytes.');
        provider_stackcp_auth_change(posted('profile_id',32),posted('review_sha256',64),$action==='provider_inventory_stackcp_auth_save',$secret,$password);
        flash($action==='provider_inventory_stackcp_auth_save'?'Authentication key stored locally. It has not been checked with 20i.':'Authentication configuration disabled. Its history is retained.');
    } elseif (in_array($action,['provider_inventory_stackcp_assignment_save','provider_inventory_stackcp_assignment_revoke'],true)) {
        $package=posted('package_id',10);if (!preg_match('/\A[1-9][0-9]{0,9}\z/',$package)||(int)$package>2147483647) throw new InvalidArgumentException('Reload the selected hosting assignment.');
        if ($action==='provider_inventory_stackcp_assignment_save'&&posted('acknowledged',1)!=='1') throw new InvalidArgumentException('Confirm that this local draft does not enable StackCP login.');
        provider_stackcp_assignment_change(posted('profile_id',32),(int)$package,posted('review_sha256',64),posted('link_id',32),posted('stack_identity',32),$action==='provider_inventory_stackcp_assignment_revoke',$password);
        flash($action==='provider_inventory_stackcp_assignment_save'?'Unverified StackCP assignment saved. Login remains unavailable.':'Local StackCP draft revoked. This does not revoke a provider session.');
    } else throw new InvalidArgumentException('Unknown StackCP setup action.');
    $raw=$_GET['stackcp_page']??'1';$page=is_string($raw)&&preg_match('/\A[1-9][0-9]{0,3}\z/',$raw)?min(500,(int)$raw):1;
    redirect(admin_url('provider-settings',['stackcp_page'=>$page,'stackcp_access_page'=>$page]).'#stackcp-prerequisites');
}
