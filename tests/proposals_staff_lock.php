<?php
/** Actual staff mutation methods with controlled lock/session boundaries; no SQL or network. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',__DIR__.'/../site');
$checks=[];$inside=false;$transaction=false;$hook=null;$permitted=true;$reads=0;$writes=0;
$actor=['id'=>2,'auth_version'=>str_repeat('a',32)];$_GET=[];
function staff_ready():bool{return true;}function studio_ready():bool{return true;}
function setting(string $key):string{return $key==='proposals_schema_version'?'1':'';}
function support_current():mixed{return null;}
function staff_require(string $permission):array{if($permission!=='clients.manage')throw new LogicException('Unexpected permission');if(!$GLOBALS['permitted'])throw new RuntimeException('Permission revoked');return $GLOBALS['actor'];}
function staff_locked(callable $work):mixed{$GLOBALS['inside']=true;try{if($GLOBALS['hook'])($GLOBALS['hook'])();return $work();}finally{$GLOBALS['inside']=false;}}
function staff_transaction(callable $work):mixed{$GLOBALS['transaction']=true;try{return $work();}finally{$GLOBALS['transaction']=false;}}
function gcents(string $amount):?int{return $amount==='10.00'?1000:null;}
function table(string $name):string{return '`fixture_'.$name.'`';}
function one(string $sql,array $args=[]):?array{if(!$GLOBALS['inside']&&str_contains($sql,'studio_clients'))return ['id'=>1];$GLOBALS['reads']++;throw new LogicException('Authenticated locked read reached');}
function query(string $sql,array $args=[]):void{$GLOBALS['writes']++;throw new LogicException('Unexpected write');}
require CWP_CODE_ROOT.'/app/marketing/proposals.php';
function lock_ok(string $name,bool $pass):void{$GLOBALS['checks'][]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new LogicException($name);}
$input=['title'=>'Synthetic scope','deliverables'=>'Reviewed site','exclusions'=>'','milestones'=>'Design | 10.00','schedule_notes'=>'Synthetic schedule'];$id=str_repeat('1',32);
$operations=['create'=>fn()=>proposal_create(1,$input,str_repeat('2',64)),'revise'=>fn()=>proposal_revise($id,$input),'send'=>fn()=>proposal_send($id),'withdraw'=>fn()=>proposal_withdraw($id)];
try{
 foreach($operations as $name=>$operation){
  foreach(['permission','identity','session','support','valid'] as $mode){
   $permitted=true;$actor=['id'=>2,'auth_version'=>str_repeat('a',32)];$_GET=[];$reads=0;$writes=0;$error=null;
   $hook=static function()use($mode):void{if($mode==='permission')$GLOBALS['permitted']=false;elseif($mode==='identity')$GLOBALS['actor']['id']=3;elseif($mode==='session')$GLOBALS['actor']['auth_version']=str_repeat('b',32);elseif($mode==='support')$_GET['support']='invalid';};
   try{$operation();}catch(Throwable $e){$error=$e;}
   $expected=match($mode){'permission'=>'Permission revoked','identity','session'=>'Administrator identity changed','support'=>'Leave support','valid'=>'Authenticated locked read reached'};
   lock_ok($name.' '.$mode.' reaches the intended authorization boundary',$error!==null&&str_contains($error->getMessage(),$expected));
   lock_ok($name.' '.$mode.' prevents premature reads and writes',$reads===($mode==='valid'?1:0)&&$writes===0&&!$inside&&!$transaction);
  }
 }
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Actual proposal staff service methods with synthetic auth/lock/SQL boundaries; not native SQL acceptance','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;exit(isset($failure)?1:0);
