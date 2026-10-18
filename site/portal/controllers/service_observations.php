<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/service_observations_views.php';
function service_observations_client_render(array $client):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');try{service_observations_actor((int)$client['id']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Sign in directly to view service history.',403);}
    $historyUnavailable=false;$histories=[];try{$link=is_string($_GET['service_link']??null)?$_GET['service_link']:'';if($link!=='')service_observations_id($link);$histories=service_observations_client((int)$client['id'],$link,max(0,(int)($_GET['history_before']??0)));}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$historyUnavailable=true;}
    site_head('Hosting service history','portal');echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR HOSTING</span><h1>Service <span class="gold-text">history</span></h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';require CWP_CODE_ROOT.'/portal/views/service_observations.php';echo '</div></section>';site_foot(false);exit;
}
