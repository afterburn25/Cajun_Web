<?php
/** Read-only projections of durable workspace facts; independent per-person read state. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/activity_schema.php';
require_once CWP_CODE_ROOT.'/app/projects/progress_activity.php';
require_once CWP_CODE_ROOT.'/app/projects/planning_activity.php';
require_once CWP_CODE_ROOT.'/app/billing/design_payment_activity.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_activity.php';
function activity_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['activity_schema_version'])['value']??'')==='1';}
function activity_audit(array $actor,string $action,string $detail): void {query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor['type']==='admin'?$actor['id']:0,'activity.'.$action,$actor['type'].' '.$actor['id'].'; '.$detail,gmdate('Y-m-d H:i:s')]);}
require_once CWP_CODE_ROOT.'/database/migrations/activity_migrate.php';
function activity_actor(string $audience): array {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Leave support to view your own activity.');
    if($audience==='admin'){$u=admin_user();if(!$u||!staff_can('clients.manage',$u))throw new RuntimeException('Activity access unavailable.');}
    elseif($audience==='client'){$u=studio_client();if(!$u)throw new RuntimeException('Sign in to view your activity.');}
    else throw new InvalidArgumentException('Invalid activity audience.');
    if(!studio_ready()||!activity_ready())throw new RuntimeException('Workspace activity has not been enabled.');
    return ['type'=>$audience,'id'=>(int)$u['id']];
}
/** Internal SQL projection. Never contains message bodies, payment notes or draft deliveries. */
function activity_projection(array $actor): array {
    $parts=[];$args=[];$client=$actor['type']==='client';
    $add=function(string $table,string $kind,string $time,string $title,string $where,string $target='s.id',string $sequence='0') use(&$parts,&$args,$actor,$client): void {
        $parts[]="SELECT CAST(CONCAT('".$kind.":',s.id) AS CHAR CHARACTER SET ascii) COLLATE ascii_bin AS event_key,'".$kind."' AS kind,CAST(s.id AS CHAR) AS source_id,".$target." AS target_id,j.id AS job_id,j.client_id,j.title AS project_title,".$title." AS item_title,".$time.' AS occurred_at,'.$sequence.' AS sort_sequence FROM '.table($table).' s JOIN '.table('studio_jobs').' j ON j.id=s.job_id JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE c.active=1 AND ('.$where.')'.($client?' AND j.client_id=?':'');
        if($client)$args[]=$actor['id'];
    };
    $add('studio_messages','message','s.created_at',"''","s.author_type='".($client?'admin':'client')."'");
    $add('studio_files','file','s.created_at','s.original_name',"s.author_type='".($client?'admin':'client')."'");
    if($client){
        $add('studio_versions','review','s.created_at',"CONCAT(s.kind,' v',s.version_no,' · ',s.title)",'1=1');
        $add('studio_invoices','invoice','s.created_at',"CONCAT(s.reference,' · ',s.title)",'1=1');
        if(ledger_ready())$add('invoice_payments','payment','s.created_at',"CASE WHEN s.kind='reversal' THEN 'Recording corrected' ELSE 'Payment recorded' END","s.kind IN ('manual','reversal') AND s.client_id=j.client_id AND EXISTS (SELECT 1 FROM ".table('studio_invoices').' i WHERE i.id=s.invoice_id AND i.job_id=j.id)','s.invoice_id');
        if(delivery_ready())$add('website_releases','release','s.published_at',"CONCAT(s.version,' · ',s.title)","s.status='published' AND s.published_at IS NOT NULL AND s.client_id=j.client_id");
    }else $add('studio_versions','decision','s.decided_at',"CONCAT(s.kind,' v',s.version_no,' · ',s.decision,' · ',s.title)","s.decision IN ('approved','changes') AND s.decided_at IS NOT NULL AND s.decided_by=j.client_id");
    if(project_progress_ready())$add('studio_progress_events','progress','s.created_at',"'Project details updated'","s.kind='updated' AND s.client_id=j.client_id".($client?'':' AND s.staff_id<>'.(int)$actor['id']),'s.id','s.revision');
    if(project_planning_ready())$add('studio_plan_events','planning','s.created_at',"CASE WHEN s.kind='plan_started' THEN 'Project plan created' ELSE 'Project plan updated' END","s.kind IN ('plan_started','plan_updated') AND s.client_id=j.client_id".($client?'':' AND s.staff_id<>'.(int)$actor['id']),'s.id','s.revision');
    if(design_payment_activity_available($actor))$add('design_payment_events','financial','s.created_at',"CASE s.kind WHEN 'offered' THEN 'Financial agreement offered' WHEN 'withdrawn' THEN 'Financial agreement withdrawn' WHEN 'accepted' THEN 'Client accepted financial terms' ELSE 'Client declined financial terms' END","s.client_id=j.client_id AND ".($client?"s.kind IN ('offered','withdrawn')":"(s.kind IN ('accepted','declined') OR (s.kind IN ('offered','withdrawn') AND s.staff_id<>".(int)$actor['id']."))"),'s.id','s.sequence');
    [$recurring,$recurringArgs]=recurring_activity_projection($actor);
    return [implode(' UNION ALL ',[...$parts,...$recurring]),[...$args,...$recurringArgs]];
}
function activity_key(string $key): void {if(!preg_match('/^(?:(?:message|review|invoice|payment|decision):[1-9][0-9]{0,19}|(?:file|release|progress|planning|financial|recurring-offer|recurring-event):[a-f0-9]{32})$/D',$key))throw new InvalidArgumentException('Activity item unavailable.');}
/** Keep projection, personal reads and current identity in the same shared transaction. */
function activity_read_locked(callable $work):mixed {
    static $depth=0;
    if($depth>0)return $work();
    return project_progress_locked(function()use($work,&$depth):mixed {
        $depth++;try{return $work();}finally{$depth--;}
    });
}
function activity_source(string $audience,string $key): array {
    return activity_read_locked(function()use($audience,$key):array {
    activity_key($key);[$sql,$args]=activity_projection(activity_actor($audience));
    $row=one('SELECT * FROM ('.$sql.') events WHERE event_key=?',[...$args,$key]);if(!$row)throw new InvalidArgumentException('Activity item unavailable.');project_progress_activity_validate($audience,[$row]);project_planning_activity_validate($audience,[$row]);design_payment_activity_validate($audience,[$row]);recurring_activity_validate($audience,[$row]);return $row;
    });
}
function activity_count(string $audience): int {
    return activity_read_locked(function()use($audience):int {
    $actor=activity_actor($audience);[$sql,$args]=activity_projection($actor);
    $rows=all('SELECT e.* FROM ('.$sql.') e LEFT JOIN '.table('activity_reads').' r ON r.actor_type=? AND r.actor_id=? AND r.event_key=e.event_key WHERE r.id IS NULL LIMIT 100',[...$args,$actor['type'],$actor['id']]);project_progress_activity_validate($audience,$rows);project_planning_activity_validate($audience,$rows);design_payment_activity_validate($audience,$rows);recurring_activity_validate($audience,$rows);return count($rows);
    });
}
function activity_passive_count(string $audience): int {
    $last=array_intersect_key($_SESSION,array_flip(['last_activity','client_last']));
    try{return activity_count($audience);}
    finally{foreach(['last_activity','client_last'] as $key){if(array_key_exists($key,$last))$_SESSION[$key]=$last[$key];else unset($_SESSION[$key]);}}
}
function activity_feed(string $audience,bool $unread=false,string $before=''): array {
    return activity_read_locked(function()use($audience,$unread,$before):array {
    $actor=activity_actor($audience);[$sql,$args]=activity_projection($actor);$where=$unread?'r.id IS NULL':'1=1';$params=[...$args,$actor['type'],$actor['id']];
    if($before!==''){$cursor=activity_source($audience,$before);$where.=' AND (e.occurred_at<? OR (e.occurred_at=? AND (e.sort_sequence<? OR (e.sort_sequence=? AND e.event_key<?))))';array_push($params,$cursor['occurred_at'],$cursor['occurred_at'],$cursor['sort_sequence'],$cursor['sort_sequence'],$before);}
    $rows=all('SELECT e.*,r.read_at FROM ('.$sql.') e LEFT JOIN '.table('activity_reads').' r ON r.actor_type=? AND r.actor_id=? AND r.event_key=e.event_key WHERE '.$where.' ORDER BY e.occurred_at DESC,e.sort_sequence DESC,e.event_key DESC LIMIT 31',$params);
    project_progress_activity_validate($audience,$rows);project_planning_activity_validate($audience,$rows);design_payment_activity_validate($audience,$rows);recurring_activity_validate($audience,$rows);$more=count($rows)>30;if($more)array_pop($rows);return ['rows'=>$rows,'next'=>$more?$rows[count($rows)-1]['event_key']:''];
    });
}
/** Only the reviewed keys are changed. A new arrival cannot be silently marked read. */
function activity_mark(string $audience,array $keys,bool $read): void {
    if(!array_is_list($keys)||count($keys)<1||count($keys)>30||count(array_unique($keys,SORT_REGULAR))!==count($keys))throw new InvalidArgumentException('Choose between 1 and 30 distinct activity items.');
    foreach($keys as $key){if(!is_string($key))throw new InvalidArgumentException('Invalid activity item.');activity_key($key);}
    staff_locked(fn()=>staff_transaction(function() use($audience,$keys,$read): void {
        $actor=activity_actor($audience);[$sql,$args]=activity_projection($actor);$marks=implode(',',array_fill(0,count($keys),'?'));
        $rows=all('SELECT * FROM ('.$sql.') e WHERE event_key IN ('.$marks.')',[...$args,...$keys]);
        if(count($rows)!==count($keys))throw new RuntimeException('Activity changed or is unavailable. Refresh before trying again.');
        project_progress_activity_validate($audience,$rows);project_planning_activity_validate($audience,$rows);design_payment_activity_validate($audience,$rows);recurring_activity_validate($audience,$rows);foreach($keys as $key){
            if($read)query('INSERT INTO '.table('activity_reads').' (actor_type,actor_id,event_key,read_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE read_at=read_at',[$actor['type'],$actor['id'],$key,gmdate('Y-m-d H:i:s')]);
            else query('DELETE FROM '.table('activity_reads').' WHERE actor_type=? AND actor_id=? AND event_key=?',[$actor['type'],$actor['id'],$key]);
        }
        activity_audit($actor,$read?'read':'unread',count($keys).' reviewed items');
    }));
}
function activity_url(string $audience,array $params=[]): string {return $audience==='admin'?admin_url('activity',$params):site_url('portal/index.php').'?'.http_build_query(['activity'=>1]+$params);}
function activity_destination(string $audience,string $key): string {
    $r=activity_source($audience,$key);$kind=$r['kind'];$id=$r['source_id'];
    if($kind==='recurring'){
        $offer=str_starts_with($key,'recurring-offer:');$params=['job'=>$r['job_id'],'schedule'=>$r['target_id']];if(!$offer)$params['focus_event']=$id;
        return ($audience==='admin'?admin_url('recurring-billing',$params):studio_url(['recurring'=>1]+$params)).'#recurring-'.($offer?'offer':'event').'-'.$id;
    }
    if($audience==='client'&&$kind==='release')return studio_url(['release'=>$id]);
    if($audience==='client'&&$kind==='payment')return studio_url(['invoice'=>$r['target_id'],'before_entry'=>(int)$id+1]).'#payment-'.$id;
    $anchor=$kind==='decision'?'review':$kind;$params=[$audience==='admin'?'id':'job'=>$r['job_id']];
    if($anchor==='financial'){$params['financial_page']=intdiv((int)$r['sort_sequence'],10)+1;$anchor='financial-event';}
    if($anchor==='planning')$params['focus_plan']=$id;
    if(in_array($anchor,['message','review','progress'],true))$params['focus_'.$anchor]=$id;
    return ($audience==='admin'?admin_url('studio-job',$params):studio_url($params)).'#'.$anchor.'-'.$id;
}
