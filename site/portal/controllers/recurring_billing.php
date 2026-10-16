<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/layout/recurring_billing.php';
function recurring_billing_portal_render(array $client,string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{project_progress_touch(fn()=>project_progress_locked(fn()=>recurring_ui_authority((int)$client['id'])),false);}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Recurring billing is unavailable for this session. Sign in as the client outside support access.',403);}
    site_head('Your recurring billing','portal');echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR CLIENT WORKSPACE</span><h1>Recurring <span class="gold-text">billing</span></h1><a class="btn outline" href="'.esc(studio_url()).'">All projects</a></div></section><section class="section"><div class="container">';show_flash();
    if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    try{
        $q=recurring_ui_query($_GET,false);$view=recurring_ui_context($q,(int)$client['id']);
        if(($_SERVER['REQUEST_METHOD']??'')==='POST'&&!isset($GLOBALS['cwp_recurring_attempt'])&&($_POST['action']??null)==='recurring_command')$GLOBALS['cwp_recurring_attempt']=recurring_ui_capture($_POST);
        recurring_ui_page($view);
    }catch(Throwable $e){$message=($e instanceof InvalidArgumentException||$e instanceof RuntimeException)&&!($e instanceof PDOException)?$e->getMessage():'Billing records are unavailable. Reference: '.report_error($e);echo '<p class="notice error" role="alert">'.esc($message).'</p>';}
    echo '</div></section>';site_foot(false);exit;
}
