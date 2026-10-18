<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/mailbox_views.php';
function provider_mailbox_admin_action(string $action):never {
    provider_settings_manager();$password=posted('current_password',200);
    if($action==='provider_inventory_mailbox_prepare'){
        provider_mailbox_prepare($password);flash('Mailbox request history is ready.');
    }elseif($action==='provider_inventory_mailbox_policy'){
        $limit=posted('daily_limit',2);$hours=posted('permission_hours',3);
        if(!ctype_digit($limit)||!ctype_digit($hours))throw new InvalidArgumentException('Set a whole-number request allowance and permission duration.');
        provider_mailbox_configure(posted('link_id',32),posted('previous_policy',32),posted('mailbox_enabled',1)==='1',posted('client_enabled',1)==='1',(int)$limit,(int)$hours,posted('capacity_reviewed',1)==='1',$password);
        flash('Mailbox permissions saved.');
    }elseif($action==='provider_inventory_mailbox_create'){
        provider_mailbox_submit(null,$password);flash('Your mailbox request is recorded below. Check its result before trying again.');
    }else throw new InvalidArgumentException('Unknown mailbox action.');
    redirect(admin_url('provider-settings').'#mailbox-management');
}
