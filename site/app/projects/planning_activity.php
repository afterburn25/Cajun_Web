<?php
/** Personal planning notices are projections, never a second business write. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/projects/planning.php';
function project_planning_activity_validate(string $audience,array $rows):void {
    $planning=array_filter($rows,fn($row)=>($row['kind']??'')==='planning');if(!$planning)return;
    if(count($planning)>101)throw new LogicException('Planning activity page exceeds its bound.');
    $work=function()use($audience,$planning):void {
        $actor=activity_actor($audience);project_planning_require_ready();$validated=[];
        foreach($planning as $row){$id=(int)$row['job_id'];
            if(!isset($validated[$id])){$job=project_progress_job($id,$actor['type']==='client'?$actor['id']:null);project_progress_client_row((int)$job['client_id']);project_planning_validate($job);$validated[$id]=$job;}
            $job=$validated[$id];$saved=one('SELECT event_json FROM '.table('studio_plan_events').' WHERE id=? AND job_id=?',[$row['source_id'],$id]);
            if(!$saved)throw new RuntimeException('Planning update unavailable.');$event=\CajunWebPro\Foundation\ProjectPlanning\ProjectPlanEvent::fromJson($saved['event_json'])->data();
            if(!in_array($event['kind'],['plan_started','plan_updated'],true)||$event['client_id']!==(int)$job['client_id']||$row['event_key']!=='planning:'.$event['id']||$row['target_id']!==$event['id']||$row['occurred_at']!==gmdate('Y-m-d H:i:s',$event['occurred_at'])||(int)$row['sort_sequence']!==$event['revision']||(int)$row['client_id']!==$event['client_id']||($actor['type']==='admin'&&$event['staff_id']===$actor['id']))throw new RuntimeException('Planning activity is inconsistent.');
        }
    };
    if(db()->inTransaction())$work();else project_progress_locked($work);
}
