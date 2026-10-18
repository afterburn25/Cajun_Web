<?php
declare(strict_types=1);
require_once __DIR__.'/../../foundation/recurring-billing/RecurringBillingSchedule.php';
use CajunWebPro\Foundation\RecurringBilling\{RecurringBillingTerms as Terms,RecurringBillingSchedule as Schedule};

$checks=[];
function verify(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function refused(string $name,callable $call):void {try{$call();$ok=false;}catch(InvalidArgumentException){$ok=true;}verify($name,$ok);}
function epoch(string $date):int {return (new DateTimeImmutable($date,new DateTimeZone('UTC')))->getTimestamp();}
function eid(int $n):string {return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function encoded(mixed $value):string {return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function terms(array $changes=[]):Terms {
    return Terms::fromArray(array_replace(['format'=>1,'id'=>str_repeat('a',32),'client_id'=>7,'project_id'=>19,
        'source_subscription'=>['id'=>str_repeat('b',32),'revision'=>3,'sha256'=>str_repeat('c',64)],
        'title'=>'Website maintenance','terms'=>'Agreed whole-period maintenance invoices.','currency'=>'USD',
        'amount_cents'=>20000,'cadence'=>'monthly','anchor'=>'2025-01-31','alignment'=>'day-of-month',
        'timezone'=>'America/New_York','due_after_days'=>15,'collection'=>'invoice-only'],$changes));
}
function offered(?Terms $terms=null):Schedule {return Schedule::create($terms??terms(),3,epoch('2025-01-01 12:00:00'));}
function accept(Schedule $schedule,?Terms $terms=null):Schedule {return $schedule->append(eid(1),'accept','client:7',epoch('2025-01-02 12:00:00'),['terms_sha256'=>($terms??terms())->fingerprint()]);}
function activate(Schedule $schedule,int $index=0):Schedule {return $schedule->append(eid(2),'activate','staff:3',epoch('2025-01-03 12:00:00'),['period_index'=>$index]);}

$zone=date_default_timezone_get();
try {
    $terms=terms();$offered=offered();$literal='{"format":1,"terms":'.$terms->json().',"created_by":3,"created_at":1735732800,"events":[]}';
    verify('New offered schedule has exact literal canonical envelope',$offered->json()===$literal);
    verify('New schedule exposes offered phase zero revision and exact fingerprint',$offered->phase()==='offered' && $offered->revision()===0 && $offered->fingerprint()===hash('sha256',$literal));
    verify('Canonical offered document round trips without new decisions',Schedule::fromJson($literal)->json()===$literal);
    $a=$offered->assessment(0,epoch('2025-02-01 12:00:00'));
    verify('An invoice date and amount without consent never makes a period eligible',$a['eligible']===false && $a['reason']==='awaiting-consent');
    $accepted=accept($offered);$running=activate($accepted);
    verify('Only exact client terms acceptance reaches accepted phase',$accepted->phase()==='accepted' && $accepted->revision()===1);
    verify('Accepted terms alone do not activate a billing period',$accepted->assessment(0,epoch('2025-02-01'))['reason']==='not-activated');
    verify('Activation retains exact original terms and creation metadata',$running->data()['terms']===$terms->data() && $running->data()['created_at']===$offered->data()['created_at']);
    $expected=['period'=>['key'=>str_repeat('a',32).':0','index'=>0,'start'=>'2025-01-31','end'=>'2025-02-28','due'=>'2025-02-15','timezone'=>'America/New_York','currency'=>'USD','amount_cents'=>20000],'eligible'=>true,'reason'=>'eligible'];
    verify('Running whole period assessment matches independent literal facts',$running->assessment(0,epoch('2025-02-01 12:00:00'))===$expected);
    verify('A scheduled future running period is not started',$running->assessment(0,epoch('2025-01-30 12:00:00'))['reason']==='not-started');
    verify('Exact local midnight makes an agreed running period eligible',$running->assessment(0,epoch('2025-01-31 04:59:59'))['reason']==='not-started' && $running->assessment(0,epoch('2025-01-31 05:00:00'))===$expected);
    $paused=$running->append(eid(3),'pause','staff:3',epoch('2025-01-04'),['period_index'=>2,'reason'=>'Scheduled client break']);
    $resumed=$paused->append(eid(4),'resume','staff:4',epoch('2025-01-05'),['period_index'=>4,'reason'=>'Agreed work resumes']);
    $ended=$resumed->append(eid(5),'end','client:7',epoch('2025-01-06'),['period_index'=>6,'reason'=>'End future maintenance']);
    verify('Recorded phases preserve planned future decisions',$paused->phase()==='paused' && $resumed->phase()==='running' && $ended->phase()==='ended');
    foreach ([0=>'eligible',1=>'eligible',2=>'paused',3=>'paused',4=>'eligible',5=>'eligible',6=>'ended',7=>'ended'] as $index=>$reason) {
        $assessment=$ended->assessment($index,epoch('2026-01-01'));
        verify('Recorded lifecycle preserves period '.$index.' as '.$reason,$assessment['reason']===$reason && $assessment['eligible']===($reason==='eligible'));
    }
    verify('Ending later preserves original earlier whole-period facts',$ended->assessment(0,epoch('2026-01-01'))===$expected);
    verify('Every command remains retained in recorded order',array_column($ended->data()['events'],'command')===['accept','activate','pause','resume','end']);
    verify('Complete lifecycle round trip preserves assessment and history',Schedule::fromJson($ended->json())->assessment(0,epoch('2026-01-01'))===$expected && Schedule::fromJson($ended->json())->json()===$ended->json());
    $late=activate($accepted,3);
    verify('Delayed activation skips earlier periods without moving original anchor',$late->assessment(2,epoch('2026-01-01'))['reason']==='before-activation' && $late->assessment(3,epoch('2026-01-01'))['period']['start']==='2025-04-30');
    $same=$paused->append(eid(6),'resume','staff:3',epoch('2025-01-05'),['reason'=>'Undo planned break','period_index'=>2]);
    verify('Later future decision at the same boundary wins without erasing prior event',$same->assessment(2,epoch('2026-01-01'))['reason']==='eligible' && count($same->data()['events'])===4 && $same->data()['events'][2]['command']==='pause');
    verify('Arguments are canonically ordered when appending',array_keys($same->data()['events'][3]['arguments'])===['period_index','reason']);
    $endSame=$same->append(eid(7),'end','staff:3',epoch('2025-01-06'),['period_index'=>2,'reason'=>'Cancel future period']);
    verify('End at the same future boundary wins while previous periods remain eligible',$endSame->assessment(2,epoch('2026-01-01'))['reason']==='ended' && $endSame->assessment(1,epoch('2026-01-01'))['eligible']);
    $cancelBeforeStart=$running->append(eid(8),'end','client:7',epoch('2025-01-04'),['period_index'=>0,'reason'=>'Cancel before beginning']);
    verify('Client may end a future activation before any period starts',$cancelBeforeStart->assessment(0,epoch('2026-01-01'))['reason']==='ended');
    $declined=$offered->append(eid(9),'decline','client:7',epoch('2025-01-02'),['reason'=>'Do not agree']);
    $withdrawn=$offered->append(eid(10),'withdraw','staff:3',epoch('2025-01-02'),['reason'=>'Replace this offer']);
    $withdrawAccepted=$accepted->append(eid(11),'withdraw','staff:4',epoch('2025-01-03'),['reason'=>'Offer obsolete before activation']);
    verify('Declined offers never produce eligible periods',$declined->phase()==='declined' && $declined->assessment(0,epoch('2026-01-01'))['reason']==='declined');
    verify('Withdrawn offered and accepted schedules never produce eligible periods',$withdrawn->assessment(0,epoch('2026-01-01'))['reason']==='withdrawn' && $withdrawAccepted->assessment(0,epoch('2026-01-01'))['reason']==='withdrawn');
    $zero=terms(['amount_cents'=>0]);$free=activate(accept(offered($zero),$zero));
    verify('Zero-price active period is not turned into an invoice charge',$free->assessment(0,epoch('2026-01-01'))['reason']==='zero-price' && $free->assessment(0,epoch('2026-01-01'))['period']['amount_cents']===0);
    $maximum=terms(['amount_cents'=>99999999]);$max=activate(accept(offered($maximum),$maximum));
    verify('Maximum approved price survives the full lifecycle exactly',$max->assessment(0,epoch('2026-01-01'))['period']['amount_cents']===99999999);

    $acceptEvent=$accepted->data()['events'][0];
    verify('Exact old consent replay is a no-op after the schedule ends',$ended->append(...array_values($acceptEvent))===$ended);
    foreach (['command'=>'decline','actor'=>'client:8','at'=>epoch('2025-01-02 12:00:01'),'arguments'=>['terms_sha256'=>str_repeat('d',64)]] as $field=>$value) {
        $event=$acceptEvent;$event[$field]=$value;
        if($field==='command')$event['arguments']=['reason'=>'Conflicting decision'];
        refused('Existing event id refuses changed '.$field,fn()=>$ended->append(...array_values($event)));
    }
    $event=$ended->data()['events'][2];
    verify('Canonical argument reordering permits exact replay',$ended->append($event['id'],$event['command'],$event['actor'],$event['at'],array_reverse($event['arguments'],true))===$ended);
    $before=$offered->json();$copy=$ended->data();$copy['terms']['amount_cents']=1;$copy['events'][0]['arguments']['terms_sha256']='changed';
    $assessment=$ended->assessment(0,epoch('2026-01-01'));$assessment['period']['amount_cents']=1;
    verify('Returned history and assessment cannot mutate either prior or later document',$offered->json()===$before && $ended->assessment(0,epoch('2026-01-01'))===$expected);
    verify('Clone preserves exact immutable decisions',(clone $ended)->json()===$ended->json());
    $text="  <b>Literal Café / reason</b>\n\tKeep text.  ";
    $literalReason=$offered->append(eid(12),'withdraw','staff:3',epoch('2025-01-02'),['reason'=>$text]);
    verify('Reason markup Unicode whitespace and controls remain literal',$literalReason->data()['events'][0]['arguments']['reason']===$text);

    $states=['offered'=>$offered,'accepted'=>$accepted,'running'=>$running,'paused'=>$paused,'declined'=>$declined,'withdrawn'=>$withdrawn,'ended'=>$ended];
    $arguments=['accept'=>['terms_sha256'=>$terms->fingerprint()],'decline'=>['reason'=>'Decline'],'withdraw'=>['reason'=>'Withdraw'],'activate'=>['period_index'=>8],
        'pause'=>['period_index'=>8,'reason'=>'Pause'],'resume'=>['period_index'=>8,'reason'=>'Resume'],'end'=>['period_index'=>8,'reason'=>'End']];
    $allowed=['offered'=>['client:7'=>['accept','decline'],'staff:3'=>['withdraw']],
        'accepted'=>['staff:3'=>['withdraw','activate']], 'running'=>['staff:3'=>['pause','end'],'client:7'=>['end']],
        'paused'=>['staff:3'=>['resume','end'],'client:7'=>['end']], 'declined'=>[], 'withdrawn'=>[], 'ended'=>[]];
    foreach($states as $phase=>$schedule) foreach(['staff:3','client:7'] as $actor) foreach($arguments as $command=>$args) {
        $permitted=in_array($command,$allowed[$phase][$actor]??[],true);
        $call=fn()=>$schedule->append(eid(100),$command,$actor,epoch('2025-01-10'),$args);
        if($permitted)verify('Contract permits '.$actor.' '.$command.' from '.$phase,$call()->revision()===$schedule->revision()+1);
        else refused('Contract refuses '.$actor.' '.$command.' from '.$phase,$call);
    }
    foreach(['client:8','client:19','staff:7','support:7','client:07','staff:0','staff:-1','staff:2147483648','client:7 ','CLIENT:7',7,null,[],new stdClass] as $n=>$actor) refused('Invalid or mismatched consent actor '.$n,fn()=>$offered->append(eid(101),'accept',$actor,epoch('2025-01-02'),['terms_sha256'=>$terms->fingerprint()]));
    refused('Another client cannot end the schedule',fn()=>$running->append(eid(102),'end','client:8',epoch('2025-01-04'),['period_index'=>1,'reason'=>'Wrong client']));
    refused('Staff cannot consent on behalf of the client',fn()=>$offered->append(eid(103),'accept','staff:3',epoch('2025-01-02'),['terms_sha256'=>$terms->fingerprint()]));
    foreach(['',str_repeat('d',64),strtoupper($terms->fingerprint()),null,[],42] as $n=>$hash) refused('Wrong or invalid consent fingerprint '.$n,fn()=>$offered->append(eid(104),'accept','client:7',epoch('2025-01-02'),['terms_sha256'=>$hash]));
    foreach([[],['terms_sha256'=>$terms->fingerprint(),'automatic_payment'=>true],null,'terms'] as $n=>$args) refused('Consent exact argument shape '.$n,fn()=>$offered->append(eid(105),'accept','client:7',epoch('2025-01-02'),$args));
    foreach(['','ACCEPT','invoice','replace','charge',null,[],true] as $n=>$command) refused('Unknown recurring command '.$n,fn()=>$offered->append(eid(106),$command,'client:7',epoch('2025-01-02'),[]));
    foreach(['',str_repeat('a',31),str_repeat('a',33),str_repeat('A',32),str_repeat('z',32),str_repeat('a',32)."\n",true,1,[],null] as $n=>$id) refused('Invalid event identity '.$n,fn()=>$offered->append($id,'decline','client:7',epoch('2025-01-02'),['reason'=>'No']));
    foreach([0,-1,2147483648,'3',3.0,true,null,[],new stdClass] as $n=>$id) refused('Invalid declared creator '.$n,fn()=>Schedule::create($terms,$id,epoch('2025-01-01')));
    verify('Declared creator accepts both exact integer endpoints',Schedule::create($terms,1,0)->data()['created_by']===1 && Schedule::create($terms,2147483647,0)->data()['created_by']===2147483647);
    foreach([-1,253402300800,'0',0.0,true,null,[],new stdClass] as $n=>$at) {
        refused('Invalid created epoch '.$n,fn()=>Schedule::create($terms,3,$at));
        refused('Invalid decision epoch '.$n,fn()=>$offered->append(eid(107),'decline','client:7',$at,['reason'=>'No']));
        refused('Invalid assessment epoch '.$n,fn()=>$running->assessment(0,$at));
    }
    refused('Decision cannot precede creation',fn()=>$offered->append(eid(108),'decline','client:7',epoch('2025-01-01 11:59:59'),['reason'=>'No']));
    refused('Decision cannot precede the previous decision',fn()=>$accepted->append(eid(109),'withdraw','staff:3',epoch('2025-01-02 11:59:59'),['reason'=>'Out of order']));
    verify('Equal timestamps retain explicit event list order',$accepted->append(eid(110),'activate','staff:3',epoch('2025-01-02 12:00:00'),['period_index'=>0])->phase()==='running');
    refused('Assessment cannot reconstruct a time before the latest retained event',fn()=>$ended->assessment(0,epoch('2025-01-05')));
    foreach([''," \t\n", "\u{00a0}","bad\0reason","bad\rreason","bad\x7freason","\xc3\x28",str_repeat('x',1001),null,[],true,1] as $n=>$reason) refused('Invalid literal reason '.$n,fn()=>$offered->append(eid(111),'withdraw','staff:3',epoch('2025-01-02'),['reason'=>$reason]));
    verify('Exactly one thousand UTF8 reason bytes are retained',strlen($offered->append(eid(112),'withdraw','staff:3',epoch('2025-01-02'),['reason'=>str_repeat('é',500)])->data()['events'][0]['arguments']['reason'])===1000);
    foreach([-1,120000,'0',0.0,true,null,[],new stdClass] as $n=>$index) {
        refused('Invalid activation period '.$n,fn()=>$accepted->append(eid(113),'activate','staff:3',epoch('2025-01-03'),['period_index'=>$index]));
        refused('Invalid assessment period '.$n,fn()=>$running->assessment($index,epoch('2025-02-01')));
    }
    refused('Boundary must have representable period and due date',fn()=>$accepted->append(eid(114),'activate','staff:3',epoch('2025-01-03'),['period_index'=>119999]));
    refused('Activation cannot backdate an elapsed start',fn()=>$accepted->append(eid(115),'activate','staff:3',epoch('2025-02-01'),['period_index'=>0]));
    refused('Activation cannot change a period on its exact local start date',fn()=>$accepted->append(eid(116),'activate','staff:3',epoch('2025-01-31 05:00:00'),['period_index'=>0]));
    verify('A start tomorrow remains prospective immediately before local midnight',$accepted->append(eid(117),'activate','staff:3',epoch('2025-01-31 04:59:59'),['period_index'=>0])->phase()==='running');
    refused('Pause cannot remove an already-started period',fn()=>$running->append(eid(118),'pause','staff:3',epoch('2025-02-01'),['period_index'=>0,'reason'=>'Backdate']));
    refused('Pause cannot precede delayed first activation',fn()=>$late->append(eid(119),'pause','staff:3',epoch('2025-01-04'),['period_index'=>2,'reason'=>'Reverse activation']));
    refused('Resume cannot reverse an already scheduled boundary',fn()=>$paused->append(eid(120),'resume','staff:3',epoch('2025-01-05'),['period_index'=>1,'reason'=>'Reverse pause']));
    $earlyEnd=$resumed->append(eid(121),'end','client:7',epoch('2025-01-06'),['period_index'=>3,'reason'=>'End before planned resume']);
    verify('Client end overrides a later planned resume without erasing it',$earlyEnd->assessment(3,epoch('2026-01-01'))['reason']==='ended' && $earlyEnd->assessment(4,epoch('2026-01-01'))['reason']==='ended' && $earlyEnd->data()['events'][3]['command']==='resume');
    verify('Earlier eligible and paused periods retain their decisions after termination override',$earlyEnd->assessment(1,epoch('2026-01-01'))['eligible'] && $earlyEnd->assessment(2,epoch('2026-01-01'))['reason']==='paused');
    $beforeActivationEnd=$late->append(eid(123),'end','client:7',epoch('2025-01-04'),['period_index'=>1,'reason'=>'Cancel delayed activation']);
    verify('Client end may precede a delayed activation and stops all its future periods',$beforeActivationEnd->assessment(0,epoch('2026-01-01'))['reason']==='before-activation' && $beforeActivationEnd->assessment(1,epoch('2026-01-01'))['reason']==='ended' && $beforeActivationEnd->assessment(3,epoch('2026-01-01'))['reason']==='ended');
    verify('Termination override survives canonical reconstruction',Schedule::fromJson($earlyEnd->json())->assessment(4,epoch('2026-01-01'))['reason']==='ended');

    $dstTerms=terms(['anchor'=>'2025-03-10']);$dst=activate(accept(offered($dstTerms),$dstTerms));
    verify('Spring DST renewal follows local midnight rather than fixed UTC hours',$dst->assessment(0,epoch('2025-03-10 03:59:59'))['reason']==='not-started' && $dst->assessment(0,epoch('2025-03-10 04:00:00'))['eligible']);
    $fallTerms=terms(['anchor'=>'2025-11-03']);$fall=activate(accept(offered($fallTerms),$fallTerms));
    verify('Fall DST renewal follows the restored standard offset',$fall->assessment(0,epoch('2025-11-03 04:59:59'))['reason']==='not-started' && $fall->assessment(0,epoch('2025-11-03 05:00:00'))['eligible']);
    $eastTerms=terms(['timezone'=>'Pacific/Kiritimati']);$east=activate(accept(offered($eastTerms),$eastTerms));
    verify('Date-line timezone uses its own renewal date',$east->assessment(0,epoch('2025-01-30 09:59:59'))['reason']==='not-started' && $east->assessment(0,epoch('2025-01-30 10:00:00'))['eligible']);
    refused('Creator epoch must have a representable stored-zone local date',fn()=>Schedule::create($eastTerms,3,253402300799));
    refused('Decision epoch cannot cross the supported local year',fn()=>$east->append(eid(122),'end','client:7',253402300799,['period_index'=>1,'reason'=>'Overflow']));
    refused('Assessment epoch cannot cross the supported local year',fn()=>$east->assessment(0,253402300799));
    foreach(['UTC','Asia/Kathmandu','Pacific/Apia'] as $processZone) {
        date_default_timezone_set($processZone);
        verify('Process timezone does not change retained eligibility '.$processZone,$ended->assessment(0,epoch('2026-01-01'))===$expected && $dst->assessment(0,epoch('2025-03-10 04:00:00'))['eligible']);
    }
    date_default_timezone_set($zone);

    foreach([null,1,true,[],new stdClass,'','{}','[]','null','"text"','{',$literal.' ',$literal."\n",str_repeat('x',524289),str_repeat('[',20).'0'.str_repeat(']',20)] as $n=>$bytes) refused('Malformed noncanonical or oversized document '.$n,fn()=>Schedule::fromJson($bytes));
    foreach(array_keys($offered->data()) as $field) {$data=$offered->data();unset($data[$field]);refused('Missing canonical document field '.$field,fn()=>Schedule::fromJson(encoded($data)));}
    foreach([0,2,'1',true,null] as $n=>$format) {$data=$offered->data();$data['format']=$format;refused('Unsupported strict document format '.$n,fn()=>Schedule::fromJson(encoded($data)));}
    $data=$offered->data();$data['accepted']=true;refused('Undeclared document acceptance flag cannot substitute for history',fn()=>Schedule::fromJson(encoded($data)));
    $data=$accepted->data();$data['terms']['amount_cents']=20001;refused('Changed price cannot retain an earlier consent hash',fn()=>Schedule::fromJson(encoded($data)));
    $data=$accepted->data();$data['terms']['client_id']=8;refused('Changed client cannot reuse another client consent',fn()=>Schedule::fromJson(encoded($data)));
    $data=$accepted->data();$data['events'][]=$data['events'][0];refused('Duplicate stored event identities are refused even if identical',fn()=>Schedule::fromJson(encoded($data)));
    foreach(array_keys($acceptEvent) as $field) {$data=$accepted->data();unset($data['events'][0][$field]);refused('Missing event field '.$field,fn()=>Schedule::fromJson(encoded($data)));}
    $data=$accepted->data();$data['events'][0]['extra']=1;refused('Undeclared event field refuses',fn()=>Schedule::fromJson(encoded($data)));
    $data=$accepted->data();$data['events']=['event'=>$data['events'][0]];refused('History must be an ordered list',fn()=>Schedule::fromJson(encoded($data)));
    $data=$accepted->data();$data['events'][0]=array_reverse($data['events'][0],true);refused('Stored event field order must be canonical',fn()=>Schedule::fromJson(encoded($data)));
    $data=$paused->data();$data['events'][2]['arguments']=array_reverse($data['events'][2]['arguments'],true);refused('Stored argument order must be canonical',fn()=>Schedule::fromJson(encoded($data)));
    refused('Stored document field order must be canonical',fn()=>Schedule::fromJson(encoded(array_reverse($offered->data(),true))));
    refused('Decimal JSON format is not an integer version',fn()=>Schedule::fromJson(str_replace('"format":1','"format":1.0',$literal)));
    refused('Duplicate top-level JSON keys refuse',fn()=>Schedule::fromJson(str_replace('"created_by":3','"created_by":3,"created_by":3',$literal)));
    refused('Duplicate nested event JSON keys refuse',fn()=>Schedule::fromJson(str_replace('"command":"accept"','"command":"accept","command":"accept"',$accepted->json())));
    refused('Alternate escaped JSON representations refuse',fn()=>Schedule::fromJson(str_replace('Website','\\u0057ebsite',$literal)));

    $bounded=$running;
    for($i=3;$i<=255;$i++) $bounded=$bounded->append(eid(1000+$i),$i%2?'pause':'resume','staff:3',epoch('2025-01-04'),['period_index'=>0,'reason'=>'Retained future decision']);
    verify('Bounded history retains 255 distinct ordered decisions',$bounded->revision()===255 && $bounded->phase()==='paused');
    refused('Final reserved slot cannot be consumed by another resume',fn()=>$bounded->append(eid(2000),'resume','staff:3',epoch('2025-01-04'),['period_index'=>0,'reason'=>'Would exhaust history']));
    $last=$bounded->append(eid(2001),'end','client:7',epoch('2025-01-04'),['period_index'=>0,'reason'=>'End at reserved slot']);
    verify('Reserved final slot allows client termination and full canonical replay',$last->revision()===256 && $last->phase()==='ended' && Schedule::fromJson($last->json())->json()===$last->json());
    verify('Exact earlier replay remains available at the history limit',$last->append(...array_values($acceptEvent))===$last);
    $data=$last->data();$data['events'][]=$acceptEvent;refused('A stored 257th event is refused before replay',fn()=>Schedule::fromJson(encoded($data)));
    $large=$last->data();$base=terms(['terms'=>'x'])->data();$room=Terms::MAX_BYTES-strlen(encoded(array_replace($base,['terms'=>''])));$base['terms']='x'.str_repeat("\t",intdiv($room-1,2)).(($room-1)%2?'x':'');
    $largeTerms=Terms::fromArray($base);$large['terms']=$largeTerms->data();$large['events'][0]['arguments']['terms_sha256']=$largeTerms->fingerprint();
    foreach($large['events'] as &$event) if(isset($event['arguments']['reason']))$event['arguments']['reason']='x'.str_repeat("\t",999);unset($event);
    verify('Large valid field values can exceed the aggregate document bound',strlen(encoded($large))>Schedule::MAX_BYTES);
    refused('Aggregate history byte bound refuses without truncation',fn()=>Schedule::fromJson(encoded($large)));
    verify('Every refusal leaves earlier accepted whole-period facts unchanged',$ended->assessment(0,epoch('2026-01-01'))===$expected && $offered->json()===$literal);
    verify('Named assertions are unique',count(array_unique(array_column($checks,'name')))===count($checks));
    echo encoded(['passed'=>true,'checks'=>$checks])."\n";
}catch(Throwable $e){date_default_timezone_set($zone);echo encoded(['passed'=>false,'checks'=>$checks,'error'=>get_class($e).': '.$e->getMessage()])."\n";exit(1);}
