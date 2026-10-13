<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function studio_client(): ?array {
 if($ctx=support_current())return $ctx['client'];
 if(!studio_ready()||empty($_SESSION['client_id']))return null;
 if(time()-(int)($_SESSION['client_last']??0)>1800||time()-(int)($_SESSION['client_login']??0)>28800){unset($_SESSION['client_id']);return null;}
 $c=one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1',[(int)$_SESSION['client_id']]);
 if(!$c||!hash_equals($c['auth_version'],(string)($_SESSION['client_auth']??''))){unset($_SESSION['client_id']);return null;}$_SESSION['client_last']=time();return $c;
}

function studio_new_invite(int $id): string {require_admin();if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$id]))throw new InvalidArgumentException('Active client not found.');$token=bin2hex(random_bytes(32));query('UPDATE '.table('studio_clients').' SET invite_hash=?,invite_expires=?,auth_version=? WHERE id=?',[hash('sha256',$token),time()+86400,bin2hex(random_bytes(16)),$id]);return studio_url(['invite'=>$token]);}

function studio_activate(string $token,string $password): void {
 if(support_current())throw new RuntimeException('Client security changes are blocked during support.');
 validate_password($password);if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new InvalidArgumentException('Invalid invitation.');
 db()->beginTransaction();try{$c=one('SELECT * FROM '.table('studio_clients').' WHERE invite_hash=? AND active=1 FOR UPDATE',[hash('sha256',$token)]);if(!$c||(int)$c['invite_expires']<=time())throw new InvalidArgumentException('This invitation expired or has already been used. Ask the studio for a new link.');$hash=password_hash($password,PASSWORD_DEFAULT);if((int)$c['invite_expires']<=time())throw new InvalidArgumentException('This invitation expired. Ask the studio for a new link.');query('UPDATE '.table('studio_clients').' SET password_hash=?,auth_version=?,invite_hash=NULL,invite_expires=NULL WHERE id=?',[$hash,bin2hex(random_bytes(16)),$c['id']]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}

function studio_login(string $email,string $password): void {
 if(support_current())throw new RuntimeException('Client login is blocked during support.');
 $allow1=consume_rate(ip_key('client-login'),15,900);$allow2=consume_rate(hash_hmac('sha256','client|'.$email,config()['key']),8,900);if(!$allow1||!$allow2)throw new RuntimeException('Too many attempts. Wait 15 minutes.');
 $c=one('SELECT * FROM '.table('studio_clients').' WHERE email=? AND active=1',[$email]);$hash=$c['password_hash']??'$2y$12$k41gRE1TEYzHWgNYkc/yDuDTWYfPr6HvCaDvQfmUTDpAWPSgwOI6u';$ok=password_verify($password,$hash);if(!$c||!$ok)throw new InvalidArgumentException('Email or password not recognized.');session_regenerate_id(true);$_SESSION['client_id']=(int)$c['id'];$_SESSION['client_auth']=$c['auth_version'];$_SESSION['client_last']=$_SESSION['client_login']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));
}
/** Decision attached to an immutable version, with a project row lock shared by publication. */
