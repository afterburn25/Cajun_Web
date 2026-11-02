<?php
/** Actual account service with explicit in-memory transaction/session dependencies; no database. */
declare(strict_types=1);
define('CWP_BOOT',true);$fixture=sys_get_temp_dir().'/cwp-account-policy-'.bin2hex(random_bytes(8));mkdir($fixture.'/app/clients',0700,true);mkdir($fixture.'/sessions',0700);file_put_contents($fixture.'/app/clients/workspace.php','<?php // Explicit test dependencies supplied below.');define('CWP_CODE_ROOT',$fixture);
session_save_path($fixture.'/sessions');session_start();$checks=[];$locked=false;$transaction=false;$hook=null;$auditFail=false;$readCount=0;
final class AccountPolicyDb {public function inTransaction():bool{return $GLOBALS['transaction'];}}
function db():AccountPolicyDb{return new AccountPolicyDb();}
function support_current():mixed{return $GLOBALS['support']?['synthetic'=>true]:null;}
function studio_client():?array{
    $GLOBALS['readCount']++;
    if(empty($_SESSION['client_id'])||time()-(int)($_SESSION['client_last']??0)>1800||time()-(int)($_SESSION['client_login']??0)>28800)return null;
    $row=$GLOBALS['rows']['clients'][(int)$_SESSION['client_id']]??null;
    if(!$row||!$row['active']||!hash_equals($row['auth_version'],(string)($_SESSION['client_auth']??'')))return null;
    return $row;
}
function validate_password(string $value):void{if(strlen($value)<14||strlen($value)>72||str_contains($value,"\0"))throw new InvalidArgumentException('Use a password between 14 and 72 bytes.');}
function staff_locked(callable $work):mixed{$GLOBALS['locked']=true;try{if($GLOBALS['hook'])($GLOBALS['hook'])();return $work();}finally{$GLOBALS['locked']=false;}}
function staff_transaction(callable $work):mixed{if(!$GLOBALS['locked']||$GLOBALS['transaction'])throw new LogicException('Incorrect transaction boundary');$before=$GLOBALS['rows'];$GLOBALS['transaction']=true;try{return $work();}catch(Throwable $e){$GLOBALS['rows']=$before;throw $e;}finally{$GLOBALS['transaction']=false;}}
function table(string $name):string{return '`policy_'.$name.'`';}
function one(string $sql,array $args=[]):?array{if(!$GLOBALS['locked']||!$GLOBALS['transaction']||$sql!=='SELECT * FROM `policy_studio_clients` WHERE id=? AND active=1 FOR UPDATE')throw new LogicException('Incorrect locked read');return $GLOBALS['rows']['clients'][$args[0]]??null;}
function query(string $sql,array $args=[]):void{if(!$GLOBALS['locked']||!$GLOBALS['transaction']||$sql!=='UPDATE `policy_studio_clients` SET password_hash=?,auth_version=?,invite_hash=NULL,invite_expires=NULL WHERE id=?')throw new LogicException('Incorrect credential write');[$hash,$version,$id]=$args;$GLOBALS['rows']['clients'][$id]=array_replace($GLOBALS['rows']['clients'][$id],['password_hash'=>$hash,'auth_version'=>$version,'invite_hash'=>null,'invite_expires'=>null]);}
function studio_client_audit(int $id,string $action,int $client):void{if($GLOBALS['auditFail'])throw new RuntimeException('Synthetic audit failure');$GLOBALS['rows']['audit'][]=[$id,$action,$client];}
function reset_account():void{
    $GLOBALS['rows']=['clients'=>[1=>['id'=>1,'active'=>1,'auth_version'=>str_repeat('a',32),'password_hash'=>password_hash('Synthetic account old passphrase',PASSWORD_DEFAULT),'invite_hash'=>str_repeat('d',64),'invite_expires'=>time()+3600],2=>['id'=>2,'active'=>1,'auth_version'=>str_repeat('b',32),'password_hash'=>'unrelated','invite_hash'=>null,'invite_expires'=>null]],'audit'=>[]];
    $_SESSION=['client_id'=>1,'client_auth'=>str_repeat('a',32),'client_last'=>time(),'client_login'=>time()-100,'uid'=>7,'auth_version'=>'untouched-admin','csrf'=>'old-csrf'];$_GET=[];$GLOBALS['support']=false;$GLOBALS['hook']=null;$GLOBALS['auditFail']=false;$GLOBALS['readCount']=0;
}
function ap_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new LogicException($name);}
function ap_deny(string $name,callable $work,string $message):void{$before=$GLOBALS['rows'];$error=null;try{$work();}catch(Throwable $e){$error=$e;}ap_ok($name.' rejects at the intended boundary',($error instanceof RuntimeException||$error instanceof InvalidArgumentException)&&str_contains($error->getMessage(),$message));ap_ok($name.' preserves exact records and transaction state',$GLOBALS['rows']===$before&&!$GLOBALS['transaction']&&!$GLOBALS['locked']);}
require __DIR__.'/../site/app/clients/account_security.php';
try{
    $old='Synthetic account old passphrase';$new='Synthetic account new passphrase';
    foreach(['anonymous','idle','absolute','revoked','disabled','support','selector','contact-id','contact-auth','administrator-only'] as $mode){reset_account();$expected='session ended';switch($mode){case 'anonymous':unset($_SESSION['client_id']);break;case 'idle':$_SESSION['client_last']=time()-1801;break;case 'absolute':$_SESSION['client_login']=time()-28801;break;case 'revoked':$_SESSION['client_auth']='revoked';break;case 'disabled':$GLOBALS['rows']['clients'][1]['active']=0;break;case 'support':$GLOBALS['support']=true;$expected='client review';break;case 'selector':$_GET['support']='invalid';$expected='client review';break;case 'contact-id':$_SESSION['contact_id']=0;$expected='primary client';break;case 'contact-auth':$_SESSION['contact_auth']='';$expected='primary client';break;case 'administrator-only':$_SESSION=['uid'=>7,'auth_version'=>'admin'];break;}ap_deny($mode,fn()=>studio_account_password_change($old,$new,$new),$expected);}
    foreach(['wrong current'=>['wrong',$new,$new,'current password'],'mismatch'=>[$old,$new,$new.'x','do not match'],'short'=>[$old,'small','small','14 and 72'],'oversize'=>[$old,str_repeat('a',73),str_repeat('a',73),'14 and 72'],'nul'=>[$old,"Synthetic nul\0 value","Synthetic nul\0 value",'14 and 72'],'same'=>[$old,$old,$old,'different']] as $name=>[$c,$p,$confirm,$message]){reset_account();ap_deny($name,fn()=>studio_account_password_change($c,$p,$confirm),$message);}
    reset_account();$GLOBALS['auditFail']=true;ap_deny('audit failure',fn()=>studio_account_password_change($old,$new,$new),'Synthetic audit');
    foreach(['revoked','support','expired','identity-switch'] as $mode){reset_account();$GLOBALS['hook']=function()use($mode){if($mode==='revoked')$_SESSION['client_auth']='revoked';elseif($mode==='support')$_GET['support']='late';elseif($mode==='expired')$_SESSION['client_last']=time()-1801;else{$_SESSION['client_id']=2;$_SESSION['client_auth']=str_repeat('b',32);}};$m=$mode==='support'?'client review':($mode==='identity-switch'?'session changed':'session ended');ap_deny('lock-time '.$mode,fn()=>studio_account_password_change($old,$new,$new),$m);}
    reset_account();$before=$GLOBALS['rows'];$sid=session_id();$csrf=$_SESSION['csrf'];$absolute=$_SESSION['client_login'];studio_account_password_change($old,$new,$new);$row=$GLOBALS['rows']['clients'][1];
    ap_ok('New password is hashed and old password no longer verifies',password_verify($new,$row['password_hash'])&&!password_verify($old,$row['password_hash'])&&$row['password_hash']!==$new);
    ap_ok('Only current primary customer changes',$GLOBALS['rows']['clients'][2]===$before['clients'][2]&&$_SESSION['uid']===7&&$_SESSION['auth_version']==='untouched-admin');
    ap_ok('Recovery invitation revoked atomically',$row['invite_hash']===null&&$row['invite_expires']===null);
    ap_ok('Client auth, session ID and CSRF rotate',$row['auth_version']!==$before['clients'][1]['auth_version']&&$_SESSION['client_auth']===$row['auth_version']&&session_id()!==$sid&&$_SESSION['csrf']!==$csrf);
    ap_ok('Current session remains valid without extending absolute lifetime',studio_client()!==null&&$_SESSION['client_login']===$absolute);
    ap_ok('Audit is minimal and customer-attributed',$GLOBALS['rows']['audit']===[[0,'client.password_change',1]]);
    $_SESSION['client_auth']=$before['clients'][1]['auth_version'];ap_ok('Other old sessions fail existing authentication',studio_client()===null);
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
session_write_close();foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($fixture);
echo json_encode(['passed'=>!isset($failure),'scope'=>'Actual account service with strict synthetic SQL/transaction dependencies and actual PHP sessions; not native database acceptance','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;exit(isset($failure)?1:0);
