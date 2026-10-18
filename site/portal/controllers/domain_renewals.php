<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_renewals_views.php';
function domain_renewals_client_action(string $action,array $client):never {
    $number=static function(string $name,int $max=4294967295,bool $zero=false):int{$v=posted($name,10);if(!preg_match($zero?'/\A(?:0|[1-9][0-9]{0,9})\z/':'/\A[1-9][0-9]{0,9}\z/',$v)||(int)$v>$max)throw new InvalidArgumentException('Reload the renewal request form.');return (int)$v;};
    if($action==='domain_renewal_create'){
        $privacy=posted('renew_privacy',3);if(!in_array($privacy,['ask','yes','no'],true))throw new InvalidArgumentException('Choose a privacy preference.');
        domain_renewals_create(posted('request_id',32),(int)$client['id'],posted('link_id',32),$number('link_revision'),$number('profile_revision'),posted('refresh_id',32),$number('requested_years',10),$privacy==='ask'?null:($privacy==='yes'),posted('request_confirm',1)==='1');flash('Your renewal request is pending studio review. No renewal was placed.');
    }elseif($action==='domain_renewal_withdraw'){
        domain_renewals_change(posted('request_id',32),$number('request_revision',4294967295,true),posted('event_id',32),'withdrawn','',(int)$client['id']);flash('Your request was withdrawn.');
    }else throw new InvalidArgumentException('Unknown renewal request action.');redirect(studio_url(['domain_renewals'=>1]));
}
function domain_renewals_client_render(array $client,string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{domain_renewals_actor((int)$client['id']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Renewal requests are unavailable for this session.',403);}
    $renewalHistory=[];$renewalOptions=[];$renewalUnavailable=false;
    try{$renewalHistory=domain_renewals_history((int)$client['id'],max(1,(int)($_GET['renewal_page']??1)));$renewalOptions=domain_renewals_options((int)$client['id']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$renewalUnavailable=true;}
    site_head('Domain renewal requests','portal');echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR DOMAINS</span><h1>Renewal <span class="gold-text">requests</span></h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';show_flash();if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';require CWP_CODE_ROOT.'/portal/views/domain_renewals.php';echo '</div></section>';site_foot(false);exit;
}
