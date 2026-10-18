<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/directory_listing_controls_views.php';
function provider_directory_indexing_controls_admin_action(string $action):never {
    provider_settings_manager();$password=posted('current_password',200);
    if($action==='provider_directory_indexing_control_prepare'){provider_directory_indexing_controls_prepare($password);flash('Directory listing changes are ready. Client sharing remains separate.');}
    elseif($action==='provider_directory_indexing_control_change'){
        $desired=posted('desired',5);if(!in_array($desired,['true','false'],true))throw new InvalidArgumentException('Choose whether to enable or disable directory listing.');
        $view=provider_directory_indexing_controls_change(posted('request_id',32),posted('link_id',32),posted('anchor_id',32),posted('binding_sha256',64),$desired==='true',posted('reviewed',1)==='1',$password);flash(provider_directory_indexing_controls_label($view['status']),$view['status']==='observed'?'success':'error');
    }elseif($action==='provider_directory_indexing_control_recover'){
        if(posted('reviewed',1)!=='1')throw new InvalidArgumentException('Review restoring this request’s saved history first.');
        provider_directory_indexing_controls_recover_finished(posted('request_id',32),posted('ledger_sha256',64),$password);flash('Saved request history restored. The result is not yet confirmed; check the current setting separately.');
    }elseif($action==='provider_directory_indexing_control_verify'){$view=provider_directory_indexing_controls_verify(posted('request_id',32),$password);flash(provider_directory_indexing_controls_label($view['status']),$view['status']==='observed'?'success':'error');}
    else throw new InvalidArgumentException('Unknown Directory listing change action.');redirect(admin_url('provider-settings').'#directory-listing-changes');
}
