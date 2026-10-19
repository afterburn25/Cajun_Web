<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/registered_nameserver_changes_views.php';

function registered_nameserver_changes_admin_action(string $action): never {
    provider_settings_manager();
    $password = posted('current_password', 200);
    if ($action === 'provider_nameserver_change_prepare') {
        registered_nameserver_changes_prepare($password);
        flash('Nameserver changes are ready for owner approval.');
    } elseif ($action === 'provider_nameserver_change_submit') {
        $desired = preg_split('/\r\n|\n|\r/', posted('desired_nameservers', 16384));
        $desired = array_map('trim', $desired);
        $view = registered_nameserver_changes_change(posted('request_id', 32), posted('link_id', 32),
            posted('anchor_id', 32), posted('binding_sha256', 64), $desired, posted('reviewed', 1) === '1', $password);
        flash(registered_nameserver_changes_label($view['status']), $view['status'] === 'observed' ? 'success' : 'error');
    } elseif ($action === 'provider_nameserver_change_recover') {
        if (posted('reviewed', 1) !== '1') throw new InvalidArgumentException('Review restoring this request’s saved history first.');
        $view = registered_nameserver_changes_recover_finished(posted('request_id', 32), posted('ledger_sha256', 64), $password);
        flash('Saved request history restored. Check current nameservers separately; the change was not resent.');
    } elseif ($action === 'provider_nameserver_change_verify') {
        $view = registered_nameserver_changes_verify(posted('request_id', 32), $password);
        flash(registered_nameserver_changes_label($view['status']), $view['status'] === 'observed' ? 'success' : 'error');
    } else {
        throw new InvalidArgumentException('Unknown nameserver change action.');
    }
    if(isset($view)&&in_array($action,['provider_nameserver_change_verify','provider_nameserver_change_recover'],true)){
        $origin=staff_locked(fn()=>staff_transaction(fn()=>registered_nameserver_changes_request($view['id'])['document']['client_origin']??null));
        if($origin!==null)redirect(admin_url('nameserver-requests',['request_id'=>$origin['id']]));
    }
    redirect(admin_url('provider-settings').'#registered-nameservers');
}
