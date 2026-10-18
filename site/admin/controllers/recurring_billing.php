<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/layout/recurring_billing.php';
function recurring_billing_admin_render(string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{recurring_billing_touch(fn()=>recurring_billing_locked(fn()=>recurring_ui_authority(null)),false);}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;http_response_code(403);admin_head('Recurring billing unavailable','recurring-billing',require_admin());echo '<p class="notice error">'.esc($e->getMessage()).'</p>';admin_foot();exit;}
    $user=require_admin();admin_head('Recurring billing','recurring-billing',$user);
    if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    try{
        $q=recurring_ui_query($_GET,true);$view=recurring_ui_context($q);
        if(($_SERVER['REQUEST_METHOD']??'')==='POST'&&!isset($GLOBALS['cwp_recurring_attempt'])&&is_string($_POST['action']??null)&&in_array($_POST['action'],['recurring_offer','recurring_command'],true))$GLOBALS['cwp_recurring_attempt']=recurring_ui_capture($_POST);
        recurring_ui_page($view,$user['role']==='owner');
    }catch(Throwable $e){$message=($e instanceof InvalidArgumentException||$e instanceof RuntimeException)&&!($e instanceof PDOException)?$e->getMessage():'Billing records are unavailable. Reference: '.report_error($e);echo '<p class="notice error" role="alert">'.esc($message).'</p>';}
    admin_foot();exit;
}
