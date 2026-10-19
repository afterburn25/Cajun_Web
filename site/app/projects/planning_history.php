<?php
/** Every event is validated; only the requested display page is retained. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
use CajunWebPro\Foundation\ProjectPlanning\{ProjectPlan,ProjectPlanEvent};
function project_planning_validate(array $job,int $page=0,string $focus=''):array{
    if($page<0||$page>410)throw new InvalidArgumentException('Invalid planning history page.');if($focus!=='')project_progress_id($focus);
    $summary=one('SELECT COUNT(*) AS total,COALESCE(SUM(OCTET_LENGTH(e.event_json)+COALESCE(OCTET_LENGTH(r.input_json),0)),0) AS bytes FROM '.table('studio_plan_events').' e LEFT JOIN '.table('studio_plan_requests').' r ON r.id=e.id WHERE e.job_id=?',[$job['id']]);
    $count=(int)$summary['total'];$bytes=(int)$summary['bytes'];
    if($count>PROJECT_PLANNING_MAX_EVENTS||$bytes>PROJECT_PLANNING_MAX_BYTES)throw new RuntimeException('Planning history exceeds the supported bound. Existing data was preserved.');
    $anchor=one('SELECT id,event_id,event_sha256,revision,retained_bytes,plan_sha256,CASE WHEN OCTET_LENGTH(plan_json)<='.ProjectPlan::MAX_BYTES.' THEN plan_json ELSE NULL END AS plan_json FROM '.table('studio_plan_heads').' WHERE id=?',[$job['id']]);
    if($anchor!==null&&!is_string($anchor['plan_json']))throw new RuntimeException('Saved current plan exceeds the supported bound.');
    if(($count===0&&$anchor!==null)||($count>0&&($anchor===null||(int)$anchor['revision']!==$count-1||(int)$anchor['retained_bytes']!==$bytes)))throw new RuntimeException('Planning history no longer matches its saved head.');
    $lastPage=$count?intdiv($count-1,PROJECT_PLANNING_PAGE_SIZE):0;$target=$page===0?$lastPage:$page-1;
    if($target>$lastPage)throw new InvalidArgumentException('Planning history page unavailable.');
    if($focus!==''){
        $selected=one('SELECT revision FROM '.table('studio_plan_events').' WHERE id=? AND job_id=?',[$focus,$job['id']]);
        if(!$selected)throw new RuntimeException('Plan update unavailable.');$target=intdiv((int)$selected['revision'],PROJECT_PLANNING_PAGE_SIZE);
        if($target>$lastPage)throw new RuntimeException('Plan update is inconsistent.');
    }
    $head=null;$items=[];$seen=[];$actualBytes=0;$revision=0;
    // Payload allocation is bounded by the aggregate byte check above. Process one decoded row at a time.
    $statement=query('SELECT e.*,r.input_json,r.input_sha256 FROM '.table('studio_plan_events').' e LEFT JOIN '.table('studio_plan_requests').' r ON r.id=e.id WHERE e.job_id=? ORDER BY e.revision',[$job['id']]);
    while($row=$statement->fetch(PDO::FETCH_ASSOC)){
        if(!is_string($row['input_json'])||strlen($row['input_json'])>ProjectPlan::MAX_BYTES+1024||!is_string($row['input_sha256']))throw new RuntimeException('Saved planning request is unavailable.');
        $event=ProjectPlanEvent::fromJson($row['event_json']);$d=$event->data();
        if(isset($seen[$d['id']])||$d['id']!==$row['id']||$d['project_id']!==(int)$job['id']||$d['client_id']!==(int)$job['client_id']||$d['project_id']!==(int)$row['job_id']||$d['client_id']!==(int)$row['client_id']||$d['staff_id']!==(int)$row['staff_id']||$d['revision']!==$revision||$d['revision']!==(int)$row['revision']||$d['kind']!==$row['kind']||gmdate('Y-m-d H:i:s',$d['occurred_at'])!==$row['created_at']||!hash_equals($event->fingerprint(),$row['event_sha256']))throw new RuntimeException('Saved planning history is inconsistent.');
        if($head===null){if($d['kind']!=='plan_started')throw new RuntimeException('Planning history has no initial plan.');$expected=project_planning_initial_token($job);}
        else{$event->assertFollows($head);$expected=$head->fingerprint();}
        $input=project_planning_input((int)$job['id'],(int)$job['client_id'],$d['staff_id'],$expected,$event->plan());
        if($row['input_json']!==$input||!hash_equals(hash('sha256',$input),$row['input_sha256']))throw new RuntimeException('Saved planning request is inconsistent.');
        $actualBytes+=strlen($row['event_json'])+strlen($row['input_json']);$seen[$d['id']]=true;
        if(intdiv($revision,PROJECT_PLANNING_PAGE_SIZE)===$target)$items[]=$event;
        $head=$event;$revision++;
    }
    $statement->closeCursor();
    if($revision!==$count||$actualBytes!==$bytes)throw new RuntimeException('Planning history is incomplete.');
    if($head){
        $plan=ProjectPlan::fromJson($anchor['plan_json']);
        if($anchor['event_id']!==$head->data()['id']||!hash_equals($head->fingerprint(),$anchor['event_sha256'])||$plan->json()!==$head->plan()->json()||!hash_equals($plan->fingerprint(),$anchor['plan_sha256']))throw new RuntimeException('Current plan differs from its retained history.');
    }
    return ['head'=>$head,'count'=>$count,'bytes'=>$bytes,'items'=>$items,'page'=>$target+1,'pages'=>$lastPage+1,'expected'=>$head?->fingerprint()??project_planning_initial_token($job)];
}
