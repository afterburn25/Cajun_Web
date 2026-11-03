<?php
/** Read-only projections of durable workspace facts; independent per-person read state. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/activity_schema.php';
function activity_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['activity_schema_version'])['value']??'')==='1';}
function activity_audit(array $actor,string $action,string $detail): void {query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor['type']==='admin'?$actor['id']:0,'activity.'.$action,$actor['type'].' '.$actor['id'].'; '.$detail,gmdate('Y-m-d H:i:s')]);}
require_once CWP_CODE_ROOT.'/database/migrations/activity_migrate.php';
require_once CWP_CODE_ROOT.'/app/activity/provider_sources.php';
function activity_actor(string $audience): array {
    // Preserve contact provenance before studio_client() can normalize the session.
    $providerExcluded=array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION);
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Leave support to view your own activity.');
    if($audience==='admin'){$u=admin_user();if(!$u||!staff_can('clients.manage',$u))throw new RuntimeException('Activity access unavailable.');}
    elseif($audience==='client'){$u=studio_client();if(!$u)throw new RuntimeException('Sign in to view your activity.');}
    else throw new InvalidArgumentException('Invalid activity audience.');
    if(!studio_ready()||!activity_ready())throw new RuntimeException('Workspace activity has not been enabled.');
    return ['type'=>$audience,'id'=>(int)$u['id'],'provider_excluded'=>$providerExcluded];
}
/** Internal SQL projection. Never contains message bodies, payment notes or draft deliveries. */
function activity_projection(array $actor): array {
    $parts=[];$args=[];$client=$actor['type']==='client';
    $add=function(string $table,string $kind,string $time,string $title,string $where,string $target='s.id') use(&$parts,&$args,$actor,$client): void {
        $parts[]="SELECT CAST(CONCAT('".$kind.":',s.id) AS CHAR CHARACTER SET ascii) COLLATE ascii_bin AS event_key,'".$kind."' AS kind,CAST(s.id AS CHAR) AS source_id,".$target." AS target_id,j.id AS job_id,j.client_id,j.title AS project_title,".$title." AS item_title,".$time.' AS occurred_at FROM '.table($table).' s JOIN '.table('studio_jobs').' j ON j.id=s.job_id JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE c.active=1 AND ('.$where.')'.($client?' AND j.client_id=?':'');
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
    return [implode(' UNION ALL ',$parts),$args];
}
function activity_key(string $key): void {if(!activity_provider_key($key)&&!preg_match('/^(?:(?:message|review|invoice|payment|decision):[1-9][0-9]{0,19}|(?:file|release):[a-f0-9]{32})$/D',$key))throw new InvalidArgumentException('Activity item unavailable.');}
function activity_source(string $audience,string $key): array {
    activity_key($key);
    if(!activity_provider_key($key))return activity_project_source(activity_actor($audience),$key);
    return activity_authenticated_snapshot($audience,function(array $actor)use($audience,$key):array{
        $rows=activity_provider_rows_locked($audience,$actor);
        if(!isset($rows[$key]))throw new InvalidArgumentException('Activity item unavailable.');return $rows[$key];
    });
}
function activity_count(string $audience): int {
    return activity_authenticated_snapshot($audience,function(array $actor)use($audience):int{
        $provider=activity_provider_rows_locked($audience,$actor);$count=activity_project_count($actor);
        foreach($provider as $row)if($row['read_at']===null&&++$count>=100)return 100;
        return min(100,$count);
    });
}
function activity_passive_count(string $audience): int {
    $last=array_intersect_key($_SESSION,array_flip(['last_activity','client_last']));
    try{return activity_count($audience);}
    finally{foreach(['last_activity','client_last'] as $key){if(array_key_exists($key,$last))$_SESSION[$key]=$last[$key];else unset($_SESSION[$key]);}}
}
function activity_feed(string $audience,bool $unread=false,string $before=''): array {
    if($before!=='')activity_key($before);
    return activity_authenticated_snapshot($audience,function(array $actor)use($audience,$unread,$before):array{
        $notices=[];$provider=[];$providerUnavailable=false;
        try{$provider=activity_provider_rows_locked($audience,$actor);}
        catch(ActivityProviderUnavailable){$providerUnavailable=true;$notices[]='Subdomain updates are temporarily unavailable. Only project updates are shown. Your read status is unchanged.';}
        $cursor=null;
        if($before!==''){
            if(activity_provider_key($before)){
                if($providerUnavailable)throw new ActivityProviderUnavailable('Subdomain updates are temporarily unavailable.');
                if(!isset($provider[$before]))throw new InvalidArgumentException('Activity item unavailable.');$cursor=$provider[$before];
            }else $cursor=activity_project_source($actor,$before);
        }
        [$sql,$args]=activity_projection($actor);$where=$unread?'r.id IS NULL':'1=1';$params=[...$args,$actor['type'],$actor['id']];
        if($cursor!==null){$where.=' AND (e.occurred_at<? OR (e.occurred_at=? AND e.event_key<?))';array_push($params,$cursor['occurred_at'],$cursor['occurred_at'],$before);}
        $rows=all('SELECT e.*,r.read_at FROM ('.$sql.') e LEFT JOIN '.table('activity_reads').' r ON r.actor_type=? AND r.actor_id=? AND r.event_key=e.event_key WHERE '.$where.' ORDER BY e.occurred_at DESC,e.event_key DESC LIMIT 31',$params);
        $count=$providerUnavailable?null:activity_project_count($actor);
        if($count!==null){foreach($provider as $row)if($row['read_at']===null&&++$count>=100)break;$count=min(100,$count);}
        return activity_merge_rows($rows,array_values($provider),$unread,$cursor)+['count'=>$count,'notices'=>$notices];
    });
}
/** Only the reviewed keys are changed. A new arrival cannot be silently marked read. */
function activity_mark(string $audience,array $keys,bool $read): void {
    if(!array_is_list($keys)||count($keys)<1||count($keys)>30||count(array_unique($keys,SORT_REGULAR))!==count($keys))throw new InvalidArgumentException('Choose between 1 and 30 distinct activity items.');
    foreach($keys as $key){if(!is_string($key))throw new InvalidArgumentException('Invalid activity item.');activity_key($key);}
    staff_locked(fn()=>staff_transaction(function() use($audience,$keys,$read): void {
        $actor=activity_actor($audience);$projectKeys=array_values(array_filter($keys,static fn($key)=>!activity_provider_key($key)));$providerKeys=array_values(array_filter($keys,'activity_provider_key'));
        if($projectKeys!==[]){[$sql,$args]=activity_projection($actor);$marks=implode(',',array_fill(0,count($projectKeys),'?'));$rows=all('SELECT event_key FROM ('.$sql.') e WHERE event_key IN ('.$marks.')',[...$args,...$projectKeys]);if(count($rows)!==count($projectKeys))throw new RuntimeException('Activity changed or is unavailable. Refresh before trying again.');}
        if($providerKeys!==[]){$provider=activity_provider_rows_locked($audience,$actor);foreach($providerKeys as $key)if(!isset($provider[$key]))throw new RuntimeException('Activity changed or is unavailable. Refresh before trying again.');}
        foreach($keys as $key){
            if($read)query('INSERT INTO '.table('activity_reads').' (actor_type,actor_id,event_key,read_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE read_at=read_at',[$actor['type'],$actor['id'],$key,gmdate('Y-m-d H:i:s')]);
            else query('DELETE FROM '.table('activity_reads').' WHERE actor_type=? AND actor_id=? AND event_key=?',[$actor['type'],$actor['id'],$key]);
        }
        activity_audit($actor,$read?'read':'unread',count($keys).' reviewed items');
    }));
}
function activity_url(string $audience,array $params=[]): string {return $audience==='admin'?admin_url('activity',$params):site_url('portal/index.php').'?'.http_build_query(['activity'=>1]+$params);}
function activity_destination(string $audience,string $key): string {
    $r=activity_source($audience,$key);$kind=$r['kind'];$id=$r['source_id'];
    if(activity_provider_key($key))return $audience==='admin'?admin_url('subdomain-requests',['request_id'=>$r['target_id']]):studio_url(['subdomain_requests'=>1,'request_id'=>$r['target_id']]);
    if($audience==='client'&&$kind==='release')return studio_url(['release'=>$id]);
    if($audience==='client'&&$kind==='payment')return studio_url(['invoice'=>$r['target_id'],'before_entry'=>(int)$id+1]).'#payment-'.$id;
    $anchor=$kind==='decision'?'review':$kind;$params=[$audience==='admin'?'id':'job'=>$r['job_id']];
    if(in_array($anchor,['message','review'],true))$params['focus_'.$anchor]=$id;
    return ($audience==='admin'?admin_url('studio-job',$params):studio_url($params)).'#'.$anchor.'-'.$id;
}
