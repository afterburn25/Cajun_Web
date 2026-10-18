<?php
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',__DIR__.'/../../site');require CWP_CODE_ROOT.'/app/plans/documents.php';
$checks=[];
function sp_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function sp_deny(string $name,callable $action):void {try{$action();}catch(InvalidArgumentException|LogicException|JsonException){sp_ok($name,true);return;}sp_ok($name,false);}
function sp_id(int $id):string {return str_pad(dechex($id),32,'0',STR_PAD_LEFT);}
$terms=['name'=>'Website care café','cadence'=>'monthly','price'=>9900,'currency'=>'USD','inclusions'=>['Reviewed updates'],'exclusions'=>['New features'],'limits'=>['updates'=>4],'support'=>'Response within two business days'];
try {
    $draft=ServicePlanDocument::create(sp_id(1),1,100,$terms);sp_ok('New bounded plan is a draft',$draft->model()->status()==='draft'&&$draft->revision()===0);
    $changed=$draft->append(sp_id(2),'replace_draft',2,101,['terms'=>service_plans_terms(array_replace($terms,['price'=>12900]))]);sp_ok('Draft edit retains original creation and prior terms',$changed->prefix(0)->json()===$draft->json()&&$changed->model()->priceAmount===12900);
    $published=$changed->append(sp_id(3),'activate',1,102,[]);$hash=$published->model()->termsHash();
    sp_ok('Canonical plan round trip preserves exact price and terms hash',ServicePlanDocument::fromJson($published->json())->model()->termsHash()===$hash);
    $copy=$published->model();$copy->retire();sp_ok('Mutating a returned model cannot retire the saved document',$published->model()->status()==='active');
    sp_deny('Published terms cannot be replaced',fn()=>$published->append(sp_id(4),'replace_draft',1,103,['terms'=>$terms]));
    sp_ok('Exact repeated plan event is idempotent',$published->append(sp_id(3),'activate',1,102,[])->json()===$published->json());
    sp_deny('Changed event identity cannot replace an old plan action',fn()=>$published->append(sp_id(3),'retire',1,103,[]));
    sp_deny('Older plan event time is refused',fn()=>$published->append(sp_id(4),'retire',1,101,[]));
    sp_deny('Unknown plan commands cannot dispatch arbitrary model methods',fn()=>$published->append(sp_id(4),'termsHash',1,103,[]));
    $retired=$published->append(sp_id(4),'retire',1,103,[]);sp_ok('Retirement retains the original published prefix',$retired->prefix(2)->json()===$published->json());
    $sub=ServiceSubscriptionDocument::create(sp_id(10),7,1,102,$published);sp_ok('A new offer starts pending agreement',$sub->model()->state()==='pending-consent'&&$sub->model()->consent()===null);
    sp_deny('A retired plan cannot create a new offer',fn()=>ServiceSubscriptionDocument::create(sp_id(11),7,1,104,$retired));
    sp_deny('An offer cannot predate its active plan',fn()=>ServiceSubscriptionDocument::create(sp_id(11),7,1,101,$published));
    sp_deny('Owner cannot record the client agreement',fn()=>$sub->append(sp_id(12),'consent','staff:1',103,['terms_hash'=>$hash]));
    sp_deny('Another client cannot consent',fn()=>$sub->append(sp_id(12),'consent','client:8',103,['terms_hash'=>$hash]));
    sp_deny('Consent requires the exact presented terms',fn()=>$sub->append(sp_id(12),'consent','client:7',103,['terms_hash'=>str_repeat('0',64)]));
    sp_deny('Subscription cannot activate before client agreement',fn()=>$sub->append(sp_id(12),'activate','staff:1',103,[]));
    $agreed=$sub->append(sp_id(12),'consent','client:7',103,['terms_hash'=>$hash]);$active=$agreed->append(sp_id(13),'activate','staff:2',104,[]);
    sp_ok('Agreed subscription pins the reviewed original price and terms',$active->model()->agreedPrice===12900&&$active->model()->consent()['termsHash']===$hash&&$active->model()->plan->termsHash()===$hash);
    sp_ok('Parent retirement cannot mutate a pinned active agreement',$active->model()->state()==='active'&&$active->model()->plan->subscribable());
    sp_deny('Client cannot impersonate staff activation',fn()=>$agreed->append(sp_id(13),'activate','client:7',104,[]));
    sp_deny('Client cannot impersonate staff pause',fn()=>$active->append(sp_id(14),'pause','client:7',105,[]));
    $paused=$active->append(sp_id(14),'pause','staff:1',105,[]);$resumed=$paused->append(sp_id(15),'resume','staff:2',106,[]);$cancelled=$resumed->append(sp_id(16),'cancel','client:7',107,['reason'=>'No longer needed']);
    sp_ok('Attributed lifecycle replays without rewriting agreement',$cancelled->model()->state()==='cancelled'&&array_column($cancelled->model()->history(),'action')===['start','consent','activate','pause','resume','cancel: No longer needed']&&$cancelled->model()->consent()===$active->model()->consent());
    sp_deny('Terminal subscription cannot resume',fn()=>$cancelled->append(sp_id(17),'resume','staff:1',108,[]));
    sp_deny('Subscription cannot reuse an old event identity with changed actor',fn()=>$active->append(sp_id(12),'consent','staff:1',103,['terms_hash'=>$hash]));
    sp_ok('Old exact consent replay preserves later subscription history',$active->append(sp_id(12),'consent','client:7',103,['terms_hash'=>$hash])->json()===$active->json());
    sp_deny('Subscription refuses time regression',fn()=>$active->append(sp_id(14),'pause','staff:1',101,[]));
    sp_ok('Subscription canonical JSON round trip preserves full history',ServiceSubscriptionDocument::fromJson($cancelled->json())->json()===$cancelled->json());
    $mutable=$active->model();$mutable->plan->retire();sp_ok('Mutable plan returned from subscription cannot alter stored consent',$active->model()->plan->subscribable()&&$active->model()->consent()['termsHash']===$hash);
    foreach(['name'=>str_repeat('n',121),'currency'=>'usd','price'=>'9900','cadence'=>'weekly','inclusions'=>array_fill(0,33,'Term'),'exclusions'=>[true],'support'=>'Unlimited changes','limits'=>['bad-name'=>2]] as $key=>$value)sp_deny('Bounded terms refuse malformed '.$key,fn()=>ServicePlanDocument::create(sp_id(20),1,100,array_replace($terms,[$key=>$value])));
    foreach(['format'=>2,'created_by'=>'1','created_at'=>-1,'id'=>sp_id(1)."\n",'events'=>[['id'=>sp_id(2)]]] as $key=>$value)sp_deny('Plan document rejects malformed '.$key,fn()=>ServicePlanDocument::fromJson(service_plans_json(array_replace($published->data(),[$key=>$value]))));
    sp_deny('Duplicate JSON identity fields are refused',fn()=>ServicePlanDocument::fromJson(str_replace('"format":1','"format":1,"format":1',$draft->json())));
    sp_deny('Unexpected document fields are refused',fn()=>ServicePlanDocument::fromJson(service_plans_json($draft->data()+['class'=>'arbitrary'])));
    sp_deny('Oversized JSON is refused before parsing',fn()=>ServicePlanDocument::fromJson(str_repeat(' ',ServicePlanDocument::MAX_BYTES+1)));
    sp_deny('Unknown subscription action is refused',fn()=>$sub->append(sp_id(12),'charge','staff:1',103,[]));
    sp_deny('Cancellation without a concrete reason is refused',fn()=>$active->append(sp_id(14),'cancel','client:7',105,['reason'=>' ']));
    $abandoned=$sub->append(sp_id(14),'abandon','client:7',105,[]);sp_ok('Client can abandon an offer without ever authorizing activation',$abandoned->model()->state()==='abandoned'&&$abandoned->model()->consent()===null);
    foreach(['one-time','monthly','quarterly','annually'] as $cadence){
        $plan=ServicePlanDocument::create(sp_id(30),1,100,array_replace($terms,['cadence'=>$cadence]))->append(sp_id(31),'activate',1,101,[]);$offer=ServiceSubscriptionDocument::create(sp_id(32),7,1,102,$plan);
        sp_deny($cadence.' application enrollment requires explicit client agreement',fn()=>$offer->append(sp_id(33),'activate','staff:1',103,[]));
        $offer=$offer->append(sp_id(34),'consent','client:7',103,['terms_hash'=>$plan->model()->termsHash()])->append(sp_id(35),'activate','staff:1',104,[]);sp_ok($cadence.' accepted terms can activate locally without a billing call',$offer->model()->state()==='active');
    }
    $bounded=$draft->data();$bounded['events']=[];
    for($i=0;$i<ServicePlanDocument::MAX_EVENTS-2;$i++)$bounded['events'][]=['id'=>sp_id(1000+$i),'command'=>'replace_draft','actor'=>1,'at'=>100+$i,'arguments'=>['terms'=>$terms]];
    $fullDraft=ServicePlanDocument::fromJson(service_plans_json($bounded));
    sp_deny('Draft history reserves publication and retirement slots',fn()=>$fullDraft->append(sp_id(2000),'replace_draft',1,400,['terms'=>$terms]));
    $fullPlan=$fullDraft->append(sp_id(2001),'activate',1,400,[])->append(sp_id(2002),'retire',1,401,[]);
    sp_ok('A full draft history still permits publication followed by retirement',$fullPlan->revision()===256&&$fullPlan->model()->status()==='retired');
    $first=$bounded['events'][0];sp_ok('Exact old plan event replay remains a no-op at the history cap',$fullPlan->append($first['id'],$first['command'],$first['actor'],$first['at'],$first['arguments'])->json()===$fullPlan->json());
    sp_deny('Plan cannot exceed its bounded history',fn()=>$fullPlan->append(sp_id(2003),'retire',1,402,[]));
    $bounded=$active->data();
    for($i=2;$i<ServiceSubscriptionDocument::MAX_EVENTS-1;$i++)$bounded['events'][]=['id'=>sp_id(3000+$i),'command'=>$i%2===0?'pause':'resume','actor'=>'staff:1','at'=>105+$i,'arguments'=>[]];
    $fullSub=ServiceSubscriptionDocument::fromJson(service_plans_json($bounded));
    sp_deny('Long subscription history reserves the last slot for ending service',fn()=>$fullSub->append(sp_id(4000),'resume','staff:1',500,[]));
    $ended=$fullSub->append(sp_id(4001),'cancel','client:7',500,['reason'=>'End retained service']);
    sp_ok('Client can cancel at the bound while retaining all previous events and agreement',$ended->revision()===256&&$ended->model()->state()==='cancelled'&&$ended->model()->consent()===$active->model()->consent());
    sp_ok('Original consent replay stays idempotent after bounded cancellation',$ended->append(sp_id(12),'consent','client:7',103,['terms_hash'=>$hash])->json()===$ended->json());
    sp_deny('Subscription cannot exceed its bounded history',fn()=>$ended->append(sp_id(4002),'cancel','client:7',501,['reason'=>'Again']));
    sp_deny('Oversized subscription JSON is refused before parsing',fn()=>ServiceSubscriptionDocument::fromJson(str_repeat(' ',ServiceSubscriptionDocument::MAX_BYTES+1)));
    $large=service_plans_terms(array_replace($terms,['inclusions'=>array_fill(0,32,str_repeat('x',1000)),'exclusions'=>array_fill(0,32,str_repeat('y',1000))]));
    $bytesBound=$draft->data();$target=ServicePlanDocument::MAX_BYTES-1024;
    do{$i=count($bytesBound['events']);$bytesBound['events'][]=['id'=>sp_id(5000+$i),'command'=>'replace_draft','actor'=>1,'at'=>200+$i,'arguments'=>['terms'=>$large]];}while(strlen(service_plans_json($bytesBound))<$target);
    $last=array_key_last($bytesBound['events']);$remove=strlen(service_plans_json($bytesBound))-$target;
    foreach(['inclusions','exclusions'] as $kind)foreach($bytesBound['events'][$last]['arguments']['terms'][$kind] as $i=>$text){$cut=min($remove,strlen($text)-1);$bytesBound['events'][$last]['arguments']['terms'][$kind][$i]=substr($text,0,strlen($text)-$cut);$remove-=$cut;}
    $byteDraft=ServicePlanDocument::fromJson(service_plans_json($bytesBound));sp_ok('Largest accepted draft reserves exact byte space for final transitions',strlen($byteDraft->json())===$target);
    $tooLarge=$bytesBound;$tooLarge['events'][$last]['arguments']['terms']['support'].='x';sp_deny('Draft bytes cannot consume reserved transition storage',fn()=>ServicePlanDocument::fromJson(service_plans_json($tooLarge)));
    $byteRetired=$byteDraft->append(sp_id(5100),'activate',1,500,[])->append(sp_id(5101),'retire',1,501,[]);sp_ok('Maximum-byte draft can publish and retire with every original byte retained',$byteRetired->model()->status()==='retired'&&$byteRetired->prefix($byteDraft->revision())->json()===$byteDraft->json());
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e."\n");}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);echo json_encode(['passed'=>$ok,'scope'=>'Pure canonical plan and subscription documents over reviewed models; no current authority, database, UI or billing tested','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";exit($ok?0:1);
