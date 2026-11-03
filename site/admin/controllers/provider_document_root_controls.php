<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/document_root_controls_views.php';

/** posted() bounds input but trims it; this control admits only the exact submitted bytes. */
function provider_document_root_controls_posted(string $key,int $limit):string {
    $value=posted($key,$limit);
    if(!isset($_POST[$key])||!is_string($_POST[$key])||$value!==$_POST[$key])throw new InvalidArgumentException('Enter the exact '.str_replace('_',' ',$key).' without surrounding whitespace.');
    return $value;
}

/** The shared admin POST dispatcher checks CSRF before calling this owner-only handler. */
function provider_document_root_controls_admin_action(string $action):never {
    provider_settings_manager();$password=posted('current_password',200);
    if($action==='provider_document_root_control_prepare'){
        provider_document_root_controls_prepare($password);flash('Website folder change request storage is ready. Client sharing remains separate.');
    }elseif($action==='provider_document_root_control_change'){
        $view=provider_document_root_controls_change(
            provider_document_root_controls_posted('request_id',32),provider_document_root_controls_posted('link_id',32),
            provider_document_root_controls_posted('anchor_id',32),provider_document_root_controls_posted('binding_sha256',64),
            provider_document_root_controls_posted('name',253),provider_document_root_controls_posted('desired_path',1024),
            provider_document_root_controls_posted('reviewed',1)==='1',$password
        );
        flash(provider_document_root_controls_label($view['status']),$view['status']==='observed'?'success':'error');
    }elseif($action==='provider_document_root_control_recover'){
        if(provider_document_root_controls_posted('reviewed',1)!=='1')throw new InvalidArgumentException('Review this local dispatch recovery first.');
        provider_document_root_controls_recover_finished(provider_document_root_controls_posted('request_id',32),provider_document_root_controls_posted('ledger_sha256',64),$password);
        flash('Local dispatch record restored. The effect remains uncertain; read the folders separately.');
    }elseif($action==='provider_document_root_control_verify'){
        $view=provider_document_root_controls_verify(provider_document_root_controls_posted('request_id',32),$password);
        flash(provider_document_root_controls_label($view['status']),$view['status']==='observed'?'success':'error');
    }else throw new InvalidArgumentException('Unknown website folder change action.');
    redirect(admin_url('provider-settings').'#website-folder-changes');
}
