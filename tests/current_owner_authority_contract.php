<?php
/** Test the test fixture's fail-closed behavior with explicit database doubles. */
declare(strict_types=1);
putenv('CWP_TEST_DATABASE=cwp_test');
$storage=__DIR__.'/../test-runtime/authority-contract-private';@mkdir($storage,0700,true);
$GLOBALS['fixture_config']=['db'=>['name'=>'cwp_test','host'=>'127.0.0.1','prefix'=>'authority_'],'storage'=>realpath($storage)];
final class AuthorityConnectionDouble {public bool $transaction=false;public function inTransaction():bool{return $this->transaction;}public function rollBack():void{$this->transaction=false;}}
$pdo=new AuthorityConnectionDouble();$checks=[];
function config():array{return $GLOBALS['fixture_config'];}
function db():AuthorityConnectionDouble{return $GLOBALS['pdo'];}
function table(string $name):string{return '`authority_'.$name.'`';}
function staff_ready():bool{return $GLOBALS['prepared'];}
function admin_session_matches(array $actor):bool{return !array_key_exists('support',$_GET)&&$actor['id']===1;}
function admin_user():array{return ['id'=>1,'role'=>$GLOBALS['prepared']?$GLOBALS['rows']['authority_staff_profiles'][0]['role']:'owner'];}
function all(string $sql,array $params=[]):array {
 if(str_contains($sql,'information_schema.tables'))return array_map(fn($name)=>['TABLE_NAME'=>$name],array_keys($GLOBALS['rows']));
 if(preg_match('/^SELECT \* FROM `([a-z_]+)`$/D',$sql,$m))return $GLOBALS['rows'][$m[1]];
 throw new AssertionError('Unexpected test query: '.$sql);
}
function one(string $sql,array $params=[]):?array {
 if(str_starts_with($sql,'SHOW CREATE TABLE '))return ['Create Table'=>'CREATE TABLE fixture (id int PRIMARY KEY) ENGINE=InnoDB'];
 if(str_starts_with($sql,'SELECT role,permissions'))return array_intersect_key($GLOBALS['rows']['authority_staff_profiles'][0],array_flip(['role','permissions']));
 throw new AssertionError('Unexpected test read: '.$sql);
}
function query(string $sql,array $params=[]):void {
 if(!str_starts_with($sql,'UPDATE `authority_staff_profiles`'))throw new AssertionError('Unexpected test mutation');
 if(count($params)===1){$GLOBALS['rows']['authority_staff_profiles'][0]['role']='administrator';$GLOBALS['rows']['authority_staff_profiles'][0]['permissions']='[]';}
 else {$GLOBALS['rows']['authority_staff_profiles'][0]['role']=$params[0];$GLOBALS['rows']['authority_staff_profiles'][0]['permissions']=$params[1];}
}
function reset_fixture():void {
 $GLOBALS['rows']=['authority_staff_profiles'=>[['user_id'=>1,'role'=>'owner','permissions'=>'[]']],'authority_audit'=>[]];
 $GLOBALS['prepared']=true;db()->transaction=false;$_GET=[];$_SESSION=['uid'=>1];
 foreach(glob(config()['storage'].'/*') as $f)unlink($f);
}
function ok_fixture(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new LogicException($name);}
function must_reject(string $name,callable $operation):void {
 $rejected=false;try{cwp_test_owner_revoked($operation);}catch(AssertionError){$rejected=true;}
 ok_fixture($name,$rejected);ok_fixture($name.' restores owner role and session',admin_user()['role']==='owner'&&$_SESSION===['uid'=>1]&&$_GET===[]&&!db()->inTransaction());
}
require __DIR__.'/current_owner_authority.php';
try {
 reset_fixture();$before=cwp_test_authority_snapshot();$correct=false;
 try{cwp_test_owner_revoked(function(){ok_fixture('Callback observes the same user without owner role',admin_user()===['id'=>1,'role'=>'administrator']);throw new RuntimeException('Your account does not have access to this action.');});}catch(RuntimeException $e){$correct=$e->getMessage()==='Your account does not have access to this action.';}
 ok_fixture('Only genuine authorization refusal is rethrown',$correct);ok_fixture('Successful denial restores exact fixture state',cwp_test_authority_snapshot()===$before);
 foreach(['Current website and inquiry permission is required.','Your current account does not have access to client administration.'] as $message){
  reset_fixture();$before=cwp_test_authority_snapshot();$refused=false;
  try{cwp_test_owner_revoked(fn()=>throw new RuntimeException($message));}catch(RuntimeException $e){$refused=$e->getMessage()===$message;}
  ok_fixture('Exact current permission boundary accepted: '.$message,$refused&&cwp_test_authority_snapshot()===$before);
  reset_fixture();must_reject('Permission message cannot mask changed data: '.$message,function()use($message){$GLOBALS['rows']['authority_audit'][]=['id'=>1];throw new RuntimeException($message);});
 }
 reset_fixture();must_reject('A callback that wrongly succeeds cannot pass',fn()=>null);
 reset_fixture();must_reject('An unrelated validation error cannot masquerade as denied access',fn()=>throw new InvalidArgumentException('Invalid product'));
 reset_fixture();must_reject('A PDO failure cannot masquerade as denied access',fn()=>throw new PDOException('Your account does not have access to this action.'));
 reset_fixture();must_reject('A write followed by the right error still fails',function(){$GLOBALS['rows']['authority_audit'][]=['id'=>1];throw new RuntimeException('Your account does not have access to this action.');});
 reset_fixture();must_reject('A private-file write followed by denial still fails',function(){file_put_contents(config()['storage'].'/unexpected','changed');throw new RuntimeException('Your account does not have access to this action.');});
 reset_fixture();must_reject('A leaked transaction cannot pass',function(){db()->transaction=true;throw new RuntimeException('Your account does not have access to this action.');});
 reset_fixture();$prepared=false;$correct=false;
 try{cwp_test_owner_revoked(function(){ok_fixture('Initial-owner case does not fabricate a staff profile',!staff_ready()&&isset($_GET['support']));throw new InvalidArgumentException('Your administrator session is no longer valid. Sign in again.');});}catch(InvalidArgumentException){$correct=true;}
 ok_fixture('Initial-owner denial is checked and query restored',$correct&&$_GET===[]);
 reset_fixture();$GLOBALS['fixture_config']['db']['name']='production';$blocked=false;try{cwp_test_owner_revoked(fn()=>null);}catch(AssertionError){$blocked=true;}
 ok_fixture('Non-disposable database refused before mutation',$blocked&&admin_user()['role']==='owner');$GLOBALS['fixture_config']['db']['name']='cwp_test';
 reset_fixture();$GLOBALS['fixture_config']['storage']=sys_get_temp_dir();$blocked=false;try{cwp_test_owner_revoked(fn()=>null);}catch(AssertionError){$blocked=true;}
 ok_fixture('Storage outside the fixture tree is refused',$blocked&&admin_user()['role']==='owner');
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Actual test-helper code with strict in-memory SQL doubles; not native SQL','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;
exit(isset($failure)?1:0);
