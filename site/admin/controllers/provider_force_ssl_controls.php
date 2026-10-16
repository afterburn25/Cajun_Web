<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/force_ssl_controls_views.php';
function provider_force_ssl_controls_admin_action(string $action):never {
    provider_settings_manager();$password=posted('current_password',200);
    if($action==='provider_force_ssl_control_prepare'){provider_force_ssl_controls_prepare($password);flash('Force SSL change request storage is ready. Client sharing remains separate.');}
    elseif($action==='provider_force_ssl_control_change'){
        $desired=posted('desired',5);if(!in_array($desired,['true','false'],true))throw new InvalidArgumentException('Choose an exact Force SSL flag value.');
        $view=provider_force_ssl_controls_change(posted('request_id',32),posted('link_id',32),posted('anchor_id',32),posted('binding_sha256',64),$desired==='true',posted('reviewed',1)==='1',$password);flash(provider_force_ssl_controls_label($view['status']),$view['status']==='observed'?'success':'error');
    }elseif($action==='provider_force_ssl_control_verify'){$view=provider_force_ssl_controls_verify(posted('request_id',32),$password);flash(provider_force_ssl_controls_label($view['status']),$view['status']==='observed'?'success':'error');}
    else throw new InvalidArgumentException('Unknown Force SSL change action.');redirect(admin_url('provider-settings').'#force-ssl-changes');
}
