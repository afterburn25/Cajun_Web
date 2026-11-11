<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/checkout_purchases.php';
require_once CWP_CODE_ROOT.'/app/hosting/checkout_purchases_views.php';

function hosting_purchases_admin_action(string $action):never {
    if($action!=='hosting_payment_reconcile')throw new InvalidArgumentException('Unknown hosting payment action.');
    $user=require_admin();
    if(($user['role']??null)!=='owner'||support_current()||!staff_can('payments.manage',$user))throw new RuntimeException('Current owner financial access outside support is required.');
    if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm the retained transaction before reconciliation.');
    $state=hosting_payment_reconcile(posted('request_id',32),posted('transaction_id',30),'');
    flash('Hosting payment reconciliation: '.str_replace('_',' ',$state).'.');redirect(admin_url('hosting-purchases'));
}

function hosting_purchases_admin_data(array $query): array {
    $user=require_admin();
    if (($user['role']??null)!=='owner'||support_current()||array_key_exists('support',$query)) throw new RuntimeException('Sign in as the owner outside support access.');
    return hosting_purchases_owner_page(hosting_purchases_ui_page($query));
}
function hosting_purchases_admin_render(string $error=''): never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    $user=require_admin();
    if (($user['role']??null)!=='owner'||support_current()||array_key_exists('support',$_GET)) {fail_page('Hosting purchases are available to the owner outside support access.',403);}
    try {hosting_purchases_ui_page($_GET);} catch (InvalidArgumentException) {fail_page('Hosting purchases page not found.',404);}
    $page=null;
    try {$page=hosting_purchases_admin_data($_GET);} catch (Throwable $e) {report_error($e);http_response_code(503);}
    admin_head('Hosting purchases','hosting-purchases',$user);
    if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    require CWP_CODE_ROOT.'/admin/views/hosting_purchases.php';
    admin_foot();exit;
}
