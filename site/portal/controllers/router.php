<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }boot();require CWP_CODE_ROOT.'/includes/layout/public.php';require CWP_CODE_ROOT.'/app/marketing/growth.php';require CWP_CODE_ROOT.'/app/clients/workspace.php';require CWP_CODE_ROOT.'/includes/layout/admin.php';require CWP_CODE_ROOT.'/admin/controllers/support.php';require CWP_CODE_ROOT.'/includes/layout/ledger.php';require CWP_CODE_ROOT.'/includes/layout/deliveries.php';require CWP_CODE_ROOT.'/includes/layout/activity.php';require_once CWP_CODE_ROOT.'/includes/layout/provider_services.php';require_once CWP_CODE_ROOT.'/includes/layout/notifications.php';require_once CWP_CODE_ROOT.'/includes/layout/proposals.php';header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: no-referrer');
require_once CWP_CODE_ROOT.'/portal/controllers/tickets.php';
require_once CWP_CODE_ROOT.'/portal/controllers/domain_search.php';
require_once CWP_CODE_ROOT.'/portal/controllers/mailboxes.php';
require_once CWP_CODE_ROOT.'/portal/controllers/domain_renewals.php';
require_once CWP_CODE_ROOT.'/portal/controllers/registered_nameserver_requests.php';
require_once CWP_CODE_ROOT.'/portal/controllers/domain_registration_drafts.php';
if(array_key_exists('registration_drafts',$_GET)||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'registration_drafts_')))header('Cache-Control: no-store, private');
require_once CWP_CODE_ROOT.'/portal/controllers/domain_contracts.php';
require_once CWP_CODE_ROOT.'/portal/controllers/domain_expiry.php';
if(array_key_exists('domain_expiry',$_GET))header('Cache-Control: no-store, private');
if(array_key_exists('domain_dates',$_GET))header('Cache-Control: no-store, private');
require_once CWP_CODE_ROOT.'/portal/controllers/service_observations.php';
require_once CWP_CODE_ROOT.'/portal/controllers/package_php.php';
require_once CWP_CODE_ROOT.'/portal/controllers/php_controls.php';
require_once CWP_CODE_ROOT.'/portal/controllers/document_roots.php';
require_once CWP_CODE_ROOT.'/portal/controllers/package_force_ssl.php';
require_once CWP_CODE_ROOT.'/portal/controllers/force_ssl_requests.php';
$httpsRequestRoute=array_key_exists('https_requests',$_GET)||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'https_request_'));
if($httpsRequestRoute){
    header('Cache-Control: no-store, private');
    if(provider_force_ssl_requests_session_blocked())fail_page('Sign in directly to your primary client account to manage HTTPS requests.',403);
}
require_once CWP_CODE_ROOT.'/portal/controllers/directory_listing.php';
require_once CWP_CODE_ROOT.'/portal/controllers/directory_listing_requests.php';
$directoryRequestRoute=array_key_exists('directory_requests',$_GET)||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'directory_request_'));
if($directoryRequestRoute){
    header('Cache-Control: no-store, private');
    if(provider_directory_indexing_requests_session_blocked())fail_page('Sign in directly to your primary client account to manage directory-listing requests.',403);
}
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
require_once CWP_CODE_ROOT.'/portal/controllers/hosting_catalog.php';
require_once CWP_CODE_ROOT.'/portal/controllers/hosting_purchases.php';
if(array_key_exists('hosting_purchases',$_GET))header('Cache-Control: no-store, private');
if(array_key_exists('hosting',$_GET)||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'hosting_catalog_')))header('Cache-Control: no-store, private');
require_once CWP_CODE_ROOT.'/portal/controllers/recurring_billing.php';
if(array_key_exists('recurring',$_GET)||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'recurring_')))header('Cache-Control: no-store, private');
if(array_key_exists('job',$_GET)){header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');}
if(array_key_exists('agreements',$_GET) || array_key_exists('agreement',$_GET) || (is_string($_POST['action']??null) && str_starts_with($_POST['action'],'service_agreements_')))header('Cache-Control: no-store, private');
if(array_key_exists('domains',$_GET)||($_POST['action']??null)==='client_domain_search')header('Cache-Control: no-store, private');
require_once CWP_CODE_ROOT.'/includes/layout/project_progress.php';require_once CWP_CODE_ROOT.'/includes/layout/project_planning.php';require_once CWP_CODE_ROOT.'/includes/layout/design_financial.php';
$phpControlsRequested=array_key_exists('php_controls',$_GET)||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'php_controls_'));
if($phpControlsRequested){header('Cache-Control: no-store, private');provider_php_controls_http_session_gate();}
$phpControlsReview=null;
$error='';$supportRequested=array_key_exists('support',$_GET);$supportId=is_string($_GET['support']??null)?$_GET['support']:'';
if($supportRequested){
 if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??null)==='support_end'){
  try{support_check_csrf($supportId);support_end($supportId);redirect(admin_url('support'));}catch(Throwable){support_unavailable();}
 }
 $context=support_resolve($supportId);if(!$context)support_unavailable();$GLOBALS['cwp_support']=$context;
}
if(array_key_exists('hosting_purchases',$_GET)){try{hosting_purchases_portal_access();}catch(RuntimeException){fail_page('Sign in as the primary client outside support access to view hosting purchases.',403);}}
if(array_key_exists('nameserver_requests',$_GET)||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'nameserver_request_'))){
 header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
 try{registered_nameserver_requests_portal_guard();}catch(RuntimeException){fail_page('Sign in directly as the primary client to review nameserver requests.',403);}
}
$client=studio_client();$jobId=isset($_GET['job'])&&is_string($_GET['job'])?(int)$_GET['job']:0;$invite=is_string($_GET['invite']??null)?$_GET['invite']:'';$cinvite=is_string($_GET['cinvite']??null)?$_GET['cinvite']:'';

