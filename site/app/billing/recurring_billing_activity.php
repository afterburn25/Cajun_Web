<?php
/** Read-only notices from retained agreements; no notification journal or business writes. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing.php';

function recurring_activity_available(array $actor):bool {
    if($actor['type']==='admin'&&!staff_can('payments.manage'))return false;
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['recurring_billing_schema_version'])['value']??'';
    if(!in_array($version,['','1'],true))throw new RuntimeException('Recurring activity storage needs owner review.');
    if($version===''){
        foreach(array_keys(recurring_billing_schema(config()['db']['prefix'])) as $name){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Retained recurring activity needs owner review.');
        }
        return false;
    }
    recurring_billing_require();return true;
}
/** Identifiers have separate namespaces; epochs convert to UTC without session-timezone functions. */
function recurring_activity_projection(array $actor):array {
    if(!recurring_activity_available($actor))return [[],[]];
    $client=$actor['type']==='client';$args=[];$parts=[];
    $joins=' JOIN '.table('studio_jobs').' j ON j.id=s.job_id JOIN '.table('studio_clients').' c ON c.id=j.client_id';
    $where=' WHERE c.active=1 AND s.client_id=j.client_id';
    $columns='s.id AS target_id,j.id AS job_id,j.client_id,j.title AS project_title,';
    $parts[]="SELECT CAST(CONCAT('recurring-offer:',s.id) AS CHAR CHARACTER SET ascii) COLLATE ascii_bin AS event_key,'recurring' AS kind,s.id AS source_id,".$columns."'Recurring billing offered' AS item_title,DATE_ADD('1970-01-01 00:00:00',INTERVAL s.created_epoch SECOND) AS occurred_at,0 AS sort_sequence FROM ".table('recurring_billing_schedules').' s'.$joins.$where.($client?' AND j.client_id=?':' AND s.created_by<>?');
    $args[]=$actor['id'];
    $parts[]="SELECT CAST(CONCAT('recurring-event:',e.id) AS CHAR CHARACTER SET ascii) COLLATE ascii_bin AS event_key,'recurring' AS kind,e.id AS source_id,".$columns."'Recurring billing updated' AS item_title,DATE_ADD('1970-01-01 00:00:00',INTERVAL e.recorded_epoch SECOND) AS occurred_at,e.revision AS sort_sequence FROM ".table('recurring_billing_events').' e JOIN '.table('recurring_billing_schedules').' s ON s.id=e.schedule_id'.$joins.$where.($client?' AND e.actor_staff_id IS NOT NULL AND j.client_id=?':' AND (e.actor_client_id IS NOT NULL OR e.actor_staff_id<>?)');
    $args[]=$actor['id'];return [$parts,$args];
}
function recurring_activity_validate(string $audience,array $rows):void {
    $items=array_filter($rows,fn($row)=>($row['kind']??'')==='recurring');if(!$items)return;
    if(count($items)>101)throw new LogicException('Recurring activity page exceeds its bound.');
    $work=function()use($audience,$items):void {
        $actor=activity_actor($audience);
        if(!recurring_activity_available($actor))throw new RuntimeException('Recurring activity unavailable.');
        if($audience==='admin')recurring_billing_staff();else recurring_billing_client($actor['id']);
        $families=[];$schedules=[];
        foreach($items as $row){
            $id=$row['target_id'];service_plans_id($id);
            if(!isset($schedules[$id])){
                $saved=recurring_billing_row($id);$job=project_progress_job((int)$saved['job_id'],$audience==='client'?$actor['id']:null);
                project_progress_client_row((int)$job['client_id']);
                if((int)$saved['client_id']!==(int)$job['client_id'])throw new RuntimeException('Recurring activity ownership changed.');
                try{
                    $families[$saved['subscription_id']]??=recurring_billing_family($saved['subscription_id'])['source'];
                    $doc=recurring_billing_validate($saved,$families[$saved['subscription_id']]);
                }catch(JsonException $e){throw new RuntimeException('Recurring history is unavailable.',0,$e);}
                $data=$doc->data();$events=[];
                foreach($data['events'] as $i=>$event)$events[$event['id']]=['actor'=>$event['actor'],'at'=>$event['at'],'sequence'=>$i+1];
                $schedules[$id]=['job'=>$job,'author'=>$data['created_by'],'at'=>$data['created_at'],'events'=>$events];
            }
            $schedule=$schedules[$id];$job=$schedule['job'];$offer=$row['event_key']==='recurring-offer:'.$id;
            if($offer){$author='staff:'.$schedule['author'];$at=$schedule['at'];$sequence=0;$source=$id;$title='Recurring billing offered';}
            else{
                $source=$row['source_id'];$event=$schedule['events'][$source]??null;
                if(!$event||$row['event_key']!=='recurring-event:'.$source)throw new RuntimeException('Recurring activity unavailable.');
                $author=$event['actor'];$at=$event['at'];$sequence=$event['sequence'];$title='Recurring billing updated';
            }
            $staff=str_starts_with($author,'staff:');$allowed=$audience==='client'?$staff:(!$staff||$author!=='staff:'.$actor['id']);
            if(!$allowed||$row['source_id']!==$source||(int)$row['job_id']!==(int)$job['id']||(int)$row['client_id']!==(int)$job['client_id']||$row['project_title']!==$job['title']||$row['item_title']!==$title||$row['occurred_at']!==gmdate('Y-m-d H:i:s',$at)||(int)$row['sort_sequence']!==$sequence)throw new RuntimeException('Recurring activity changed or is inconsistent.');
        }
    };
    if(db()->inTransaction())$work();else project_progress_locked($work);
}
