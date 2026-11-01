<?php
/** Disposable loopback MySQL/MariaDB self-service credentials and competing-session acceptance. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require __DIR__.'/../site/app/clients/account_security.php';
$private=__DIR__.'/../test-runtime/account-native-private';if(!is_dir($private))mkdir($private,0700,true);if(!is_dir(__DIR__.'/../qa/account-security'))mkdir(__DIR__.'/../qa/account-security',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('account-synthetic-',4),'db'=>['host'=>'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER'),'pass'=>getenv('CWP_TEST_PASSWORD'),'prefix'=>'accsec_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();$checks=[];$old='Synthetic account original passphrase';$new='Synthetic account updated passphrase';
function an_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function an_session(int $id=1):void{$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_last'=>time(),'client_login'=>time()-120,'csrf'=>bin2hex(random_bytes(32))];$_GET=[];unset($GLOBALS['cwp_support']);}
function an_state():array{
 $out=[];
 foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(TABLE_NAME,7)=? ORDER BY TABLE_NAME',['accsec_']) as $item){$name=$item['TABLE_NAME'];if(!preg_match('/^accsec_[a-zA-Z0-9_]+$/D',$name))throw new LogicException('Unsafe fixture namespace');$rows=array_map('serialize',all('SELECT * FROM `'.$name.'`'));sort($rows);$out[$name]=hash('sha256',serialize($rows));}
 return $out;
}
function an_deny(string $name,callable $work,string $message):void{$before=an_state();$failure=null;try{$work();}catch(Throwable $e){$failure=$e;}an_ok($name.' is refused at its actual boundary',($failure instanceof InvalidArgumentException||$failure instanceof RuntimeException)&&!($failure instanceof PDOException)&&str_contains($failure->getMessage(),$message));an_ok($name.' preserves every fixture table and closes its transaction',an_state()===$before&&!db()->inTransaction());}
function an_worker(string $name,array $session):array{
 global $private;$path=$private.'/worker-'.$name.'.json';file_put_contents($path,json_encode($session));
 $p=proc_open([PHP_BINARY,__FILE__,'worker',$name],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 if(!is_resource($p))throw new RuntimeException('Cannot start native account worker');foreach([1,2] as $i)stream_set_timeout($pipes[$i],20);
 for($i=0;$i<100&&!is_file($private.'/ready-'.$name);$i++)usleep(20000);
 if(!is_file($private.'/ready-'.$name))throw new RuntimeException('Account worker did not initialize');return [$p,$pipes];
}
function an_finish(array $worker):string{[$p,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0||$err!=='')throw new RuntimeException('Account worker failed: '.$err);return trim($out);}
if(($argv[1]??'')==='worker'){
 $name=$argv[2]??'';if(!preg_match('/^[a-z0-9]+$/D',$name))exit(2);$_SESSION=json_decode(file_get_contents($private.'/worker-'.$name.'.json'),true,16,JSON_THROW_ON_ERROR);file_put_contents($private.'/ready-'.$name,'ready');
 try{studio_account_password_change($old,$new,$new);$result='changed';}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$result=str_contains($e->getMessage(),'session')?'revoked':'unexpected';}
 session_write_close();echo $result;exit($result==='unexpected'?1:0);
}
try{
 foreach(schema_statements('accsec_') as $sql)db()->exec($sql);foreach(default_settings() as $key=>$value)save_setting($key,$value);save_setting('studio_schema_version','2');save_setting('email_mode','disabled');
 foreach([1,2] as $id)query('INSERT INTO '.table('studio_clients').' (id,name,email,password_hash,auth_version,active,invite_hash,invite_expires,created_at) VALUES (?,?,?,?,?,1,?,?,?)',[$id,'Synthetic client '.$id,'account'.$id.'@example.invalid',password_hash($old,PASSWORD_DEFAULT),str_repeat((string)$id,32),str_repeat('d',64),time()+3600,gmdate('Y-m-d H:i:s')]);
 query('INSERT INTO '.table('users').' (id,name,email,password_hash,auth_version,created_at) VALUES (7,?,?,?,?,?)',['Unrelated administrator','admin@example.invalid',password_hash('Unrelated administrator passphrase',PASSWORD_DEFAULT),str_repeat('a',32),gmdate('Y-m-d H:i:s')]);staff_seed_owner(7);
 an_session();$base=an_state();
 foreach(['anonymous','idle','absolute','revoked','disabled','support','selector','contact','contact-auth','admin-only'] as $mode){an_session();$message='session ended';switch($mode){case 'anonymous':$_SESSION=[];break;case 'idle':$_SESSION['client_last']=time()-1801;break;case 'absolute':$_SESSION['client_login']=time()-28801;break;case 'revoked':$_SESSION['client_auth']='revoked';break;case 'disabled':query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');break;case 'support':$GLOBALS['cwp_support']=['client'=>one('SELECT * FROM '.table('studio_clients').' WHERE id=1')];$message='client review';break;case 'selector':$_GET['support']='invalid';$message='client review';break;case 'contact':$_SESSION['contact_id']=0;$message='primary client';break;case 'contact-auth':$_SESSION['contact_auth']='';$message='primary client';break;case 'admin-only':$_SESSION=['uid'=>7,'auth_version'=>str_repeat('a',32),'login_at'=>time(),'last_activity'=>time()];break;}
  an_deny('Native '.$mode,fn()=>studio_account_password_change($old,$new,$new),$message);if($mode==='disabled')query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');
 }
 an_session();foreach(['incorrect current'=>['Wrong synthetic value',$new,$new,'current password'],'mismatched confirmation'=>[$old,$new,$new.'!','do not match'],'short password'=>[$old,'short','short','14 and 72'],'oversized password'=>[$old,str_repeat('x',73),str_repeat('x',73),'14 and 72'],'NUL password'=>[$old,"Invalid\0 synthetic passphrase","Invalid\0 synthetic passphrase",'14 and 72'],'unchanged password'=>[$old,$old,$old,'different']] as $name=>[$a,$b,$c,$message])an_deny($name,fn()=>studio_account_password_change($a,$b,$c),$message);
 an_ok('Negative scenarios preserved original business state',an_state()===$base);
 $session=$_SESSION;$before=an_state();db()->exec('CREATE TRIGGER accsec_audit_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic account audit failure'");$failed=false;try{studio_account_password_change($old,$new,$new);}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER accsec_audit_fail');}
 an_ok('Audit failure rolls back credentials and invitations',$failed&&an_state()===$before&&!db()->inTransaction());an_ok('Failed commit leaves credential session and CSRF unchanged',$_SESSION['client_auth']===$session['client_auth']&&$_SESSION['csrf']===$session['csrf']);
 // Two stale sessions compete for the same row; the shared guard must allow only one update.
 an_session();$stale=$_SESSION;$beforeAudit=count(all('SELECT * FROM '.table('audit')));$workers=[];
 staff_locked(function()use(&$workers,$stale):void{$workers[]=an_worker('first',$stale);$workers[]=an_worker('second',$stale);usleep(300000);an_ok('Workers cannot modify credentials through the held account lock',one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=1')['auth_version']===$stale['client_auth']);});
 $out=array_map('an_finish',$workers);sort($out);an_ok('Exactly one competing old session changes credentials',$out===['changed','revoked']);an_ok('Competing request records exactly one successful audit',count(all('SELECT * FROM '.table('audit')))===$beforeAudit+1);$_SESSION=$stale;an_ok('Other authenticated client session is revoked',studio_client()===null);
 an_session();$admin=one('SELECT * FROM '.table('users').' WHERE id=7');$other=one('SELECT * FROM '.table('studio_clients').' WHERE id=2');$_SESSION+=['uid'=>7,'auth_version'=>$admin['auth_version'],'login_at'=>time(),'last_activity'=>time()];$oldSessionId=session_id();$csrf=$_SESSION['csrf'];$absolute=$_SESSION['client_login'];$oldVersion=$_SESSION['client_auth'];
 query('UPDATE '.table('studio_clients').' SET invite_hash=?,invite_expires=? WHERE id=1',[str_repeat('e',64),time()+3600]);
 $third='Synthetic final account passphrase';studio_account_password_change($new,$third,$third);$row=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');
 an_ok('Successful change stores only a password hash',password_verify($third,$row['password_hash'])&&!password_verify($new,$row['password_hash'])&&$row['password_hash']!==$third);
 an_ok('New credentials invalidate unused recovery invitations',$row['invite_hash']===null&&$row['invite_expires']===null);
 an_ok('Current customer session is retained and rotated',studio_client()!==null&&session_id()!==$oldSessionId&&$_SESSION['client_auth']===$row['auth_version']&&$row['auth_version']!==$oldVersion&&$_SESSION['csrf']!==$csrf);
 an_ok('Absolute session lifetime is not silently extended',$_SESSION['client_login']===$absolute);
 an_ok('Administrator and unrelated customer credentials remain byte-identical',one('SELECT * FROM '.table('users').' WHERE id=7')===$admin&&one('SELECT * FROM '.table('studio_clients').' WHERE id=2')===$other&&$_SESSION['uid']===7&&$_SESSION['auth_version']===$admin['auth_version']);
 $audit=all('SELECT user_id,action,detail FROM '.table('audit')." WHERE action='client.password_change'");an_ok('Audits contain only the customer operation and record identity',count($audit)===2&&array_unique(array_column($audit,'detail'))===['Client 1']&&array_unique(array_column($audit,'user_id'))===[0]);
 $json=json_encode(all('SELECT * FROM '.table('audit')));an_ok('Audit never stores passwords, hashes, email or raw recovery material',!str_contains($json,$old)&&!str_contains($json,$new)&&!str_contains($json,$third)&&!str_contains($json,$row['password_hash'])&&!str_contains($json,'@example.invalid')&&!str_contains($json,str_repeat('e',64)));
 an_ok('Account action creates no provider or mail credentials',!is_file($private.'/provider-master.key')&&!is_file($private.'/mail-master.key')&&!all('SELECT * FROM '.table('provider_profiles'))&&!all('SELECT * FROM '.table('mail_accounts')));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.get_class($e).': '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$passed=count($checks)>0&&!in_array(false,array_column($checks,'passed'),true);file_put_contents(__DIR__.'/../qa/account-security/native-results.json',json_encode(['passed'=>$passed,'source_sha'=>getenv('CWP_SOURCE_SHA'),'scope'=>'Actual loopback SQL, primary-client identity, rollback, competing sessions and private audit checks; no live account or provider operation','checks'=>$checks],JSON_PRETTY_PRINT));echo ($passed?'PASS':'FAIL').' '.count($checks).' native account checks'.PHP_EOL;exit($passed?0:1);
