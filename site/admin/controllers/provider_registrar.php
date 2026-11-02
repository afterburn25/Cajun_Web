<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/registrar.php';

function provider_registrar_admin_action(string $action):void {
    $password=posted('current_password',200);
    if($action==='provider_inventory_registrar_prepare'){provider_registrar_prepare($password);flash('Registered-domain storage is ready.');redirect(admin_url('provider-settings',['section'=>'domains']));}
    provider_settings_manager();
    $number=static function(string $key,int $max=4294967295):int{$v=posted($key,18);if(!preg_match('/\A[1-9][0-9]{0,17}\z/',$v)||(int)$v>$max)throw new InvalidArgumentException('Reload the registered-domain form.');return (int)$v;};
    if($action==='provider_inventory_registrar_refresh'){
        $state=provider_inventory_refresh(posted('profile_id',32),$number('revision'),$password,null,null,\CajunWebPro\Foundation\TwentyIInventory\InventoryRead::DOMAINS_LIST);
        flash(match($state){'complete'=>'The returned registered-domain identities were saved.','partial'=>'Some returned domains could not be validated. Assignments require a new complete observation.',default=>'Registered domains could not be confirmed. Earlier observations remain in history; refresh before assigning domains.'},$state==='complete'?'success':'error');redirect(admin_url('provider-settings',['section'=>'domains']));
    }
    if($action==='provider_inventory_registrar_link')provider_registrar_link(posted('profile_id',32),$number('domain_id',999999999999999999),$number('client_id'),$number('revision'),posted('refresh_id',32),$password);
    elseif($action==='provider_inventory_registrar_relink')provider_registrar_relink(posted('link_id',32),$number('link_revision'),$number('revision'),posted('refresh_id',32),$password);
    elseif($action==='provider_inventory_registrar_unlink')provider_registrar_unlink(posted('link_id',32),$number('link_revision'),$password);
    else throw new InvalidArgumentException('Unknown registered-domain action.');
    flash('Registered-domain assignment saved.');redirect(admin_url('provider-settings',['section'=>'domains']));
}
