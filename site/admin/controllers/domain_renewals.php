<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_renewals.php';
function domain_renewals_admin_action(string $action):never {
    provider_settings_manager();$password=posted('current_password',200);
    if($action==='provider_inventory_renewal_prepare'){domain_renewals_prepare($password);flash('Renewal request history is ready.');}
    elseif($action==='provider_inventory_renewal_review'){
        $revision=posted('request_revision',10);if(!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/',$revision)||(int)$revision>4294967295)throw new InvalidArgumentException('Reload the review.');
        domain_renewals_change(posted('request_id',32),(int)$revision,posted('event_id',32),posted('command',12),posted('review_note',1000),null,$password);flash('Review saved. No renewal was placed.');
    }else throw new InvalidArgumentException('Unknown renewal request action.');redirect(admin_url('provider-settings').'#domain-renewal-requests');
}
