<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function studio_client(): ?array {
 if($ctx=support_current())return $ctx['client'];
 if(!studio_ready()||empty($_SESSION['client_id']))return null;
 if(time()-(int)($_SESSION['client_last']??0)>1800||time()-(int)($_SESSION['client_login']??0)>28800){unset($_SESSION['client_id']);return null;}
 $c=one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1',[(int)$_SESSION['client_id']]);
 if(!$c||!hash_equals($c['auth_version'],(string)($_SESSION['client_auth']??''))){unset($_SESSION['client_id']);return null;}
 if(!empty($_SESSION['contact_id'])){
  $ct=contacts_ready()?one('SELECT * FROM '.table('studio_contacts').' WHERE id=? AND client_id=? AND enabled=1',[(int)$_SESSION['contact_id'],(int)$c['id']]):null;
  if(!$ct||!hash_equals($ct['auth_version'],(string)($_SESSION['contact_auth']??''))){unset($_SESSION['client_id'],$_SESSION['contact_id'],$_SESSION['contact_auth']);return null;}
 }
 $_SESSION['client_last']=time();return $c;
}

require_once CWP_CODE_ROOT.'/app/clients/accounts.php';
require_once CWP_CODE_ROOT.'/app/clients/contacts.php';

function studio_login(string $email,string $password): void {
 if(support_current())throw new RuntimeException('Client login is blocked during support.');
 $allow1=consume_rate(ip_key('client-login'),15,900);$allow2=consume_rate(hash_hmac('sha256','client|'.$email,config()['key']),8,900);if(!$allow1||!$allow2)throw new RuntimeException('Too many attempts. Wait 15 minutes.');
 $c=one('SELECT * FROM '.table('studio_clients').' WHERE email=? AND active=1',[$email]);$contact=null;
 if(!$c&&contacts_ready()){
  $contact=one('SELECT * FROM '.table('studio_contacts').' WHERE email=? AND enabled=1',[$email]);
  if($contact)$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1',[(int)$contact['client_id']]);
  if(!$c)$contact=null;
 }
 $row=$contact?:$c;$hash=$row['password_hash']??'$2y$12$k41gRE1TEYzHWgNYkc/yDuDTWYfPr6HvCaDvQfmUTDpAWPSgwOI6u';$ok=password_verify($password,$hash);if(!$row||!$ok)throw new InvalidArgumentException('Email or password not recognized.');session_regenerate_id(true);$_SESSION['client_id']=(int)$c['id'];$_SESSION['client_auth']=$c['auth_version'];if($contact){$_SESSION['contact_id']=(int)$contact['id'];$_SESSION['contact_auth']=$contact['auth_version'];}$_SESSION['client_last']=$_SESSION['client_login']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));
}
/** Self-service credential change: re-reads the active actor, contact parent and expected authentication versions under the shared account lock, verifies the current password against the fresh row, and commits the credential and audit atomically before rotating this session. */
function studio_password_change(bool $isContact,#[\SensitiveParameter] string $current,#[\SensitiveParameter] string $new): void {
 validate_password($new);
 $version=staff_locked(fn()=>staff_transaction(function()use($isContact,$current,$new):string {
  if($isContact){
   $contact=one('SELECT * FROM '.table('studio_contacts').' WHERE id=? AND enabled=1 FOR UPDATE',[(int)($_SESSION['contact_id']??0)]);
   $client=$contact?one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1 FOR UPDATE',[(int)$contact['client_id']]):null;
   if(!$contact||!$client||!hash_equals((string)$contact['auth_version'],(string)($_SESSION['contact_auth']??''))||!hash_equals((string)$client['auth_version'],(string)($_SESSION['client_auth']??'')))throw new RuntimeException('Your sign-in session ended. Please sign in again.');
   $actor=$contact;$auditClient=(int)$client['id'];
  }else{
   $actor=one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1 FOR UPDATE',[(int)($_SESSION['client_id']??0)]);
   if(!$actor||!hash_equals((string)$actor['auth_version'],(string)($_SESSION['client_auth']??'')))throw new RuntimeException('Your sign-in session ended. Please sign in again.');
   $auditClient=(int)$actor['id'];
  }
  if(!password_verify($current,(string)$actor['password_hash']))throw new InvalidArgumentException('Your current password is not correct.');
  $version=bin2hex(random_bytes(16));
  query('UPDATE '.table($isContact?'studio_contacts':'studio_clients').' SET password_hash=?,auth_version=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$version,(int)$actor['id']]);
  studio_client_audit(0,'password_change',$auditClient);
  return $version;
 }));
 if($isContact)$_SESSION['contact_auth']=$version;else $_SESSION['client_auth']=$version;
 session_regenerate_id(true);
}
/** Decision attached to an immutable version, with a project row lock shared by publication. */
