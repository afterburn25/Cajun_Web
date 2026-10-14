<?php
declare(strict_types=1);
use CajunWebPro\Foundation\ProjectPlanning\{ProjectPlan, ProjectPlanEvent};
require __DIR__.'/../../foundation/project-planning/ProjectPlanEvent.php';
$checks = [];
function check(string $name, bool $pass): void {
    global $checks; $checks[] = ['name' => $name, 'passed' => $pass];
    if (!$pass) throw new RuntimeException($name);
}
function refused(string $name, callable $action): void {
    try { $action(); } catch (InvalidArgumentException|LogicException|JsonException) { check($name, true); return; }
    check($name, false);
}
function json(array $data): string { return json_encode($data, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
function identity(int $number): string { return str_pad(dechex($number), 32, '0', STR_PAD_LEFT); }
function milestone(int $number, string $state = 'planned', array $dependencies = []): array {
    return ['id'=>identity($number),'title'=>'Milestone '.$number,'details'=>'','state'=>$state,'owner'=>'studio','due_date'=>null,'depends_on'=>$dependencies];
}
function plan(array $milestones = []): array {
    return ['scope'=>'','assigned_contact'=>'','target_date'=>null,'client_action'=>'','blockers'=>'','milestones'=>$milestones];
}
try {
    $empty = ProjectPlan::fromArray(plan());
    check('Empty unassigned plan does not invent dates milestones or client requests', $empty->data()===plan());
    check('Canonical plan round trip is exact', ProjectPlan::fromJson($empty->json())->json()===$empty->json());
    check('Plan fingerprint identifies exact stored bytes', $empty->fingerprint()===hash('sha256',$empty->json()));
    $text = "Résumé <script>alert(1)</script>\r\nNext\rLine\t✓";
    $data = plan([milestone(1)]); $data['scope']=$text; $data['client_action']=$text; $data['blockers']=$text; $data['milestones'][0]['details']=$text; $data['assigned_contact']='Actual studio contact';
    $public = ProjectPlan::fromArray($data);
    check('Exact multiline CRLF CR tab Unicode and raw markup remain text', $public->data()===$data);
    check('Multiline canonical round trip preserves bytes', ProjectPlan::fromJson($public->json())->data()===$data);
    $alias='Original scope';$incoming=plan();$incoming['scope']=&$alias;$immutable=ProjectPlan::fromArray($incoming);$alias='Changed externally';
    check('Caller references cannot change a saved scope', $immutable->data()['scope']==='Original scope');
    $dependency=identity(1);$incoming=plan([milestone(1),milestone(2,'planned',[&$dependency])]);$immutable=ProjectPlan::fromArray($incoming);$dependency=identity(99);
    check('Nested caller references cannot change accepted dependencies', $immutable->data()['milestones'][1]['depends_on']===[identity(1)]);
    $returned=$immutable->data();$returned['milestones'][0]['state']='completed';
    check('Returned arrays cannot mutate an accepted plan', $immutable->data()['milestones'][0]['state']==='planned');
    check('Plan constructor is private', !(new ReflectionClass(ProjectPlan::class))->getConstructor()->isPublic());
    foreach(ProjectPlan::FIELDS as $field){$bad=plan();unset($bad[$field]);refused('Missing planning field '.$field,fn()=>ProjectPlan::fromArray($bad));}
    foreach([true,1,null,[],new stdClass()] as $i=>$badValue){$bad=plan();$bad['scope']=$badValue;refused('Nontext scope is refused '.$i,fn()=>ProjectPlan::fromArray($bad));}
    $bad=plan();$bad['private_notes']='Internal';refused('Unknown private planning field is refused',fn()=>ProjectPlan::fromArray($bad));
    refused('Reordered planning fields are refused',fn()=>ProjectPlan::fromArray(array_reverse(plan(),true)));
    foreach(['scope'=>10000,'assigned_contact'=>160,'client_action'=>4000,'blockers'=>4000] as $field=>$limit){
        $data=plan();$data[$field]=str_repeat('x',$limit);check('Exact byte limit accepted for '.$field,ProjectPlan::fromArray($data)->data()===$data);
        $data[$field].='x';refused('Over byte limit refused for '.$field,fn()=>ProjectPlan::fromArray($data));
    }
    foreach(["\0","\x01","\x08","\x0b","\x0c","\x1f","\x7f","\xff"] as $i=>$badText){$bad=plan();$bad['scope']='Text'.$badText;refused('Unsafe multiline text refused '.$i,fn()=>ProjectPlan::fromArray($bad));}
    foreach(["Contact\nNext","Contact\tNext",'   ',"\u{00a0}"] as $i=>$contact){$bad=plan();$bad['assigned_contact']=$contact;refused('Invalid visible contact label refused '.$i,fn()=>ProjectPlan::fromArray($bad));}
    foreach([null,'0001-01-01','2000-02-29','2024-02-29','2026-10-14','9999-12-31'] as $i=>$date){
        $data=plan([milestone(1)]);$data['target_date']=$date;$data['milestones'][0]['due_date']=$date;
        check('Valid calendar date retained without timezone conversion '.$i,ProjectPlan::fromArray($data)->data()===$data);
    }
    foreach(['0000-01-01','1900-02-29','2025-02-29','2026-04-31','2026-13-01','2026-00-01','2026-01-00','26-01-01','2026-1-01','2026-01-01T00:00:00Z',' 2026-01-01',"2026-01-01\n",1,true,[],new stdClass()] as $i=>$date){
        $bad=plan([milestone(1)]);$bad['target_date']=$date;refused('Invalid target date refused '.$i,fn()=>ProjectPlan::fromArray($bad));
        $bad=plan([milestone(1)]);$bad['milestones'][0]['due_date']=$date;refused('Invalid milestone date refused '.$i,fn()=>ProjectPlan::fromArray($bad));
    }
    foreach(ProjectPlan::STATES as $state){$data=plan([milestone(1,$state)]);check('Explicit milestone state retained '.$state,ProjectPlan::fromArray($data)->data()===$data);}
    $data=plan([milestone(1,'awaiting_client')]);$data['milestones'][0]['owner']='client';check('Client responsibility does not create a login or inferred completion',ProjectPlan::fromArray($data)->data()===$data);
    foreach(ProjectPlan::MILESTONE_FIELDS as $field){$bad=plan([milestone(1)]);unset($bad['milestones'][0][$field]);refused('Missing milestone field '.$field,fn()=>ProjectPlan::fromArray($bad));}
    foreach(['id'=>['ABC',identity(1)."\n",1,null],'title'=>[''," \t",str_repeat('x',161),"line\nnext",[],"\xff"],'details'=>[str_repeat('x',2001),"\x01",false],'state'=>['paid','unknown',0,null],'owner'=>['admin',1,null],'depends_on'=>['',null,[identity(2),identity(2)]]] as $field=>$values){
        foreach($values as $i=>$value){$bad=plan([milestone(1)]);$bad['milestones'][0][$field]=$value;refused('Invalid milestone '.$field.' case '.$i,fn()=>ProjectPlan::fromArray($bad));}
    }
    $data=plan([milestone(1)]);$data['milestones'][0]['title']=str_repeat('é',80);$data['milestones'][0]['details']=str_repeat('x',2000);check('Milestone limits count exact UTF8 bytes',ProjectPlan::fromArray($data)->data()===$data);
    $data['milestones'][0]['title'].='é';refused('Multibyte overlength title refused',fn()=>ProjectPlan::fromArray($data));
    $bad=plan([milestone(1),milestone(1)]);refused('Duplicate milestone identities refused',fn()=>ProjectPlan::fromArray($bad));
    foreach([null,'',new stdClass(),[1=>milestone(1)]] as $i=>$value){$bad=plan();$bad['milestones']=$value;refused('Milestones must be an ordered list '.$i,fn()=>ProjectPlan::fromArray($bad));}
    $bad=plan([milestone(1)]);$bad['milestones'][0]['extra']='x';refused('Unknown milestone fields refused',fn()=>ProjectPlan::fromArray($bad));
    $bad=plan([array_reverse(milestone(1),true)]);refused('Milestone field order is canonical',fn()=>ProjectPlan::fromArray($bad));
    $bad=plan([milestone(1,'planned',[identity(1)])]);refused('Self-dependency refused',fn()=>ProjectPlan::fromArray($bad));
    $bad=plan([milestone(1,'planned',[identity(2)])]);refused('Dependency outside this project plan refused',fn()=>ProjectPlan::fromArray($bad));
    $bad=plan([milestone(1,'planned',[identity(2)]),milestone(2,'planned',[identity(1)])]);refused('Two-node dependency cycle refused',fn()=>ProjectPlan::fromArray($bad));
    $bad=plan([milestone(1),milestone(2,'planned',[identity(3)]),milestone(3,'planned',[identity(4)]),milestone(4,'planned',[identity(2)])]);refused('Cycle in a disconnected graph component refused',fn()=>ProjectPlan::fromArray($bad));
    $bad=plan([milestone(1),milestone(2),milestone(3,'planned',[identity(2),identity(1)])]);refused('Unsorted dependencies refused without silent reordering',fn()=>ProjectPlan::fromArray($bad));
    $milestones=[];for($i=1;$i<=9;$i++)$milestones[]=milestone($i,'completed');$deps=array_column(array_slice($milestones,0,8),'id');$milestones[]=milestone(10,'completed',$deps);
    check('Eight satisfied dependencies accepted',count(ProjectPlan::fromArray(plan($milestones))->data()['milestones'])===10);
    $milestones[9]['depends_on'][]=identity(9);refused('Ninth dependency refused',fn()=>ProjectPlan::fromArray(plan($milestones)));
    foreach(['planned','in_progress','awaiting_client','blocked','cancelled'] as $state){$bad=plan([milestone(1,$state),milestone(2,'completed',[identity(1)])]);refused('Completion requires completed dependency not '.$state,fn()=>ProjectPlan::fromArray($bad));}
    $data=plan([milestone(1,'completed'),milestone(2,'completed',[identity(1)]),milestone(3,'in_progress',[identity(2)])]);$chainPlan=ProjectPlan::fromArray($data);
    check('Transitive completed prerequisites and ongoing final work remain distinct',$chainPlan->data()===$data);
    $bad=$data;$bad['milestones'][0]['state']='in_progress';refused('Reopening prerequisite cannot leave completed dependent inconsistent',fn()=>ProjectPlan::fromArray($bad));
    $milestones=[];for($i=1;$i<=100;$i++)$milestones[]=milestone($i,'planned',$i===1?[]:[identity($i-1)]);
    check('100-node dependency chain accepted within bound',count(ProjectPlan::fromArray(plan($milestones))->data()['milestones'])===100);
    $milestones[0]['depends_on']=[identity(100)];refused('Long dependency cycle refused',fn()=>ProjectPlan::fromArray(plan($milestones)));$milestones[0]['depends_on']=[];
    $milestones[]=milestone(101);refused('101 retained milestones refused',fn()=>ProjectPlan::fromArray(plan($milestones)));array_pop($milestones);
    foreach($milestones as &$m)$m['details']=str_repeat("\t",2000);unset($m);refused('Aggregate encoded plan bound applies despite individual text limits',fn()=>ProjectPlan::fromArray(plan($milestones)));
    foreach(['null','[]','{}',$empty->json()."\n",str_replace('"scope":""','"scope":"","scope":""',$empty->json()),str_replace('"milestones":[]','"milestones":{}',$empty->json()),'{invalid'] as $i=>$bytes)refused('Noncanonical malformed or duplicate-key plan refused '.$i,fn()=>ProjectPlan::fromJson($bytes));
    refused('Oversized plan input refused before parsing',fn()=>ProjectPlan::fromJson(str_repeat('x',ProjectPlan::MAX_BYTES+1)));

    $first=ProjectPlanEvent::start(identity(1001),11,22,33,1000,$chainPlan);
    check('First explicit plan records no invented prior event', $first->data()['kind']==='plan_started'&&$first->data()['before']===null&&$first->data()['revision']===0&&$first->data()['changed_fields']===[]);
    $changed=$chainPlan->data();$changed['client_action']='Approve the homepage layout';$changed['target_date']='2026-11-01';$changed['blockers']='Waiting for supplied photographs';$after=ProjectPlan::fromArray($changed);
    $second=$first->update(identity(1002),34,1000,$first->fingerprint(),$after);
    check('Changes preserve actual complete before and after plans',$second->data()['before']===$chainPlan->data()&&$second->plan()->data()===$after->data());
    check('Changed fields follow plan order without inferred metrics',$second->data()['changed_fields']===['target_date','client_action','blockers']);
    check('Same-second change retains next revision',$second->data()['revision']===1&&$second->data()['occurred_at']===1000);
    ProjectPlanEvent::verifyChain([$first,$second]);check('Valid full planning chain accepted',true);
    ProjectPlanEvent::verifyChain([$second],$first);check('Bounded planning page validates supplied predecessor',true);
    check('Exact event round trip and fingerprint retained',ProjectPlanEvent::fromJson($second->json())->json()===$second->json()&&$second->fingerprint()===hash('sha256',$second->json()));
    check('Event constructor is private',!(new ReflectionClass(ProjectPlanEvent::class))->getConstructor()->isPublic());
    $returned=$second->data();$returned['after']['scope']='Overwrite';check('Returned event data cannot mutate retained snapshot',$second->plan()->data()['scope']===$after->data()['scope']);
    refused('Stale expected head refused',fn()=>$second->update(identity(1003),33,1001,$first->fingerprint(),$chainPlan));
    refused('No-op plan save refused',fn()=>$second->update(identity(1003),33,1001,$second->fingerprint(),$after));
    refused('Reused latest event identity refused',fn()=>$second->update(identity(1002),33,1001,$second->fingerprint(),$chainPlan));
    refused('Backward event time refused',fn()=>$second->update(identity(1003),33,999,$second->fingerprint(),$chainPlan));
    $removed=$after->data();array_pop($removed['milestones']);refused('Plan update cannot remove a retained milestone',fn()=>$second->update(identity(1003),33,1001,$second->fingerprint(),ProjectPlan::fromArray($removed)));
    $cancelled=$after->data();$cancelled['milestones'][2]['state']='cancelled';$third=$second->update(identity(1003),33,1001,$second->fingerprint(),ProjectPlan::fromArray($cancelled));
    check('Explicit cancellation retains milestone identity and prior state',$third->data()['after']['milestones'][2]['state']==='cancelled'&&$third->data()['before']['milestones'][2]['state']==='in_progress');
    $projection=$third->forClient(22);check('Public projection contains only approved history fields',array_keys($projection)===['id','revision','kind','occurred_at','before','after','changed_fields']);
    refused('Cross-client public projection refused',fn()=>$third->forClient(23));
    $projection['after']['scope']='tampered';check('Public projection cannot mutate saved event',$third->plan()->data()['scope']==='');
    foreach(['format'=>2,'id'=>'bad','project_id'=>0,'client_id'=>'22','staff_id'=>true,'revision'=>-1,'occurred_at'=>-1,'kind'=>'paid','before'=>null,'before_sha256'=>'bad','changed_fields'=>[],'after'=>[]] as $field=>$value){$bad=$second->data();$bad[$field]=$value;refused('Invalid event field refused '.$field,fn()=>ProjectPlanEvent::fromJson(json($bad)));}
    foreach(['project_id'=>12,'client_id'=>23,'revision'=>2,'occurred_at'=>999,'before_sha256'=>str_repeat('a',64)] as $field=>$value){$bad=$second->data();$bad[$field]=$value;$forged=ProjectPlanEvent::fromJson(json($bad));refused('Standalone event cannot bypass predecessor '.$field,fn()=>$forged->assertFollows($first));}
    $bad=$second->data();$bad['before']['scope']='Other project scope';$bad['changed_fields']=['scope','target_date','client_action','blockers'];$forged=ProjectPlanEvent::fromJson(json($bad));refused('Valid individual snapshot cannot substitute earlier plan',fn()=>$forged->assertFollows($first));
    foreach(['revision'=>1,'before_sha256'=>str_repeat('a',64),'before'=>$empty->data(),'changed_fields'=>['scope']] as $field=>$value){$bad=$first->data();$bad[$field]=$value;refused('Initial event cannot invent prior '.$field,fn()=>ProjectPlanEvent::fromJson(json($bad)));}
    foreach(['project_id','client_id','staff_id'] as $field){$bad=$first->data();$bad[$field]=2147483648;refused('Local account upper bound '.$field,fn()=>ProjectPlanEvent::fromJson(json($bad)));}
    $bad=$first->data();$bad['occurred_at']=253402300800;refused('Event time upper bound enforced',fn()=>ProjectPlanEvent::fromJson(json($bad)));
    $bad=$second->data();$bad['revision']=2147483647;$edge=ProjectPlanEvent::fromJson(json($bad));refused('Revision overflow refused without wrapping',fn()=>$edge->update(identity(1003),33,1001,$edge->fingerprint(),$chainPlan));
    foreach(['null','{}',$first->json()."\n",str_replace('"format":1','"format":1,"format":1',$first->json())] as $i=>$bytes)refused('Noncanonical event encoding refused '.$i,fn()=>ProjectPlanEvent::fromJson($bytes));
    refused('Oversized event input refused before parsing',fn()=>ProjectPlanEvent::fromJson(str_repeat('x',ProjectPlanEvent::MAX_BYTES+1)));
    foreach([[],[1=>$first],[$second],[$first,$first],[$first,$third],[$first,$second,new stdClass()]] as $i=>$events)refused('Invalid bounded planning chain refused '.$i,fn()=>ProjectPlanEvent::verifyChain($events));
    refused('Predecessor identity cannot appear again in page',fn()=>ProjectPlanEvent::verifyChain([$first],$first));
    $events=[$first];$last=$first;for($i=1;$i<100;$i++){$snapshot=$last->plan()->data();$snapshot['client_action']='Request '.$i;$last=$last->update(identity(2000+$i),33,1000+$i,$last->fingerprint(),ProjectPlan::fromArray($snapshot));$events[]=$last;}
    ProjectPlanEvent::verifyChain($events);check('100-event page remains bounded and complete',true);
    $events[]=$third;refused('101-event page refused before validation',fn()=>ProjectPlanEvent::verifyChain($events));
    echo json_encode(['passed'=>true,'php'=>PHP_VERSION,'scope'=>'Pure planning data and history only; no native authority, persistence, HTTP, provider or release acceptance.','checks'=>$checks],JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    echo json_encode(['passed'=>false,'php'=>PHP_VERSION,'checks'=>$checks,'error'=>get_class($error).': '.$error->getMessage()],JSON_THROW_ON_ERROR)."\n";
    exit(1);
}
