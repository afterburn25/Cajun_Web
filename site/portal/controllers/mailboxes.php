<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/mailbox_views.php';
function provider_mailbox_client_navigation(int $clientId):bool {
    try{return provider_mailbox_options($clientId)!==[]||provider_mailbox_history($clientId)!==[];}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;return false;}
}
function provider_mailbox_client_action(array $client):never {
    provider_mailbox_submit((int)$client['id']);flash('Your mailbox request is recorded below. Check its result before trying again.');redirect(studio_url(['hosting_mail'=>1]));
}
function provider_mailbox_client_render(array $client,string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{staff_locked(fn()=>provider_mailbox_actor((int)$client['id']));}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Mailbox management is unavailable for this session.',403);}
    $mailboxOptions=[];$mailboxHistory=[];$mailboxUnavailable=false;
    try{$mailboxHistory=provider_mailbox_history((int)$client['id']);$mailboxOptions=provider_mailbox_options((int)$client['id']);}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$mailboxUnavailable=true;}
    site_head('Hosting mailboxes','portal');
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR HOSTING</span><h1>Hosting <span class="gold-text">mailboxes</span></h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';show_flash();
    if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    $mailboxAdmin=false;require CWP_CODE_ROOT.'/portal/views/mailboxes.php';echo '</div></section>';site_foot(false);exit;
}
