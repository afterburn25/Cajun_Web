<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/registration_periods.php';
function provider_registration_period_admin_action(string $action):never {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new InvalidArgumentException('Submit the registration period form.');
    $password=posted('current_password',200);
    if($action==='provider_inventory_period_prepare'){provider_registration_period_prepare($password);flash('Registration period history is ready. No provider read has been made.');}
    elseif($action==='provider_inventory_period_refresh'){
        $revision=posted('period_revision',10);if(!preg_match('/\A[1-9][0-9]{0,9}\z/',$revision)||(int)$revision>2147483647)throw new InvalidArgumentException('Reload the current registration period form.');
        $result=provider_registration_period_refresh(posted('period_profile',32),(int)$revision,$password);
        flash(match($result['state']){'complete'=>'The observed registration period options were saved.','partial'=>'Some registration period entries could not be validated. The partial observation was saved.',default=>'Registration period options are unavailable from this attempt. The history is retained.'},$result['state']==='complete'?'success':'error');
    }else throw new InvalidArgumentException('Unknown registration period action.');
    redirect(admin_url('provider-settings').'#registration-periods');
}
