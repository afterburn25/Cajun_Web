<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }boot();require CWP_CODE_ROOT.'/includes/layout/public.php';require CWP_CODE_ROOT.'/app/marketing/growth.php';require CWP_CODE_ROOT.'/app/clients/workspace.php';require CWP_CODE_ROOT.'/includes/layout/admin.php';require CWP_CODE_ROOT.'/admin/controllers/support.php';require CWP_CODE_ROOT.'/includes/layout/ledger.php';require CWP_CODE_ROOT.'/includes/layout/deliveries.php';require CWP_CODE_ROOT.'/includes/layout/activity.php';header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: no-referrer');
require_once CWP_CODE_ROOT.'/portal/controllers/plans.php';
if(array_key_exists('agreements',$_GET) || array_key_exists('agreement',$_GET) || (is_string($_POST['action']??null) && str_starts_with($_POST['action'],'service_agreements_')))header('Cache-Control: no-store, private');
$error='';$supportRequested=array_key_exists('support',$_GET);$supportId=is_string($_GET['support']??null)?$_GET['support']:'';
if($supportRequested){
 if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??null)==='support_end'){
  try{support_check_csrf($supportId);support_end($supportId);redirect(admin_url('support'));}catch(Throwable){support_unavailable();}
 }
 $context=support_resolve($supportId);if(!$context)support_unavailable();$GLOBALS['cwp_support']=$context;
}
$client=studio_client();$jobId=isset($_GET['job'])&&is_string($_GET['job'])?(int)$_GET['job']:0;$invite=is_string($_GET['invite']??null)?$_GET['invite']:'';
require CWP_CODE_ROOT.'/portal/controllers/actions.php';
if(isset($_GET['activity']))activity_render('client',$error);
if(isset($_GET['download_release']))delivery_download(is_string($_GET['download_release'])?$_GET['download_release']:'');
if(isset($_GET['downloads'])||isset($_GET['release']))delivery_portal_render();
if(isset($_GET['file'])){if(!$client)fail_page('Sign in to view this file.',403);studio_download(is_string($_GET['file'])?$_GET['file']:'',(int)$client['id']);}
if(isset($_GET['invoice'])||isset($_GET['receipt']))ledger_portal_render();
require CWP_CODE_ROOT.'/portal/views/auth.php';
if(array_key_exists('agreements',$_GET) || array_key_exists('agreement',$_GET))service_plans_portal_render($client,$error);
if($jobId){try{$job=studio_job($jobId,(int)$client['id']);}catch(InvalidArgumentException){if(support_current())support_record('portal.view','project',(string)$jobId,'denied');fail_page('Project not found or access denied.',404);}}
if(support_current())support_record('portal.view',$jobId?'project':'client',(string)($jobId?:$client['id']));
require CWP_CODE_ROOT.'/portal/views/workspace.php';
