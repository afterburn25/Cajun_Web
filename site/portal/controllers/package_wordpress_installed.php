<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/package_wordpress_installed_views.php';
function provider_wp_installed_client_render(array $client):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');try{service_observations_actor((int)$client['id']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Sign in directly to view your website WordPress installation indication.',403);}
    $view=['status'=>'not_shared','rows'=>[]];try{$link=is_string($_GET['service_link']??null)?$_GET['service_link']:'';$view=provider_wp_installed_client((int)$client['id'],$link);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$view=['status'=>'unavailable','rows'=>[]];}
    site_head('Website WordPress installation indication','portal');echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR HOSTING</span><h1>Website <span class="gold-text">WordPress installation indication</span></h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';require CWP_CODE_ROOT.'/portal/views/package_wordpress_installed.php';echo '</div></section>';site_foot(false);exit;
}
