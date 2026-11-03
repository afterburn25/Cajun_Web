<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/subdomain_add_views.php';

function provider_subdomain_add_admin_action(string $action): never {
    provider_settings_manager();$password=posted('current_password',200);
    if ($action==='provider_inventory_subdomain_prepare') {
        provider_subdomain_add_prepare($password);flash('Subdomain requests are ready.');
    } elseif ($action==='provider_inventory_subdomain_add') {
        $result=provider_subdomain_add_add(posted('request_id',32),posted('link_id',32),posted('parent_domain',253),posted('subdomain',253),posted('review_proof',4096),posted('confirmed',1)==='1',$password);
        flash(provider_subdomain_add_label($result['status']),$result['status']==='observed'?'success':'error');
    } elseif ($action==='provider_inventory_subdomain_verify') {
        $result=provider_subdomain_add_verify(posted('request_id',32),$password);
        flash(provider_subdomain_add_label($result['status']),$result['status']==='observed'?'success':'error');
    } elseif ($action==='provider_inventory_subdomain_recover') {
        if (posted('confirmed',1)!=='1') throw new InvalidArgumentException('Review the saved attempt before restoring its local record.');
        provider_subdomain_add_recover_finished(posted('request_id',32),posted('ledger_sha256',64),$password);
        flash('The saved local request record was restored. Check the reported website names separately.');
    } else throw new InvalidArgumentException('Unknown subdomain request action.');
    redirect(admin_url('provider-settings').'#subdomain-requests');
}
