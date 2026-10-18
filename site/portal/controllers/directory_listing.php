<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/directory_listing_views.php';
function provider_directory_indexing_client_render(array $client):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');try{service_observations_actor((int)$client['id']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Sign in directly to view your website directory listing setting.',403);}
    $view=['status'=>'not_shared','rows'=>[]];try{$link=is_string($_GET['service_link']??null)?$_GET['service_link']:'';$view=provider_directory_indexing_client((int)$client['id'],$link);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$view=['status'=>'unavailable','rows'=>[]];}
    site_head('Website directory listing setting','portal');echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR HOSTING</span><h1>Website <span class="gold-text">directory listing setting</span></h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';require CWP_CODE_ROOT.'/portal/views/directory_listing.php';echo '</div></section>';site_foot(false);exit;
}
