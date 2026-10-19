<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{if($supportRequested)support_check_csrf($supportId);else check_csrf();if(!studio_ready())throw new RuntimeException('The client workspace has not been enabled. Please contact the studio.');$action=posted('action',40);
 if(support_current()){
  if(str_starts_with($action,'client_tickets_'))support_tickets_portal_action($action,$client);
  if(!in_array($action,['message','upload'],true)){support_record('action.blocked','portal','','denied');throw new RuntimeException('This action is blocked during support. Client decisions, payments and security changes require the proper independent workflow.');}
  if(!consume_rate('support-action-'.support_current()['actor']['id'],30,3600))throw new RuntimeException('Please wait before making more support changes.');
   if($action==='message')support_message($supportId,$jobId,posted('body',5000));else support_upload($supportId,$jobId);
  flash('Saved as Studio support.');redirect(studio_url(['job'=>$jobId]));
 }
 elseif(str_starts_with($action,'hosting_catalog_')){if(!$client)throw new RuntimeException('Sign in to continue.');hosting_catalog_portal_action($action,$client);}
 elseif(str_starts_with($action,'registration_drafts_')){if(!$client)throw new RuntimeException('Sign in to continue.');registration_drafts_client_action($action,$client);}
 elseif(str_starts_with($action,'forwarding_')){if(!$client)throw new RuntimeException('Sign in to continue.');provider_forwarding_client_action($action,$client);}
 elseif(str_starts_with($action,'nameserver_request_')){if(!$client)throw new RuntimeException('Sign in to continue.');registered_nameserver_requests_client_action($action,$client);}
 elseif(str_starts_with($action,'domain_renewal_')){if(!$client)throw new RuntimeException('Sign in to continue.');domain_renewals_client_action($action,$client);}
 elseif($action==='client_mailbox_create'){if(!$client)throw new RuntimeException('Sign in to continue.');provider_mailbox_client_action($client);}
 elseif($action==='client_domain_search'){if(!$client)throw new RuntimeException('Sign in to continue.');provider_domain_client_action($client);}
 elseif($action==='financial_decide'){if(!$client)throw new RuntimeException('Sign in to continue.');design_financial_client_action($client,$jobId);}
 elseif(str_starts_with($action,'recurring_')){if(!$client)throw new RuntimeException('Sign in to continue.');recurring_ui_action($action,(int)$client['id']);}
 elseif(str_starts_with($action,'service_agreements_')){if(!$client)throw new RuntimeException('Sign in to continue.');service_plans_portal_action($action,$client);}
 elseif(str_starts_with($action,'client_tickets_')){if(!$client)throw new RuntimeException('Sign in to continue.');if(studio_contact()&&!contact_can('projects')&&!contact_can('billing')&&!contact_can('downloads'))throw new RuntimeException('Your contact permissions do not include support tickets.');support_tickets_portal_action($action,$client);}
 elseif($action==='financial_decide'){if(!$client)throw new RuntimeException('Sign in to continue.');design_financial_client_action($client,$jobId);}
 elseif(in_array($action,['activity_mark','activity_prefs'],true)){if(!contact_can('projects')&&!contact_can('billing')&&!contact_can('downloads'))throw new RuntimeException('Your contact permissions do not include workspace activity.');activity_post('client');}
 elseif($action==='activate'){$password=posted('password',72);if($password!==posted('confirm_password',72))throw new InvalidArgumentException('Passwords do not match.');if(!consume_rate(ip_key('client-activate'),10,900))throw new RuntimeException('Please wait before trying again.');studio_activate(is_string($_POST['invite']??null)?$_POST['invite']:'',$password);flash('Your password is saved. Sign in below.');redirect(studio_url());}
 elseif($action==='contact_activate'){$password=posted('password',72);if($password!==posted('confirm_password',72))throw new InvalidArgumentException('Passwords do not match.');if(!consume_rate(ip_key('client-activate'),10,900))throw new RuntimeException('Please wait before trying again.');contact_activate(is_string($_POST['cinvite']??null)?$_POST['cinvite']:'',$password);flash('Your password is saved. Sign in below.');redirect(studio_url());}
 elseif($action==='login'){studio_login(strtolower(posted('email',190)),posted('password',1000));require_once CWP_CODE_ROOT.'/app/hosting/catalog_login.php';redirect(studio_url(hosting_catalog_login_destination($_GET)));}
 elseif($action==='logout'){unset($_SESSION['client_id'],$_SESSION['client_auth'],$_SESSION['client_last'],$_SESSION['client_login'],$_SESSION['contact_id'],$_SESSION['contact_auth']);session_regenerate_id(true);redirect(studio_url());}
 else{if(!$client)throw new RuntimeException('Sign in to continue.');
 if($action==='license_domain_add'){if(!contact_can('billing'))throw new RuntimeException('Your contact permissions do not include billing.');$licenseId=gint($_POST,'license_id',1,2147483647);$domain=posted('domain',255);require_once CWP_CODE_ROOT.'/app/licenses/service.php';
  if(!license_ready())throw new RuntimeException('Licensing storage is not prepared yet.');
  if(!consume_rate(ip_key('client-license'),20,3600))throw new RuntimeException('Please wait before making more license changes.');
  if(!one('SELECT id FROM '.table('website_licenses').' WHERE id=? AND customer_id=? AND is_master=0',[$licenseId,$client['id']]))throw new InvalidArgumentException('License not found.');
  license_register_domain($licenseId,$domain,(int)$client['id']);flash('Domain registered.');redirect(studio_url());}
 elseif($action==='license_domain_deactivate'){if(!contact_can('billing'))throw new RuntimeException('Your contact permissions do not include billing.');$licenseId=gint($_POST,'license_id',1,2147483647);$domain=posted('domain',255);require_once CWP_CODE_ROOT.'/app/licenses/service.php';
  if(!license_ready())throw new RuntimeException('Licensing storage is not prepared yet.');
  if(!consume_rate(ip_key('client-license'),20,3600))throw new RuntimeException('Please wait before making more license changes.');
  if(!one('SELECT id FROM '.table('website_licenses').' WHERE id=? AND customer_id=? AND is_master=0',[$licenseId,$client['id']]))throw new InvalidArgumentException('License not found.');
  license_deactivate_domain($licenseId,$domain,(int)$client['id']);flash('Domain deactivated.');redirect(studio_url());}
 elseif($action==='license_domain_replace'){if(!contact_can('billing'))throw new RuntimeException('Your contact permissions do not include billing.');$licenseId=gint($_POST,'license_id',1,2147483647);$old=posted('old_domain',255);$new=posted('new_domain',255);require_once CWP_CODE_ROOT.'/app/licenses/service.php';
  if(!license_ready())throw new RuntimeException('Licensing storage is not prepared yet.');
  if(!consume_rate(ip_key('client-license'),20,3600))throw new RuntimeException('Please wait before making more license changes.');
  if(!one('SELECT id FROM '.table('website_licenses').' WHERE id=? AND customer_id=? AND is_master=0',[$licenseId,$client['id']]))throw new InvalidArgumentException('License not found.');
  license_replace_domain($licenseId,$old,$new,(int)$client['id']);flash('Domain replaced — the old site goes offline at its next license check.');redirect(studio_url());}
 elseif($action==='account_password'){$contact=studio_contact();$current=posted('current_password',72);$new=posted('password',72);if($new!==posted('confirm_password',72))throw new InvalidArgumentException('Passwords do not match.');if(!consume_rate(ip_key('client-password'),10,900))throw new RuntimeException('Please wait before trying again.');studio_password_change((bool)$contact,$current,$new);flash('Your password is updated.');redirect(studio_url(['account'=>1]));}
 if(in_array($action,['notification_client_mark','notification_client_mark_all','notification_client_prefs'],true)&&studio_contact()&&!contact_can('projects')&&!contact_can('billing')&&!contact_can('downloads'))throw new RuntimeException('Your contact permissions do not include notifications.');
 if($action==='notification_client_mark'){notification_mark('client',(int)$client['id'],posted('id',32),posted('read',1)==='1');flash('Saved.');redirect(studio_url(['notifications'=>1]));}
 elseif($action==='notification_client_mark_all'){notification_mark_all('client',(int)$client['id']);flash('All notifications marked read.');redirect(studio_url(['notifications'=>1]));}
 elseif($action==='notification_client_prefs'){notification_prefs_save('client',(int)$client['id'],(string)$client['id']);flash('Email preferences saved.');redirect(studio_url(['notifications'=>1]));}
 if($action==='proposal_decide'){if($contact&&!contact_can('billing'))throw new RuntimeException('Your contact permissions do not include proposals.');if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm your decision applies to this exact version.');if(!consume_rate(ip_key('client-proposal'),20,3600))throw new RuntimeException('Please wait before recording another decision.');proposal_decide(posted('proposal_id',32),posted('choice',20),posted('note',2000),$client);flash('Your decision was recorded against that exact proposal version.');redirect(studio_url());}
 $job=studio_job($jobId,(int)$client['id']);$contact=studio_contact();
 if($action==='message'){if($contact&&!contact_can('projects'))throw new RuntimeException('Your contact permissions do not include project access.');$body=posted('body',5000);if(strlen($body)<2)throw new InvalidArgumentException('Write a message.');if(!consume_rate(ip_key('client-message'),30,3600))throw new RuntimeException('Please wait before sending more messages.');query('INSERT INTO '.table('studio_messages').' (job_id,author_type,author_id,body,created_at) VALUES (?,?,?,?,?)',[$jobId,$contact?'contact':'client',$contact?(int)$contact['id']:$client['id'],$body,gmdate('Y-m-d H:i:s')]);flash('Message saved.');}
 elseif($action==='decision'){if($contact&&!contact_can('projects'))throw new RuntimeException('Your contact permissions do not include project access.');if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm the decision for this version.');$version=(int)posted('version',12);if(!one('SELECT id FROM '.table('studio_versions').' WHERE id=? AND job_id=?',[$version,$jobId]))throw new InvalidArgumentException('Review not found.');studio_decide((int)$client['id'],$version,posted('choice',20),posted('feedback',5000));flash('Your decision was recorded against that version.');}
 elseif($action==='upload'){if($contact&&!contact_can('projects'))throw new RuntimeException('Your contact permissions do not include project access.');if(!consume_rate(ip_key('client-upload'),10,3600))throw new RuntimeException('Upload limit reached.');studio_upload($jobId,$contact?'contact':'client');flash('File saved privately.');}
 else throw new InvalidArgumentException('Unknown action.');redirect(studio_url(['job'=>$jobId]));}
 }catch(PDOException $e){$error='Request could not be recorded. Reference: '.report_error($e);}catch(InvalidArgumentException|RuntimeException $e){$error=$e->getMessage();}catch(Throwable $e){$error='Request failed. Reference: '.report_error($e);}
}
