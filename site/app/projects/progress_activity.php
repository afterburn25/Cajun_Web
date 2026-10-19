<?php
/** Validate bounded activity metadata against authorized, complete native history. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/projects/progress.php';
function project_progress_activity_validate(string $audience,array $rows):void {
    $progress=array_filter($rows,fn($row)=>($row['kind']??'')==='progress');if(!$progress)return;
    if(count($progress)>101)throw new LogicException('Project activity page exceeds its bound.');
    $work=function()use($audience,$progress):void {
        $actor=activity_actor($audience);project_progress_require_ready();$validated=[];
        foreach($progress as $row){
            $id=(int)$row['job_id'];
            if(!isset($validated[$id])){
                $job=project_progress_job($id,$actor['type']!=='admin'?$actor['client_id']:null);project_progress_client_row((int)$job['client_id']);
                project_progress_validate($job);$validated[$id]=$job;
            }
            $job=$validated[$id];$saved=one('SELECT event_json FROM '.table('studio_progress_events').' WHERE id=? AND job_id=?',[$row['source_id'],$id]);
            if(!$saved)throw new RuntimeException('Project update unavailable.');$event=\CajunWebPro\Foundation\Projects\ProjectProgressEvent::fromJson($saved['event_json'])->data();
            if($event['kind']!=='updated'||$event['client_id']!==(int)$job['client_id']||$row['event_key']!=='progress:'.$event['id']||$row['occurred_at']!==gmdate('Y-m-d H:i:s',$event['occurred_at'])||(int)$row['sort_sequence']!==$event['revision']||(int)$row['client_id']!==$event['client_id']||($actor['type']==='admin'&&$event['staff_id']===$actor['id']))throw new RuntimeException('Project activity is inconsistent.');
        }
    };
    // activity_mark already owns the same account lock and transaction.
    if(db()->inTransaction())$work();else project_progress_locked($work);
}
