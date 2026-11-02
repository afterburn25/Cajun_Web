<?php
/** Synthetic SQL-only fixture and probes; never loads installed production configuration. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/app/clients/master_portal.php';
$private=dirname(__DIR__).'/test-runtime/master-portal-private';
$config=['site_url'=>'http://127.0.0.1:8092/master','key'=>str_repeat('c',64),'storage'=>$private,'db'=>['host'=>'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER'),'pass'=>getenv('CWP_TEST_PASSWORD'),'prefix'=>'masterci_']];
$GLOBALS['cwp_config']=$config;
$mode=$argv[1]??'seed';
if($mode==='config'){echo '<?php return '.var_export($config,true).';';exit;}
if($mode==='snapshot'){
 $rows=all('SELECT id,email,password_hash,auth_version,active FROM '.table('studio_clients').' ORDER BY id');
 $last=one('SELECT actor_id,client_id,context FROM '.table('support_sessions').' ORDER BY started_at DESC,id DESC LIMIT 1');
 echo json_encode(['clients'=>count($rows),'credentials_hash'=>hash('sha256',json_encode($rows)),'owner_reviews'=>(int)query("SELECT COUNT(*) FROM ".table('support_events')." WHERE action='owner.review.start'")->fetchColumn(),'sessions'=>(int)query('SELECT COUNT(*) FROM '.table('support_sessions'))->fetchColumn(),'last_session'=>$last]);exit;
}
if($mode==='revoke'){query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[str_repeat('f',32)]);echo '{}';exit;}
if($mode!=='seed')exit(2);
@mkdir($private,0700,true);@mkdir(dirname(__DIR__).'/qa',0775,true);
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8092','SCRIPT_NAME'=>'/master/index.php','REQUEST_METHOD'=>'GET'];start_session();
$checks=[];$failure=null;
function mp_check(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function mp_deny(string $name,callable $f):void {try{$f();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;mp_check($name,true);return;}mp_check($name,false);}
function mp_actor(int $id):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION['uid']=$id;$_SESSION['auth_version']=$u['auth_version'];$_SESSION['last_activity']=$_SESSION['login_at']=time();unset($GLOBALS['cwp_support']);$_GET=[];}
try {
 foreach(schema_statements('masterci_') as $sql)db()->exec($sql);
 foreach(default_settings() as $key=>$value)save_setting($key,$value);
 foreach([[1,'Master Owner','master-owner@example.invalid','owner',[]],[2,'Portal Staff','master-staff@example.invalid','administrator',['support.login']],[3,'Website Staff','master-denied@example.invalid','administrator',['website.manage']]] as [$id,$name,$email,$role,$perms]){
  query('INSERT INTO '.table('users').' (id,name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?,?)',[$id,$name,$email,password_hash('Synthetic master portal passphrase',PASSWORD_DEFAULT),str_repeat((string)$id,32),gmdate('Y-m-d H:i:s')]);
  query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode($perms)]);
 }
 save_setting('staff_schema_version','1');
 foreach([[1,'Alpha Client','master-owner@example.invalid',1],[2,'Beta Client','beta@example.invalid',1],[3,'Disabled Client','disabled@example.invalid',0],[4,'100% Studio','percent@example.invalid',1]] as [$id,$name,$email,$active]){
  query('INSERT INTO '.table('studio_clients').' (id,name,email,password_hash,auth_version,active,created_at) VALUES (?,?,?,?,?,?,?)',[$id,$name,$email,password_hash('Synthetic customer-only passphrase',PASSWORD_DEFAULT),str_repeat(dechex($id),32),$active,gmdate('Y-m-d H:i:s')]);
 }
 for($i=5;$i<=58;$i++)query('INSERT INTO '.table('studio_clients').' (id,name,email,password_hash,auth_version,active,created_at) VALUES (?,?,?,?,?,1,?)',[$i,'Synthetic Client '.$i,'client'.$i.'@example.invalid','',str_repeat('a',32),gmdate('Y-m-d H:i:s')]);
 $original=all('SELECT id,email,password_hash,auth_version,active FROM '.table('studio_clients').' ORDER BY id');
 mp_deny('Unauthenticated hub cannot list clients',fn()=>master_portal_clients([]));
 mp_actor(1);mp_deny('Owner list unavailable before explicit support setup',fn()=>master_portal_clients([]));
 support_migrate();mp_check('Support enabled through real owner migration',support_ready()&&setting('support_policy_confirmed')==='1');
 $data=master_portal_clients(['q'=>'Alpha']);mp_check('Owner lists active client without staff opt-in',array_column($data['clients'],'id')===[1]);
 mp_check('Same email never creates a customer session',studio_client()===null&&empty($_SESSION['client_id']));
 mp_check('Disabled client omitted',master_portal_clients(['q'=>'Disabled'])['clients']===[]);
 mp_check('Literal percent search does not enumerate all rows',array_column(master_portal_clients(['q'=>'%'])['clients'],'id')===[4]);
 $page1=master_portal_clients([]);$page2=master_portal_clients(['portal_page'=>'2']);
 mp_check('Owner pagination exposes bounded page and next flag',count($page1['clients'])===50&&$page1['more']&&count($page2['clients'])===7&&!$page2['more']);
 mp_check('Pages contain no duplicate client IDs',array_intersect(array_column($page1['clients'],'id'),array_column($page2['clients'],'id'))===[]);
 support_policy(2,true);mp_actor(2);
 mp_check('Staff only sees policy-approved active clients',array_column(master_portal_clients([])['clients'],'id')===[2]);
 mp_check('Staff search cannot reveal unapproved clients',master_portal_clients(['q'=>'Alpha'])['clients']===[]);
 mp_deny('Forged switch to unapproved client refused',fn()=>support_start(1));
 mp_actor(3);mp_deny('Unpermitted administrator cannot list clients',fn()=>master_portal_clients([]));
 mp_actor(2);$session=$_SESSION;$_SESSION['last_activity']=time()-1801;mp_deny('Expired session cannot list clients',fn()=>master_portal_clients([]));$_SESSION=$session;
 $_SESSION['auth_version']=str_repeat('0',32);mp_deny('Revoked session cannot list clients',fn()=>master_portal_clients([]));mp_actor(2);
 query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=2');mp_deny('Disabled account cannot list clients',fn()=>master_portal_clients([]));query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=2');
 mp_actor(1);$_GET=['support'=>'invalid'];mp_deny('Support parameter cannot expose master list',fn()=>master_portal_clients([]));$_GET=[];
 $_SESSION['client_id']=2;$_SESSION['client_auth']=str_repeat('2',32);$_SESSION['client_login']=$_SESSION['client_last']=time();$keys=array_intersect_key($_SESSION,array_flip(['client_id','client_auth','client_login','client_last']));
 $id=support_start(1);$GLOBALS['cwp_support']=support_resolve($id);
 mp_check('Owner review keeps distinct actor and selected client',$GLOBALS['cwp_support']['actor']['id']==1&&$GLOBALS['cwp_support']['client']['id']==1);
 mp_check('Review preserves existing customer authentication',$keys===array_intersect_key($_SESSION,$keys));
 mp_deny('Support context cannot enumerate master accounts',fn()=>master_portal_clients([]));
 mp_deny('Support review cannot sign client decisions',fn()=>studio_decide(1,1,'approved','Synthetic'));
 support_end($id);mp_check('Review end retains original customer identity',$keys===array_intersect_key($_SESSION,$keys));
 mp_check('Review audited with real owner identity',one('SELECT actor_id,client_id FROM '.table('support_events')." WHERE action='owner.review.start' ORDER BY id DESC LIMIT 1")===['actor_id'=>1,'client_id'=>1]);
 save_setting('support_policy_confirmed','0');mp_deny('Revoked review policy blocks hub data',fn()=>master_portal_clients([]));save_setting('support_policy_confirmed','1');
 mp_check('Hub reads and review never create clients or change credentials',all('SELECT id,email,password_hash,auth_version,active FROM '.table('studio_clients').' ORDER BY id')===$original);
 unset($_SESSION['client_id'],$_SESSION['client_auth'],$_SESSION['client_login'],$_SESSION['client_last']);
 mp_actor(1);
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
file_put_contents(__DIR__.'/../qa/master-portal-native-results.json',json_encode(['passed'=>$failure===null,'scope'=>'Actual disposable SQL; no provider/production calls','checks'=>$checks,'failure'=>$failure],JSON_PRETTY_PRINT));
echo json_encode(['passed'=>$failure===null,'checks'=>count($checks),'failure'=>$failure])."\n";exit($failure===null?0:1);