require CWP_CODE_ROOT.'/portal/controllers/actions.php';
if(isset($_GET['activity'])){if($client&&!contact_can('projects')&&!contact_can('billing')&&!contact_can('downloads'))fail_page('Your contact permissions do not include workspace activity.',403);activity_render('client',$error);}
if(isset($_GET['download_release'])){if($client&&!contact_can('downloads'))fail_page('Your contact permissions do not include downloads.',403);delivery_download(is_string($_GET['download_release'])?$_GET['download_release']:'');}
if(isset($_GET['downloads'])||isset($_GET['release'])){if($client&&!contact_can('downloads'))fail_page('Your contact permissions do not include downloads.',403);delivery_portal_render();}
if(isset($_GET['file'])){if(!$client)fail_page('Sign in to view this file.',403);if(!contact_can('downloads'))fail_page('Your contact permissions do not include downloads.',403);studio_download(is_string($_GET['file'])?$_GET['file']:'',(int)$client['id']);}
if(isset($_GET['preview'])){if(!$client)fail_page('Sign in to view this preview.',403);if(!contact_can('projects'))fail_page('Your contact permissions do not include project workspace.',403);studio_preview_download(is_string($_GET['preview'])&&ctype_digit($_GET['preview'])?(int)$_GET['preview']:0,(int)$client['id']);}
if(isset($_GET['proposal'])){if($client&&!contact_can('billing'))fail_page('Your contact permissions do not include proposals.',403);proposals_portal_render($error);}
if(isset($_GET['invoice'])||isset($_GET['receipt'])){if($client&&!contact_can('billing'))fail_page('Your contact permissions do not include billing.',403);ledger_portal_render();}
if(isset($_GET['services'])){if($client&&!contact_can('billing'))fail_page('Your contact permissions do not include billing.',403);provider_services_portal_render();}
if(isset($_GET['account'])&&$client){require CWP_CODE_ROOT.'/portal/views/account.php';exit;}
if(isset($_GET['notifications'])){if($client&&!contact_can('projects')&&!contact_can('billing')&&!contact_can('downloads'))fail_page('Your contact permissions do not include notifications.',403);notifications_portal_render($error);}
if(array_key_exists('tickets',$_GET)||array_key_exists('ticket',$_GET)||array_key_exists('ticket_new',$_GET)){if($client&&!contact_can('projects')&&!contact_can('billing')&&!contact_can('downloads'))fail_page('Your contact permissions do not include support tickets.',403);support_tickets_portal_render($client,$error);}
require CWP_CODE_ROOT.'/portal/views/auth.php';
if(array_key_exists('hosting_purchases',$_GET))hosting_purchases_portal_render($client);
if(array_key_exists('hosting',$_GET))hosting_catalog_portal_render($client,$error);
if($httpsRequestRoute)provider_force_ssl_requests_client_render($client,$error);
if($directoryRequestRoute)provider_directory_indexing_requests_client_render($client,$error);
if(array_key_exists('registration_drafts',$_GET))registration_drafts_client_render($client,$error);
if(array_key_exists('domains',$_GET))provider_domain_client_render($client,$error);
if(array_key_exists('hosting_mail',$_GET))provider_mailbox_client_render($client,$error);
if(array_key_exists('domain_dates',$_GET))domain_contracts_client_render($client);
if(array_key_exists('domain_expiry',$_GET))domain_expiry_client_render($client);
if(array_key_exists('domain_renewals',$_GET))domain_renewals_client_render($client,$error);
if(array_key_exists('nameserver_requests',$_GET))registered_nameserver_requests_client_render($client,$error);
if(array_key_exists('service_history',$_GET))service_observations_client_render($client);
if(array_key_exists('php_controls',$_GET))provider_php_controls_client_settings_render($client,$error,$phpControlsReview);
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
