<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
    check_csrf();$action=posted('action',60);
    require CWP_CODE_ROOT.'/admin/controllers/auth.php';
    $user=require_admin();
    $permission=staff_action_permission($action);if($permission!==null)staff_require($permission);
    if(in_array($action,['staff_migrate','staff_save','staff_invite'],true))staff_admin_action($action);
    if(in_array($action,['support_migrate','support_policy','support_start'],true))support_admin_action($action);
    if(str_starts_with($action,'registration_drafts_'))registration_drafts_admin_action($action);
    if(str_starts_with($action,'recurring_')){recurring_ui_action($action);return;}
    if(str_starts_with($action,'service_plans_')){service_plans_admin_action($action);return;}
    if(str_starts_with($action,'catalog_'))catalog_admin_action($action,$user);
    if(str_starts_with($action,'payments_'))payments_admin_action($action);
    if(str_starts_with($action,'provider_settings_')){provider_settings_admin_action($action);return;}
    if(str_starts_with($action,'provider_operations_')){provider_operations_admin_action($action);return;}
    if(str_starts_with($action,'provider_force_ssl_control_'))provider_force_ssl_controls_admin_action($action);
    if(str_starts_with($action,'provider_renewal_dispatch_'))domain_renewal_dispatch_admin_action($action);
    if(str_starts_with($action,'provider_directory_indexing_control_'))provider_directory_indexing_controls_admin_action($action);
    if(str_starts_with($action,'provider_inventory_')){provider_inventory_admin_action($action);return;}
    if(str_starts_with($action,'mail_accounts_')){mail_accounts_admin_action($action);return;}
    if(str_starts_with($action,'mail_notices_'))mail_notices_admin_action($action);
    if(str_starts_with($action,'mail_submissions_')){mail_submissions_admin_action($action);return;}
    if(str_starts_with($action,'mail_drafts_')){$draftResult=mail_drafts_admin_action($action);return;}
    if(str_starts_with($action,'mail_message_')){$messageResult=mail_message_admin_action($action);return;}
    if(str_starts_with($action,'mail_inbox_')){$inboxResult=mail_inbox_admin_action($action);return;}
    if(str_starts_with($action,'appointments_')){appointments_admin_action($action);return;}
    if(str_starts_with($action,'reviews_')){reviews_admin_action($action);return;}
    if(str_starts_with($action,'mail_templates_')){mail_templates_admin_action($action);return;}
    if(str_starts_with($action,'activity_'))activity_post('admin');
    if(str_starts_with($action,'delivery_'))delivery_admin_action($action);
    if(str_starts_with($action,'studio_')){studio_admin_action($action,$user);}
    if($action==='logout'){support_end_browser('admin-logout');log_event('auth.logout');$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'', ['expires'=>time()-42000,'path'=>$p['path'],'secure'=>$p['secure'],'httponly'=>true,'samesite'=>'Lax']);}session_destroy();redirect(admin_url('login'));}
    require CWP_CODE_ROOT.'/admin/controllers/portfolio.php';
    require CWP_CODE_ROOT.'/admin/controllers/services.php';
    require CWP_CODE_ROOT.'/admin/controllers/testimonials.php';
    require CWP_CODE_ROOT.'/admin/controllers/inquiries.php';
    require CWP_CODE_ROOT.'/admin/controllers/settings.php';
    require CWP_CODE_ROOT.'/admin/controllers/mail.php';
    require CWP_CODE_ROOT.'/admin/controllers/account.php';
    require CWP_CODE_ROOT.'/admin/controllers/maintenance.php';
    throw new InvalidArgumentException('Unknown administration action.');
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$error=$e instanceof PDOException?'The change could not be saved. Check for duplicate values or database issues. Reference: '.report_error($e):$e->getMessage();}
}
