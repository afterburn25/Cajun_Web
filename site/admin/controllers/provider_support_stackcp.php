<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/support_stackcp_views.php';

function provider_support_stackcp_positive(string $field):int {
    $raw=posted($field,10);
    if(preg_match('/\A[1-9][0-9]{0,9}\z/',$raw)!==1||(int)$raw>2147483647)throw new InvalidArgumentException('Reload the current hosting support form.');
    return (int)$raw;
}
/** The common administrator route checks CSRF and owner permission first. */
function provider_support_stackcp_admin_action(string $action):never {
    provider_settings_manager();$password=posted('current_password',200);$staffId=null;
    if($action==='provider_support_stackcp_prepare'){
        provider_support_stackcp_prepare($password);flash('Hosting support access history is ready.');
    }elseif($action==='provider_support_stackcp_permission'){
        $staffId=provider_support_stackcp_positive('staff_id');$enabled=posted('enabled',1);
        if(!in_array($enabled,['0','1'],true))throw new InvalidArgumentException('Reload the current support permission.');
        provider_support_stackcp_permission_change(posted('profile_id',32),provider_support_stackcp_positive('package_id'),$staffId,posted('review_proof',64),$enabled==='1',posted('confirmed',1)==='1',$password);
        flash($enabled==='1'?'Hosting support access is enabled for the selected staff member.':'New hosting support launches are disabled. Existing provider sessions remain signed in.');
    }else throw new InvalidArgumentException('Unknown hosting support action.');
    redirect(admin_url('provider-settings',$staffId===null?[]:['support_stackcp_staff'=>(string)$staffId]).'#support-stackcp');
}
