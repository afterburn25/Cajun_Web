<?php
/** Inactive native project-history adapter; no route, automatic setup or external dispatch. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/includes/foundation/projects/ProjectProgressEvent.php';
require_once CWP_CODE_ROOT.'/database/schema/project_progress_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/project_progress_migrate.php';
use CajunWebPro\Foundation\Projects\{ProjectSnapshot,ProjectProgressEvent};
const PROJECT_PROGRESS_MAX_EVENTS=4096;
function project_progress_json(array $data):string {return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
function project_progress_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/',$id))throw new InvalidArgumentException('Invalid project update identity.');}
function project_progress_number(int $id,bool $zero=false):void {if($id<($zero?0:1)||$id>2147483647)throw new InvalidArgumentException('Invalid project or client.');}
function project_progress_ready():bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['project_progress_schema_version'])['value']??'')==='1';}
function project_progress_require_ready():void {if(!project_progress_ready())throw new RuntimeException('An owner must prepare project history first.');}
function project_progress_locked(callable $work):mixed {
    if(db()->inTransaction())throw new LogicException('Project history cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction($work));
}
function project_progress_touch(callable $work,bool $touch):mixed {
    $saved=array_intersect_key($_SESSION,array_flip(['last_activity','client_last']));
    try{return $work();}finally{if(!$touch)foreach(['last_activity','client_last'] as $key){if(array_key_exists($key,$saved))$_SESSION[$key]=$saved[$key];else unset($_SESSION[$key]);}}
}
function project_progress_staff(bool $ready=true):array {
    $actor=studio_client_manager();if(!staff_ready())throw new RuntimeException('Current staff accounts are required.');
    if($ready)project_progress_require_ready();return $actor;
}
function project_progress_client_row(int $id,bool $active=true):array {
    project_progress_number($id);$row=one('SELECT * FROM '.table('studio_clients').' WHERE id=? FOR UPDATE',[$id]);
    if(!$row||($active&&(int)$row['active']!==1))throw new RuntimeException('Client unavailable.');return $row;
}
function project_progress_job(int $id,?int $client=null):array {
    project_progress_number($id);$row=one('SELECT * FROM '.table('studio_jobs').' WHERE id=? FOR UPDATE',[$id]);
    if(!$row||($client!==null&&(int)$row['client_id']!==$client))throw new RuntimeException('Project unavailable.');return $row;
}
function project_progress_snapshot(array $job):ProjectSnapshot {return ProjectSnapshot::fromArray(['title'=>$job['title'],'summary'=>$job['summary'],'stage'=>$job['stage'],'milestones'=>$job['milestones']]);}
function project_progress_initial_token(array $job):string {return hash('sha256',project_progress_json(['format'=>1,'project_id'=>(int)$job['id'],'client_id'=>(int)$job['client_id'],'snapshot'=>project_progress_snapshot($job)->data(),'head'=>null]));}
function project_progress_input(int $job,int $client,int $staff,string $expected,ProjectSnapshot $fields):string {
    return project_progress_json(['format'=>1,'command'=>$job===0?'create':'update','project_id'=>$job,'client_id'=>$client,'staff_id'=>$staff,'expected'=>$expected,'fields'=>$fields->data()]);
}
function project_progress_audit(int $actor,string $action,string $detail):void {query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'project_progress.'.$action,$detail,gmdate('Y-m-d H:i:s')]);}
/** Full retained history is streamed; display pages do not weaken integrity checks. */
function project_progress_validate(array $job,int $page=0,string $focus=''):array {
    if($page<0||$page>137)throw new InvalidArgumentException('Invalid project history page.');if($focus!=='')project_progress_id($focus);
    $snapshot=project_progress_snapshot($job);$count=(int)query('SELECT COUNT(*) FROM '.table('studio_progress_events').' WHERE job_id=?',[$job['id']])->fetchColumn();
    if($count>PROJECT_PROGRESS_MAX_EVENTS)throw new RuntimeException('Project history exceeds the supported bound. Existing data was preserved.');
    $anchor=one('SELECT * FROM '.table('studio_progress_heads').' WHERE id=?',[$job['id']]);
    if(($count===0&&$anchor!==null)||($count>0&&($anchor===null||(int)$anchor['revision']!==$count-1)))throw new RuntimeException('Project history no longer matches its saved head.');
    $head=null;$baseline=null;$adopted=false;$items=[];$lastPage=$count?intdiv($count-1,30):0;$target=$page===0?$lastPage:$page-1;
    if($target>$lastPage)throw new InvalidArgumentException('Project history page unavailable.');
    if($focus!==''){
        $selected=one('SELECT revision FROM '.table('studio_progress_events').' WHERE id=? AND job_id=?',[$focus,$job['id']]);
        if(!$selected)throw new RuntimeException('Project update unavailable.');$target=intdiv((int)$selected['revision'],30);
    }
    for($offset=0;$offset<$count;$offset+=100){
        $rows=all('SELECT e.*,r.input_json,r.input_sha256 FROM '.table('studio_progress_events').' e LEFT JOIN '.table('studio_progress_requests').' r ON r.id=e.id WHERE e.job_id=? ORDER BY e.revision LIMIT 100 OFFSET '.$offset,[$job['id']]);
        if(count($rows)!==min(100,$count-$offset))throw new RuntimeException('Project history is incomplete.');
        foreach($rows as $i=>$row){
            $event=ProjectProgressEvent::fromJson($row['event_json']);$d=$event->data();$revision=$offset+$i;
            if($d['id']!==$row['id']||$d['project_id']!==(int)$job['id']||$d['client_id']!==(int)$job['client_id']||$d['project_id']!==(int)$row['job_id']||$d['client_id']!==(int)$row['client_id']||$d['staff_id']!==(int)$row['staff_id']||$d['revision']!==$revision||$d['revision']!==(int)$row['revision']||$d['kind']!==$row['kind']||gmdate('Y-m-d H:i:s',$d['occurred_at'])!==$row['created_at']||!hash_equals($event->fingerprint(),$row['event_sha256']))throw new RuntimeException('Saved project history is inconsistent.');
            if($head===null){
                if($d['kind']!=='tracking_started')throw new RuntimeException('Project history has no baseline.');
                $baseline=$event;$adopted=$row['input_json']===null;$expectedInput=project_progress_input(0,$d['client_id'],$d['staff_id'],'',$event->snapshot());
                if($adopted&&($row['input_sha256']!==null||$count<2))throw new RuntimeException('Project adoption has no recorded change.');
            }else{
                $event->assertFollows($head);$expected=$head->fingerprint();
                if($revision===1&&$adopted){
                    $b=$baseline->data();if($d['staff_id']!==$b['staff_id']||$d['occurred_at']!==$b['occurred_at'])throw new RuntimeException('Project adoption attribution is inconsistent.');
                    $expected=project_progress_initial_token(['id'=>$job['id'],'client_id'=>$job['client_id']]+$baseline->snapshot()->data());
                }
                $expectedInput=project_progress_input((int)$job['id'],$d['client_id'],$d['staff_id'],$expected,$event->snapshot());
            }
            if(!($revision===0&&$adopted)&&($row['input_json']!==$expectedInput||!is_string($row['input_sha256'])||!hash_equals(hash('sha256',$expectedInput),$row['input_sha256'])))throw new RuntimeException('Saved project request is inconsistent.');
            if(intdiv($revision,30)===$target)$items[]=$event;$head=$event;
        }
    }
    if($head!==null&&$head->snapshot()->data()!==$snapshot->data())throw new RuntimeException('Current project differs from its retained history.');
    if($head!==null&&($anchor['event_id']!==$head->data()['id']||!hash_equals($head->fingerprint(),$anchor['event_sha256'])))throw new RuntimeException('Project history no longer matches its saved head.');
    return ['head'=>$head,'count'=>$count,'items'=>$items,'page'=>$target+1,'pages'=>$lastPage+1,'expected'=>$head?->fingerprint()??project_progress_initial_token($job)];
}
function project_progress_append(ProjectProgressEvent $event):void {
    $d=$event->data();query('INSERT INTO '.table('studio_progress_events').' (id,job_id,client_id,staff_id,revision,kind,created_at,event_json,event_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$d['id'],$d['project_id'],$d['client_id'],$d['staff_id'],$d['revision'],$d['kind'],gmdate('Y-m-d H:i:s',$d['occurred_at']),$event->json(),$event->fingerprint()]);
}
function project_progress_store_head(ProjectProgressEvent $event,bool $existing):void {
    $d=$event->data();
    if($existing){
        if(query('UPDATE '.table('studio_progress_heads').' SET event_id=?,event_sha256=?,revision=? WHERE id=?',[$d['id'],$event->fingerprint(),$d['revision'],$d['project_id']])->rowCount()!==1)throw new RuntimeException('Saved project head is unavailable.');
    }else query('INSERT INTO '.table('studio_progress_heads').' (id,event_id,event_sha256,revision) VALUES (?,?,?,?)',[$d['project_id'],$d['id'],$event->fingerprint(),$d['revision']]);
}
function project_progress_save(int $id,int $client,string $request,string $expected,array $fields):array {
    return project_progress_locked(function()use($id,$client,$request,$expected,$fields):array {
        $actor=project_progress_staff();project_progress_number($id,true);project_progress_number($client);project_progress_id($request);
        if(($id===0&&$expected!=='')||($id!==0&&!preg_match('/\A[a-f0-9]{64}\z/',$expected)))throw new InvalidArgumentException('Invalid original project version.');
        $snapshot=ProjectSnapshot::fromArray($fields);project_progress_client_row($client);$input=project_progress_input($id,$client,(int)$actor['id'],$expected,$snapshot);
        $existing=one('SELECT r.*,e.job_id FROM '.table('studio_progress_requests').' r JOIN '.table('studio_progress_events').' e ON e.id=r.id WHERE r.id=?',[$request]);
        if($existing){
            if($existing['input_json']!==$input||!hash_equals(hash('sha256',$input),$existing['input_sha256']))throw new RuntimeException('This project request was already used with different details.');
            $job=project_progress_job((int)$existing['job_id'],$client);project_progress_validate($job);
            return ['job_id'=>(int)$job['id'],'event_id'=>$request,'replayed'=>true];
        }
        if(one('SELECT id FROM '.table('studio_progress_events').' WHERE id=?',[$request]))throw new RuntimeException('Project request identity is already in use.');
        $at=time();$tracked=false;
        if($id===0){
            $f=$snapshot->data();query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,stage,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[$client,$f['title'],$f['summary'],$f['stage'],$f['milestones'],gmdate('Y-m-d H:i:s',$at),gmdate('Y-m-d H:i:s',$at)]);$id=(int)db()->lastInsertId();
            $event=ProjectProgressEvent::baseline($request,$id,$client,(int)$actor['id'],$at,$snapshot);
        }else{
            $job=project_progress_job($id,$client);$state=project_progress_validate($job);$tracked=$state['head']!==null;
            if(!hash_equals($state['expected'],$expected))throw new RuntimeException('This project changed. Review the current project before saving.');
            if($snapshot->data()===project_progress_snapshot($job)->data())throw new InvalidArgumentException('No project fields changed.');
            if($state['count']+($state['head']===null?2:1)>PROJECT_PROGRESS_MAX_EVENTS)throw new RuntimeException('Project history is full. Existing project details were preserved.');
            $head=$state['head'];
            if($head===null){
                do{$baselineId=bin2hex(random_bytes(16));}while($baselineId===$request||one('SELECT id FROM '.table('studio_progress_events').' WHERE id=?',[$baselineId]));
                $head=ProjectProgressEvent::baseline($baselineId,$id,$client,(int)$actor['id'],$at,project_progress_snapshot($job));project_progress_append($head);
            }
            $event=$head->update($request,(int)$actor['id'],$at,$head->fingerprint(),$snapshot);$f=$snapshot->data();
            query('UPDATE '.table('studio_jobs').' SET title=?,summary=?,stage=?,milestones=?,updated_at=? WHERE id=?',[$f['title'],$f['summary'],$f['stage'],$f['milestones'],gmdate('Y-m-d H:i:s',$at),$id]);
        }
        project_progress_append($event);query('INSERT INTO '.table('studio_progress_requests').' (id,input_json,input_sha256) VALUES (?,?,?)',[$request,$input,hash('sha256',$input)]);
        project_progress_store_head($event,$tracked);
        project_progress_audit((int)$actor['id'],'saved','Project '.$id.'; update '.$request);return ['job_id'=>$id,'event_id'=>$request,'replayed'=>false];
    });
}
function project_progress_view(array $job,array $state,bool $staff):array {
    $items=[];foreach($state['items'] as $event)$items[]=$staff?$event->data():$event->forClient((int)$job['client_id']);
    $result=['project'=>['id'=>(int)$job['id']]+project_progress_snapshot($job)->data(),'tracked'=>$state['head']!==null,'count'=>$state['count'],'items'=>$items,'page'=>$state['page'],'pages'=>$state['pages']];
    if($staff)$result+=['client_id'=>(int)$job['client_id'],'expected'=>$state['expected']];return $result;
}
function project_progress_staff_get(int $id,int $page=0,string $focus='',bool $touch=true):array {
    return project_progress_touch(fn()=>project_progress_locked(function()use($id,$page,$focus):array {
        project_progress_staff();$job=project_progress_job($id);project_progress_client_row((int)$job['client_id'],false);return project_progress_view($job,project_progress_validate($job,$page,$focus),true);
    }),$touch);
}
function project_progress_client_get(int $id,int $client,int $page=0,string $focus='',?string $support=null,bool $touch=true):array {
    return project_progress_touch(fn()=>project_progress_locked(function()use($id,$client,$page,$focus,$support,$touch):array {
        project_progress_require_ready();if(!studio_ready()||!staff_ready())throw new RuntimeException('Project history unavailable.');project_progress_number($client);$ctx=null;
        if($support!==null||support_current()||array_key_exists('support',$_GET)){
            if($support===null||$support==='')throw new RuntimeException('A current scoped support session is required.');$ctx=support_check($support,$touch);
            if(!$ctx||(int)$ctx['client']['id']!==$client)throw new RuntimeException('Current support access to this project is required.');
        }else{
            $row=project_progress_client_row($client);$now=time();
            if((int)($_SESSION['client_id']??0)!==$client||$now-(int)($_SESSION['client_last']??0)>1800||$now-(int)($_SESSION['client_login']??0)>28800||!hash_equals($row['auth_version'],(string)($_SESSION['client_auth']??'')))throw new RuntimeException('Sign in to view your current project.');
            if($touch)$_SESSION['client_last']=$now;
        }
        $job=project_progress_job($id,$client);$view=project_progress_view($job,project_progress_validate($job,$page,$focus),false);
        if($ctx&&$touch){project_progress_audit((int)$ctx['actor']['id'],'support_read','Project '.$id.'; support '.$ctx['session']['id']);support_audit($ctx['session'],'project.progress','project',(string)$id);}
        return $view;
    }),$touch);
}
