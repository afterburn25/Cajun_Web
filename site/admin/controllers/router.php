<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
boot();require CWP_CODE_ROOT.'/includes/layout/public.php';require CWP_CODE_ROOT.'/includes/layout/admin.php';require CWP_CODE_ROOT.'/app/mail/transport.php';
require_once CWP_CODE_ROOT.'/app/marketing/growth.php';require_once CWP_CODE_ROOT.'/app/clients/workspace.php';require_once CWP_CODE_ROOT.'/admin/controllers/studio.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';require_once CWP_CODE_ROOT.'/admin/controllers/staff.php';require_once CWP_CODE_ROOT.'/admin/controllers/support.php';require_once CWP_CODE_ROOT.'/admin/controllers/catalog.php';require_once CWP_CODE_ROOT.'/admin/controllers/payments.php';
require_once CWP_CODE_ROOT.'/includes/layout/deliveries.php';
require_once CWP_CODE_ROOT.'/includes/layout/activity.php';
header('X-Robots-Tag: noindex, nofollow');
if(in_array($_GET['view']??'', ['staff-setup','reset'],true))header('Referrer-Policy: no-referrer');
$view=is_string($_GET['view']??null)?$_GET['view']:'dashboard';
$validViews=['login','forgot','reset','dashboard','projects','project','services','service','leads','lead','testimonials','testimonial','settings','email','security','health'];
$validViews=array_merge($validViews,['activity','deliveries','staff','staff-setup','support','catalog','catalog-invoice','payments','invoice-payments','payment-settings','payment-trials','payment-notifications'],array_keys(studio_admin_views()));
if(!in_array($view,$validViews,true)){$view='dashboard';}
$error='';$id=(int)($_GET['id']??0);
require CWP_CODE_ROOT.'/admin/controllers/actions.php';
if($view==='staff-setup')staff_setup_render($error);
require CWP_CODE_ROOT.'/admin/views/auth.php';
$user=require_admin();
$permission=staff_view_permission($view);
if($permission!==null&&!staff_can($permission,$user)){
    if($view==='dashboard')redirect(admin_url(staff_home($user)));
    http_response_code(403);admin_head('Access unavailable','security',$user);echo '<div class="notice error">Your account does not have access to this section.</div>';admin_foot();exit;
}
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
