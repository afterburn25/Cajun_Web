<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/stackcp_access_views.php';
use CajunWebPro\Foundation\TwentyIStackCpAccess\StackCpAccessBinding;
/** Shared dispatch must route this longer prefix before provider_inventory_stackcp_. */
function provider_stackcp_access_admin_action(string $action):never {
    provider_settings_manager();$password=posted('current_password',200);$page=1;
    if ($action==='provider_inventory_stackcp_access_prepare') {provider_stackcp_access_prepare($password);flash('StackCP observation storage is ready. No provider read or login was requested.');}
    elseif (in_array($action,['provider_inventory_stackcp_access_membership','provider_inventory_stackcp_access_grants'],true)) {
        if (posted('confirmed',1)!=='1') throw new InvalidArgumentException('Confirm this explicit access observation.');$package=posted('package_id',10);if (!preg_match('/\A[1-9][0-9]{0,9}\z/',$package)||(int)$package>2147483647) throw new InvalidArgumentException('Reload the selected hosting assignment.');
        $raw=posted('stackcp_access_page',4);if (!preg_match('/\A[1-9][0-9]{0,3}\z/',$raw)||(int)$raw>500) throw new InvalidArgumentException('Reload the selected access observation page.');$page=(int)$raw;
        $operation=$action==='provider_inventory_stackcp_access_membership'?StackCpAccessBinding::MEMBERSHIP:StackCpAccessBinding::GRANTS;
        $result=provider_stackcp_access_refresh(posted('profile_id',32),(int)$package,$operation,posted('review_sha256',64),$password);
        flash($result['state']==='complete'?'The access observation was saved. Review its date and selected assignment below. No login was created.':'The latest access read is unavailable. Older results do not verify this assignment.');
    } else throw new InvalidArgumentException('Unknown StackCP access observation action.');
    redirect(admin_url('provider-settings',['stackcp_access_page'=>$page]).'#stackcp-access-observations');
}
