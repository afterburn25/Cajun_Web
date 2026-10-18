<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_registration_drafts_views.php';

function registration_drafts_admin_action(string $action):never {
    registration_drafts_owner();$password=posted('owner_password',200);
    if($action==='registration_drafts_prepare'){domain_registration_drafts_prepare($password);flash('Private registration drafts are ready.');redirect(admin_url('registration-drafts'));}
    if($action!=='registration_drafts_owner_save')throw new InvalidArgumentException('Unknown registration draft action.');
    $client=registration_drafts_number($_POST,'client_id',4294967295);$id=registration_drafts_text($_POST,'draft_id',32);
    domain_registration_drafts_owner_save($client,registration_drafts_text($_POST,'client_version',32),$id,registration_drafts_number($_POST,'expected_revision',256,true),registration_drafts_text($_POST,'expected_head',64),registration_drafts_text($_POST,'command_id',32),registration_drafts_posted_content($_POST,$client),$password);
    flash('Private draft saved. The client must submit the current revision for studio review.');redirect(admin_url('registration-drafts',['draft'=>$id]));
}
function registration_drafts_admin_render(string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{$actor=registration_drafts_owner();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Registration drafts are unavailable for this session.',403);}
    admin_head('Private registration drafts','registration-drafts',$actor);show_flash();if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    try{require CWP_CODE_ROOT.'/admin/views/domain_registration_drafts.php';}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<div class="notice error" role="alert">'.esc($e->getMessage()).'</div>';}
    admin_foot();exit;
}
