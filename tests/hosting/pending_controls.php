<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once __DIR__.'/../../foundation/hosting/HostingJournal.php';
use CajunWebPro\Foundation\Hosting\{HostingJournal,HostingPackage,OperationResult};
$checks=[];
function hp_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function hp_deny(string $name,callable $fn):void{try{$fn();}catch(InvalidArgumentException|LogicException){hp_ok($name,true);return;}hp_ok($name,false);}
function hp_step(HostingJournal $j,string $command,int $at,array $args=[]):HostingJournal{return $j->append(str_pad(dechex($j->revision()+1),32,'0',STR_PAD_LEFT),$command,'staff:1',$at,$args);}
try{
    $j=HostingJournal::create('account-pending',new HostingPackage('WEB-P','Synthetic package','811','Synthetic location',['sites'=>1]),'client:1',10);
    $j=hp_step($j,'begin_provisioning',11);$before=$j->json();$attempt=$j->account()->pendingAttempt();
    $result=OperationResult::acceptedPending('hosting.provision','866239',12,'account-pending',$attempt);
    hp_ok('Assigned ID acknowledgement is neither applied nor retryable',!$result->ok()&&!$result->retryable&&$result->receipt===null&&$result->error===null);
    foreach([['hosting.suspend','866239',12,'account-pending',$attempt],['hosting.provision','',12,'account-pending',$attempt],['hosting.provision',"866239\n",12,'account-pending',$attempt],['hosting.provision',str_repeat('x',257),12,'account-pending',$attempt],['hosting.provision','866239',-1,'account-pending',$attempt],['hosting.provision','866239',12,'account-pending','']] as $n=>$bad)hp_deny('Pending result rejects malformed identity '.$n,fn()=>OperationResult::acceptedPending(...$bad));
    hp_deny('Pending identity rejects invalid UTF-8 bytes',fn()=>OperationResult::acceptedPending('hosting.provision',"\xff",12,'account-pending',$attempt));
    hp_deny('Pending identity requires its original subject',fn()=>OperationResult::acceptedPending('hosting.provision','866239',12,'',$attempt));
    $data=['operation'=>'hosting.provision','outcome'=>'accepted_pending','at'=>12,'subject_ref'=>'account-pending','receipt'=>null,'resource_ref'=>'866239','error'=>null,'retryable'=>false,'attempt_ref'=>$attempt];
    foreach([['receipt'=>'fabricated-receipt'],['resource_ref'=>null],['error'=>'failure'],['retryable'=>true],['operation'=>'hosting.suspend'],['attempt_ref'=>'other-attempt'],['subject_ref'=>'other-account']] as $n=>$bad)hp_deny('Canonical journal refuses contradictory pending result '.$n,fn()=>hp_step($j,'record_provision',12,['result'=>array_replace($data,$bad)]));
    hp_ok('Refused observations preserve original dispatch document',$j->json()===$before);
    $pending=hp_step($j,'record_provision',12,['result'=>$data]);$a=$pending->account();
    hp_ok('Accepted package remains provisioning and requires reconciliation',$a->status()==='provisioning'&&$a->awaitingReconciliation());
    hp_ok('Acknowledged ID is retained separately from active resource',$a->acknowledgedResourceRef()==='866239'&&$a->providerRef()===null&&$a->pendingAttempt()===$attempt);
    hp_ok('Pending canonical round trip retains original request and response',HostingJournal::fromJson($pending->json(),'account-pending','client:1')->json()===$pending->json()&&$j->json()===$before);
    hp_deny('Acknowledgement cannot trigger another provisioning dispatch',fn()=>hp_step($pending,'begin_provisioning',13));
    hp_deny('Acknowledgement cannot trigger a provisioning retry',fn()=>hp_step($pending,'retry_provision',13));
    hp_deny('Accepted provider purchase cannot be locally cancelled as unstarted',fn()=>hp_step($pending,'cancel_order',13));
    hp_deny('Second response cannot replace original accepted observation',fn()=>hp_step($pending,'record_provision',13,['result'=>$data]));
    $applied=array_replace($data,['outcome'=>'applied','receipt'=>'synthetic-later-receipt']);
    hp_deny('Delayed success must reconcile rather than replace original response',fn()=>hp_step($pending,'record_provision',13,['result'=>$applied]));
    foreach(['reconcile','reconcile_without_dispatch'] as $command){
        foreach([['applied_remotely'=>false,'resource_ref'=>null],['applied_remotely'=>true,'resource_ref'=>'foreign-id']] as $n=>$bad){
            $args=$bad+['evidence'=>'Synthetic exact-resource evidence','attempt_ref'=>$attempt];
            hp_deny($command.' cannot discard or substitute acknowledged identity '.$n,fn()=>hp_step($pending,$command,13,$args));
        }
    }
    $a=$pending->account();$snapshot=serialize($a);
    hp_deny('Direct reconciliation refuses a substituted ID before mutation',fn()=>$a->reconcileWithoutDispatch('staff:1',13,true,'foreign-id','Synthetic evidence',$attempt));
    hp_ok('Rejected reconciliation preserves acknowledged state and all history',serialize($a)===$snapshot);
    $done=hp_step($pending,'reconcile_without_dispatch',13,['applied_remotely'=>true,'resource_ref'=>'866239','evidence'=>'Synthetic exact-resource confirmation','attempt_ref'=>$attempt]);
    hp_ok('Only exact-ID conclusive confirmation activates the hosting record',$done->account()->status()==='active'&&$done->account()->providerRef()==='866239');
    hp_ok('Confirmation clears pending state without arming a new attempt',!$done->account()->awaitingReconciliation()&&$done->account()->pendingAttempt()===null&&$done->account()->acknowledgedResourceRef()===null);
    hp_ok('Confirmed history retains original accepted response unchanged',$done->account()->operations()[0]->outcome==='accepted_pending'&&$done->account()->operations()[0]->resourceRef==='866239'&&count($done->account()->operations())===1);
    hp_ok('Confirmed canonical history round trips exactly',HostingJournal::fromJson($done->json(),'account-pending','client:1')->json()===$done->json());
}catch(Throwable $e){
    $checks[]=['name'=>'Unexpected pending lifecycle failure: '.$e->getMessage(),'passed'=>false];throw $e;
}finally{
    echo json_encode(['scope'=>'Synthetic canonical accepted-pending lifecycle and exact assigned-ID reconciliation; no provider call, entitlement or native database acceptance','passed'=>count($checks)>0&&!in_array(false,array_column($checks,'passed'),true),'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
}
