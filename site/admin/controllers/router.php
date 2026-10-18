<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
boot();require CWP_CODE_ROOT.'/includes/layout/public.php';require CWP_CODE_ROOT.'/includes/layout/admin.php';require CWP_CODE_ROOT.'/app/mail/transport.php';
require_once CWP_CODE_ROOT.'/app/marketing/growth.php';require_once CWP_CODE_ROOT.'/app/clients/workspace.php';require_once CWP_CODE_ROOT.'/admin/controllers/studio.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';require_once CWP_CODE_ROOT.'/admin/controllers/staff.php';require_once CWP_CODE_ROOT.'/admin/controllers/support.php';require_once CWP_CODE_ROOT.'/admin/controllers/catalog.php';require_once CWP_CODE_ROOT.'/admin/controllers/payments.php';
require_once CWP_CODE_ROOT.'/includes/layout/deliveries.php';
require_once CWP_CODE_ROOT.'/includes/layout/activity.php';require_once CWP_CODE_ROOT.'/includes/layout/design_financial.php';
require_once CWP_CODE_ROOT.'/admin/controllers/mail_templates.php';
require_once CWP_CODE_ROOT.'/admin/controllers/mail_accounts.php';
require_once CWP_CODE_ROOT.'/admin/controllers/providers.php';
require_once CWP_CODE_ROOT.'/admin/controllers/mail_inbox.php';
require_once CWP_CODE_ROOT.'/admin/controllers/mail_message.php';
require_once CWP_CODE_ROOT.'/admin/controllers/mail_notices.php';
require_once CWP_CODE_ROOT.'/admin/controllers/mail_drafts.php';
require_once CWP_CODE_ROOT.'/admin/controllers/mail_submissions.php';
require_once CWP_CODE_ROOT.'/admin/controllers/reviews.php';
require_once CWP_CODE_ROOT.'/admin/controllers/appointments.php';
require_once CWP_CODE_ROOT.'/admin/controllers/plans.php';
require_once CWP_CODE_ROOT.'/admin/controllers/recurring_billing.php';
require_once CWP_CODE_ROOT.'/admin/controllers/domain_registration_drafts.php';
header('X-Robots-Tag: noindex, nofollow');
if(in_array($_GET['view']??'', ['staff-setup','reset'],true))header('Referrer-Policy: no-referrer');
$view=is_string($_GET['view']??null)?$_GET['view']:'dashboard';
$validViews=['login','forgot','reset','dashboard','projects','project','services','service','leads','lead','website-review','consultations','consultation','consultation-settings','testimonials','testimonial','settings','email','security','health'];
$validViews=array_merge($validViews,['registration-drafts','domain-renewal-dispatch','domain-renewal-agreements','recurring-billing','service-plans','service-plan','service-agreements','service-agreement','mail-submissions','mail-submission','mail-send','mail-drafts','mail-draft','provider-settings','provider-operations','mail-notices','mail-message','mail-inbox','mail-accounts','mail-account','mailboxes','mail-templates','mail-template','activity','deliveries','staff','staff-setup','support','catalog','catalog-invoice','payments','invoice-payments','payment-settings','payment-trials','payment-notifications'],array_keys(studio_admin_views()));
if(!in_array($view,$validViews,true)){$view='dashboard';}
if(in_array($view,['studio-clients','service-plans','service-plan','service-agreements','service-agreement'],true) || (is_string($_POST['action']??null) && str_starts_with($_POST['action'],'service_plans_'))){header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');}
if($view==='recurring-billing'||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'recurring_'))){header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');}
if(in_array($view,['domain-renewal-agreements','domain-renewal-dispatch'],true)){header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');}
if($view==='registration-drafts'||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'registration_drafts_'))){header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');}
if(in_array($view,['studio-clients','studio-job','studio-upgrade'],true)){header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');}
$error='';$id=(int)($_GET['id']??0);$inboxResult=null;$messageResult=null;$draftResult=null;
require CWP_CODE_ROOT.'/admin/controllers/actions.php';
if($view==='staff-setup')staff_setup_render($error);
require CWP_CODE_ROOT.'/admin/views/auth.php';
$user=require_admin();
$permission=staff_view_permission($view);
if($permission!==null&&!staff_can($permission,$user)){
    if($view==='dashboard')redirect(admin_url(staff_home($user)));
    http_response_code(403);admin_head('Access unavailable','security',$user);echo '<div class="notice error">Your account does not have access to this section.</div>';admin_foot();exit;
}
if(in_array($view,['studio-clients','studio-job'],true) && (support_current() || array_key_exists('support',$_GET))){
    http_response_code(403);admin_head('Client administration unavailable','studio-clients',$user);echo '<div class="notice error">Leave client support before using client administration.</div>';admin_foot();exit;
}
if(in_array($view,['mail-accounts','mail-account','mailboxes'],true))mail_accounts_admin_render($view,$error);
if(in_array($view,['service-plans','service-plan','service-agreements','service-agreement'],true))service_plans_admin_render($view,$error);
if($view==='recurring-billing')recurring_billing_admin_render($error);
if($view==='registration-drafts')registration_drafts_admin_render($error);
if($view==='provider-settings')provider_settings_admin_render($error);
if($view==='domain-renewal-agreements')domain_renewal_agreements_admin_render($error);
if($view==='domain-renewal-dispatch')domain_renewal_dispatch_admin_render($error);
if($view==='provider-operations')provider_operations_admin_render($error);
if($view==='mail-inbox')mail_inbox_admin_render($error,$inboxResult);
if($view==='mail-message')mail_message_admin_render($error,$messageResult);
if($view==='mail-notices')mail_notices_admin_render($error);
if(in_array($view,['mail-drafts','mail-draft'],true))mail_drafts_admin_render($view,$error,$draftResult);
if(in_array($view,['mail-submissions','mail-submission','mail-send'],true))mail_submissions_admin_render($view,$error);
if($view==='website-review')reviews_admin_render($error);
if(in_array($view,['consultations','consultation','consultation-settings'],true))appointments_admin_render($view,$error);
if(in_array($view,['mail-templates','mail-template'],true))mail_templates_admin_render($view,$error);
if($view==='staff'){staff_admin_render($user,$error);exit;}
if($view==='support')support_admin_render($user,$error);
if(in_array($view,['catalog','catalog-invoice'],true))catalog_admin_render($view,$user,$error);
if(in_array($view,['payments','invoice-payments','payment-settings','payment-trials','payment-notifications'],true))payments_admin_render($view,$user,$error);
if($view==='deliveries')delivery_admin_render($user,$error);
if($view==='activity')activity_render('admin',$error);
if(array_key_exists($view,studio_admin_views())){studio_admin_render($view,$user,$error);exit;}
$titles=['dashboard'=>'Your studio, at a glance.','projects'=>'Portfolio','project'=>$id?'Edit project':'New project','services'=>'Services','service'=>$id?'Edit service':'New service','leads'=>'Project inquiries','lead'=>'Inquiry details','testimonials'=>'Client testimonials','testimonial'=>$id?'Edit testimonial':'New testimonial','settings'=>'Website settings','email'=>'Email delivery','security'=>'Your account','health'=>'System health'];admin_head($titles[$view],$view,$user);if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
if($view==='dashboard'){require CWP_CODE_ROOT.'/admin/views/dashboard.php';
}elseif($view==='projects'){require CWP_CODE_ROOT.'/admin/views/projects.php';
}elseif($view==='project'){require CWP_CODE_ROOT.'/admin/views/project.php';
}elseif($view==='services'){require CWP_CODE_ROOT.'/admin/views/services.php';
}elseif($view==='service'){require CWP_CODE_ROOT.'/admin/views/service.php';
}elseif($view==='leads'){require CWP_CODE_ROOT.'/admin/views/leads.php';
}elseif($view==='lead'){require CWP_CODE_ROOT.'/admin/views/lead.php';
}elseif($view==='testimonials'){require CWP_CODE_ROOT.'/admin/views/testimonials.php';
}elseif($view==='testimonial'){require CWP_CODE_ROOT.'/admin/views/testimonial.php';
}elseif($view==='settings'){require CWP_CODE_ROOT.'/admin/views/settings.php';
}elseif($view==='email'){require CWP_CODE_ROOT.'/admin/views/email.php';
}elseif($view==='security'){require CWP_CODE_ROOT.'/admin/views/security.php';
}elseif($view==='health'){require CWP_CODE_ROOT.'/admin/views/health.php';
}
admin_foot();
