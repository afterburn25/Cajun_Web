<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/cloud_providers.php';
function provider_cloud_providers_admin_action(string $action):void {
    provider_settings_manager();$password=posted('current_password',200);
    if($action==='provider_inventory_cloud_providers_prepare'){
        provider_cloud_providers_prepare($password);flash('Cloud provider discovery is ready.');redirect(admin_url('provider-settings',['section'=>'cloud']).'#cloud-providers');
    }
    if($action!=='provider_inventory_cloud_providers_refresh')throw new InvalidArgumentException('Unknown cloud provider action.');
    $revision=posted('revision',10);if(!preg_match('/\A[1-9][0-9]{0,9}\z/',$revision)||(int)$revision>2147483647)throw new InvalidArgumentException('Reload the current cloud provider form.');
    $state=provider_inventory_refresh(posted('profile_id',32),(int)$revision,$password,null,null,\CajunWebPro\Foundation\TwentyIInventory\InventoryRead::CLOUD_PROVIDERS_LIST);
    flash($state==='complete'?'The returned cloud provider identifiers were saved.':'Cloud providers could not be confirmed. The last saved observation remains in history.',$state==='complete'?'success':'error');
    redirect(admin_url('provider-settings',['section'=>'cloud']).'#cloud-providers');
}
