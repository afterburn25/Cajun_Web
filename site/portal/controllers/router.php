<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }boot();require CWP_CODE_ROOT.'/includes/layout/public.php';require CWP_CODE_ROOT.'/app/marketing/growth.php';require CWP_CODE_ROOT.'/app/clients/workspace.php';require CWP_CODE_ROOT.'/includes/layout/admin.php';require CWP_CODE_ROOT.'/admin/controllers/support.php';require CWP_CODE_ROOT.'/includes/layout/ledger.php';require CWP_CODE_ROOT.'/includes/layout/deliveries.php';require CWP_CODE_ROOT.'/includes/layout/activity.php';require_once CWP_CODE_ROOT.'/includes/layout/provider_services.php';header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: no-referrer');
require_once CWP_CODE_ROOT.'/includes/layout/proposals.php';require_once CWP_CODE_ROOT.'/includes/layout/notifications.php';
require_once CWP_CODE_ROOT.'/app/clients/account_security.php';
$identityRoute='';$identityToken='';$identityKeys=[];
foreach(['register','verify','resend','forgot','reset'] as $key)if(array_key_exists($key,$_GET))$identityKeys[]=$key;
if($identityKeys){
    header('Cache-Control: no-store, private');
    if(count($identityKeys)!==1||array_key_exists('support',$_GET))fail_page('Invalid account security request.',400);
    $identityRoute=$identityKeys[0];$value=$_GET[$identityRoute];
    if(!is_string($value)||(in_array($identityRoute,['register','resend','forgot'],true)&&$value!=='1')||(in_array($identityRoute,['verify','reset'],true)&&!preg_match('/\A[a-f0-9]{64}\z/D',$value)))fail_page('Invalid account security request.',400);
    $allowed=[$identityRoute,'hosting','offer','offer_sha256','p'];
    if(array_diff(array_keys($_GET),$allowed))fail_page('Invalid account security request.',400);
    if(in_array($identityRoute,['verify','reset'],true))$identityToken=$value;
}
$accountRequested=array_key_exists('account',$_GET);
if($accountRequested||($_POST['action']??null)==='account_password'){
    header('Cache-Control: no-store, private');
    if(array_key_exists('support',$_GET))fail_page('Account security is unavailable during client review.',403);
}
if($accountRequested&&(($_GET['account']??null)!=='1'||array_diff(array_keys($_GET),['account'])))fail_page('Invalid account request.',400);
if(array_key_exists('proposal',$_GET)||(is_string($_POST['action']??null)&&$_POST['action']==='proposal_decide'))header('Cache-Control: no-store, private');
// License and financing links must reach their authenticated views, not the project fallback.
$softwareRequested=array_key_exists('software',$_GET);$financeRequested=array_key_exists('finance',$_GET);
if($softwareRequested||$financeRequested){
    header('Cache-Control: no-store, private');
    if(($softwareRequested&&$financeRequested)||($softwareRequested&&($_GET['software']??null)!=='1')
        ||($financeRequested&&(!is_string($_GET['finance'])||($_GET['finance']!=='1'&&!preg_match('/^[a-f0-9]{32}$/D',$_GET['finance']))))){
        fail_page('Invalid software or financing request.',400);
    }
}
require_once CWP_CODE_ROOT.'/portal/controllers/tickets.php';
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
require_once CWP_CODE_ROOT.'/portal/controllers/hosting_catalog.php';
require_once CWP_CODE_ROOT.'/portal/controllers/hosting_purchases.php';
if(array_key_exists('hosting_purchases',$_GET))header('Cache-Control: no-store, private');
if(array_key_exists('hosting',$_GET)||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'hosting_catalog_')))header('Cache-Control: no-store, private');
require_once CWP_CODE_ROOT.'/portal/controllers/recurring_billing.php';
if(array_key_exists('recurring',$_GET)||(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'recurring_')))header('Cache-Control: no-store, private');
if(array_key_exists('agreements',$_GET) || array_key_exists('agreement',$_GET) || (is_string($_POST['action']??null) && str_starts_with($_POST['action'],'service_agreements_')))header('Cache-Control: no-store, private');
if(array_key_exists('domains',$_GET)||($_POST['action']??null)==='client_domain_search')header('Cache-Control: no-store, private');
$error='';$supportRequested=array_key_exists('support',$_GET);$supportId=is_string($_GET['support']??null)?$_GET['support']:'';
if($supportRequested)header('Cache-Control: no-store, private');
if($supportRequested){
 if($_SERVER['REQUEST_METHOD']==='POST'&&in_array($_POST['action']??null,['support_end','support_switch'],true)){
  try{$action=$_POST['action'];support_check_csrf($supportId);support_end($supportId);redirect($action==='support_switch'?master_portal_url():admin_url('support'));}catch(Throwable){support_unavailable();}
 }
 $context=support_resolve($supportId);if(!$context)support_unavailable();$GLOBALS['cwp_support']=$context;
}
if(array_key_exists('hosting_purchases',$_GET)){try{hosting_purchases_portal_access();}catch(RuntimeException){fail_page('Sign in as the primary client outside support access to view hosting purchases.',403);}}
master_portal_dispatch();
// Preserve checkout contact provenance before identity normalization can discard stale keys.
if(!$supportRequested&&!support_current()&&(
    (($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&($_GET['hosting']??null)==='checkout')
    ||(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&in_array($_POST['action']??null,['hosting_catalog_checkout_preview','hosting_catalog_checkout_confirm','hosting_catalog_checkout_pay'],true))
)){
    require_once CWP_CODE_ROOT.'/app/hosting/catalog_checkout.php';
    try{hosting_checkout_reject_contact();}catch(RuntimeException $e){fail_page($e->getMessage(),403);}
}
$client=studio_client();
if($client&&$identityRoute!=='')redirect(studio_url(client_identity_destination($_GET)));
$jobId=isset($_GET['job'])&&is_string($_GET['job'])?(int)$_GET['job']:0;$invite=is_string($_GET['invite']??null)?$_GET['invite']:'';$cinvite=is_string($_GET['cinvite']??null)?$_GET['cinvite']:'';
require CWP_CODE_ROOT.'/portal/controllers/actions.php';
if(isset($_GET['activity'])){if($client&&!contact_can('projects'))fail_page('Your contact permissions do not include project access.',403);activity_render('client',$error);}
if(isset($_GET['download_release'])){if($client&&!contact_can('downloads'))fail_page('Your contact permissions do not include downloads.',403);delivery_download(is_string($_GET['download_release'])?$_GET['download_release']:'');}
if(isset($_GET['downloads'])||isset($_GET['release'])){if($client&&!contact_can('downloads'))fail_page('Your contact permissions do not include downloads.',403);delivery_portal_render();}
if(isset($_GET['file'])){if(!$client)fail_page('Sign in to view this file.',403);if(!contact_can('downloads'))fail_page('Your contact permissions do not include downloads.',403);studio_download(is_string($_GET['file'])?$_GET['file']:'',(int)$client['id']);}
if(isset($_GET['invoice'])||isset($_GET['receipt'])){if($client&&!contact_can('billing'))fail_page('Your contact permissions do not include billing.',403);ledger_portal_render();}
if(isset($_GET['proposal']))proposals_portal_render($error);
if(array_key_exists('notifications',$_GET))notification_center_portal_render($error);
if(isset($_GET['services'])){if($client&&!contact_can('billing'))fail_page('Your contact permissions do not include billing.',403);provider_services_portal_render();}
if(array_key_exists('tickets',$_GET)||array_key_exists('ticket',$_GET)||array_key_exists('ticket_new',$_GET))support_tickets_portal_render($client,$error);
require CWP_CODE_ROOT.'/portal/views/auth.php';
if($accountRequested){require CWP_CODE_ROOT.'/portal/views/account.php';}
if($softwareRequested){require_once CWP_CODE_ROOT.'/portal/views/software.php';software_portal_render($client,$error);}
if($financeRequested){require_once CWP_CODE_ROOT.'/portal/views/financing.php';license_finance_portal_render($client,$_GET['finance'],$error);}

if(array_key_exists('hosting_purchases',$_GET))hosting_purchases_portal_render($client);
if(array_key_exists('hosting',$_GET))hosting_catalog_portal_render($client,$error);
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
