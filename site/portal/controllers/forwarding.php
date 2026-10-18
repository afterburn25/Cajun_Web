<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/forwarding_views.php';
function provider_forwarding_client_action(string $action,array $client):never {
    if($action==='forwarding_create'){provider_forwarding_create(posted('request_id',32),(int)$client['id'],posted('link_id',32),posted('permission_id',32),posted('context',64),posted('local',64),posted('remote',254),posted('request_confirm',1)==='1');flash('Your forwarding request is pending studio review. Email delivery is unchanged.');}
    elseif($action==='forwarding_cancel'){provider_forwarding_change(posted('request_id',32),posted('expected_event',32),posted('event_id',32),'cancel','',(int)$client['id']);flash('Your forwarding request was cancelled.');}
    else throw new InvalidArgumentException('Unknown forwarding request action.');redirect(studio_url(['forwarding'=>1]));
}
function provider_forwarding_client_render(array $client,string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{provider_forwarding_actor((int)$client['id']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Forwarding requests are unavailable for this session.',403);}
    $forwardingHistory=[];$forwardingOptions=[];$forwardingUnavailable=false;
    try{$forwardingHistory=provider_forwarding_history((int)$client['id'],max(1,(int)($_GET['forwarding_page']??1)));$forwardingOptions=provider_forwarding_options((int)$client['id']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$forwardingUnavailable=true;}
    site_head('Email forwarding requests','portal');echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR HOSTING EMAIL</span><h1>Forwarding <span class="gold-text">requests</span></h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';show_flash();if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';require CWP_CODE_ROOT.'/portal/views/forwarding.php';echo '</div></section>';site_foot(false);exit;
}
