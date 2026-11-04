<?php
/** Actual proposal service/model with strict in-memory records. No SQL, network or live customer. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',__DIR__.'/../site');
require CWP_CODE_ROOT.'/app/marketing/proposals.php';
$checks=[];$insideLock=false;$inTx=false;$lockHook=null;$current=['id'=>1,'auth_version'=>str_repeat('a',32)];$support=false;$failAudit=false;
function staff_ready():bool{return true;}function studio_ready():bool{return true;}function setting(string $key):string{return $key==='proposals_schema_version'?'1':'';}
function studio_client():?array{if(!$GLOBALS['insideLock']||!$GLOBALS['inTx'])throw new LogicException('Authentication must be revalidated under the mutation lock');return $GLOBALS['current'];}
function contact_require_permission(string $permission,int $clientId):void{if(!$GLOBALS['insideLock']||!$GLOBALS['inTx']||$permission!=='billing'||$clientId!==$GLOBALS['current']['id'])throw new LogicException('Contact billing authority must be checked under the mutation lock');if(!$GLOBALS['contactAllowed'])throw new RuntimeException('Your contact permissions do not include billing.');}
function support_current():mixed{return $GLOBALS['support']?['synthetic'=>true]:null;}
function table(string $name):string{return '`proposal_binding_'.$name.'`';}
function staff_locked(callable $work):mixed{$GLOBALS['insideLock']=true;try{if($GLOBALS['lockHook'])($GLOBALS['lockHook'])();return $work();}finally{$GLOBALS['insideLock']=false;}}
function staff_transaction(callable $work):mixed{$before=$GLOBALS['rows'];$GLOBALS['inTx']=true;try{return $work();}catch(Throwable $e){$GLOBALS['rows']=$before;throw $e;}finally{$GLOBALS['inTx']=false;}}
function one(string $sql,array $args=[]):?array{
 if($sql==='SELECT * FROM `proposal_binding_proposals` WHERE id=? FOR UPDATE'){if(!$GLOBALS['insideLock']||!$GLOBALS['inTx'])throw new LogicException('Missing lock');return $args[0]===$GLOBALS['rows']['proposal']['id']?$GLOBALS['rows']['proposal']:null;}
 if($sql==='SELECT * FROM `proposal_binding_proposal_versions` WHERE proposal_id=? AND version=?')return $GLOBALS['rows']['versions'][$args[1]]??null;
 throw new LogicException('Unexpected synthetic read: '.$sql);
}
function all(string $sql,array $args=[]):array{
 if($sql==='SELECT * FROM `proposal_binding_proposal_versions` WHERE proposal_id=? ORDER BY version')return array_values($GLOBALS['rows']['versions']);
 if($sql==='SELECT * FROM `proposal_binding_proposal_decisions` WHERE proposal_id=? ORDER BY version')return array_values($GLOBALS['rows']['decisions']);
 if($sql==='SELECT * FROM `proposal_binding_proposal_events` WHERE proposal_id=? ORDER BY id')return array_values($GLOBALS['rows']['events']);
 throw new LogicException('Unexpected synthetic list: '.$sql);
}
function query(string $sql,array $args=[]):void{
 if(!$GLOBALS['insideLock']||!$GLOBALS['inTx'])throw new LogicException('Write outside lock');
 if(str_starts_with($sql,'INSERT INTO `proposal_binding_proposal_decisions` ')){$GLOBALS['rows']['decisions'][]=array_combine(['id','proposal_id','version','decision','client_id','note','created_at'],$args);return;}
 if($sql==='UPDATE `proposal_binding_proposals` SET status=?,decided_at=? WHERE id=?'){$GLOBALS['rows']['proposal']['status']=$args[0];$GLOBALS['rows']['proposal']['decided_at']=$args[1];return;}
 if(str_starts_with($sql,'INSERT INTO `proposal_binding_proposal_events` ')){if($GLOBALS['failAudit'])throw new RuntimeException('Synthetic history write failure');$GLOBALS['rows']['events'][]=array_combine(['proposal_id','event','actor_type','actor_id','detail','created_at'],$args);return;}
 throw new LogicException('Unexpected synthetic write: '.$sql);
}
function binding_seed():array{
 $GLOBALS['current']=['id'=>1,'auth_version'=>str_repeat('a',32)];$GLOBALS['contactAllowed']=true;$GLOBALS['support']=false;$GLOBALS['failAudit']=false;$GLOBALS['lockHook']=null;$_GET=[];
 $id=str_repeat('c',32);$at='2026-01-01T12:00:00Z';$v=[];
 foreach([1,2] as $n){$input=['deliverables'=>['Site v'.$n],'exclusions'=>[],'schedule_notes'=>'Synthetic schedule '.$n,'milestones'=>[['label'=>'Design','amountCents'=>100000*$n]]];$v[$n]=['id'=>str_repeat((string)$n,32),'proposal_id'=>$id,'version'=>$n,'title'=>'Synthetic scope '.$n,'deliverables'=>json_encode($input['deliverables']),'exclusions'=>'[]','schedule_notes'=>$input['schedule_notes'],'milestones'=>json_encode($input['milestones']),'content_hash'=>proposal_content_hash($input),'created_by'=>2,'created_at'=>$at];}
 $events=[];foreach([['version-added','staff',2],['sent','staff',2],['changes-requested','client',1],['revised','staff',2],['sent','staff',2]] as [$event,$type,$actor])$events[]=['event'=>$event,'actor_type'=>$type,'actor_id'=>$actor,'created_at'=>$at];
 $GLOBALS['rows']=['proposal'=>['id'=>$id,'client_id'=>1,'title'=>'Synthetic scope 2','created_by'=>2,'created_at'=>$at,'current_version'=>2,'status'=>'sent','decided_at'=>null],'versions'=>$v,'events'=>$events,'decisions'=>[['id'=>str_repeat('d',32),'version'=>1,'client_id'=>1,'decision'=>'changes-requested','note'=>'Please revise the scope']]];
 return [$id,$GLOBALS['current'],$v[1],$v[2]];
}
function binding_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new LogicException($name);}
function binding_deny(string $name,callable $work,string $message):void{$before=$GLOBALS['rows'];$error=null;try{$work();}catch(Throwable $e){$error=$e;}binding_ok($name,$error instanceof RuntimeException||$error instanceof InvalidArgumentException||$error instanceof LogicException);binding_ok($name.' fails at the intended boundary',$error!==null&&str_contains($error->getMessage(),$message));binding_ok($name.' retains exact state',$GLOBALS['rows']===$before&&!$GLOBALS['inTx']&&!$GLOBALS['insideLock']);}
try{
 [$id,$client,$v1,$v2]=binding_seed();
 foreach(['approve','changes'] as $kind)binding_deny('Stale v1 '.$kind,fn()=>proposal_decide($id,$kind,'Original tab',$client,$v1['id'],$v1['content_hash']),'changed after you reviewed');
 binding_deny('Missing version',fn()=>proposal_decide($id,'approve','',$client),'review the exact');
 binding_deny('Wrong content hash',fn()=>proposal_decide($id,'approve','',$client,$v2['id'],str_repeat('0',64)),'changed after you reviewed');
 binding_deny('Invalid choice',fn()=>proposal_decide($id,'invented','Not a change request',$client,$v2['id'],$v2['content_hash']),'Choose approval');
 $GLOBALS['rows']['versions'][2]['schedule_notes']='Damaged without hash update';binding_deny('Changed stored content',fn()=>proposal_decide($id,'approve','',$client,$v2['id'],$v2['content_hash']),'content is inconsistent');
 [$id,$client,$v1,$v2]=binding_seed();$GLOBALS['current']=null;binding_deny('Expired customer',fn()=>proposal_decide($id,'approve','',$client,$v2['id'],$v2['content_hash']),'Current client authentication');
 [$id,$client,$v1,$v2]=binding_seed();$GLOBALS['current']['auth_version']=str_repeat('b',32);binding_deny('Revoked customer identity',fn()=>proposal_decide($id,'approve','',$client,$v2['id'],$v2['content_hash']),'Current client authentication');
 [$id,$client,$v1,$v2]=binding_seed();$GLOBALS['current']['id']=2;$foreign=$GLOBALS['current'];binding_deny('Other authenticated customer',fn()=>proposal_decide($id,'approve','',$foreign,$v2['id'],$v2['content_hash']),'Proposal not found');
 foreach(['actual','selector','late'] as $mode){[$id,$client,$v1,$v2]=binding_seed();if($mode==='actual')$GLOBALS['support']=true;elseif($mode==='selector')$_GET['support']='invalid';else $GLOBALS['lockHook']=static function(){$GLOBALS['support']=true;};binding_deny('Support context '.$mode,fn()=>proposal_decide($id,'approve','',$client,$v2['id'],$v2['content_hash']),'Support sessions');}
 [$id,$client,$v1,$v2]=binding_seed();$GLOBALS['lockHook']=static function(){$GLOBALS['current']=null;};binding_deny('Authority revoked while acquiring lock',fn()=>proposal_decide($id,'approve','',$client,$v2['id'],$v2['content_hash']),'Current client authentication');
 foreach(['approve','changes'] as $kind){[$id,$client,$v1,$v2]=binding_seed();$GLOBALS['contactAllowed']=false;binding_deny('Non-billing contact '.$kind,fn()=>proposal_decide($id,$kind,'Reviewed terms',$client,$v2['id'],$v2['content_hash']),'contact permissions do not include billing');}
 [$id,$client,$v1,$v2]=binding_seed();$GLOBALS['lockHook']=static function(){$GLOBALS['contactAllowed']=false;};binding_deny('Contact grant revoked while acquiring lock',fn()=>proposal_decide($id,'approve','',$client,$v2['id'],$v2['content_hash']),'contact permissions do not include billing');
 [$id,$client,$v1,$v2]=binding_seed();$GLOBALS['failAudit']=true;binding_deny('History failure after writes',fn()=>proposal_decide($id,'approve','',$client,$v2['id'],$v2['content_hash']),'Synthetic history');
 foreach(['approve'=>'approved','changes'=>'changes-requested'] as $kind=>$status){[$id,$client,$v1,$v2]=binding_seed();proposal_decide($id,$kind,'Reviewed v2',$client,$v2['id'],$v2['content_hash']);$last=end($GLOBALS['rows']['decisions']);binding_ok('Exact v2 '.$kind.' succeeds',$GLOBALS['rows']['proposal']['status']===$status&&$last['version']===2&&$last['client_id']===1&&$last['decision']===$status);binding_deny('Repeated '.$kind.' cannot duplicate decision',fn()=>proposal_decide($id,$kind,'Repeat',$client,$v2['id'],$v2['content_hash']),'Cannot perform that action');}
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Actual proposal service and foundation models with strict synthetic SQL/auth/lock doubles; not installed or database acceptance','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;exit(isset($failure)?1:0);
