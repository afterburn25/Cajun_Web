<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/projects/ProjectProgressEvent.php';
use CajunWebPro\Foundation\Projects\{ProjectSnapshot,ProjectProgressEvent};
$checks=[];
function check(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function refused(string $name,callable $work):void {try{$work();}catch(InvalidArgumentException|LogicException|JsonException){check($name,true);return;}check($name,false);}
function eventId(int $id):string {return str_pad(dechex($id),32,'0',STR_PAD_LEFT);}
function jsonData(array $data):string {return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);}
function altered(ProjectProgressEvent $event,array $fields):ProjectProgressEvent {return ProjectProgressEvent::fromJson(jsonData(array_replace($event->data(),$fields)));}
try {
    $fields=['title'=>'Cajun cafe website','summary'=>'A new seasonal menu.','stage'=>'design','milestones'=>"Menu approved\nPhotography requested"];
    $snapshot=ProjectSnapshot::fromArray($fields);$original=$snapshot->json();
    $baseline=ProjectProgressEvent::baseline(eventId(1),21,7,3,100,$snapshot);$baseJson=$baseline->json();
    check('Existing work starts an honest baseline without invented earlier transitions',$baseline->data()['kind']==='tracking_started'&&$baseline->data()['revision']===0&&$baseline->data()['before']===null&&$baseline->data()['changed_fields']===[]&&$baseline->data()['occurred_at']===100);
    check('Baseline public projection has the actual current project snapshot',$baseline->forClient(7)['after']===$fields);
    $nextFields=array_replace($fields,['stage'=>'development','milestones'=>"Menu approved\nPhotography received"]);
    $update=$baseline->update(eventId(2),4,101,$baseline->fingerprint(),ProjectSnapshot::fromArray($nextFields));
    check('Update retains exact old and new public values',$update->data()['before']===$fields&&$update->data()['after']===$nextFields&&$update->data()['changed_fields']===['stage','milestones']);
    check('Attribution and recorded time are retained independently of stage',$update->data()['staff_id']===4&&$update->data()['occurred_at']===101&&$update->data()['project_id']===21&&$update->data()['client_id']===7);
    check('Project and earlier event remain immutable after an update',$baseline->json()===$baseJson&&$snapshot->json()===$original);
    $third=$update->update(eventId(3),3,101,$update->fingerprint(),ProjectSnapshot::fromArray(array_replace($nextFields,['summary'=>'Content ready.'])));
    ProjectProgressEvent::verifyChain([$baseline,$update,$third]);
    check('Same-second updates remain consecutive and ordered',$third->data()['revision']===2&&$third->data()['occurred_at']===$update->data()['occurred_at']);
    ProjectProgressEvent::verifyChain([$third],$update);check('Later bounded page verifies against its actual predecessor',true);
    check('Snapshots and events roundtrip byte-for-byte',ProjectSnapshot::fromArray($snapshot->data())->json()===$original&&ProjectProgressEvent::fromJson($third->json())->json()===$third->json());
    check('Event fingerprint binds exact canonical bytes',$third->fingerprint()===hash('sha256',$third->json())&&$third->data()['before_sha256']===$update->fingerprint());
    check('Snapshot fingerprint is stable and independent of event metadata',$baseline->snapshot()->fingerprint()===$snapshot->fingerprint()&&$snapshot->fingerprint()===hash('sha256',$original));
    $reverted=$third->update(eventId(4),4,102,$third->fingerprint(),$snapshot);
    check('Deliberate reversion is a new dated event rather than rewritten history',$reverted->snapshot()->json()===$original&&$reverted->data()['revision']===3&&$reverted->fingerprint()!==$baseline->fingerprint());
    refused('Returning to earlier values cannot make an old form token current',fn()=>$reverted->update(eventId(5),3,103,$baseline->fingerprint(),$update->snapshot()));
    refused('An unchanged save cannot manufacture progress',fn()=>$third->update(eventId(5),3,102,$third->fingerprint(),$third->snapshot()));
    refused('A stale form cannot overwrite a later edit',fn()=>$third->update(eventId(5),3,102,$update->fingerprint(),$snapshot));
    refused('An immediately reused event identity is refused',fn()=>$third->update(eventId(3),3,102,$third->fingerprint(),$snapshot));
    refused('Timestamp cannot precede the actual previous event',fn()=>$third->update(eventId(5),3,100,$third->fingerprint(),$snapshot));
    refused('Another client cannot obtain the public projection',fn()=>$third->forClient(8));
    $public=$third->forClient(7);
    check('Client receives only dated public content without internal attribution or hashes',array_keys($public)===['id','revision','kind','occurred_at','before','after','changed_fields']&&!isset($public['staff_id'],$public['before_sha256'],$public['client_id']));
    $copy=$third->data();$copy['after']['title']='Edited externally';$public['after']['title']='Edited externally';$copySnapshot=$snapshot->data();$copySnapshot['title']='Edited externally';
    check('Returned arrays cannot mutate retained history',$third->snapshot()->data()['title']===$fields['title']&&$snapshot->json()===$original&&$third->forClient(7)['after']['title']===$fields['title']);
    $ref='Referenced title';$refFields=array_replace($fields,['title'=>&$ref]);$refSnapshot=ProjectSnapshot::fromArray($refFields);$ref='Changed after construction';
    check('Caller references cannot mutate an immutable snapshot',$refSnapshot->data()['title']==='Referenced title');
    $literal="Literal </textarea><script>alert(1)</script> & é\n\tcontent";
    $text=ProjectSnapshot::fromArray(array_replace($fields,['summary'=>$literal]));
    check('Text is retained literally for later escaped display',$text->data()['summary']===$literal);
    check('Empty optional project descriptions are valid',ProjectSnapshot::fromArray(array_replace($fields,['summary'=>'','milestones'=>'']))->data()['milestones']==='');
    foreach(ProjectSnapshot::STAGES as $stage){
        $stageSnapshot=ProjectSnapshot::fromArray(array_replace($fields,['stage'=>$stage]));
        check('Existing stage remains valid: '.$stage,$stageSnapshot->data()['stage']===$stage);
    }
    foreach(['title'=>160,'summary'=>10000,'milestones'=>15000] as $key=>$max){
        $f=array_replace($fields,[$key=>str_repeat('x',$max)]);
        check('Exact byte limit accepted for '.$key,strlen(ProjectSnapshot::fromArray($f)->data()[$key])===$max);
        refused('Oversized '.$key.' cannot be silently truncated',fn()=>ProjectSnapshot::fromArray(array_replace($f,[$key=>str_repeat('x',$max+1)])));
    }
    check('UTF-8 title limit counts bytes without requiring mbstring',ProjectSnapshot::fromArray(array_replace($fields,['title'=>str_repeat('é',80)]))->data()['title']===str_repeat('é',80));
    refused('Multibyte title cannot exceed its byte limit',fn()=>ProjectSnapshot::fromArray(array_replace($fields,['title'=>str_repeat('é',81)])));
    $badSnapshots=[
        'private field'=>$fields+['private_note'=>'Internal only'],
        'fabricated progress'=>$fields+['percentage'=>100],
        'missing field'=>array_diff_key($fields,['summary'=>true]),
        'reordered fields'=>array_reverse($fields,true),
        'unknown stage'=>array_replace($fields,['stage'=>'completed']),
        'empty title'=>array_replace($fields,['title'=>'']),
        'blank title'=>array_replace($fields,['title'=>'   ']),
        'title line break'=>array_replace($fields,['title'=>"Name\nextra"]),
        'title tab'=>array_replace($fields,['title'=>"Name\textra"]),
    ];
    foreach($badSnapshots as $name=>$f)refused('Snapshot refuses '.$name,fn()=>ProjectSnapshot::fromArray($f));
    foreach(['title','summary','stage','milestones'] as $key){
        foreach([null,true,123,[],new stdClass()] as $i=>$value)refused('Snapshot refuses non-text '.$key.' case '.$i,fn()=>ProjectSnapshot::fromArray(array_replace($fields,[$key=>$value])));
    }
    foreach(["x\0y","x\ry","x\r\ny","x\x01y","x\x7Fy","x\xFFy"] as $i=>$text)refused('Snapshot refuses invalid/control text '.$i,fn()=>ProjectSnapshot::fromArray(array_replace($fields,['summary'=>$text])));
    $badEvents=[
        'extra private field'=>['private_note'=>'Internal'], 'wrong format'=>['format'=>2], 'coerced format'=>['format'=>'1'],
        'empty identity'=>['id'=>''], 'uppercase identity'=>['id'=>str_repeat('A',32)], 'long identity'=>['id'=>eventId(1).'a'],
        'numeric identity'=>['id'=>1], 'trailing identity newline'=>['id'=>eventId(1)."\n"],
        'zero project'=>['project_id'=>0], 'negative client'=>['client_id'=>-1], 'overflow staff'=>['staff_id'=>2147483648],
        'string project'=>['project_id'=>'21'], 'float client'=>['client_id'=>7.0], 'boolean staff'=>['staff_id'=>true],
        'negative time'=>['occurred_at'=>-1], 'overflow time'=>['occurred_at'=>253402300800], 'string time'=>['occurred_at'=>'101'],
        'negative revision'=>['revision'=>-1], 'overflow revision'=>['revision'=>2147483648], 'string revision'=>['revision'=>'1'],
        'unknown kind'=>['kind'=>'completed'], 'hidden kind'=>['kind'=>'private'], 'empty after'=>['after'=>[]],
        'non-array after'=>['after'=>'text'], 'non-array before'=>['before'=>'text'], 'missing before'=>['before'=>null],
        'missing previous hash'=>['before_sha256'=>null], 'invalid previous hash'=>['before_sha256'=>'not-a-hash'],
        'uppercase hash'=>['before_sha256'=>str_repeat('A',64)], 'incorrect changes'=>['changed_fields'=>['summary']],
        'reordered changes'=>['changed_fields'=>['milestones','stage']], 'duplicated changes'=>['changed_fields'=>['stage','stage','milestones']],
        'empty changes'=>['changed_fields'=>[]], 'private changes'=>['changed_fields'=>['private_note']], 'unchanged snapshot'=>['after'=>$fields],
        'baseline masquerade'=>['kind'=>'tracking_started'], 'zero update revision'=>['revision'=>0],
    ];
    foreach($badEvents as $name=>$values)refused('Stored event refuses '.$name,fn()=>altered($update,$values));
    foreach(['revision'=>1,'before'=>$fields,'before_sha256'=>$update->fingerprint(),'changed_fields'=>['stage'],'kind'=>'updated'] as $key=>$value)refused('Baseline refuses invented '.$key,fn()=>altered($baseline,[$key=>$value]));
    $changed=altered($update,['staff_id'=>5]);check('Changing actual author changes the retained event fingerprint',$changed->fingerprint()!==$update->fingerprint());
    foreach(['project_id'=>22,'client_id'=>8,'revision'=>2,'occurred_at'=>99,'before_sha256'=>$third->fingerprint(),'before'=>array_replace($fields,['summary'=>'Other previous content'])] as $key=>$value){
        // Keep a standalone changed-fields list exact, then verify the independent cross-record boundary.
        $d=array_replace($update->data(),[$key=>$value]);$d['changed_fields']=ProjectSnapshot::fromArray($d['after'])->changesFrom(ProjectSnapshot::fromArray($d['before']));
        $candidate=ProjectProgressEvent::fromJson(jsonData($d));
        refused('History rejects mismatched previous '.$key,fn()=>$candidate->assertFollows($baseline));
    }
    $duplicate=$third->update(eventId(1),3,102,$third->fingerprint(),$snapshot);
    refused('Whole page rejects nonadjacent duplicate event identity',fn()=>ProjectProgressEvent::verifyChain([$baseline,$update,$third,$duplicate]));
    refused('History rejects skipped revision',fn()=>ProjectProgressEvent::verifyChain([$baseline,$third]));
    refused('History rejects reordered updates',fn()=>ProjectProgressEvent::verifyChain([$baseline,$third,$update]));
    refused('History rejects missing baseline or predecessor',fn()=>ProjectProgressEvent::verifyChain([$update,$third]));
    refused('History rejects a second baseline',fn()=>ProjectProgressEvent::verifyChain([$baseline,ProjectProgressEvent::baseline(eventId(8),21,7,3,102,$snapshot)]));
    refused('Page refuses an arbitrary preceding event',fn()=>ProjectProgressEvent::verifyChain([$third],$baseline));
    refused('Empty history cannot claim validation',fn()=>ProjectProgressEvent::verifyChain([]));
    refused('Associative history is not chronological evidence',fn()=>ProjectProgressEvent::verifyChain(['start'=>$baseline]));
    refused('History refuses a non-event value',fn()=>ProjectProgressEvent::verifyChain([$baseline,[]]));
    $chain=[$baseline];for($i=1;$i<100;$i++){$previous=$chain[$i-1];$chain[]=$previous->update(eventId($i+100),3,100+$i,$previous->fingerprint(),ProjectSnapshot::fromArray(array_replace($fields,['summary'=>'Recorded update '.$i])));}
    ProjectProgressEvent::verifyChain($chain);check('A maximum bounded page validates real consecutive history',count($chain)===100);
    refused('Unbounded pages are refused before reconstruction',fn()=>ProjectProgressEvent::verifyChain([...$chain,$third]));
    $max=altered($third,['revision'=>2147483647]);refused('Revision overflow cannot wrap into a new baseline',fn()=>$max->update(eventId(600),3,103,$max->fingerprint(),$snapshot));
    foreach(['','[]','{}','null','true','42'," \n".$baseJson,$baseJson."\n",str_replace('"format":1','"format":1,"format":1',$baseJson),str_replace('"format":1','"format":1.0',$baseJson),json_encode($baseline->data(),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),jsonData(array_reverse($baseline->data(),true)),str_repeat('x',ProjectProgressEvent::MAX_BYTES+1)] as $i=>$raw)refused('Noncanonical or oversized JSON refused '.$i,fn()=>ProjectProgressEvent::fromJson($raw));
    $large=ProjectSnapshot::fromArray(['title'=>str_repeat('é',80),'summary'=>str_repeat("\n",10000),'stage'=>'care','milestones'=>str_repeat("\t",15000)]);
    $largeBase=ProjectProgressEvent::baseline(eventId(700),21,7,3,0,$large);$largeUpdate=$largeBase->update(eventId(701),3,253402300799,$largeBase->fingerprint(),ProjectSnapshot::fromArray(array_replace($large->data(),['stage'=>'paused'])));
    check('Worst-case escaped valid snapshots fit event bounds',strlen($largeUpdate->json())<ProjectProgressEvent::MAX_BYTES);
    check('UTC boundary timestamps roundtrip exactly',$largeBase->data()['occurred_at']===0&&$largeUpdate->data()['occurred_at']===253402300799);
    $names=array_column($checks,'name');if(count($names)!==count(array_unique($names)))throw new RuntimeException('Duplicate test names.');
    echo jsonData(['scope'=>'Pure synthetic records; no current authority, database, UI, activity or provider acceptance.','php'=>PHP_VERSION,'checks'=>$checks,'passed'=>true])."\n";
}catch(Throwable $error){echo jsonData(['checks'=>$checks,'passed'=>false,'failure'=>get_class($error).': '.$error->getMessage()])."\n";exit(1);}
