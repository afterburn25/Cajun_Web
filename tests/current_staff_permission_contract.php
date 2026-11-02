<?php
/** Execute the real delegated-staff helper with strict in-memory SQL doubles. */
declare(strict_types=1);
putenv('CWP_TEST_DATABASE=cwp_test');
$storage=__DIR__.'/../test-runtime/staff-permission-contract';if(!is_dir($storage))mkdir($storage,0700,true);
$GLOBALS['fixture_config']=['db'=>['name'=>'cwp_test','host'=>'127.0.0.1','prefix'=>'authority_'],'storage'=>realpath($storage)];
final class StaffPermissionConnection {public bool $transaction=false;public function inTransaction():bool{return $this->transaction;}public function rollBack():void{$this->transaction=false;}}
$pdo=new StaffPermissionConnection();$checks=[];
function config():array{return $GLOBALS['fixture_config'];}
function db():StaffPermissionConnection{return $GLOBALS['pdo'];}
function table(string $name):string{return '`authority_'.$name.'`';}
function staff_ready():bool{return true;}
function admin_session_matches(array $actor):bool{return $GLOBALS['session_valid']&&!array_key_exists('support',$_GET)&&$actor['id']===2;}
function admin_user():array{$r=$GLOBALS['rows']['authority_staff_profiles'][0];return ['id'=>2,'role'=>$r['role'],'permissions'=>json_decode($r['permissions'],true)];}
function staff_can(string $permission,array $actor):bool{return in_array($permission,$actor['permissions'],true);}
function all(string $sql,array $params=[]):array{
 if(str_contains($sql,'information_schema.tables'))return array_map(fn($n)=>['TABLE_NAME'=>$n],array_keys($GLOBALS['rows']));
 if(preg_match('/^SELECT \* FROM `([a-z_]+)`$/D',$sql,$m))return $GLOBALS['rows'][$m[1]];
 throw new AssertionError('Unexpected synthetic query');
}
function one(string $sql,array $params=[]):?array{
 if(str_starts_with($sql,'SHOW CREATE TABLE '))return ['Create Table'=>'CREATE TABLE synthetic (id int PRIMARY KEY) ENGINE=InnoDB'];
 if(str_starts_with($sql,'SELECT role,permissions')&&$params===[2])return array_intersect_key($GLOBALS['rows']['authority_staff_profiles'][0],array_flip(['role','permissions']));
 throw new AssertionError('Unexpected synthetic read');
}
function query(string $sql,array $params=[]):void{
 if($sql!=='UPDATE `authority_staff_profiles` SET permissions=? WHERE user_id=?'||count($params)!==2||$params[1]!==2)throw new AssertionError('Unexpected synthetic mutation');
 $GLOBALS['rows']['authority_staff_profiles'][0]['permissions']=$params[0];
}
function reset_staff():void{
 $GLOBALS['rows']=['authority_staff_profiles'=>[['user_id'=>2,'role'=>'administrator','permissions'=>'["website.manage","clients.manage"]']],'authority_audit'=>[]];
 $GLOBALS['session_valid']=true;db()->transaction=false;$_GET=[];$_SESSION=['uid'=>2,'synthetic'=>'retained'];
 foreach(glob(config()['storage'].'/*')?:[] as $f)unlink($f);
}
function ok_staff(string $name,bool $pass):void{global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new LogicException($name);}
function rejects_staff(string $name,callable $operation):void{
 $rejected=false;try{cwp_test_staff_permission_revoked('website.manage',$operation);}catch(AssertionError){$rejected=true;}
 ok_staff($name,$rejected);ok_staff($name.' restores identity, permissions and session',admin_user()===['id'=>2,'role'=>'administrator','permissions'=>['website.manage','clients.manage']]&&$_SESSION===['uid'=>2,'synthetic'=>'retained']&&$_GET===[]&&!db()->inTransaction());
}
require __DIR__.'/current_staff_permission.php';
try{
 reset_staff();$before=cwp_test_authority_snapshot();$refused=false;
 try{cwp_test_staff_permission_revoked('website.manage',function(){ok_staff('Same staff remains authenticated and retains unrelated permission',admin_user()===['id'=>2,'role'=>'administrator','permissions'=>['clients.manage']]&&admin_session_matches(admin_user()));throw new RuntimeException('Current website and inquiry permission is required.');});}catch(RuntimeException $e){$refused=$e->getMessage()==='Current website and inquiry permission is required.';}
 ok_staff('Actual permission refusal is rethrown',$refused);ok_staff('Successful denial retains original rows and files',cwp_test_authority_snapshot()===$before);
 foreach(['success'=>fn()=>null,'unrelated validation'=>fn()=>throw new InvalidArgumentException('Bad field'),'SQL fault'=>fn()=>throw new PDOException('Current website and inquiry permission is required.'),'write then deny'=>function(){$GLOBALS['rows']['authority_audit'][]=['id'=>1];throw new RuntimeException('Current website and inquiry permission is required.');},'private write then deny'=>function(){file_put_contents(config()['storage'].'/changed','changed');throw new RuntimeException('Current website and inquiry permission is required.');},'transaction leak'=>function(){db()->transaction=true;throw new RuntimeException('Current website and inquiry permission is required.');}] as $name=>$operation){reset_staff();rejects_staff('Rejects '.$name,$operation);}
 foreach(['owner','revoked','missing permission','support'] as $mode){reset_staff();if($mode==='owner')$GLOBALS['rows']['authority_staff_profiles'][0]['role']='owner';elseif($mode==='revoked')$GLOBALS['session_valid']=false;elseif($mode==='missing permission')$GLOBALS['rows']['authority_staff_profiles'][0]['permissions']='["clients.manage"]';else $_GET['support']='test';$before=$GLOBALS['rows'];$called=false;$denied=false;try{cwp_test_staff_permission_revoked('website.manage',function()use(&$called){$called=true;});}catch(AssertionError){$denied=true;}ok_staff('Invalid starting context refused before mutation: '.$mode,$denied&&!$called&&$GLOBALS['rows']===$before);}
 reset_staff();$GLOBALS['fixture_config']['db']['name']='production';$denied=false;try{cwp_test_staff_permission_revoked('website.manage',fn()=>null);}catch(AssertionError){$denied=true;}ok_staff('Production database never admitted',$denied&&staff_can('website.manage',admin_user()));
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Real staff-denial helper with synthetic SQL doubles, not database acceptance','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;exit(isset($failure)?1:0);
