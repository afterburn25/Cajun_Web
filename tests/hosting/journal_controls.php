<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once __DIR__.'/../../foundation/hosting/HostingJournal.php';
use CajunWebPro\Foundation\Hosting\{HostingJournal,HostingPackage};
$checks=[];
function hj_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function hj_deny(string $name,callable $fn):void{try{$fn();}catch(InvalidArgumentException|LogicException){hj_ok($name,true);return;}hj_ok($name,false);}
function hj_encode(array $value):string{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
function hj_read(string $value,string $id='acct-1',string $client='client-1'):HostingJournal{return HostingJournal::fromJson($value,$id,$client);}
function hj_roundtrip(string $label,HostingJournal $journal,string $state,?string $attempt=null,bool $uncertain=false):HostingJournal{
    $restored=hj_read($journal->json());$a=$restored->account();$b=$journal->account();
    hj_ok($label.' preserves canonical bytes and fingerprint',$restored->json()===$journal->json()&&$restored->fingerprint()===hash('sha256',$journal->json()));
    hj_ok($label.' preserves status and exact attempt',$a->status()===$state&&$a->pendingAttempt()===$attempt&&$a->awaitingReconciliation()===$uncertain);
    hj_ok($label.' preserves history observations resource and domain',$a->history()===$b->history()&&$a->operations()==$b->operations()&&$a->providerRef()===$b->providerRef()&&$a->primaryDomain()===$b->primaryDomain()&&$a->disputeOpen()===$b->disputeOpen());
    return $restored;
}
function hj_step(HostingJournal $j,string $command,int $at,array $args=[],string $actor='staff-1'):HostingJournal{return $j->append(str_pad(dechex($j->revision()+1),32,'0',STR_PAD_LEFT),$command,$actor,$at,$args);}
function hj_result(string $operation,string $outcome,int $at,string $attempt,?string $resource=null,bool $retryable=false):array{return ['result'=>['operation'=>'hosting.'.$operation,'outcome'=>$outcome,'at'=>$at,'subject_ref'=>'acct-1','receipt'=>$outcome==='applied'?'synthetic-receipt':null,'resource_ref'=>$resource,'error'=>$outcome==='failed'?'synthetic-failure':null,'retryable'=>$retryable,'attempt_ref'=>$attempt]];}
try{
    $limit=10;$package=new HostingPackage('WEB-1','Cajun café','synthetic-type','synthetic-region',['disk_gb'=>&$limit,'sites'=>null]);
    $j=HostingJournal::create('acct-1',$package,'client-1',10);$limit=999;
    hj_ok('Creation detaches caller quotas and preserves null and Unicode',$j->account()->package->limits===['disk_gb'=>10,'sites'=>null]&&$j->account()->package->label==='Cajun café');
    $j=hj_roundtrip('Initial state',$j,'pending');
    $copy=$j->account();$copy->cancelOrder('staff-2',11);
    hj_ok('Mutating an emitted model cannot change journal state',$j->account()->status()==='pending'&&$j->revision()===0);
    $initial=$j;$j=hj_step($j,'begin_provisioning',11);$j=hj_roundtrip('Provisioning dispatch',$j,'provisioning','acct-1#1');
    $event=json_decode($j->json(),true)['events'][0];
    hj_ok('Identical event replay returns same immutable journal',$j->append($event['id'],$event['command'],$event['actor'],$event['at'],$event['arguments'])===$j);
    hj_deny('Changed stable event identity cannot overwrite earlier intent',fn()=>$j->append($event['id'],$event['command'],'staff-2',$event['at'],$event['arguments']));
    $j=hj_step($j,'record_provision',12,hj_result('provision','timeout',12,'acct-1#1'));
    $j=hj_roundtrip('Unknown provisioning',$j,'failed','acct-1#1',true);
    $before=$j->json();hj_deny('Restored unknown create cannot retry',fn()=>hj_step($j,'retry_provision',13));hj_deny('Restored unknown create cannot cancel',fn()=>hj_step($j,'cancel_order',13));
    hj_deny('Restored unknown create rejects foreign reconciliation attempt',fn()=>hj_step($j,'reconcile',13,['applied_remotely'=>false,'resource_ref'=>null,'evidence'=>'Synthetic no-resource evidence','attempt_ref'=>'acct-1#0']));
    hj_ok('Refused commands leave original bytes and revision intact',$j->json()===$before&&$j->revision()===2);
    $j=hj_step($j,'reconcile',13,['applied_remotely'=>false,'resource_ref'=>null,'evidence'=>'Synthetic no-resource evidence','attempt_ref'=>'acct-1#1']);$j=hj_roundtrip('Confirmed absent create',$j,'failed');
    $j=hj_step($j,'retry_provision',14);$j=hj_roundtrip('Deliberate provision retry',$j,'pending');
    $j=hj_step($j,'begin_provisioning',15);$j=hj_roundtrip('Fresh retry attempt',$j,'provisioning','acct-1#2');
    hj_deny('Restored retry rejects old success receipt',fn()=>hj_step($j,'record_provision',16,hj_result('provision','applied',16,'acct-1#1','resource-old')));
    $missing=hj_step($j,'record_provision',16,hj_result('provision','applied',16,'acct-1#2'));
    $missing=hj_roundtrip('Applied create missing resource',$missing,'failed','acct-1#2',true);
    $adopted=hj_step($missing,'reconcile',17,['applied_remotely'=>true,'resource_ref'=>'synthetic-resource','evidence'=>'Synthetic identity observation','attempt_ref'=>'acct-1#2']);
    hj_roundtrip('Reconciled resource adoption',$adopted,'active');
    hj_ok('Resource identity is separate from the operation receipt',$adopted->account()->providerRef()==='synthetic-resource');
    $j=hj_step($j,'record_provision',16,hj_result('provision','applied',16,'acct-1#2','resource-1'));$j=hj_roundtrip('Active provisioned account',$j,'active');
    $j=hj_step($j,'link_domain',17,['domain'=>'EXAMPLE.COM']);$j=hj_roundtrip('Domain linking',$j,'active');
    hj_ok('Restored domain keeps canonical lowercase value',$j->account()->primaryDomain()==='example.com');
    $j=hj_step($j,'set_grace',18,['until'=>25]);$j=hj_roundtrip('Billing grace',$j,'active');
    hj_deny('Restored billing grace refuses early suspension',fn()=>hj_step($j,'suspend',24,['reason'=>'Synthetic overdue invoice']));
    $j=hj_step($j,'open_dispute',19);$j=hj_roundtrip('Open billing dispute',$j,'active');
    hj_deny('Restored dispute refuses suspension after grace',fn()=>hj_step($j,'suspend',25,['reason'=>'Synthetic overdue invoice']));
    $j=hj_step($j,'close_dispute',25);$j=hj_roundtrip('Closed billing dispute',$j,'active');
    $active=$j;$j=hj_step($j,'suspend',26,['reason'=>'Synthetic overdue invoice']);$j=hj_roundtrip('Suspension intent',$j,'suspending','acct-1#3');
    $suspendedUnknown=hj_step($j,'record_suspend',27,hj_result('suspend','timeout',27,'acct-1#3'));hj_roundtrip('Uncertain suspension',$suspendedUnknown,'suspending','acct-1#3',true);
    $suspendedUnknown=hj_step($suspendedUnknown,'reconcile',28,['applied_remotely'=>true,'resource_ref'=>null,'evidence'=>'Synthetic suspended observation','attempt_ref'=>'acct-1#3']);hj_roundtrip('Reconciled suspension',$suspendedUnknown,'suspended');
    $j=hj_step($j,'record_suspend',27,hj_result('suspend','applied',27,'acct-1#3'));$j=hj_roundtrip('Confirmed suspension',$j,'suspended');
    $j=hj_step($j,'resume',28);$j=hj_roundtrip('Resumption intent',$j,'resuming','acct-1#4');
    $resumeUnknown=hj_step($j,'record_resume',29,hj_result('resume','timeout',29,'acct-1#4'));hj_roundtrip('Uncertain resumption',$resumeUnknown,'resuming','acct-1#4',true);
    $j=hj_step($j,'record_resume',29,hj_result('resume','applied',29,'acct-1#4'));$j=hj_roundtrip('Confirmed resumption',$j,'active');
    $j=hj_step($j,'request_cancellation',30);$j=hj_roundtrip('Cancellation request',$j,'cancellation_requested');
    hj_deny('Restored cancellation retains first actor requirement',fn()=>hj_step($j,'approve_deprovision',31,['retention_confirmed'=>true]));
    hj_deny('Restored cancellation retains data-retention requirement',fn()=>hj_step($j,'approve_deprovision',31,['retention_confirmed'=>false],'staff-2'));
    $j=hj_step($j,'approve_deprovision',31,['retention_confirmed'=>true],'staff-2');$j=hj_roundtrip('Two-actor approved removal',$j,'deprovisioning','acct-1#5');
    $notRetryable=hj_step($j,'record_deprovision',32,hj_result('deprovision','failed',32,'acct-1#5'));hj_roundtrip('Unsafe removal failure',$notRetryable,'deprovision_failed');
    hj_deny('Restored nonretryable removal cannot rearm',fn()=>hj_step($notRetryable,'retry_deprovision',33));
    $retryable=hj_step($j,'record_deprovision',32,hj_result('deprovision','failed',32,'acct-1#5',null,true));$retryable=hj_roundtrip('Retryable removal failure',$retryable,'deprovision_failed');
    $retryable=hj_step($retryable,'retry_deprovision',33);hj_roundtrip('Explicit rearmed removal',$retryable,'deprovisioning','acct-1#6');
    $j=hj_step($j,'record_deprovision',32,hj_result('deprovision','timeout',32,'acct-1#5'));$j=hj_roundtrip('Unknown destructive outcome',$j,'deprovisioning','acct-1#5',true);
    hj_deny('Unknown removal cannot take normal retry path',fn()=>hj_step($j,'retry_deprovision',33));
    $j=hj_step($j,'reconcile',33,['applied_remotely'=>false,'resource_ref'=>null,'evidence'=>'Synthetic resource still present','attempt_ref'=>'acct-1#5']);$j=hj_roundtrip('Reconciled removal arms fresh identity',$j,'deprovisioning','acct-1#6');
    hj_deny('Reconciled removal refuses stale result after restore',fn()=>hj_step($j,'record_deprovision',34,hj_result('deprovision','applied',34,'acct-1#5')));
    $j=hj_step($j,'record_deprovision',34,hj_result('deprovision','applied',34,'acct-1#6'));$j=hj_roundtrip('Confirmed terminal removal',$j,'deprovisioned');
    hj_deny('Terminal restoration cannot reprovision',fn()=>hj_step($j,'begin_provisioning',35));
    hj_roundtrip('Never-provisioned cancellation',hj_step($initial,'cancel_order',11),'cancelled');
    $negative=[
        'unknown format'=>static function(&$d){$d['format']=2;},
        'extra top-level state'=>static function(&$d){$d['status']='active';},
        'missing client binding'=>static function(&$d){unset($d['client_ref']);},
        'numeric account id'=>static function(&$d){$d['id']=1;},
        'newline account id'=>static function(&$d){$d['id'].="\n";},
        'float creation time'=>static function(&$d){$d['created_at']=10.5;},
        'negative creation time'=>static function(&$d){$d['created_at']=-1;},
        'string quota'=>static function(&$d){$d['package']['limits']['disk_gb']='10';},
        'boolean quota'=>static function(&$d){$d['package']['limits']['disk_gb']=true;},
        'unknown package field'=>static function(&$d){$d['package']['secret']='not allowed';},
        'noncanonical quota order'=>static function(&$d){$d['package']['limits']=array_reverse($d['package']['limits'],true);},
        'unbounded quota promise'=>static function(&$d){$d['package']['limits']=['unlimited_disk'=>null];},
        'unknown method command'=>static function(&$d){$d['events'][0]['command']='__construct';},
        'class command payload'=>static function(&$d){$d['events'][0]['command']=['class'=>'arbitrary'];},
        'forged command argument'=>static function(&$d){$d['events'][0]['arguments']=['status'=>'active'];},
        'unknown event key'=>static function(&$d){$d['events'][0]['method']='exec';},
        'missing actor'=>static function(&$d){$d['events'][0]['actor']='';},
        'actor newline'=>static function(&$d){$d['events'][0]['actor'].="\n";},
        'integer event identity'=>static function(&$d){$d['events'][0]['id']=1;},
        'event before creation'=>static function(&$d){$d['events'][0]['at']=9;},
        'reversed event times'=>static function(&$d){$d['events'][1]['at']=10;},
        'duplicate event identities'=>static function(&$d){$d['events'][1]['id']=$d['events'][0]['id'];},
        'result for foreign subject'=>static function(&$d){$d['events'][1]['arguments']['result']['subject_ref']='acct-2';},
        'result for foreign attempt'=>static function(&$d){$d['events'][1]['arguments']['result']['attempt_ref']='acct-1#9';},
        'result without attempt'=>static function(&$d){$d['events'][1]['arguments']['result']['attempt_ref']=null;},
        'result after event'=>static function(&$d){$d['events'][1]['arguments']['result']['at']=999;},
        'result before creation'=>static function(&$d){$d['events'][1]['arguments']['result']['at']=0;},
        'result before dispatched attempt'=>static function(&$d){$d['events'][1]['arguments']['result']['at']=10;},
        'timeout with receipt'=>static function(&$d){$d['events'][1]['arguments']['result']['receipt']='fake';},
        'retryable timeout'=>static function(&$d){$d['events'][1]['arguments']['result']['retryable']=true;},
        'string result boolean'=>static function(&$d){$d['events'][1]['arguments']['result']['retryable']='false';},
    ];
    foreach($negative as $name=>$change){$d=json_decode($j->json(),true);$change($d);hj_deny('Decode refuses '.$name,fn()=>hj_read(hj_encode($d)));}
    hj_deny('Decode binds trusted account selection',fn()=>hj_read($j->json(),'acct-2'));
    hj_deny('Decode binds trusted client selection',fn()=>hj_read($j->json(),'acct-1','client-2'));
    hj_deny('Decode refuses PHP serialized objects',fn()=>hj_read('O:8:"stdClass":0:{}'));
    hj_deny('Decode refuses duplicate JSON keys',fn()=>hj_read(str_replace('"format":1','"format":1,"format":1',$j->json())));
    hj_deny('Decode refuses trailing whitespace',fn()=>hj_read($j->json().' '));
    hj_deny('Decode refuses malformed UTF-8',fn()=>hj_read("{\"id\":\"\xff\"}"));
    hj_deny('Decode enforces byte budget before parsing',fn()=>hj_read(str_repeat(' ',HostingJournal::MAX_BYTES+1)));
    $d=json_decode($initial->json(),true);
    for($i=1;$i<=HostingJournal::MAX_EVENTS;$i++)$d['events'][]=['id'=>str_pad(dechex($i),32,'0',STR_PAD_LEFT),'command'=>'close_dispute','actor'=>'staff-1','at'=>10,'arguments'=>[]];
    $full=hj_read(hj_encode($d));hj_ok('Maximum bounded event list reconstructs completely',$full->revision()===HostingJournal::MAX_EVENTS&&count($full->account()->history())===HostingJournal::MAX_EVENTS+1);
    hj_deny('Append refuses to silently truncate full journal',fn()=>hj_step($full,'close_dispute',10));
    $reason='Original synthetic reason';$args=['reason'=>&$reason];$s=hj_step($active,'suspend',26,$args);$reason='Changed by caller';
    hj_ok('Caller argument references cannot mutate retained event bytes',str_contains($s->json(),'Original synthetic reason')&&!str_contains($s->json(),'Changed by caller'));
    hj_ok('Original immutable source journal remains unchanged after all branches',$initial->revision()===0&&$initial->account()->status()==='pending'&&count($initial->account()->history())===1);
}finally{
    echo json_encode(['scope'=>'Isolated immutable hosting journal reconstruction and refusal checks; no database authorization or provider operation','passed'=>count($checks)>0&&!in_array(false,array_column($checks,'passed'),true),'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
}
