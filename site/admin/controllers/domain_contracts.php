<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_contracts.php';
function domain_contracts_admin_action(string $action):never {
    provider_settings_manager();$password=posted('current_password',200);
    if($action==='provider_inventory_contract_prepare'){domain_contracts_prepare($password);flash('Renewal date observations are ready.');}
    elseif($action==='provider_inventory_contract_refresh'){
        $number=static function(string $name):int{$v=posted($name,10);if(!preg_match('/\A[1-9][0-9]{0,9}\z/',$v)||(int)$v>4294967295)throw new InvalidArgumentException('Reload the renewal date form.');return (int)$v;};
        $r=domain_contracts_refresh(posted('link_id',32),$number('link_revision'),$number('profile_revision'),posted('refresh_id',32),$password);flash($r['state']==='complete'?'The observed renewal date was saved.':'The renewal date is unavailable from the latest refresh.',$r['state']==='complete'?'success':'error');
    }else throw new InvalidArgumentException('Unknown renewal date action.');redirect(admin_url('provider-settings',['section'=>'domains']).'#domain-contracts');
}
