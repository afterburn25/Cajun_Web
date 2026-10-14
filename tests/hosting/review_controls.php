<?php
declare(strict_types=1);
require __DIR__.'/../../foundation/hosting/HostingAccount.php';
require __DIR__.'/../../foundation/hosting/PlanChange.php';
use CajunWebPro\Foundation\Hosting\{HostingAccount,HostingPackage,PlanChange,OperationResult};
$checks=[];
function check(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function denied(callable $fn):bool{try{$fn();return false;}catch(InvalidArgumentException|LogicException){return true;}}
function pkg():HostingPackage{return new HostingPackage('p','P','type','region',['disk_mb'=>20,'mailboxes'=>2]);}
function active(string $id):HostingAccount{$a=new HostingAccount($id,pkg(),'client',100);$a->beginProvisioning('staff',101);$a->provisionResult(OperationResult::applied(HostingAccount::OP_PROVISION,'receipt',102,$id,'resource-'.$id,$a->pendingAttempt()),'staff',102);return $a;}
$a=new HostingAccount('replay',pkg(),'client',100);$a->beginProvisioning('staff',101);$first=$a->pendingAttempt();$a->provisionResult(OperationResult::timeout(HostingAccount::OP_PROVISION,102,'replay',$first),'staff',102);$a->reconcileTimeout('staff',103,false,null,'Absent first attempt',$first);$a->retry('staff',104);$a->beginProvisioning('staff',105);$second=$a->pendingAttempt();$a->provisionResult(OperationResult::timeout(HostingAccount::OP_PROVISION,106,'replay',$second),'staff',106);$before=serialize($a);
check('Stale reconciliation identity cannot clear second uncertain provision',denied(fn()=>$a->reconcileTimeout('staff',103,false,null,'Absent first attempt',$first))&&serialize($a)===$before);
check('Second unknown provision still refuses retry and cancellation',denied(fn()=>$a->retry('staff',107))&&denied(fn()=>$a->cancelOrder('staff',107))&&$a->awaitingReconciliation());
check('Empty expected reconciliation attempt cannot mutate uncertainty',denied(fn()=>$a->reconcileTimeout('staff',107,false,null,'Absent',''))&&serialize($a)===$before);
$a->reconcileTimeout('staff',108,true,'verified-resource','Second attempt exists',$second);check('Exact current reconciliation adopts the resource',$a->status()===HostingAccount::ACTIVE&&$a->providerRef()==='verified-resource'&&!$a->awaitingReconciliation()&&$a->pendingAttempt()===null);
$before=serialize($a);check('Duplicate terminal reconciliation cannot change history',denied(fn()=>$a->reconcileTimeout('staff',108,true,'verified-resource','Second attempt exists',$second))&&serialize($a)===$before);
$notes=array_column($a->history(),'note');check('Reconciliation retains exact attempt identity',str_contains(implode('|',$notes),'attempt '.$second));
$c=new PlanChange('change','account',PlanChange::UPGRADE,pkg(),'owner',100,200);$c->approve([],200);$first=$c->dispatch();$c->recordResult(OperationResult::timeout('hosting.upgrade',201,'account',$first),201);$c->resolve(false,'First unapplied',202,$first);$second=$c->dispatch();$c->recordResult(OperationResult::timeout('hosting.upgrade',203,'account',$second),203);$before=serialize($c);
check('Stale plan reconciliation cannot clear second uncertainty',denied(fn()=>$c->resolve(false,'First unapplied',202,$first))&&serialize($c)===$before&&$c->status()===PlanChange::UNCERTAIN);
check('Empty plan attempt cannot mutate uncertainty',denied(fn()=>$c->resolve(false,'Empty',204,''))&&serialize($c)===$before);
$c->resolve(true,'Second applied',204,$second);check('Exact plan reconciliation applies and retains attempt',$c->status()===PlanChange::APPLIED&&str_contains($c->reason(),$second));$before=serialize($c);check('Duplicate plan resolution cannot rewrite terminal result',denied(fn()=>$c->resolve(false,'Late',205,$second))&&serialize($c)===$before);
foreach([false,true] as $retryable){
 $a=active('delete-'.(int)$retryable);$a->requestCancellation('first',103);$a->approveDeprovision('second',true,104);$old=$a->pendingAttempt();$a->deprovisionResult(OperationResult::failed(HostingAccount::OP_DEPROVISION,'Synthetic known failure',$retryable,105,$a->id,$old),'second',105);
 check('Deletion failure is unarmed '.(int)$retryable,$a->status()===HostingAccount::DEPROVISION_FAILED&&$a->pendingAttempt()===null&&!$a->awaitingReconciliation());
 $before=serialize($a);check('Delayed failed-attempt deletion result refused '.(int)$retryable,denied(fn()=>$a->deprovisionResult(OperationResult::applied(HostingAccount::OP_DEPROVISION,'late',106,$a->id,null,$old),'second',106))&&serialize($a)===$before);
 if(!$retryable){check('Unsafe deletion retry refuses without mutation',denied(fn()=>$a->retryDeprovision('second',106))&&serialize($a)===$before);}
 else{$a->retryDeprovision('second',106);$new=$a->pendingAttempt();check('Explicit eligible retry arms a fresh attempt',$new!==null&&$new!==$old&&$a->status()===HostingAccount::DEPROVISIONING);check('Older receipt cannot satisfy the eligible retry',denied(fn()=>$a->deprovisionResult(OperationResult::applied(HostingAccount::OP_DEPROVISION,'old',107,$a->id,null,$old),'second',107)));$a->deprovisionResult(OperationResult::applied(HostingAccount::OP_DEPROVISION,'current',108,$a->id,null,$new),'second',108);check('Exact eligible deletion retry completes',$a->status()===HostingAccount::DEPROVISIONED);}
}
$cap=20;$unmetered=null;$limits=['disk_mb'=>&$cap,'mailboxes'=>2,'bandwidth'=>&$unmetered];$p=new HostingPackage('p','P','type','region',$limits);$cap=999999;$unmetered=-99;$limits['mailboxes']=200;
check('Caller references and array mutation cannot alter package quotas',$p->limits===['disk_mb'=>20,'mailboxes'=>2,'bandwidth'=>null]);
$c=new PlanChange('down','account',PlanChange::DOWNGRADE,$p,'owner',100,200);$before=serialize($c);check('Partial downgrade stays undecided',denied(fn()=>$c->approve(['disk_mb'=>1],200))&&serialize($c)===$before);$c->approve(['disk_mb'=>1,'mailboxes'=>1],200);check('Complete fitting downgrade can still approve',$c->status()===PlanChange::APPROVED);
echo json_encode(['original_review_source'=>'c53dc4ae69f964fc53a42ee40aed61ba2ab24bdf','scope'=>'Independent synthetic model controls only; no provider/persistence/UI','passed'=>true,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
