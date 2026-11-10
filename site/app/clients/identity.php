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
require_once CWP_CODE_ROOT.'/app/clients/public_identity.php';

function studio_login(string $email,string $password): void {
 if(support_current())throw new RuntimeException('Client login is blocked during support.');
 $email=client_identity_email($email);$allow1=consume_rate(ip_key('client-login'),15,900);$allow2=consume_rate(hash_hmac('sha256','client|'.$email,config()['key']),8,900);if(!$allow1||!$allow2)throw new RuntimeException('Too many attempts. Wait 15 minutes.');
 $namespace=client_identity_namespace($email);$c=null;$contact=null;
 if(!$namespace['ambiguous']){
  if($namespace['primary']&&(int)$namespace['primary']['active']===1)$c=$namespace['primary'];
  elseif($namespace['contact']&&(int)$namespace['contact']['enabled']===1){$contact=$namespace['contact'];$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1',[(int)$contact['client_id']]);if(!$c)$contact=null;}
 }
 $row=$contact?:$c;$hash=$row['password_hash']??'$2y$12$k41gRE1TEYzHWgNYkc/yDuDTWYfPr6HvCaDvQfmUTDpAWPSgwOI6u';$ok=password_verify($password,$hash);
 if(!$row||!$ok||(!$contact&&client_identity_pending((int)$c['id'])))throw new InvalidArgumentException('Email or password not recognized.');
 client_identity_session_start($c,$contact);
}
/** Decision attached to an immutable version, with a project row lock shared by publication. */
