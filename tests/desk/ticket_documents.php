<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',__DIR__.'/../../site');
require CWP_CODE_ROOT.'/app/support/ticket_documents.php';
$checks=[];
function ticket_check(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function ticket_refused(string $name,callable $action):void {try{$action();}catch(InvalidArgumentException|LogicException|JsonException){ticket_check($name,true);return;}ticket_check($name,false);}
function ticket_id(int $n):string {return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function ticket_mutate(SupportTicketDocument $doc,callable $change):SupportTicketDocument {$data=$doc->data();$change($data);return SupportTicketDocument::fromJson(support_tickets_json($data));}
try {
$ticket=SupportTicketDocument::create(ticket_id(1),7,'client:7',100,'Website support',ticket_id(2),'Please update the menu.');
$initial=$ticket->json();$view=$ticket->forClient(7);
ticket_check('Opening uses the real creation time and public sequence one',$view['created_at']===100&&$view['updated_at']===100&&$view['public_revision']===1&&count($view['events'])===1&&$view['events'][0]['at']===100&&$ticket->revision()===0);
ticket_check('Canonical serialization roundtrips without alteration',SupportTicketDocument::fromJson($initial)->json()===$initial&&$ticket->prefix(0)->fingerprint()===$ticket->fingerprint());
ticket_refused('Other client cannot obtain a projection',fn()=>$ticket->forClient(8));
$private=$ticket->append(ticket_id(3),'assign','staff:1',101,['staff_id'=>2])->append(ticket_id(4),'note','staff:2',102,['body'=>'Private note: do not share.']);
ticket_check('Assignments and internal notes leave every client projection byte unchanged',$private->forClient(7)===$view&&$private->publicRevision()===1&&$private->revision()===2);
ticket_check('Staff model retains the private note and assignment',count($private->model()->allMessages())===2&&count($private->model()->clientMessages())===1&&$private->model()->assigneeRef()==='staff:2');
$replied=$private->append(ticket_id(5),'reply','staff:2',102,['body'=>'We received your request.'])->append(ticket_id(6),'reply','client:7',102,['body'=>'Thank you.']);
ticket_check('Same-second public replies have distinct consecutive sequences',array_column($replied->forClient(7)['events'],'sequence')===[1,2,3]&&$replied->publicRevision()===3&&$replied->forClient(7)['events'][1]['at']===$replied->forClient(7)['events'][2]['at']);
ticket_check('Client projection excludes private bodies and internal revision metadata',!str_contains(support_tickets_json($replied->forClient(7)),'Private note')&&!array_key_exists('revision',$replied->forClient(7))&&!array_key_exists('fingerprint',$replied->forClient(7))&&!array_key_exists('links',$replied->forClient(7)));
ticket_check('Exact request replay returns the same immutable document',$replied->append(ticket_id(6),'reply','client:7',102,['body'=>'Thank you.'])===$replied);
ticket_refused('Changed request identity replay is refused',fn()=>$replied->append(ticket_id(6),'reply','client:7',102,['body'=>'Different content']));
ticket_refused('Opening identity cannot be reused as a later request',fn()=>$ticket->append(ticket_id(2),'reply','client:7',101,['body'=>'Duplicate opening']));
ticket_check('History prefixes reconstruct the unchanged earlier document',$replied->prefix(2)->json()===$private->json()&&$replied->prefix(0)->json()===$initial);
foreach([-1,99] as $revision)ticket_refused('Invalid prefix revision '.$revision,fn()=>$ticket->prefix($revision));
$model=$private->model();$model->assign('staff:99','staff:1',103);$model->transition('closed','staff:1',104);
$data=$private->data();$data['events'][1]['arguments']['body']='Altered copy';$copy=$private->forClient(7);$copy['events'][0]['body']='Altered client copy';
ticket_check('Mutating returned data models and projections cannot mutate the document',$private->model()->status()==='open'&&$private->model()->assigneeRef()==='staff:2'&&$private->data()['events'][1]['arguments']['body']==='Private note: do not share.'&&$private->forClient(7)===$view);
$pending=$ticket->append(ticket_id(10),'status','staff:1',101,['status'=>'pending']);
ticket_check('Client reply reopens a pending ticket',$pending->append(ticket_id(11),'reply','client:7',102,['body'=>'Here is the detail.'])->model()->status()==='open');
ticket_check('Staff reply does not silently change pending status',$pending->append(ticket_id(11),'reply','staff:1',102,['body'=>'Still awaiting the details.'])->model()->status()==='pending');
$resolved=$pending->append(ticket_id(12),'status','staff:1',102,['status'=>'resolved']);
ticket_check('Private note preserves resolved state and public update time',$resolved->append(ticket_id(13),'note','staff:1',103,['body'=>'Private follow-up'])->forClient(7)===$resolved->forClient(7));
ticket_check('Public reply reopens a resolved ticket',$resolved->append(ticket_id(13),'reply','staff:1',103,['body'=>'One further item.'])->model()->status()==='open');
ticket_check('Client can explicitly reopen resolved work',$resolved->append(ticket_id(13),'status','client:7',103,['status'=>'open'])->model()->status()==='open');
$closed=$resolved->append(ticket_id(14),'status','client:7',104,['status'=>'closed']);
ticket_check('Client closure retains the preceding immutable history',$closed->model()->status()==='closed'&&$closed->prefix(2)->json()===$resolved->json());
foreach(['reply'=>['body'=>'Reply'],'note'=>['body'=>'Note'],'assign'=>['staff_id'=>2],'status'=>['status'=>'open']] as $command=>$args)ticket_refused('Closed ticket refuses '.$command,fn()=>$closed->append(ticket_id(15),$command,'staff:1',105,$args));
ticket_check('Exact closing replay is harmless even after closure',$closed->append(ticket_id(14),'status','client:7',104,['status'=>'closed'])===$closed);
foreach(['note'=>['body'=>'Hidden'],'assign'=>['staff_id'=>1],'status'=>['status'=>'resolved']] as $command=>$args)ticket_refused('Client cannot perform staff action '.$command,fn()=>$ticket->append(ticket_id(20),$command,'client:7',101,$args));
foreach(['client:8','staff:0','staff:01','staff:2147483648','system:1','client:7x','owner:1'] as $actor)ticket_refused('Invalid or other-client actor '.$actor,fn()=>$ticket->append(ticket_id(21),'reply',$actor,101,['body'=>'Reply']));
foreach([-1,99,253402300800] as $at)ticket_refused('Invalid or decreasing event time '.$at,fn()=>$private->append(ticket_id(22),'reply','staff:1',$at,['body'=>'Reply']));
foreach(['','  ',"bad\0body","bad\rbody","bad\xFFbody",str_repeat('x',16385)] as $index=>$body)ticket_refused('Invalid or oversized reply body '.$index,fn()=>$ticket->append(ticket_id(23),'reply','client:7',101,['body'=>$body]));
ticket_refused('Reply cannot carry a private flag',fn()=>$ticket->append(ticket_id(24),'reply','client:7',101,['body'=>'Text','internal'=>true]));
ticket_refused('Unknown command cannot enter history',fn()=>$ticket->append(ticket_id(24),'erase','staff:1',101,[]));
ticket_refused('No-op status transition is refused',fn()=>$ticket->append(ticket_id(24),'status','staff:1',101,['status'=>'open']));
$body="Literal <script>alert('x')</script>\n\t& text";
ticket_check('Plain text is retained literally for later escaped rendering',$ticket->append(ticket_id(25),'reply','client:7',101,['body'=>$body])->forClient(7)['events'][1]['body']===$body);
$links=[['kind'=>'invoice','id'=>'1'],['kind'=>'project','id'=>'2'],['kind'=>'subscription','id'=>ticket_id(8)]];
$withLinks=SupportTicketDocument::create(ticket_id(30),7,'staff:1',100,str_repeat('é',90),ticket_id(31),'Body',$links);
$links[0]['id']='99';ticket_check('Related-record input is detached and available only to native resolution',$withLinks->data()['links'][0]['id']==='1'&&count($withLinks->model()->trace)===3&&!isset($withLinks->forClient(7)['links']));
$invalid=[
    'extra field'=>static function(&$d){$d['extra']=true;},
    'wrong format'=>static function(&$d){$d['format']=2;},
    'noninteger client'=>static function(&$d){$d['client_id']='7';},
    'different opening client'=>static function(&$d){$d['created_by']='client:8';},
    'negative opening time'=>static function(&$d){$d['created_at']=-1;},
    'uppercase identity'=>static function(&$d){$d['id']=str_repeat('A',32);},
    'overlong UTF8 subject'=>static function(&$d){$d['subject']=str_repeat('é',91);},
    'multiline subject'=>static function(&$d){$d['subject']="Line\nTwo";},
    'unknown opening field'=>static function(&$d){$d['opening']['internal']=true;},
    'invalid opening body'=>static function(&$d){$d['opening']['body']=false;},
    'nonlist events'=>static function(&$d){$d['events']=['named'=>[]];},
    'unreviewed provider link'=>static function(&$d){$d['links']=[['kind'=>'hosting_service','id'=>'1']];},
    'ambiguous numeric link'=>static function(&$d){$d['links']=[['kind'=>'invoice','id'=>'01']];},
    'duplicate links'=>static function(&$d){$d['links']=[['kind'=>'invoice','id'=>'1'],['kind'=>'invoice','id'=>'1']];},
    'unordered links'=>static function(&$d){$d['links']=[['kind'=>'project','id'=>'2'],['kind'=>'invoice','id'=>'1']];},
    'too many links'=>static function(&$d){$d['links']=array_map(fn($n)=>['kind'=>'project','id'=>(string)$n],range(1,9));},
];
foreach($invalid as $name=>$change)ticket_refused('Document rejects '.$name,fn()=>ticket_mutate($ticket,$change));
ticket_refused('Noncanonical whitespace is refused',fn()=>SupportTicketDocument::fromJson($initial."\n"));
ticket_refused('Duplicate JSON keys are refused',fn()=>SupportTicketDocument::fromJson(str_replace('"format":1','"format":1,"format":1',$initial)));
ticket_refused('Malformed JSON is refused',fn()=>SupportTicketDocument::fromJson('{'));
ticket_refused('Scalar JSON is refused',fn()=>SupportTicketDocument::fromJson('null'));
$ref='1';$linked=[['kind'=>'invoice','id'=>&$ref]];
$detached=SupportTicketDocument::create(ticket_id(40),7,'client:7',100,'Reference test',ticket_id(41),'Body',$linked);$ref='2';
ticket_check('Nested PHP reference aliases cannot change accepted links',$detached->data()['links'][0]['id']==='1');
$many=$ticket->data();for($i=0;$i<511;$i++)$many['events'][]=['id'=>ticket_id(1000+$i),'command'=>'assign','actor'=>'staff:1','at'=>100,'arguments'=>['staff_id'=>1]];
$full=SupportTicketDocument::fromJson(support_tickets_json($many));
ticket_check('Private event capacity does not expose a count or update time',$full->revision()===511&&$full->forClient(7)===$view);
ticket_refused('Last event slot is reserved for closure',fn()=>$full->append(ticket_id(2000),'reply','client:7',101,['body'=>'One more']));
$end=$full->append(ticket_id(2000),'status','client:7',101,['status'=>'closed']);
ticket_check('Full history still permits closure and retains its preceding prefix',$end->revision()===512&&$end->model()->status()==='closed'&&$end->prefix(511)->json()===$full->json());
ticket_refused('Event count beyond the closed limit is refused',fn()=>$end->append(ticket_id(2001),'status','staff:1',102,['status'=>'open']));
// Reach the byte budget independently of the event-count budget.
$large=$ticket->data();$budget=SupportTicketDocument::MAX_BYTES-1024;
for($i=0;$i<100;$i++){
    $large['events'][]=['id'=>ticket_id(3000+$i),'command'=>'reply','actor'=>'staff:1','at'=>100,'arguments'=>['body'=>str_repeat('x',SupportTicketDocument::MAX_BODY)]];
    $bytes=support_tickets_json($large);
    if(strlen($bytes)>$budget){$last=array_key_last($large['events']);$large['events'][$last]['arguments']['body']=substr($large['events'][$last]['arguments']['body'],0,SupportTicketDocument::MAX_BODY-(strlen($bytes)-$budget));break;}
}
$atBytes=SupportTicketDocument::fromJson(support_tickets_json($large));
ticket_check('Open history accepts the exact byte limit while reserving closure',strlen($atBytes->json())===$budget);
ticket_refused('Open history cannot consume the close-byte reserve',fn()=>$atBytes->append(ticket_id(4000),'reply','client:7',101,['body'=>'Over capacity']));
$byteClosed=$atBytes->append(ticket_id(4000),'status','client:7',101,['status'=>'closed']);
ticket_check('Byte-full ticket still closes without changing its accepted prefix',$byteClosed->model()->status()==='closed'&&$byteClosed->prefix($atBytes->revision())->json()===$atBytes->json());
ticket_refused('Forged closed history cannot contain an invalid oversized open prefix',fn()=>ticket_mutate($byteClosed,static function(&$d){$n=count($d['events'])-2;$d['events'][$n]['arguments']['body'].='x';}));
ticket_refused('Absolute document byte budget is enforced before decoding',fn()=>SupportTicketDocument::fromJson(str_repeat(' ',SupportTicketDocument::MAX_BYTES+1)));
ticket_check('Rejected operations leave the original ticket byte-identical',$ticket->json()===$initial&&$private->forClient(7)===$view);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e.PHP_EOL);}
$passed=!array_filter($checks,fn($c)=>!$c['passed']);
echo json_encode(['passed'=>$passed,'scope'=>'Pure ticket document/replay/privacy/limits; no database, current grants, HTTP or client UI acceptance','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed?0:1);
