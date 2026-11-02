<?php
/** Current-authorized structured plans; no routes, automatic setup or external dispatch. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/projects/progress.php';
require_once CWP_CODE_ROOT.'/includes/foundation/project-planning/ProjectPlanEvent.php';
require_once CWP_CODE_ROOT.'/database/schema/project_planning_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/project_planning_migrate.php';
require_once CWP_CODE_ROOT.'/app/projects/planning_history.php';
use CajunWebPro\Foundation\ProjectPlanning\{ProjectPlan,ProjectPlanEvent};
const PROJECT_PLANNING_MAX_EVENTS=4096;
const PROJECT_PLANNING_MAX_BYTES=33554432;
const PROJECT_PLANNING_PAGE_SIZE=10;
function project_planning_ready():bool{return (one('SELECT value FROM '.table('settings').' WHERE name=?',['project_planning_schema_version'])['value']??'')==='1';}
function project_planning_require_ready():void{if(!project_planning_ready())throw new RuntimeException('An owner must prepare project planning first.');}
function project_planning_staff(bool $ready=true):array{
    $actor=project_progress_staff(false);if($ready)project_planning_require_ready();return $actor;
}
function project_planning_initial_token(array $job):string{return hash('sha256',project_progress_json(['format'=>1,'project_id'=>(int)$job['id'],'client_id'=>(int)$job['client_id'],'head'=>null]));}
function project_planning_input(int $job,int $client,int $staff,string $expected,ProjectPlan $plan):string{
    return project_progress_json(['format'=>1,'project_id'=>$job,'client_id'=>$client,'staff_id'=>$staff,'expected'=>$expected,'fields'=>$plan->data()]);
}
function project_planning_audit(int $actor,string $action,string $detail):void{
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'project_planning.'.$action,$detail,gmdate('Y-m-d H:i:s')]);
}
function project_planning_append(ProjectPlanEvent $event,string $input):void{
    $d=$event->data();query('INSERT INTO '.table('studio_plan_events').' (id,job_id,client_id,staff_id,revision,kind,created_at,event_json,event_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$d['id'],$d['project_id'],$d['client_id'],$d['staff_id'],$d['revision'],$d['kind'],gmdate('Y-m-d H:i:s',$d['occurred_at']),$event->json(),$event->fingerprint()]);
    query('INSERT INTO '.table('studio_plan_requests').' (id,input_json,input_sha256) VALUES (?,?,?)',[$d['id'],$input,hash('sha256',$input)]);
}
function project_planning_store_head(ProjectPlanEvent $event,int $bytes,bool $existing):void{
    $d=$event->data();$plan=$event->plan();$args=[$d['id'],$event->fingerprint(),$d['revision'],$plan->json(),$plan->fingerprint(),$bytes,$d['project_id']];
    if($existing){
        if(query('UPDATE '.table('studio_plan_heads').' SET event_id=?,event_sha256=?,revision=?,plan_json=?,plan_sha256=?,retained_bytes=? WHERE id=?',$args)->rowCount()!==1)throw new RuntimeException('Saved planning head is unavailable.');
    }else query('INSERT INTO '.table('studio_plan_heads').' (event_id,event_sha256,revision,plan_json,plan_sha256,retained_bytes,id) VALUES (?,?,?,?,?,?,?)',$args);
}
function project_planning_save(int $id,int $client,string $request,string $expected,array $fields):array{
    return project_progress_locked(function()use($id,$client,$request,$expected,$fields):array{
        $actor=project_planning_staff();project_progress_number($id);project_progress_number($client);project_progress_id($request);
        if(!preg_match('/\A[a-f0-9]{64}\z/',$expected))throw new InvalidArgumentException('Invalid original planning version.');
        $plan=ProjectPlan::fromArray($fields);project_progress_client_row($client);$job=project_progress_job($id,$client);
        $input=project_planning_input($id,$client,(int)$actor['id'],$expected,$plan);
        $existing=one('SELECT * FROM '.table('studio_plan_requests').' WHERE id=?',[$request]);
        if($existing){
            if($existing['input_json']!==$input||!hash_equals(hash('sha256',$input),$existing['input_sha256']))throw new RuntimeException('This planning request was already used with different details.');
            project_planning_validate($job);
            $event=one('SELECT job_id FROM '.table('studio_plan_events').' WHERE id=?',[$request]);
            if(!$event||(int)$event['job_id']!==$id)throw new RuntimeException('Saved planning request is inconsistent.');
            return ['job_id'=>$id,'event_id'=>$request,'replayed'=>true];
        }
        if(one('SELECT id FROM '.table('studio_plan_events').' WHERE id=?',[$request]))throw new RuntimeException('Planning request identity is already in use.');
        $state=project_planning_validate($job);$head=$state['head'];
        if(!hash_equals($state['expected'],$expected))throw new RuntimeException('This plan changed. Review the current plan before saving.');
        if($head&&$head->plan()->json()===$plan->json())throw new InvalidArgumentException('No planning fields changed.');
        $event=$head?$head->update($request,(int)$actor['id'],time(),$expected,$plan):ProjectPlanEvent::start($request,$id,$client,(int)$actor['id'],time(),$plan);
        $bytes=$state['bytes']+strlen($event->json())+strlen($input);
        if($state['count']>=PROJECT_PLANNING_MAX_EVENTS||$bytes>PROJECT_PLANNING_MAX_BYTES)throw new RuntimeException('Planning history is full. Existing plans were preserved.');
        project_planning_append($event,$input);project_planning_store_head($event,$bytes,$head!==null);
        project_planning_audit((int)$actor['id'],'saved','Project '.$id.'; plan '.$request);
        return ['job_id'=>$id,'event_id'=>$request,'replayed'=>false];
    });
}
function project_planning_view(array $job,array $state,bool $staff):array{
    $items=[];foreach($state['items'] as $event)$items[]=$staff?$event->data():$event->forClient((int)$job['client_id']);
    $view=['project'=>['id'=>(int)$job['id'],'title'=>$job['title']],'planned'=>$state['head']!==null,'plan'=>$state['head']?->plan()->data(),'count'=>$state['count'],'items'=>$items,'page'=>$state['page'],'pages'=>$state['pages']];
    if($staff)$view+=['client_id'=>(int)$job['client_id'],'expected'=>$state['expected'],'retained_bytes'=>$state['bytes']];return $view;
}
function project_planning_staff_get(int $id,int $page=0,string $focus='',bool $touch=true):array{
    return project_progress_touch(fn()=>project_progress_locked(function()use($id,$page,$focus):array{
        project_planning_staff();$job=project_progress_job($id);project_progress_client_row((int)$job['client_id'],false);
        return project_planning_view($job,project_planning_validate($job,$page,$focus),true);
    }),$touch);
}
function project_planning_client_get(int $id,int $client,int $page=0,string $focus='',?string $support=null,bool $touch=true):array{
    return project_progress_touch(fn()=>project_progress_locked(function()use($id,$client,$page,$focus,$support,$touch):array{
        project_planning_require_ready();if(!studio_ready()||!staff_ready())throw new RuntimeException('Project planning unavailable.');project_progress_number($client);$ctx=null;
        if($support!==null||support_current()||array_key_exists('support',$_GET)){
            if($support===null||$support==='')throw new RuntimeException('A current scoped support session is required.');$ctx=support_check($support,$touch);
            if(!$ctx||(int)$ctx['client']['id']!==$client)throw new RuntimeException('Current support access to this project is required.');
        }else{
            $row=project_progress_client_row($client);$now=time();
            if((int)($_SESSION['client_id']??0)!==$client||$now-(int)($_SESSION['client_last']??0)>1800||$now-(int)($_SESSION['client_login']??0)>28800||!hash_equals($row['auth_version'],(string)($_SESSION['client_auth']??'')))throw new RuntimeException('Sign in to view your current project.');
            if($touch)$_SESSION['client_last']=$now;
        }
        $job=project_progress_job($id,$client);$view=project_planning_view($job,project_planning_validate($job,$page,$focus),false);
        if($ctx&&$touch){project_planning_audit((int)$ctx['actor']['id'],'support_read','Project '.$id.'; support '.$ctx['session']['id']);support_audit($ctx['session'],'project.planning','project',(string)$id);}
        return $view;
    }),$touch);
}
