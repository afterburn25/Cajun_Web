<?php
/** Read-only projections of durable workspace facts; independent per-person read state. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/activity_schema.php';
function activity_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['activity_schema_version'])['value']??'')==='1';}
function activity_audit(array $actor,string $action,string $detail): void {query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor['type']==='admin'?$actor['id']:0,'activity.'.$action,$actor['type'].' '.$actor['id'].'; '.$detail,gmdate('Y-m-d H:i:s')]);}
require_once CWP_CODE_ROOT.'/database/migrations/activity_migrate.php';
function activity_actor(string $audience): array {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Leave support to view your own activity.');
    if($audience==='admin'){$u=admin_user();if(!$u||!staff_can('clients.manage',$u))throw new RuntimeException('Activity access unavailable.');$actor=['type'=>'admin','id'=>(int)$u['id'],'client_id'=>0,'perms'=>null];}
    elseif($audience==='client'){
        $u=studio_client();if(!$u)throw new RuntimeException('Sign in to view your activity.');
        $contact=studio_contact();
        $actor=$contact!==null?['type'=>'contact','id'=>(int)$contact['id'],'client_id'=>(int)$u['id'],'perms'=>contact_perms_decode($contact['permissions'])]:['type'=>'client','id'=>(int)$u['id'],'client_id'=>(int)$u['id'],'perms'=>null];
    }
    else throw new InvalidArgumentException('Invalid activity audience.');
    if(!studio_ready()||!activity_ready())throw new RuntimeException('Workspace activity has not been enabled.');
    return $actor;
}
/** Preference storage is additive; older installs get it by re-running workspace-activity setup. */
function activity_prefs_ready(): bool {
    static $ready=null;
    if($ready===null)$ready=activity_ready()&&(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'activity_prefs']);
    return $ready;
}
/** The update kinds an actor can actually receive under current permissions and enabled storage. */
function activity_kinds(array $actor): array {
    $member=$actor['type']!=='admin';
    $can=function(string $permission) use($actor,$member): bool {return !$member||$actor['perms']===null||in_array($permission,$actor['perms'],true);};
    $kinds=[];
    if($can('projects')){array_push($kinds,'message','file');if($member)$kinds[]='review';if(studio_milestones_ready())$kinds[]='milestone';}
    if(!$member)$kinds[]='decision';
    else{if($can('billing')){$kinds[]='invoice';if(ledger_ready())$kinds[]='payment';}if($can('downloads')&&delivery_ready())$kinds[]='release';}
    return $kinds;
}
function activity_disabled(array $actor): array {
    return activity_prefs_ready()?array_column(all('SELECT kind FROM '.table('activity_prefs').' WHERE actor_type=? AND actor_id=?',[$actor['type'],$actor['id']]),'kind'):[];
}
function activity_prefs_save(array $actor,array $hidden): void {
    if(!activity_prefs_ready())throw new RuntimeException('Notification preferences are not enabled on this installation.');
    $allowed=array_flip(activity_kinds($actor));$hidden=array_values(array_unique(array_filter($hidden,fn($k)=>is_string($k)&&isset($allowed[$k]))));
    staff_locked(fn()=>staff_transaction(function() use($actor,$hidden): void {
        query('DELETE FROM '.table('activity_prefs').' WHERE actor_type=? AND actor_id=?',[$actor['type'],$actor['id']]);
        foreach($hidden as $kind)query('INSERT INTO '.table('activity_prefs').' (actor_type,actor_id,kind) VALUES (?,?,?)',[$actor['type'],$actor['id'],$kind]);
        activity_audit($actor,'preferences',count($hidden).' hidden update types');
    }));
}
/** Internal SQL projection. Never contains message bodies, payment notes or draft deliveries. */
function activity_projection(array $actor): array {
    $parts=[];$args=[];$member=$actor['type']!=='admin';$contact=$actor['type']==='contact';
    $hidden=array_flip(activity_disabled($actor));
    $can=function(string $permission) use($actor,$member): bool {return !$member||$actor['perms']===null||in_array($permission,$actor['perms'],true);};
    $add=function(string $table,string $kind,string $time,string $title,string $where,string $target='s.id',string $keySource='s.id',string $author="''") use(&$parts,&$args,$member,$actor,$hidden): void {
        if(isset($hidden[$kind]))return;
        $parts[]="SELECT CAST(CONCAT('".$kind.":',".$keySource.") AS CHAR CHARACTER SET ascii) COLLATE ascii_bin AS event_key,'".$kind."' AS kind,CAST(s.id AS CHAR) AS source_id,".$target." AS target_id,j.id AS job_id,j.client_id,j.title AS project_title,".$title." AS item_title,".$time." AS occurred_at,".$author.' AS author_type FROM '.table($table).' s JOIN '.table('studio_jobs').' j ON j.id=s.job_id JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE c.active=1 AND ('.$where.')'.($member?' AND j.client_id=?':'');
        if($member)$args[]=$actor['client_id'];
    };
    $authors=$member?($contact?" IN ('admin','client')":"='admin'"):" IN ('client','contact')";
    if($can('projects')){
        $add('studio_messages','message','s.created_at',"''","s.author_type".$authors,'s.id','s.id','s.author_type');
        $add('studio_files','file','s.created_at','s.original_name',"s.author_type".$authors);
        if(studio_milestones_ready())$add('studio_milestones','milestone','s.updated_at',"CONCAT('Milestone: ',s.title,' · ',s.state)",'1=1','s.id',"CONCAT(s.id,':',SUBSTR(SHA1(CONCAT_WS('|',s.title,s.state,COALESCE(s.target_date,''),s.updated_at)),1,16))");
    }
    if($member){
        if($can('projects'))$add('studio_versions','review','s.created_at',"CONCAT(s.kind,' v',s.version_no,' · ',s.title)",'1=1');
        if($can('billing')){
            $add('studio_invoices','invoice','s.created_at',"CONCAT(s.reference,' · ',s.title)",'1=1');
            if(ledger_ready())$add('invoice_payments','payment','s.created_at',"CASE WHEN s.kind='reversal' THEN 'Recording corrected' ELSE 'Payment recorded' END","s.kind IN ('manual','reversal') AND s.client_id=j.client_id AND EXISTS (SELECT 1 FROM ".table('studio_invoices').' i WHERE i.id=s.invoice_id AND i.job_id=j.id)','s.invoice_id');
        }
        if($can('downloads')&&delivery_ready())$add('website_releases','release','s.published_at',"CONCAT(s.version,' · ',s.title)","s.status='published' AND s.published_at IS NOT NULL AND s.client_id=j.client_id");
    }else $add('studio_versions','decision','s.decided_at',"CONCAT(s.kind,' v',s.version_no,' · ',s.decision,' · ',s.title)","s.decision IN ('approved','changes') AND s.decided_at IS NOT NULL AND s.decided_by=j.client_id");
    if(!$parts)return ["SELECT '' AS event_key,'none' AS kind,'' AS source_id,0 AS target_id,0 AS job_id,0 AS client_id,'' AS project_title,'' AS item_title,'1970-01-01 00:00:00' AS occurred_at WHERE 1=0",[]];
    return [implode(' UNION ALL ',$parts),$args];
}
function activity_key(string $key): void {if(!preg_match('/^(?:(?:message|review|invoice|payment|decision):[1-9][0-9]{0,19}|milestone:[1-9][0-9]{0,19}(?::[a-f0-9]{16})?|(?:file|release):[a-f0-9]{32})$/D',$key))throw new InvalidArgumentException('Activity item unavailable.');}
function activity_source(string $audience,string $key): array {
    activity_key($key);[$sql,$args]=activity_projection(activity_actor($audience));
    $row=one('SELECT * FROM ('.$sql.') events WHERE event_key=?',[...$args,$key]);if(!$row)throw new InvalidArgumentException('Activity item unavailable.');return $row;
}
function activity_count(string $audience): int {
    $actor=activity_actor($audience);[$sql,$args]=activity_projection($actor);
    return count(all('SELECT e.event_key FROM ('.$sql.') e LEFT JOIN '.table('activity_reads').' r ON r.actor_type=? AND r.actor_id=? AND r.event_key=e.event_key WHERE r.id IS NULL LIMIT 100',[...$args,$actor['type'],$actor['id']]));
}
function activity_passive_count(string $audience): int {
    $last=array_intersect_key($_SESSION,array_flip(['last_activity','client_last']));
    try{return activity_count($audience);}
    finally{foreach(['last_activity','client_last'] as $key){if(array_key_exists($key,$last))$_SESSION[$key]=$last[$key];else unset($_SESSION[$key]);}}
}
function activity_feed(string $audience,bool $unread=false,string $before=''): array {
    $actor=activity_actor($audience);[$sql,$args]=activity_projection($actor);$where=$unread?'r.id IS NULL':'1=1';$params=[...$args,$actor['type'],$actor['id']];
    if($before!==''){$cursor=activity_source($audience,$before);$where.=' AND (e.occurred_at<? OR (e.occurred_at=? AND e.event_key<?))';array_push($params,$cursor['occurred_at'],$cursor['occurred_at'],$before);}
    $rows=all('SELECT e.*,r.read_at FROM ('.$sql.') e LEFT JOIN '.table('activity_reads').' r ON r.actor_type=? AND r.actor_id=? AND r.event_key=e.event_key WHERE '.$where.' ORDER BY e.occurred_at DESC,e.event_key DESC LIMIT 31',$params);
    $more=count($rows)>30;if($more)array_pop($rows);return ['rows'=>$rows,'next'=>$more?$rows[count($rows)-1]['event_key']:''];
}
/** Only the reviewed keys are changed. A new arrival cannot be silently marked read. */
function activity_mark(string $audience,array $keys,bool $read): void {
    if(!array_is_list($keys)||count($keys)<1||count($keys)>30||count(array_unique($keys,SORT_REGULAR))!==count($keys))throw new InvalidArgumentException('Choose between 1 and 30 distinct activity items.');
    foreach($keys as $key){if(!is_string($key))throw new InvalidArgumentException('Invalid activity item.');activity_key($key);}
    staff_locked(fn()=>staff_transaction(function() use($audience,$keys,$read): void {
        $actor=activity_actor($audience);[$sql,$args]=activity_projection($actor);$marks=implode(',',array_fill(0,count($keys),'?'));
        $rows=all('SELECT event_key FROM ('.$sql.') e WHERE event_key IN ('.$marks.')',[...$args,...$keys]);
        if(count($rows)!==count($keys))throw new RuntimeException('Activity changed or is unavailable. Refresh before trying again.');
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
    if($audience==='client'&&$kind==='release')return studio_url(['release'=>$id]);
    if($audience==='client'&&$kind==='payment')return studio_url(['invoice'=>$r['target_id'],'before_entry'=>(int)$id+1]).'#payment-'.$id;
    $anchor=$kind==='decision'?'review':$kind;$params=[$audience==='admin'?'id':'job'=>$r['job_id']];
    if(in_array($anchor,['message','review'],true))$params['focus_'.$anchor]=$id;
    return ($audience==='admin'?admin_url('studio-job',$params):studio_url($params)).'#'.$anchor.'-'.$id;
}
