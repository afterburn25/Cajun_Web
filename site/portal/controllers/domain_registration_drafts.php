<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_registration_drafts_views.php';

function registration_drafts_client_action(string $action,array $client):never {
    $clientId=(int)$client['id'];registration_drafts_client($clientId);$id=registration_drafts_text($_POST,'draft_id',32);$revision=registration_drafts_number($_POST,'expected_revision',256,true);$head=registration_drafts_text($_POST,'expected_head',64);$command=registration_drafts_text($_POST,'command_id',32);
    if($action==='registration_drafts_client_save')domain_registration_drafts_client_save($clientId,$id,$revision,$head,$command,registration_drafts_posted_content($_POST,$clientId));
    else{
        $change=match($action){'registration_drafts_client_submit'=>'submit','registration_drafts_client_withdraw'=>'withdraw','registration_drafts_client_reopen'=>'reopen',default=>throw new InvalidArgumentException('Unknown registration draft action.')};
        if(registration_drafts_text($_POST,'confirm',1)!=='1')throw new InvalidArgumentException('Confirm the action for this draft revision.');domain_registration_drafts_client_change($clientId,$id,$revision,$head,$command,$change);
    }
    flash('Your private registration draft was saved. No domain was registered or payment submitted.');redirect(studio_url(['registration_drafts'=>1,'draft'=>$id]));
}
function registration_drafts_client_render(array $client,string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{registration_drafts_client((int)$client['id']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Registration drafts are unavailable for this session.',403);}
    site_head('Private registration drafts','portal');echo '<section class="page-hero compact"><div class="container"><h1>Private registration drafts</h1><a class="btn outline" href="'.esc(studio_url()).'">Client workspace</a></div></section><section class="section"><div class="container">';show_flash();if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    try{require CWP_CODE_ROOT.'/portal/views/domain_registration_drafts.php';}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<div class="notice error" role="alert">'.esc($e->getMessage()).'</div>';}
    echo '</div></section>';site_foot(false);exit;
}
