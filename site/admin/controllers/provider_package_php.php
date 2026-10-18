<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/package_php.php';
function provider_php_admin_action(string $action):never {
    provider_settings_manager();$password=posted('current_password',200);
    if($action==='provider_inventory_php_prepare'){provider_php_prepare($password);flash('PHP observation storage is ready. Sharing starts separately for each assigned website.');}
    elseif($action==='provider_inventory_php_refresh'){
        $link=posted('link_id',32);$expected=posted('binding_sha256',64);$binding=staff_locked(fn()=>staff_transaction(fn()=>provider_php_binding($link)));if(!hash_equals($binding->sha256,$expected))throw new RuntimeException('The hosting assignment or saved inventory changed. Reload before refreshing.');
        $r=provider_php_refresh($link,$binding,$password);flash($r['state']==='complete'?'The reported PHP version was saved.':'The PHP version refresh did not provide a usable observation.',$r['state']==='complete'?'success':'error');
    }elseif($action==='provider_inventory_php_share'){
        $sharing=posted('sharing',7);if(!in_array($sharing,['enable','disable'],true))throw new InvalidArgumentException('Choose whether to share the PHP observation.');provider_php_share(posted('link_id',32),posted('expected_id',32),posted('event_id',32),$sharing==='enable',posted('anchor_id',32),$password);flash($sharing==='enable'?'The saved PHP version is shared with the assigned client.':'PHP version sharing is off.');
    }else throw new InvalidArgumentException('Unknown PHP observation action.');redirect(admin_url('provider-settings').'#package-php-version');
}
