<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }boot();require CWP_CODE_ROOT.'/includes/layout/public.php';require CWP_CODE_ROOT.'/app/marketing/growth.php';require CWP_CODE_ROOT.'/app/clients/workspace.php';require CWP_CODE_ROOT.'/includes/layout/admin.php';require CWP_CODE_ROOT.'/admin/controllers/support.php';require CWP_CODE_ROOT.'/includes/layout/ledger.php';require CWP_CODE_ROOT.'/includes/layout/deliveries.php';require CWP_CODE_ROOT.'/includes/layout/activity.php';require_once CWP_CODE_ROOT.'/includes/layout/provider_services.php';header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: no-referrer');
require_once CWP_CODE_ROOT.'/portal/controllers/domain_search.php';
require_once CWP_CODE_ROOT.'/portal/controllers/mailboxes.php';
require_once CWP_CODE_ROOT.'/portal/controllers/domain_renewals.php';
require_once CWP_CODE_ROOT.'/portal/controllers/domain_registration_drafts.php';
if(array_key_exists('registration_drafts',$_GET)||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'registration_drafts_')))header('Cache-Control: no-store, private');
require_once CWP_CODE_ROOT.'/portal/controllers/domain_contracts.php';
require_once CWP_CODE_ROOT.'/portal/controllers/domain_expiry.php';
if(array_key_exists('domain_expiry',$_GET))header('Cache-Control: no-store, private');
if(array_key_exists('domain_dates',$_GET))header('Cache-Control: no-store, private');
require_once CWP_CODE_ROOT.'/portal/controllers/service_observations.php';
require_once CWP_CODE_ROOT.'/portal/controllers/package_php.php';
require_once CWP_CODE_ROOT.'/portal/controllers/document_roots.php';
require_once CWP_CODE_ROOT.'/portal/controllers/package_force_ssl.php';
require_once CWP_CODE_ROOT.'/portal/controllers/directory_listing.php';
require_once CWP_CODE_ROOT.'/portal/controllers/package_wordpress_installed.php';
if(array_key_exists('force_ssl',$_GET))header('Cache-Control: no-store, private');
if(array_key_exists('directory_indexing',$_GET))header('Cache-Control: no-store, private');
if(array_key_exists('wp_installed',$_GET))header('Cache-Control: no-store, private');
if(array_key_exists('document_roots',$_GET))header('Cache-Control: no-store, private');
if(array_key_exists('php_version',$_GET))header('Cache-Control: no-store, private');
if(array_key_exists('service_history',$_GET))header('Cache-Control: no-store, private');
require_once CWP_CODE_ROOT.'/portal/controllers/forwarding.php';
require_once CWP_CODE_ROOT.'/portal/controllers/cloud_site_requests.php';
if(array_key_exists('cloud_websites',$_GET)||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'cloud_website_request_')))header('Cache-Control: no-store, private');
if(array_key_exists('forwarding',$_GET)||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'forwarding_')))header('Cache-Control: no-store, private');
if(array_key_exists('domain_renewals',$_GET)||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'domain_renewal_')))header('Cache-Control: no-store, private');
if(array_key_exists('hosting_mail',$_GET)||($_POST['action']??null)==='client_mailbox_create')header('Cache-Control: no-store, private');
require_once CWP_CODE_ROOT.'/portal/controllers/plans.php';
require_once CWP_CODE_ROOT.'/portal/controllers/recurring_billing.php';
if(array_key_exists('recurring',$_GET)||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'recurring_')))header('Cache-Control: no-store, private');
if(array_key_exists('agreements',$_GET) || array_key_exists('agreement',$_GET) || (is_string($_POST['action']??null) && str_starts_with($_POST['action'],'service_agreements_')))header('Cache-Control: no-store, private');
if(array_key_exists('domains',$_GET)||($_POST['action']??null)==='client_domain_search')header('Cache-Control: no-store, private');
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
if(isset($_GET['services']))provider_services_portal_render();
require CWP_CODE_ROOT.'/portal/views/auth.php';
if(array_key_exists('registration_drafts',$_GET))registration_drafts_client_render($client,$error);
if(array_key_exists('domains',$_GET))provider_domain_client_render($client,$error);
if(array_key_exists('hosting_mail',$_GET))provider_mailbox_client_render($client,$error);
if(array_key_exists('domain_dates',$_GET))domain_contracts_client_render($client);
if(array_key_exists('domain_expiry',$_GET))domain_expiry_client_render($client);
if(array_key_exists('domain_renewals',$_GET))domain_renewals_client_render($client,$error);
if(array_key_exists('service_history',$_GET))service_observations_client_render($client);
if(array_key_exists('php_version',$_GET))provider_php_client_render($client);
if(array_key_exists('document_roots',$_GET))provider_document_roots_client_render($client);
if(array_key_exists('force_ssl',$_GET))provider_force_ssl_client_render($client);
if(array_key_exists('directory_indexing',$_GET))provider_directory_indexing_client_render($client);
if(array_key_exists('wp_installed',$_GET))provider_wp_installed_client_render($client);
if(array_key_exists('forwarding',$_GET))provider_forwarding_client_render($client,$error);
if(array_key_exists('cloud_websites',$_GET))provider_cloud_site_requests_client_render($client,$error);
if(array_key_exists('recurring',$_GET))recurring_billing_portal_render($client,$error);
if(array_key_exists('agreements',$_GET) || array_key_exists('agreement',$_GET))service_plans_portal_render($client,$error);
if($jobId){try{$job=studio_job($jobId,(int)$client['id']);}catch(InvalidArgumentException){if(support_current())support_record('portal.view','project',(string)$jobId,'denied');fail_page('Project not found or access denied.',404);}}
if(support_current())support_record('portal.view',$jobId?'project':'client',(string)($jobId?:$client['id']));
require CWP_CODE_ROOT.'/portal/views/workspace.php';
