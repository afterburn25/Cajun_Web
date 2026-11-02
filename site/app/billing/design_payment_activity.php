<?php
/** Financial notices project retained facts; personal reads never change financial terms. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/design_payments.php';
function design_payment_activity_available(array $actor):bool {
    if($actor['type']==='admin'&&!staff_can('payments.manage'))return false;
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['design_payments_schema_version'])['value']??'';
    if(!in_array($version,['','1'],true))throw new RuntimeException('Financial activity storage needs owner review.');
    if($version===''){
        if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'design_payment_events'])&&(int)query('SELECT COUNT(*) FROM '.table('design_payment_events'))->fetchColumn())throw new RuntimeException('Retained financial activity needs owner review.');
        return false;
    }
    design_payments_require_ready();return true;
}
function design_payment_activity_title(string $kind):string {
    return match($kind){'offered'=>'Financial agreement offered','withdrawn'=>'Financial agreement withdrawn','accepted'=>'Client accepted financial terms','declined'=>'Client declined financial terms',default=>throw new RuntimeException('Financial activity unavailable.')};
}
function design_payment_activity_validate(string $audience,array $rows):void {
    $financial=array_filter($rows,fn($r)=>($r['kind']??'')==='financial');if(!$financial)return;
    if(count($financial)>101)throw new LogicException('Financial activity page exceeds its bound.');
    $work=function()use($audience,$financial):void {
        $actor=activity_actor($audience);
        if($audience==='admin')design_payments_staff();else design_payments_client($actor['id'],false);
        $jobs=[];
        foreach($financial as $row){
            $id=(int)$row['job_id'];
            if(!isset($jobs[$id])){
                $job=project_progress_job($id,$audience==='client'?$actor['id']:null);project_progress_client_row((int)$job['client_id']);
                try{design_payments_validate($job);}catch(JsonException $e){throw new RuntimeException('Financial history is unavailable.',0,$e);}
                $jobs[$id]=$job;
            }
            $job=$jobs[$id];$event=one('SELECT id,job_id,client_id,staff_id,client_actor_id,sequence,kind,created_at FROM '.table('design_payment_events').' WHERE id=? AND job_id=?',[$row['source_id'],$id]);
            if(!$event)throw new RuntimeException('Financial activity unavailable.');
            $staff=in_array($event['kind'],['offered','withdrawn'],true);$decision=in_array($event['kind'],['accepted','declined'],true);
            $allowed=$audience==='client'?$staff:($decision||$staff&&(int)$event['staff_id']!==$actor['id']);
            if(!$allowed||(int)$event['client_id']!==(int)$job['client_id']||(int)$row['client_id']!==(int)$job['client_id']||$row['project_title']!==$job['title']||$row['event_key']!=='financial:'.$event['id']||$row['target_id']!==$event['id']||$row['occurred_at']!==$event['created_at']||(int)$row['sort_sequence']!==(int)$event['sequence']||$row['item_title']!==design_payment_activity_title($event['kind']))throw new RuntimeException('Financial activity changed or is inconsistent.');
        }
    };
    if(db()->inTransaction())$work();else project_progress_locked($work);
}
