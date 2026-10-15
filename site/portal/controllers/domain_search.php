<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_search.php';

function provider_domain_client_navigation(int $client):bool {
    try{$p=provider_domain_client_policy();if(!$p)return false;provider_domain_client_authority($client,$p['id']);return true;}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;return false;}
}
function provider_domain_client_action(array $client):never {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new InvalidArgumentException('Submit the domain search form.');
    $policy=$_POST['search_policy']??null;$request=$_POST['search_request']??null;
    if(!is_string($policy)||!is_string($request))throw new InvalidArgumentException('Reload the current domain search form.');
    provider_domain_client_run((int)$client['id'],$policy,$request,$_POST['domain_query']??null);
    flash('Your domain lookup is recorded below.');redirect(studio_url(['domains'=>1]));
}
function provider_domain_client_render(array $client,string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{staff_locked(fn()=>provider_domain_client_actor((int)$client['id']));}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Domain search is unavailable for this session.',403);}
    $searchContext=null;$searchHistory=[];$historyUnavailable=false;
    try{
        $searchHistory=provider_domain_client_history((int)$client['id']);
        $p=provider_domain_client_policy();
        if($p){[,,$cfg]=provider_domain_client_authority((int)$client['id'],$p['id']);$searchContext=['policy'=>$p['id'],'tlds'=>$cfg['tlds']];}
    }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$historyUnavailable=$searchHistory===[];}
    site_head('Find a domain','portal');
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR CLIENT WORKSPACE</span><h1>Find a <span class="gold-text">domain</span></h1><a class="btn outline" href="'.esc(studio_url()).'">All projects</a></div></section><section class="section"><div class="container">';show_flash();
    if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    require CWP_CODE_ROOT.'/portal/views/domain_search.php';echo '</div></section>';site_foot(false);exit;
}
