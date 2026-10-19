<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$deployment=in_array('--deployment',$argv,true);
require __DIR__.($deployment?'/../../site/includes':'/../..').'/foundation/desk/FulfillmentJob.php';
use CajunWebPro\Foundation\Desk\{TraceLink,ApprovalRequest,FulfillmentJob};
$checks=[];
function check(string $name, bool $passed):void {global $checks; $checks[]=['name'=>$name,'passed'=>$passed]; if(!$passed)throw new RuntimeException($name);}
function refused(callable $action):bool {try{$action();return false;}catch(LogicException){return true;}}
$subject=new TraceLink('hosting_service','synthetic-service');
$a=new ApprovalRequest('approval','deprovision',$subject,'requester',100,200);
check('Decision before request is refused',refused(fn()=>$a->approve('reviewer','owner',99)));
$a->approve('reviewer','owner',110);
check('Authority is absent before decision',!$a->approved(109));
check('Authority begins at decision',$a->approved(110));
check('Authority remains live just before expiry',$a->approved(199));
check('Approved authority expires at boundary',$a->status(200)==='expired'&&!$a->approved(200));
$job=new FulfillmentJob('expired-approval','deprovision','synthetic-key',[$subject]);
check('Expired approved request cannot release destructive job',refused(fn()=>$job->run('worker',300,10,$a)));
foreach(['different-worker','same-worker'] as $mode){
 foreach(['success','failure'] as $outcome){
  $j=new FulfillmentJob($mode.$outcome,'provision','key-'.$mode.$outcome,[$subject]);
  $old=$j->run('first-worker',100,10);
  check($mode.' '.$outcome.' lease expires',$j->expireLease(110)&&$j->attemptToken()===null);
  $j->resolveStalled($old,'not_executed','Synthetic verified absence','reviewer',111);
  $j->requeue('operator',111);
  $new=$j->run($mode==='same-worker'?'first-worker':'second-worker',112,10);
  $before=serialize($j);
  $denied=refused(fn()=>$outcome==='success'?$j->succeed('first-worker',113,$old):$j->fail('late failure','first-worker',113,$old));
  check($mode.' '.$outcome.' stale result refused with full state unchanged',$denied&&serialize($j)===$before&&$old!==$new);
  $j->succeed('current-worker',114,$new);
  check($mode.' '.$outcome.' current exact token can settle',$j->status()==='succeeded');
 }
}
$denied=new ApprovalRequest('denied','deprovision',$subject,'requester',100,200);
$denied->deny('reviewer','owner',110,'Original reason');$before=serialize($denied);
check('Rejected repeated denial preserves full state',refused(fn()=>$denied->deny('requester','administrator',120,'Replacement'))&&serialize($denied)===$before);
$approved=new ApprovalRequest('approved','deprovision',$subject,'requester',100,200);
$approved->approve('reviewer','owner',110);$before=serialize($approved);
check('Rejected denial on approval preserves full state',refused(fn()=>$approved->deny('requester','administrator',120,'Injected'))&&serialize($approved)===$before);

foreach(['provision','refund'] as $kind){
 $link=new TraceLink($kind==='refund'?'payment':'hosting_service','synthetic-'.$kind);
 $a=new ApprovalRequest('approval-'.$kind,$kind,$link,'requester',90,1000);$a->approve('reviewer','owner',95);
 $j=new FulfillmentJob('unknown-'.$kind,$kind,'idempotency-'.$kind,[$link]);
 $old=$j->run('first-worker',100,10,$a);$j->expireLease(110);$before=serialize($j);
 check($kind.' unresolved requeue now refuses with no mutation',refused(fn()=>$j->requeue('operator',111))&&serialize($j)===$before);
 check($kind.' unresolved replacement claim also refuses',refused(fn()=>$j->run('replacement-worker',112,10,$a))&&serialize($j)===$before);
 $j->resolveStalled($old,'not_executed','Synthetic conclusive absence','reviewer',113);$j->requeue('operator',114);$new=$j->run('replacement-worker',115,10,$a);
 check($kind.' verified absence permits a separately fenced retry',$new!==$old&&$j->attempts()===2&&$j->status()==='running');
}

foreach(['completed','not_executed'] as $outcome){
 $j=new FulfillmentJob('recovery-'.$outcome,'provision','key-'.$outcome,[$subject]);
 $token=$j->run('worker',100,10);$j->expireLease(110);
 $j->resolveStalled($token,'unknown','Provider temporarily unavailable','reviewer',111);
 $first=$j->observations($token);$before=serialize($j);
 check($outcome.' unknown remains blocked without final resolution',refused(fn()=>$j->requeue('operator',112))&&serialize($j)===$before&&$j->resolution($token)===null);
 $j->resolveStalled($token,'unknown','Provider temporarily unavailable','reviewer',113);
 check($outcome.' identical unknown observation does not duplicate',serialize($j)===$before);
 $j->resolveStalled($token,'unknown','Second verification remains unclear','other-reviewer',114);
 check($outcome.' distinct unknown observations retain original metadata',count($j->observations($token))===2&&$j->observations($token)[0]===$first[0]);
 $j->resolveStalled($token,$outcome,'Conclusive exact-request evidence','reviewer',120);
 check($outcome.' later conclusion preserves unknown history',$j->resolution($token)===['outcome'=>$outcome,'evidence'=>'Conclusive exact-request evidence','actor'=>'reviewer','at'=>120]&&count($j->observations($token))===2);
 if($outcome==='completed'){
  check('Verified completed cannot dispatch again',$j->status()==='succeeded'&&refused(fn()=>$j->run('worker',121,10)));
 }else{
  $j->requeue('operator',121);$next=$j->run('worker',122,10);
  check('Verified absence permits a distinct fenced attempt',$next!==$token&&$j->status()==='running');
 }
 $before=serialize($j);$j->resolveStalled($token,$outcome,'Conclusive exact-request evidence','reviewer',125);
 check($outcome.' consumed conclusive replay leaves complete state unchanged',serialize($j)===$before);
 check($outcome.' changed evidence replay refuses without mutation',refused(fn()=>$j->resolveStalled($token,$outcome,'Changed evidence','reviewer',126))&&serialize($j)===$before);
 check($outcome.' opposite conclusive outcome refuses without mutation',refused(fn()=>$j->resolveStalled($token,$outcome==='completed'?'not_executed':'completed','Conclusive exact-request evidence','reviewer',126))&&serialize($j)===$before);
 check($outcome.' wrong token refuses without mutation',refused(fn()=>$j->resolveStalled('missing-token',$outcome,'Conclusive exact-request evidence','reviewer',126))&&serialize($j)===$before);
}
echo json_encode(['source'=>'df0a1d23f5900541557b795511332d1e80be5e52','deployment'=>$deployment,'scope'=>'Independent isolated service-desk correction controls; no queue, provider, billing or application activation','passed'=>!in_array(false,array_column($checks,'passed'),true),'checks'=>$checks],JSON_PRETTY_PRINT).PHP_EOL;
