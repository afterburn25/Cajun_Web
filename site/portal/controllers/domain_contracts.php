<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_contracts_views.php';
function domain_contracts_client_render(array $client):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');try{domain_contracts_actor((int)$client['id']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Sign in directly to view renewal dates.',403);}
    $datesUnavailable=false;$dates=[];try{$dates=domain_contracts_client((int)$client['id']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$datesUnavailable=true;}
    site_head('Domain renewal dates','portal');echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR DOMAINS</span><h1>Renewal <span class="gold-text">dates</span></h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';require CWP_CODE_ROOT.'/portal/views/domain_contracts.php';echo '</div></section>';site_foot(false);exit;
}
