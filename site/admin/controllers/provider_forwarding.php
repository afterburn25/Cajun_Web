<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/forwarding.php';
function provider_forwarding_admin_action(string $action):never {
    provider_settings_manager();$password=posted('current_password',200);
    if($action==='provider_inventory_forwarding_prepare'){provider_forwarding_prepare($password);flash('Forwarding request history is ready. Enable separate permission for each domain below.');}
    elseif($action==='provider_inventory_forwarding_permission'){
        $number=static function(string $key,int $max):int{$s=posted($key,3);if(!preg_match('/\A[1-9][0-9]{0,2}\z/',$s)||(int)$s>$max)throw new InvalidArgumentException('Choose a valid request limit and expiry.');return (int)$s;};
        provider_forwarding_configure(posted('event_id',32),posted('link_id',32),posted('permission_id',32),posted('context',64),posted('enabled',1)==='1',$number('daily_limit',20),$number('hours',720),posted('permission_confirm',1)==='1',$password);flash('Forwarding request permission saved. No forwarding was activated.');
    }elseif($action==='provider_inventory_forwarding_review'){
        provider_forwarding_change(posted('request_id',32),posted('expected_event',32),posted('event_id',32),posted('command',7),posted('review_note',1000),null,$password);flash('Review saved. Email delivery is unchanged.');
    }else throw new InvalidArgumentException('Unknown forwarding request action.');redirect(admin_url('provider-settings').'#forwarding-requests');
}
