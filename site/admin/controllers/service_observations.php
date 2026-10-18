<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/service_observations.php';
function service_observations_admin_action(string $action):never {
    provider_settings_manager();$password=posted('current_password',200);
    if($action==='provider_inventory_history_prepare'){service_observations_prepare($password);flash('Hosting history storage is ready. Enable sharing separately for each assignment.');}
    elseif($action==='provider_inventory_history_change'){$sharing=posted('sharing',7);if(!in_array($sharing,['enable','disable'],true))throw new InvalidArgumentException('Choose whether to share history.');service_observations_change(posted('link_id',32),posted('expected_id',32),posted('event_id',32),$sharing==='enable',posted('refresh_id',32),$password);flash($sharing==='enable'?'Hosting history is shared from the reviewed read onward.':'Hosting history sharing is off.');}
    else throw new InvalidArgumentException('Unknown hosting history action.');redirect(admin_url('provider-settings').'#service-observations');
}
