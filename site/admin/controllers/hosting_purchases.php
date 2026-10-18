<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/checkout_purchases.php';
require_once CWP_CODE_ROOT.'/app/hosting/checkout_purchases_views.php';

function hosting_purchases_admin_data(array $query): array {
    $user=require_admin();
    if (($user['role']??null)!=='owner'||support_current()||array_key_exists('support',$query)) throw new RuntimeException('Sign in as the owner outside support access.');
    return hosting_purchases_owner_page(hosting_purchases_ui_page($query));
}
function hosting_purchases_admin_render(): never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    $user=require_admin();
    if (($user['role']??null)!=='owner'||support_current()||array_key_exists('support',$_GET)) {fail_page('Hosting purchases are available to the owner outside support access.',403);}
    try {hosting_purchases_ui_page($_GET);} catch (InvalidArgumentException) {fail_page('Hosting purchases page not found.',404);}
    $page=null;
    try {$page=hosting_purchases_admin_data($_GET);} catch (Throwable $e) {report_error($e);http_response_code(503);}
    admin_head('Hosting purchases','hosting-purchases',$user);
    require CWP_CODE_ROOT.'/admin/views/hosting_purchases.php';
    admin_foot();exit;
}
