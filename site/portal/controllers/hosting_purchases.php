<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/checkout_purchases.php';
require_once CWP_CODE_ROOT.'/app/hosting/checkout_purchases_views.php';

/** Also called by the router before studio_client can normalize a revoked contact session. */
function hosting_purchases_portal_access(): void {
    if (array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION)||array_key_exists('support',$_GET)||support_current()) throw new RuntimeException('Sign in as the primary client outside support access.');
}
function hosting_purchases_portal_data(array $client,array $query): array {
    hosting_purchases_portal_access();
    if (($query['hosting_purchases']??null)!=='1') throw new InvalidArgumentException('Choose the hosting purchases page.');
    $id=$client['id']??null;
    if ((!is_int($id)&&!is_string($id))||preg_match('/\A[1-9][0-9]{0,9}\z/',(string)$id)!==1||(int)$id>2147483647) throw new RuntimeException('Sign in as the primary client.');
    return hosting_purchases_client_page((int)$id,hosting_purchases_ui_page($query));
}
function hosting_purchases_portal_render(array $client): never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try {hosting_purchases_portal_access();} catch (RuntimeException) {fail_page('Sign in as the primary client outside support access to view hosting purchases.',403);}
    try {
        if (($_GET['hosting_purchases']??null)!=='1') throw new InvalidArgumentException('Choose the hosting purchases page.');
        hosting_purchases_ui_page($_GET);
    } catch (InvalidArgumentException) {fail_page('Hosting purchases page not found.',404);}
    $page=null;
    try {$page=hosting_purchases_portal_data($client,$_GET);} catch (Throwable $e) {report_error($e);http_response_code(503);}
    site_head('Your hosting purchases','portal');
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR CLIENT WORKSPACE</span><h1>Hosting <span class="gold-text">purchases</span></h1><a class="btn outline" href="'.esc(studio_url()).'">All projects</a></div></section><section class="section"><div class="container narrow">';show_flash();
    require CWP_CODE_ROOT.'/portal/views/hosting_purchases.php';
    echo '</div></section>';site_foot(false);exit;
}
