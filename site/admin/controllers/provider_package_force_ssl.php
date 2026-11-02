<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/package_force_ssl.php';
function provider_force_ssl_admin_action(string $action):never {
    provider_settings_manager();$password=posted('current_password',200);
    if($action==='provider_inventory_force_ssl_prepare'){provider_force_ssl_prepare($password);flash('Force SSL observation storage is ready. Sharing starts separately for each assigned website.');}
    elseif($action==='provider_inventory_force_ssl_refresh'){
        $link=posted('link_id',32);$expected=posted('binding_sha256',64);$binding=staff_locked(fn()=>staff_transaction(fn()=>provider_force_ssl_binding($link)));if(!hash_equals($binding->sha256,$expected))throw new RuntimeException('The hosting assignment or saved inventory changed. Reload before refreshing.');
        $r=provider_force_ssl_refresh($link,$binding,$password);flash($r['state']==='complete'?'The reported Force SSL flag was saved.':'The Force SSL flag refresh did not provide a usable observation.',$r['state']==='complete'?'success':'error');
    }elseif($action==='provider_inventory_force_ssl_share'){
        $sharing=posted('sharing',7);if(!in_array($sharing,['enable','disable'],true))throw new InvalidArgumentException('Choose whether to share the Force SSL observation.');provider_force_ssl_share(posted('link_id',32),posted('expected_id',32),posted('event_id',32),$sharing==='enable',posted('anchor_id',32),$password);flash($sharing==='enable'?'The saved Force SSL flag is shared with the assigned client.':'Force SSL flag sharing is off.');
    }else throw new InvalidArgumentException('Unknown Force SSL observation action.');redirect(admin_url('provider-settings',['section'=>'website']).'#package-force-ssl');
}
