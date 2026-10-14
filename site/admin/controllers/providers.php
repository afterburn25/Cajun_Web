<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/checks.php';
require_once CWP_CODE_ROOT.'/app/providers/operations.php';
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/app/providers/reconcile.php';
use CajunWebPro\Foundation\Provider\OperationResult;
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationRegister;

function provider_inventory_message(?string $code):string {
    return [
        'complete'=>'The observed package list was saved.',
        'partial'=>'A partial observation was saved; some reported packages could not be validated.',
        'unavailable'=>'No usable inventory was returned. The last good observation is unchanged.',
        'refused'=>'The refresh was refused before a usable read completed. No observation changed.',
        'credential'=>'Protected credentials could not be opened. Check the original private key and saved settings.',
        'access_changed'=>'Current access or the saved profile changed during the refresh. No observation was recorded under the stale settings.',
    ][$code??'']??'The refresh did not complete. Try again after the cooldown.';
}

function provider_inventory_admin_action(string $action):void {
    $password=posted('current_password',200);
    if($action==='provider_inventory_prepare'){
        provider_inventory_migrate($password);flash('20i inventory storage is ready.');redirect(admin_url('provider-settings'));
    }
    provider_settings_manager();$id=posted('profile_id',32);
    if($action==='provider_inventory_refresh'||$action==='provider_inventory_link'||$action==='provider_inventory_relink'){
        $revision=posted('revision',10);if(!ctype_digit($revision))throw new InvalidArgumentException('Reload the current 20i form.');
    }
    if($action==='provider_inventory_refresh'){
        $code=provider_inventory_refresh($id,(int)$revision,$password);
        flash(provider_inventory_message($code),$code==='complete'?'success':'error');redirect(admin_url('provider-settings'));
    }
    if($action==='provider_inventory_link'){
        $client=(int)posted('client_id',10);$package=(int)posted('package_id',20);
        provider_inventory_link($id,$package,$client,(int)$revision,$password);flash('Service linked to the client.');redirect(admin_url('provider-settings'));
    }
    if($action==='provider_inventory_unlink'){
        provider_inventory_unlink(posted('link_id',32),$password);flash('Service link removed.');redirect(admin_url('provider-settings'));
    }
    if($action==='provider_inventory_relink'){
        provider_inventory_relink(posted('link_id',32),(int)$revision,$password);flash('Service link re-approved under the current provider settings.');redirect(admin_url('provider-settings'));
    }
    throw new InvalidArgumentException('Unknown provider inventory action.');
}

