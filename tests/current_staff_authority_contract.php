<?php
/** Actual delegated-staff test helper with strict SQL doubles and private synthetic bytes. */
declare(strict_types=1);
putenv('CWP_TEST_DATABASE=cwp_test');$storage=__DIR__.'/../test-runtime/staff-authority-private';@mkdir($storage,0700,true);
$GLOBALS['fixture_config']=['db'=>['name'=>'cwp_test','host'=>'127.0.0.1','prefix'=>'staffauthority_'],'storage'=>realpath($storage)];
final class StaffAuthorityConnection {public bool $transaction=false;public function inTransaction():bool{return $this->transaction;}public function rollBack():void{$this->transaction=false;}}
$pdo=new StaffAuthorityConnection();$checks=[];
function config():array{return $GLOBALS['fixture_config'];}
function db():StaffAuthorityConnection{return $GLOBALS['pdo'];}
function table(string $name):string{return '`staffauthority_'.$name.'`';}
function staff_ready():bool{return $GLOBALS['prepared'];}
function admin_session_matches(array $u):bool{return ($_SESSION['uid']??null)===2&&($_SESSION['auth_version']??null)==='current'&&!array_key_exists('support',$_GET);}
function admin_user():?array{$p=$GLOBALS['rows']['staffauthority_staff_profiles'][0];return $GLOBALS['signed']?['id'=>2,'role'=>$p['role'],'enabled'=>$p['enabled'],'permissions'=>json_decode($p['permissions'],true)]:null;}
function staff_can(string $p,array $u):bool{return (int)$u['enabled']===1&&in_array($p,$u['permissions'],true);}
function all(string $sql,array $params=[]):array{
 if(str_contains($sql,'information_schema.tables'))return array_map(fn($n)=>['TABLE_NAME'=>$n],array_keys($GLOBALS['rows']));
 if(preg_match('/^SELECT \* FROM `([a-z_]+)`$/D',$sql,$m))return $GLOBALS['rows'][$m[1]];
 throw new AssertionError('Unexpected fixture query');
}
function one(string $sql,array $params=[]):?array{
 if(str_starts_with($sql,'SHOW CREATE TABLE '))return ['Create Table'=>'CREATE TABLE synthetic (id int PRIMARY KEY) ENGINE=InnoDB'];
 if(str_starts_with($sql,'SELECT role,enabled,permissions')&&$params===[2])return array_intersect_key($GLOBALS['rows']['staffauthority_staff_profiles'][0],array_flip(['role','enabled','permissions']));
 throw new AssertionError('Unexpected fixture read');
}
function query(string $sql,array $params=[]):void{
 if($sql==="UPDATE `staffauthority_staff_profiles` SET permissions='[]' WHERE user_id=?"&&$params===[2]){$GLOBALS['rows']['staffauthority_staff_profiles'][0]['permissions']='[]';return;}
 if($sql==='UPDATE `staffauthority_staff_profiles` SET permissions=? WHERE user_id=?'&&count($params)===2&&$params[1]===2){$GLOBALS['rows']['staffauthority_staff_profiles'][0]['permissions']=$params[0];return;}
 throw new AssertionError('Unexpected fixture mutation');
}
function reset_staff():void{
 $GLOBALS['rows']=['staffauthority_staff_profiles'=>[['user_id'=>2,'role'=>'administrator','enabled'=>1,'permissions'=>'["website.manage"]']],'staffauthority_audit'=>[]];
 $GLOBALS['prepared']=$GLOBALS['signed']=true;db()->transaction=false;$_GET=[];$_SESSION=['uid'=>2,'auth_version'=>'current'];$GLOBALS['cwp_current_authority_evidence']=[];
 foreach(glob(config()['storage'].'/*')?:[] as $f)unlink($f);
}
function staff_ok(string $name,bool $pass):void{global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new LogicException($name);}
function staff_bad(string $name,callable $work):void{
 $blocked=false;try{cwp_test_website_staff_revoked($work);}catch(AssertionError){$blocked=true;}
 staff_ok($name,$blocked);staff_ok($name.' retains delegated role and current session',admin_user()['role']==='administrator'&&admin_user()['permissions']===['website.manage']&&$_SESSION===['uid'=>2,'auth_version'=>'current']&&!db()->inTransaction());
 staff_ok($name.' cannot produce passing evidence',$GLOBALS['cwp_current_authority_evidence']===[]);
}
require __DIR__.'/current_staff_authority.php';
try{
 reset_staff();$before=cwp_test_authority_snapshot();$denied=false;
 try{cwp_test_website_staff_revoked(function(){staff_ok('Callback remains signed in as the same non-owner without website permission',admin_user()['id']===2&&admin_user()['role']==='administrator'&&admin_session_matches(admin_user())&&!staff_can('website.manage',admin_user()));throw new RuntimeException('Current website and inquiry permission is required.');});}catch(RuntimeException $e){$denied=$e->getMessage()==='Current website and inquiry permission is required.';}
 staff_ok('Actual permission refusal is rethrown',$denied);staff_ok('Original rows files and permissions restored exactly',cwp_test_authority_snapshot()===$before);
 staff_ok('Passing proof records the real delegated actor',$GLOBALS['cwp_current_authority_evidence'][0]['actor_id']===2&&$GLOBALS['cwp_current_authority_evidence'][0]['mode']==='website-staff-permission-revoked');
 foreach(['silent'=>fn()=>null,'unrelated'=>fn()=>throw new RuntimeException('Wrong record'),'PDO'=>fn()=>throw new PDOException('Current website and inquiry permission is required.'),'write-then-refuse'=>function(){$GLOBALS['rows']['staffauthority_audit'][]=['id'=>1];throw new RuntimeException('Current website and inquiry permission is required.');},'private-write'=>function(){file_put_contents(config()['storage'].'/unexpected','changed');throw new RuntimeException('Current website and inquiry permission is required.');},'transaction-leak'=>function(){db()->transaction=true;throw new RuntimeException('Current website and inquiry permission is required.');}] as $name=>$work){reset_staff();staff_bad($name.' is not an authority pass',$work);}
 foreach(['owner','disabled','missing-grant','signed-out','revoked','support','unprepared','production'] as $mode){
  reset_staff();
  if($mode==='owner')$GLOBALS['rows']['staffauthority_staff_profiles'][0]['role']='owner';
  elseif($mode==='disabled')$GLOBALS['rows']['staffauthority_staff_profiles'][0]['enabled']=0;
  elseif($mode==='missing-grant')$GLOBALS['rows']['staffauthority_staff_profiles'][0]['permissions']='[]';
  elseif($mode==='signed-out')$GLOBALS['signed']=false;
  elseif($mode==='revoked')$_SESSION['auth_version']='old';
  elseif($mode==='support')$_GET['support']='test';
  elseif($mode==='unprepared')$GLOBALS['prepared']=false;
  else $GLOBALS['fixture_config']['db']['name']='production';
  $before=$GLOBALS['rows'];$called=false;$blocked=false;try{cwp_test_website_staff_revoked(function()use(&$called){$called=true;});}catch(AssertionError){$blocked=true;}
  staff_ok('Invalid '.$mode.' fixture is refused before any callback or mutation',$blocked&&!$called&&$GLOBALS['rows']===$before&&$GLOBALS['cwp_current_authority_evidence']===[]);$GLOBALS['fixture_config']['db']['name']='cwp_test';
 }
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Actual delegated permission helper with strict synthetic persistence; not native database acceptance','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;exit(isset($failure)?1:0);
