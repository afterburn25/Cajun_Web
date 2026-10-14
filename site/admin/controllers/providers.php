<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/checks.php';

function provider_settings_admin_action(string $action):void {
    $password=posted('current_password',200);
    if($action==='provider_settings_setup'){
        provider_settings_migrate($password);flash('20i settings and check history are ready.');redirect(admin_url('provider-settings'));
    }
    provider_settings_manager();$id=posted('profile_id',32);$revision=posted('revision',10);
    if(!ctype_digit($revision))throw new InvalidArgumentException('Reload the current 20i form.');
    if($action==='provider_settings_check'){
        $code=provider_checks_check($id,(int)$revision,$password);flash(provider_checks_message($code),$code==='connected'?'success':'error');redirect(admin_url('provider-settings'));
    }
    if($action==='provider_settings_save'){
        // Preserve the exact raw key; posted() trims non-password values.
        $secret=$_POST['general_key']??'';
        if(!is_string($secret)||strlen($secret)>4096)throw new InvalidArgumentException('Enter a General API key up to 4,096 bytes.');
        provider_settings_save($id,(int)$revision,posted('label',120),posted('enabled',1)==='1',$secret,$password);
    }else{
        $operation=match($action){'provider_settings_enable'=>'enable','provider_settings_disable'=>'disable','provider_settings_clear'=>'clear',default=>throw new InvalidArgumentException('Unknown provider settings action.')};
        provider_settings_state($id,(int)$revision,$operation,$password);
    }
    flash('20i settings saved. Check the saved connection when you are ready.');redirect(admin_url('provider-settings'));
}
function provider_settings_admin_render(string $error):void {
    header('Referrer-Policy: no-referrer');
    try{$actor=provider_settings_manager(false);}
    catch(RuntimeException $e){if($e instanceof PDOException)throw $e;http_response_code(403);admin_head('20i access unavailable','security',require_admin());echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';admin_foot();exit;}
    admin_head('20i connection','provider-settings',$actor);
    if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    if(!provider_settings_ready()){
        echo '<section class="workspace-card"><h2>Prepare your 20i connection</h2><p>Save a protected General API key and keep a history of explicit connection checks. Back up your database before adding this storage.</p><form method="post">';
        admin_action('provider_settings_setup');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Prepare 20i settings</button></form></section>';admin_foot();exit;
    }
    require CWP_CODE_ROOT.'/admin/views/providers.php';admin_foot();exit;
}
