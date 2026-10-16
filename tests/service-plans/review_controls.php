<?php
declare(strict_types=1);
$base=__DIR__.'/../../'.(in_array('--deployment',$argv,true)?'site/includes/':'').'foundation/service-plans/';
require $base.'ServicePlan.php';
require $base.'PlanSubscription.php';
use CajunWebPro\Foundation\ServicePlans\{ServicePlan,PlanSubscription};
function fixture(array $overrides=[]):ServicePlan {return ServicePlan::draft(...array_replace(['id'=>'plan','name'=>'Website care','cadence'=>'monthly','priceAmount'=>9900,'currency'=>'USD','inclusions'=>['Monthly updates'],'exclusions'=>['New pages'],'limits'=>['updates'=>1],'supportExpectation'=>'Two business days','createdBy'=>'owner'],$overrides));}
function denied(callable $c):bool {try{$c();return false;}catch(InvalidArgumentException|LogicException){return true;}}
$fixed=[];$p=fixture()->activate();$s=PlanSubscription::start('s',$p,'client-a','owner','2026-10-14T12:00:00Z');
$fixed['foreign client consent refused']=denied(fn()=>$s->recordConsent('client-b',$p->termsHash(),'2026-10-14T12:01:00Z'));
$fixed['different terms refused']=denied(fn()=>$s->recordConsent('client-a',str_repeat('0',64),'2026-10-14T12:01:00Z'));
$fixed['empty actor refused']=denied(fn()=>PlanSubscription::start('blank',$p,'client-a',' ','2026-10-14T12:00:00Z'));
$fixed['relative time refused']=denied(fn()=>PlanSubscription::start('relative',$p,'client-a','owner','tomorrow'));
$s->recordConsent('client-a',$p->termsHash(),'2026-10-14T12:01:00Z');$before=$s->history();
$fixed['out of order activation refused without mutation']=denied(fn()=>$s->activate('owner','2020-01-01T00:00:00Z'))&&$s->history()===$before&&$s->state()===PlanSubscription::PENDING_CONSENT;
$fixed['nonstring terms refused']=denied(fn()=>fixture(['inclusions'=>[true,25,null]]));
$fixed['whitespace support refused']=denied(fn()=>fixture(['supportExpectation'=>'   ']));
$fixed['newline currency refused']=denied(fn()=>fixture(['currency'=>"USD\n"]));
$fixed['invalid UTF8 refused']=denied(fn()=>fixture(['inclusions'=>["bad\xC3\x28"]]));
$term=new class {public string $value='Monthly updates';public function __toString():string{return $this->value;}};
$fixed['mutable object terms refused']=denied(fn()=>fixture(['inclusions'=>[$term]]));
$fixed['numeric meter names refused']=denied(fn()=>fixture(['limits'=>[1,2]]));
$s->activate('owner','2026-10-14T12:02:00Z');$fixed['valid pinned client consent activates']=$s->state()===PlanSubscription::ACTIVE;

$cap=1;$limits=['updates'=>&$cap];$p=fixture(['limits'=>$limits]);$beforeDraft=$p->termsHash();$cap=900;
$fixed['draft limits detach caller references']=$p->limits['updates']===1&&$p->termsHash()===$beforeDraft;
$p->activate();$hash=$p->termsHash();$s=PlanSubscription::start('ref',$p,'client-a','owner','2026-10-14T12:00:00Z');$cap=-99;
$fixed['active plan retains immutable limit and terms hash']=$p->limits['updates']===1&&$p->termsHash()===$hash;
$s->recordConsent('client-a',$hash,'2026-10-14T12:01:00Z')->activate('owner','2026-10-14T12:02:00Z');
$fixed['consent matches unchanged exposed subscription terms']=$s->consent()['termsHash']===$s->plan->termsHash()&&$s->plan->limits['updates']===1&&$s->state()===PlanSubscription::ACTIVE;
$fixed['canonical meter refuses final LF']=denied(fn()=>fixture(['limits'=>["updates\n"=>1]]));
$fixed['canonical meter refuses final CRLF']=denied(fn()=>fixture(['limits'=>["updates\r\n"=>1]]));
$fixed['creation refuses noncanonical final LF']=denied(fn()=>PlanSubscription::start('lf',fixture()->activate(),'client-a','owner',"2026-10-14T12:00:00Z\n"));
$before=serialize($s);
$fixed['transition final LF refused with complete unchanged state']=denied(fn()=>$s->pause('owner',"2026-10-14T12:03:00Z\n"))&&serialize($s)===$before;
$pending=PlanSubscription::start('pending',$p,'client-a','owner','2026-10-14T12:00:00Z');$before=serialize($pending);
$fixed['consent final LF refused with complete unchanged state']=denied(fn()=>$pending->recordConsent('client-a',$hash,"2026-10-14T12:01:00Z\n"))&&serialize($pending)===$before;
$included='Original scope';$excluded='Original exclusion';$p=fixture(['inclusions'=>[&$included],'exclusions'=>[&$excluded]])->activate();$hash=$p->termsHash();$included='Changed';$excluded='Changed';
$fixed['other retained term arrays remain reference free']=$p->inclusions===['Original scope']&&$p->exclusions===['Original exclusion']&&$p->termsHash()===$hash;
echo json_encode(['source'=>'e7a283b5a32c6c2f859609d42797ba30275f9198','scope'=>'Independent isolated correction controls; no billing, persistence, UI or network','passed'=>!in_array(false,$fixed,true),'checks'=>array_map(fn($name,$passed)=>['name'=>$name,'passed'=>$passed],array_keys($fixed),array_values($fixed))],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
exit(in_array(false,$fixed,true)?1:0);
