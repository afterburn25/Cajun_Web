<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once __DIR__.'/../../foundation/hosting/HostingJournal.php';
use CajunWebPro\Foundation\Hosting\{HostingJournal,HostingPackage,OperationResult};
$checks=[];
function rc_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function rc_deny(string $name,callable $fn):void{try{$fn();}catch(InvalidArgumentException|LogicException){rc_ok($name,true);return;}rc_ok($name,false);}
function rc_step(HostingJournal $j,string $command,int $at,array $args=[],string $actor='staff:1'):HostingJournal{return $j->append(str_pad(dechex($j->revision()+1),32,'0',STR_PAD_LEFT),$command,$actor,$at,$args);}
function rc_result(HostingJournal $j,string $operation,string $outcome,int $at,?string $resource=null,?string $attempt=null):HostingJournal{
    return rc_step($j,'record_'.$operation,$at,['result'=>['operation'=>'hosting.'.$operation,'outcome'=>$outcome,'at'=>$at,'subject_ref'=>'account-rc','receipt'=>$outcome==='applied'?'synthetic-receipt':null,'resource_ref'=>$resource,'error'=>null,'retryable'=>false,'attempt_ref'=>$attempt??$j->account()->pendingAttempt()]]);
}
function rc_unknown(string $op):HostingJournal{
    $j=HostingJournal::create('account-rc',new HostingPackage('WEB-RC','Synthetic reconciliation','synthetic-type','synthetic-region',['sites'=>1]),'client:1',10);
    $j=rc_step($j,'begin_provisioning',11);
    if($op==='provision')return rc_result($j,'provision','timeout',12);
    $j=rc_result($j,'provision','applied',12,'original-resource');
    if($op==='deprovision'){
        $j=rc_step($j,'request_cancellation',13);$j=rc_step($j,'approve_deprovision',14,['retention_confirmed'=>true],'staff:2');
        return rc_result($j,'deprovision','timeout',15);
    }
    $j=rc_step($j,'suspend',13,['reason'=>'Synthetic confirmed policy']);
    if($op==='suspend')return rc_result($j,'suspend','timeout',14);
    $j=rc_result($j,'suspend','applied',14);$j=rc_step($j,'resume',15);return rc_result($j,'resume','timeout',16);
}
function rc_args(HostingJournal $j,bool $applied,?string $resource):array{return ['applied_remotely'=>$applied,'resource_ref'=>$resource,'evidence'=>'Synthetic exact-provider observation','attempt_ref'=>$j->account()->pendingAttempt()];}
try{
    foreach(['provision','suspend','resume','deprovision'] as $operation){
        foreach([false,true] as $applied){
            $label=$operation.' '.($applied?'applied':'not-applied');$prior=rc_unknown($operation);$original=$prior->json();$attempt=$prior->account()->pendingAttempt();
            $resource=$operation==='provision'?($applied?'adopted-resource':null):'original-resource';$args=rc_args($prior,$applied,$resource);
            $j=rc_step($prior,'reconcile_without_dispatch',30,$args,'staff:2');$a=$j->account();
            $state=match($operation){'provision'=>$applied?'active':'failed','suspend'=>$applied?'suspended':'active','resume'=>$applied?'active':'suspended','deprovision'=>$applied?'deprovisioned':'deprovision_failed'};
            rc_ok($label.' resolves observed state without a pending attempt',$a->status()===$state&&$a->pendingAttempt()===null&&!$a->awaitingReconciliation());
            rc_ok($label.' retains original resource and every earlier observation',$a->providerRef()===($operation==='provision'?$resource:'original-resource')&&$a->operations()==$prior->account()->operations()&&$prior->json()===$original);
            $copy=HostingJournal::fromJson($j->json(),'account-rc','client:1');
            rc_ok($label.' survives exact canonical journal reconstruction',$copy->json()===$j->json()&&$copy->fingerprint()===$j->fingerprint()&&$copy->account()->pendingAttempt()===null&&$copy->account()->status()===$state);
            $history=$a->history();$last=$history[array_key_last($history)];
            rc_ok($label.' records original attempt and evidence in retained history',str_contains($last['note'],$attempt)&&str_contains($last['note'],$args['evidence'])&&$last['actor']==='staff:2');
            $event=json_decode($j->json(),true)['events'][$j->revision()-1];
            rc_ok($label.' exact reconciliation event replay is idempotent',$j->append($event['id'],$event['command'],$event['actor'],$event['at'],$event['arguments'])===$j);
            rc_deny($label.' changed decision cannot reuse reconciliation identity',fn()=>$j->append($event['id'],$event['command'],$event['actor'],$event['at'],array_replace($args,['applied_remotely'=>!$applied])));
            rc_deny($label.' second reconciliation cannot rewrite a settled attempt',fn()=>rc_step($j,'reconcile_without_dispatch',31,$args,'staff:2'));
            if(!$applied&&in_array($operation,['provision','deprovision'],true)){
                $retry=rc_step($j,$operation==='provision'?'retry_provision':'retry_deprovision',31,[],'staff:2');
                if($operation==='provision')$retry=rc_step($retry,'begin_provisioning',32);
                [$identity,$number]=explode('#',$attempt);
                rc_ok($label.' only an explicit retry allocates the next distinct attempt',$retry->account()->pendingAttempt()===$identity.'#'.((int)$number+1));
                rc_deny($label.' old delayed result cannot settle that later retry',fn()=>rc_result($retry,$operation,'applied',33,$resource,$attempt));
                $restored=HostingJournal::fromJson($retry->json(),'account-rc','client:1');
                rc_ok($label.' separately retried journal retains reconciliation and current attempt',$restored->json()===$retry->json()&&$restored->account()->pendingAttempt()===$retry->account()->pendingAttempt());
            }
        }
    }
    foreach(['provision','deprovision'] as $operation){
        $j=rc_unknown($operation);$resource=$operation==='provision'?null:'original-resource';$args=rc_args($j,false,$resource);
        foreach(['attempt'=>['attempt_ref'=>'wrong-attempt'],'evidence'=>['evidence'=>'  '],'resource'=>['resource_ref'=>'substituted-resource']] as $name=>$change){
            $a=$j->account();$before=serialize($a);$v=array_replace($args,$change);
            rc_deny($operation.' refuses malformed '.$name.' before mutation',fn()=>$a->reconcileWithoutDispatch('staff:2',30,$v['applied_remotely'],$v['resource_ref'],$v['evidence'],$v['attempt_ref']));
            rc_ok($operation.' malformed '.$name.' preserves original uncertain state',serialize($a)===$before);
        }
        foreach([['',30],['staff:2',-1],['staff:2',10]] as $index=>[$actor,$at]){
            $a=$j->account();$before=serialize($a);
            rc_deny($operation.' refuses absent actor or impossible time '.$index,fn()=>$a->reconcileWithoutDispatch($actor,$at,false,$resource,$args['evidence'],$args['attempt_ref']));
            rc_ok($operation.' refused actor/time leaves uncertainty intact '.$index,serialize($a)===$before);
        }
    }
    $j=rc_unknown('provision');$args=rc_args($j,true,null);$a=$j->account();$before=serialize($a);
    rc_deny('Applied provisioning without a resource cannot be adopted',fn()=>$a->reconcileWithoutDispatch('staff:2',30,true,null,$args['evidence'],$args['attempt_ref']));
    rc_ok('Missing adoption identity leaves the unknown attempt unchanged',serialize($a)===$before);
    foreach(['suspend','resume'] as $operation){
        $j=rc_unknown($operation);$a=$j->account();$before=serialize($a);
        rc_deny($operation.' reconciliation cannot substitute resource identity',fn()=>$a->reconcileWithoutDispatch('staff:2',30,true,'foreign-resource','Synthetic mismatch',$a->pendingAttempt()));
        rc_ok($operation.' mismatched resource leaves the unknown attempt unchanged',serialize($a)===$before);
    }
    $prior=rc_unknown('deprovision');$args=rc_args($prior,false,'original-resource');
    $legacy=rc_step($prior,'reconcile',30,$args,'staff:2');$modern=rc_step($prior,'reconcile_without_dispatch',30,$args,'staff:2');
    rc_ok('Existing legacy reconciliation command retains its historical armed state',$legacy->account()->status()==='deprovisioning'&&$legacy->account()->pendingAttempt()==='account-rc#3');
    rc_ok('New command is distinguishable and never silently rewrites legacy journal semantics',$legacy->json()!==$modern->json()&&$modern->account()->status()==='deprovision_failed'&&$modern->account()->pendingAttempt()===null);
    $d=json_decode($modern->json(),true);$d['events'][count($d['events'])-1]['arguments']['applied_remotely']='false';
    rc_deny('New journal decision requires a true boolean',fn()=>HostingJournal::fromJson(json_encode($d,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),'account-rc','client:1'));
}finally{
    echo json_encode(['scope'=>'Canonical state-only reconciliation and compatibility; synthetic observations, no provider evidence validation, database or dispatch authority','passed'=>count($checks)>0&&!in_array(false,array_column($checks,'passed'),true),'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
}
