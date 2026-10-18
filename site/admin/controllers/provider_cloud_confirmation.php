<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/cloud_site_confirmation.php';
function provider_cloud_confirmation_admin_action(string $action):void {
    $password=posted('current_password',200);
    if($action==='provider_inventory_cloud_confirmation_prepare'){
        provider_cloud_confirmation_prepare($password);flash('Cloud website confirmation history is ready.');
    }elseif($action==='provider_inventory_cloud_confirmation_confirm'){
        provider_cloud_confirmation_confirm(posted('request_id',32),posted('refresh_id',32),posted('review',64),posted('reviewed',1)==='1',$password);
        flash('Allocated package observed and assigned to the original client. Website health, current server location and billing activation are not verified.');
    }else throw new InvalidArgumentException('Unknown cloud confirmation action.');
    redirect(admin_url('provider-settings'));
}