function provider_settings_admin_action(string $action):void {
    $password=posted('current_password',200);
    if($action==='provider_settings_setup'){
        provider_settings_migrate($password);flash('20i settings and check history are ready.');redirect(admin_url('provider-settings'));
    }
    provider_settings_manager();$id=posted('profile_id',32);$revision=posted('revision',10);
    if(!ctype_digit($revision))throw new InvalidArgumentException('Reload the current 20i form.');
    if($action==='provider_settings_check'){
        $code=provider_checks_check($id,(int)$revision,$password);flash(provider_checks_message($code),$code==='connected'?'success':'error');redirect(admin_url('provider-settings'));
    }
    if($action==='provider_settings_save'){
        // Preserve the exact raw key; posted() trims non-password values.
        $secret=$_POST['general_key']??'';
        if(!is_string($secret)||strlen($secret)>4096)throw new InvalidArgumentException('Enter a General API key up to 4,096 bytes.');
        provider_settings_save($id,(int)$revision,posted('label',120),posted('enabled',1)==='1',$secret,$password);
    }else{
        $operation=match($action){'provider_settings_enable'=>'enable','provider_settings_disable'=>'disable','provider_settings_clear'=>'clear',default=>throw new InvalidArgumentException('Unknown provider settings action.')};
        provider_settings_state($id,(int)$revision,$operation,$password);
    }
    flash('20i settings saved. Check the saved connection when you are ready.');redirect(admin_url('provider-settings'));
}
function provider_settings_admin_render(string $error):void {
    header('Referrer-Policy: no-referrer');
    try{$actor=provider_settings_manager(false);}
    catch(RuntimeException $e){if($e instanceof PDOException)throw $e;http_response_code(403);admin_head('20i access unavailable','security',require_admin());echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';admin_foot();exit;}
    admin_head('20i connection','provider-settings',$actor);
    if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    if(!provider_settings_ready()){
        echo '<section class="workspace-card"><h2>Prepare your 20i connection</h2><p>Save a protected General API key and keep a history of explicit connection checks. Back up your database before adding this storage.</p><form method="post">';
        admin_action('provider_settings_setup');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Prepare 20i settings</button></form></section>';admin_foot();exit;
    }
    require CWP_CODE_ROOT.'/admin/views/providers.php';admin_foot();exit;
}

/**
 * Owner-approved provider write requests. Granting binds one exact
 * operation and its approved parameters for a single run; running a
 * grant supplies only the idempotency key — the approved scope can
 * never be widened at send time. Every step needs the owner's current
 * password; ambiguous outcomes record uncertain for reconciliation.
 */
function provider_operations_admin_action(string $action):void {
    $password=posted('current_password',200);
    if($action==='provider_operations_setup'){
        provider_operations_migrate($password);flash('Provider operation approval records are ready.');redirect(admin_url('provider-operations'));
    }
    provider_settings_manager();$id=posted('profile_id',32);
    if($action==='provider_operations_grant'){
        $raw=(string)($_POST['scope_params']??'');
        if(strlen($raw)>4096)throw new InvalidArgumentException('The approved request fields exceed the bounded size.');
        $params=json_decode($raw,true);
        if(!is_array($params))throw new InvalidArgumentException('Enter the approved request fields as a JSON object.');
        $ttl=posted('ttl_seconds',10);if(!ctype_digit($ttl))throw new InvalidArgumentException('Choose a grant duration.');
        provider_operation_grant($id,posted('operation',64),$params,$password,(int)$ttl);
        flash('The operation is approved for one request.');redirect(admin_url('provider-operations'));
    }
    if($action==='provider_operations_revoke'){
        provider_operation_revoke(posted('grant_id',32),$password);
        flash('The approval was revoked.');redirect(admin_url('provider-operations'));
    }
    if($action==='provider_operations_run'){
        $revision=posted('revision',10);if(!ctype_digit($revision))throw new InvalidArgumentException('Reload the current 20i page.');
        $result=provider_operations_execute($id,(int)$revision,posted('grant_id',32),posted('idempotency_key',200),$password);
        $status=$result->status;
        flash('Operation '.$result->operation.': '.$status.' — '.$result->message,$status===OperationResult::SUCCEEDED?'success':($status===OperationResult::UNCERTAIN?'warning':'error'));
        redirect(admin_url('provider-operations'));
    }
    if($action==='provider_operations_reconcile'){
        $revision=posted('revision',10);if(!ctype_digit($revision))throw new InvalidArgumentException('Reload the current 20i page.');
        $result=provider_operations_reconcile($id,(int)$revision,posted('idempotency_key',200),$password);
        flash('Reconciliation: '.$result['outcome'].' — '.$result['message'],$result['outcome']==='observed'?'success':($result['outcome']==='inconclusive'||$result['outcome']==='unavailable'?'warning':'error'));
        redirect(admin_url('provider-operations'));
    }
    throw new InvalidArgumentException('Unknown provider operation action.');
}
function provider_operations_admin_render(string $error):void {
    header('Referrer-Policy: no-referrer');
    try{$actor=provider_settings_manager(false);}
    catch(RuntimeException $e){if($e instanceof PDOException)throw $e;http_response_code(403);admin_head('20i access unavailable','security',require_admin());echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';admin_foot();exit;}
    admin_head('20i operations','provider-operations',$actor);
    if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    if(!provider_settings_ready()){
        echo '<section class="workspace-card"><h2>Set up your 20i connection first</h2><p>Approved provider operations need a saved, enabled connection profile. <a href="'.esc(admin_url('provider-settings')).'">Open the 20i connection page</a>.</p></section>';admin_foot();exit;
    }
    if(!provider_operations_ready()){
        echo '<section class="workspace-card"><h2>Prepare operation approvals</h2><p>Operation approvals and their durable history need this storage. Back up your database before adding it. No provider request is made by preparing storage.</p><form method="post">';
        admin_action('provider_operations_setup');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Prepare operation records</button></form></section>';admin_foot();exit;
    }
    require CWP_CODE_ROOT.'/admin/views/operations.php';admin_foot();exit;
}
