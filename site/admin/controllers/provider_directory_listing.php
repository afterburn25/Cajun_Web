<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/directory_listing.php';
function provider_directory_indexing_admin_action(string $action):never {
    provider_settings_manager();$password=posted('current_password',200);
    if($action==='provider_inventory_directory_indexing_prepare'){provider_directory_indexing_prepare($password);flash('Directory listing observation storage is ready. Sharing starts separately for each assigned website.');}
    elseif($action==='provider_inventory_directory_indexing_refresh'){
        $link=posted('link_id',32);$expected=posted('binding_sha256',64);$binding=staff_locked(fn()=>staff_transaction(fn()=>provider_directory_indexing_binding($link)));if(!hash_equals($binding->sha256,$expected))throw new RuntimeException('The hosting assignment or saved inventory changed. Reload before refreshing.');
        $r=provider_directory_indexing_refresh($link,$binding,$password);flash($r['state']==='complete'?'The reported directory listing setting was saved.':'The directory listing setting refresh did not provide a usable observation.',$r['state']==='complete'?'success':'error');
    }elseif($action==='provider_inventory_directory_indexing_share'){
        $sharing=posted('sharing',7);if(!in_array($sharing,['enable','disable'],true))throw new InvalidArgumentException('Choose whether to share the Directory listing observation.');provider_directory_indexing_share(posted('link_id',32),posted('expected_id',32),posted('event_id',32),$sharing==='enable',posted('anchor_id',32),$password);flash($sharing==='enable'?'The saved directory listing setting is shared with the assigned client.':'directory listing setting sharing is off.');
    }else throw new InvalidArgumentException('Unknown Directory listing observation action.');redirect(admin_url('provider-settings').'#directory-listing');
}
