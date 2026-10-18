<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_agreements.php';
function domain_renewal_agreements_admin_action(string $action):never {
    provider_settings_manager();service_plans_owner();$password=posted('current_password',200);
    if($action==='provider_inventory_renewal_agreements_prepare'){domain_renewal_agreements_prepare($password);flash('Renewal agreement history is ready.');redirect(admin_url('provider-settings').'#domain-renewal-requests');}
    if($action!=='provider_inventory_renewal_agreement_bind')throw new InvalidArgumentException('Unknown renewal agreement action.');
    $revision=posted('request_revision',10);if(!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/',$revision)||(int)$revision>4294967295)throw new InvalidArgumentException('Reload the renewal request.');
    $deadline=posted('offer_expires',16);$date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$deadline,new DateTimeZone('UTC'));if(!$date||$date->format('Y-m-d\TH:i')!==$deadline)throw new InvalidArgumentException('Choose a valid acceptance deadline in UTC.');
    $privacy=posted('renew_privacy',1);if(!in_array($privacy,['0','1'],true))throw new InvalidArgumentException('Choose the quoted privacy scope.');if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm the exact domain and service terms.');
    $previous=posted('previous_sha256',64);domain_renewal_agreements_bind(posted('binding_id',32),posted('request_id',32),(int)$revision,posted('request_sha256',64),posted('agreement_id',32),posted('agreement_sha256',64),$previous===''?null:$previous,$date->getTimestamp(),$privacy==='1',$password);
    flash('Renewal terms linked for client review. No renewal or payment was submitted.');redirect(admin_url('domain-renewal-agreements',['request'=>posted('request_id',32)]));
}

function domain_renewal_agreements_admin_render(string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{$actor=provider_settings_manager();service_plans_owner();}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;http_response_code(403);admin_head('Renewal agreements unavailable','provider-settings',require_admin());echo '<div class="notice error">'.esc($e->getMessage()).'</div>';admin_foot();exit;}
    admin_head('Renewal service terms','provider-settings',$actor);show_flash();if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    try{require CWP_CODE_ROOT.'/admin/views/domain_renewal_agreements.php';}
    catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;echo '<div class="notice error" role="alert">'.esc($e->getMessage()).'</div>';}
    admin_foot();exit;
}
