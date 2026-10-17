<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/package_force_ssl_views.php';
function provider_force_ssl_client_render(array $client):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');try{service_observations_actor((int)$client['id']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Sign in directly to view your website Force SSL flag.',403);}
    $view=['status'=>'not_shared','rows'=>[]];try{$link=is_string($_GET['service_link']??null)?$_GET['service_link']:'';$view=provider_force_ssl_client((int)$client['id'],$link);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$view=['status'=>'unavailable','rows'=>[]];}
    site_head('Website Force SSL flag','portal');echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR HOSTING</span><h1>Website <span class="gold-text">Force SSL flag</span></h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';require CWP_CODE_ROOT.'/portal/views/package_force_ssl.php';echo '</div></section>';site_foot(false);exit;
}
