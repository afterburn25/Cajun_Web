<?php
/** Read-only projections of durable workspace facts; independent per-person read state. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/activity_schema.php';
require_once CWP_CODE_ROOT.'/app/projects/progress_activity.php';
require_once CWP_CODE_ROOT.'/app/projects/planning_activity.php';
require_once CWP_CODE_ROOT.'/app/billing/design_payment_activity.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_activity.php';
require_once CWP_CODE_ROOT.'/app/activity/provider_sources.php';
function activity_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['activity_schema_version'])['value']??'')==='1';}
function activity_audit(array $actor,string $action,string $detail): void {query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor['type']==='admin'?$actor['id']:0,'activity.'.$action,$actor['type'].' '.$actor['id'].'; '.$detail,gmdate('Y-m-d H:i:s')]);}
require_once CWP_CODE_ROOT.'/database/migrations/activity_migrate.php';
function activity_actor(string $audience): array {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Leave support to view your own activity.');
    // Preserve contact provenance before studio_client() can normalize the session.
    $providerExcluded=array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION);
    if($audience==='admin'){$u=admin_user();if(!$u||!staff_can('clients.manage',$u))throw new RuntimeException('Activity access unavailable.');$actor=['type'=>'admin','id'=>(int)$u['id'],'client_id'=>0,'perms'=>null,'provider_excluded'=>$providerExcluded];}
    elseif($audience==='client'){
        $u=studio_client();if(!$u)throw new RuntimeException('Sign in to view your activity.');
        $contact=studio_contact();
        $actor=$contact!==null?['type'=>'contact','id'=>(int)$contact['id'],'client_id'=>(int)$u['id'],'perms'=>contact_perms_decode($contact['permissions']),'provider_excluded'=>true]:['type'=>'client','id'=>(int)$u['id'],'client_id'=>(int)$u['id'],'perms'=>null,'provider_excluded'=>$providerExcluded];
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
    $add=function(string $table,string $kind,string $time,string $title,string $where,string $target='s.id',string $keySource='s.id',string $author="''",string $sequence='0') use(&$parts,&$args,$member,$actor,$hidden): void {
        if(isset($hidden[$kind]))return;
        $parts[]="SELECT CAST(CONCAT('".$kind.":',".$keySource.") AS CHAR CHARACTER SET ascii) COLLATE ascii_bin AS event_key,'".$kind."' AS kind,CAST(s.id AS CHAR) AS source_id,".$target." AS target_id,j.id AS job_id,j.client_id,j.title AS project_title,".$title." AS item_title,".$time." AS occurred_at,".$author.' AS author_type,'.$sequence.' AS sort_sequence FROM '.table($table).' s JOIN '.table('studio_jobs').' j ON j.id=s.job_id JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE c.active=1 AND ('.$where.')'.($member?' AND j.client_id=?':'');
        if($member)$args[]=$actor['client_id'];
    };
    $authors=$member?($contact?" IN ('admin','client')":"='admin'"):" IN ('client','contact')";
    if($can('projects')){
        $add('studio_messages','message','s.created_at',"''","s.author_type".$authors,'s.id','s.id','s.author_type');
        $add('studio_files','file','s.created_at','s.original_name',"s.author_type".$authors);
        if(studio_milestones_ready())$add('studio_milestones','milestone','s.updated_at',"CONCAT('Milestone: ',s.title,' · ',s.state)",'1=1','s.id',"CONCAT(s.id,':',SUBSTR(SHA1(CONCAT_WS('|',s.title,s.state,COALESCE(s.target_date,''),s.updated_at)),1,16))");
        $add('studio_stage_events','stage','s.created_at',"CONCAT('Stage: ',s.from_stage,' → ',s.to_stage)",'1=1','s.id','s.id','s.actor_type');
    }
    if($member){
        if($can('projects'))$add('studio_versions','review','s.created_at',"CONCAT(s.kind,' v',s.version_no,' · ',s.title)",'1=1');
        if($can('billing')){
            $add('studio_invoices','invoice','s.created_at',"CONCAT(s.reference,' · ',s.title)",'1=1');
            if(ledger_ready())$add('invoice_payments','payment','s.created_at',"CASE WHEN s.kind='reversal' THEN 'Recording corrected' ELSE 'Payment recorded' END","s.kind IN ('manual','reversal') AND s.client_id=j.client_id AND EXISTS (SELECT 1 FROM ".table('studio_invoices').' i WHERE i.id=s.invoice_id AND i.job_id=j.id)','s.invoice_id');
        }
        if($can('downloads')&&delivery_ready())$add('website_releases','release','s.published_at',"CONCAT(s.version,' · ',s.title)","s.status='published' AND s.published_at IS NOT NULL AND s.client_id=j.client_id");
    }else $add('studio_versions','decision','s.decided_at',"CONCAT(s.kind,' v',s.version_no,' · ',s.decision,' · ',s.title)","s.decision IN ('approved','changes') AND s.decided_at IS NOT NULL AND s.decided_by=j.client_id");
    if($member){
        if($can('projects')){
            if(project_progress_ready())$add('studio_progress_events','progress','s.created_at',"'Project details updated'","s.kind='updated' AND s.client_id=j.client_id",'s.id','s.id',"''",'s.revision');
            if(project_planning_ready())$add('studio_plan_events','planning','s.created_at',"CASE WHEN s.kind='plan_started' THEN 'Project plan created' ELSE 'Project plan updated' END","s.kind IN ('plan_started','plan_updated') AND s.client_id=j.client_id",'s.id','s.id',"''",'s.revision');
            if(design_payment_activity_available($actor))$add('design_payment_events','financial','s.created_at',"CASE s.kind WHEN 'offered' THEN 'Financial agreement offered' WHEN 'withdrawn' THEN 'Financial agreement withdrawn' WHEN 'accepted' THEN 'Client accepted financial terms' ELSE 'Client declined financial terms' END","s.client_id=j.client_id AND s.kind IN ('offered','withdrawn')",'s.id','s.id',"''",'s.sequence');
        }
    }else{
        if(project_progress_ready())$add('studio_progress_events','progress','s.created_at',"'Project details updated'","s.kind='updated' AND s.client_id=j.client_id AND s.staff_id<>".(int)$actor['id'],'s.id','s.id',"''",'s.revision');
        if(project_planning_ready())$add('studio_plan_events','planning','s.created_at',"CASE WHEN s.kind='plan_started' THEN 'Project plan created' ELSE 'Project plan updated' END","s.kind IN ('plan_started','plan_updated') AND s.client_id=j.client_id AND s.staff_id<>".(int)$actor['id'],'s.id','s.id',"''",'s.revision');
        if(design_payment_activity_available($actor))$add('design_payment_events','financial','s.created_at',"CASE s.kind WHEN 'offered' THEN 'Financial agreement offered' WHEN 'withdrawn' THEN 'Financial agreement withdrawn' WHEN 'accepted' THEN 'Client accepted financial terms' ELSE 'Client declined financial terms' END","s.client_id=j.client_id AND (s.kind IN ('accepted','declined') OR (s.kind IN ('offered','withdrawn') AND s.staff_id<>".(int)$actor['id']."))",'s.id','s.id',"''",'s.sequence');
    }
    [$recurring,$recurringArgs]=recurring_activity_projection($actor);
    if(!$parts&&!$recurring)return ["SELECT '' AS event_key,'none' AS kind,'' AS source_id,0 AS target_id,0 AS job_id,0 AS client_id,'' AS project_title,'' AS item_title,'1970-01-01 00:00:00' AS occurred_at,'' AS author_type,0 AS sort_sequence WHERE 1=0",[]];
    return [implode(' UNION ALL ',[...$parts,...$recurring]),[...$args,...$recurringArgs]];
}
function activity_key(string $key): void {if(!activity_provider_key($key)&&!preg_match('/^(?:(?:message|review|invoice|payment|decision|stage):[1-9][0-9]{0,19}|(?:recurring-offer|recurring-event|file|release|progress|planning|financial):[a-f0-9]{32}|milestone:[1-9][0-9]{0,19}(?::[a-f0-9]{16})?)$/D',$key))throw new InvalidArgumentException('Activity item unavailable.');}
/** Keep projection, personal reads and current identity in the same shared transaction. */
function activity_read_locked(callable $work):mixed {
    static $depth=0;
    if($depth>0)return $work();
    return project_progress_locked(function()use($work,&$depth):mixed {
        $depth++;try{return $work();}finally{$depth--;}
    });
}
function activity_source(string $audience,string $key): array {
    activity_key($key);
    if(!activity_provider_key($key))return activity_read_locked(function()use($audience,$key):array {
    [$sql,$args]=activity_projection(activity_actor($audience));
    $row=one('SELECT * FROM ('.$sql.') events WHERE event_key=?',[...$args,$key]);if(!$row)throw new InvalidArgumentException('Activity item unavailable.');project_progress_activity_validate($audience,[$row]);project_planning_activity_validate($audience,[$row]);design_payment_activity_validate($audience,[$row]);recurring_activity_validate($audience,[$row]);return $row;
    });
    return activity_authenticated_snapshot($audience,function(array $actor)use($audience,$key):array{
        if($actor['type']!==$audience||$actor['provider_excluded'])throw new InvalidArgumentException('Activity item unavailable.');
        $rows=activity_provider_rows_locked($audience,$actor);
        if(!isset($rows[$key]))throw new InvalidArgumentException('Activity item unavailable.');return $rows[$key];
    });
}
function activity_count(string $audience): int {
    return activity_authenticated_snapshot($audience,function(array $actor)use($audience):int{
        $provider=$actor['type']===$audience&&!$actor['provider_excluded']?activity_provider_rows_locked($audience,$actor):[];
        [$sql,$args]=activity_projection($actor);
        $rows=all('SELECT e.* FROM ('.$sql.') e LEFT JOIN '.table('activity_reads').' r ON r.actor_type=? AND r.actor_id=? AND r.event_key=e.event_key WHERE r.id IS NULL LIMIT 100',[...$args,$actor['type'],$actor['id']]);project_progress_activity_validate($audience,$rows);project_planning_activity_validate($audience,$rows);design_payment_activity_validate($audience,$rows);recurring_activity_validate($audience,$rows);
        $count=count($rows);
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
        $visible=$actor['type']===$audience&&!$actor['provider_excluded'];
        if($visible)try{$provider=activity_provider_rows_locked($audience,$actor);}
        catch(ActivityProviderUnavailable){$providerUnavailable=true;$notices[]='Subdomain updates are temporarily unavailable. Only project updates are shown. Your read status is unchanged.';}
        $cursor=null;
        if($before!==''){
            if(activity_provider_key($before)){
                if(!$visible||$providerUnavailable)throw new ActivityProviderUnavailable('Subdomain updates are temporarily unavailable.');
                if(!isset($provider[$before]))throw new InvalidArgumentException('Activity item unavailable.');$cursor=$provider[$before];
            }else{$cursor=activity_project_source($actor,$before);project_progress_activity_validate($audience,[$cursor]);project_planning_activity_validate($audience,[$cursor]);design_payment_activity_validate($audience,[$cursor]);recurring_activity_validate($audience,[$cursor]);}
        }
        [$sql,$args]=activity_projection($actor);$where=$unread?'r.id IS NULL':'1=1';$params=[...$args,$actor['type'],$actor['id']];
        if($cursor!==null){$where.=' AND (e.occurred_at<? OR (e.occurred_at=? AND (e.sort_sequence<? OR (e.sort_sequence=? AND e.event_key<?))))';array_push($params,$cursor['occurred_at'],$cursor['occurred_at'],$cursor['sort_sequence']??0,$cursor['sort_sequence']??0,$before);}
        $rows=all('SELECT e.*,r.read_at FROM ('.$sql.') e LEFT JOIN '.table('activity_reads').' r ON r.actor_type=? AND r.actor_id=? AND r.event_key=e.event_key WHERE '.$where.' ORDER BY e.occurred_at DESC,e.sort_sequence DESC,e.event_key DESC LIMIT 31',$params);
        project_progress_activity_validate($audience,$rows);project_planning_activity_validate($audience,$rows);design_payment_activity_validate($audience,$rows);recurring_activity_validate($audience,$rows);
        $count=null;
        if(!$providerUnavailable){$unreadRows=all('SELECT e.* FROM ('.$sql.') e LEFT JOIN '.table('activity_reads').' r ON r.actor_type=? AND r.actor_id=? AND r.event_key=e.event_key WHERE r.id IS NULL LIMIT 100',[...$args,$actor['type'],$actor['id']]);project_progress_activity_validate($audience,$unreadRows);project_planning_activity_validate($audience,$unreadRows);design_payment_activity_validate($audience,$unreadRows);recurring_activity_validate($audience,$unreadRows);$count=count($unreadRows);foreach($provider as $row)if($row['read_at']===null&&++$count>=100)break;$count=min(100,$count);}
        return activity_merge_rows($rows,array_values($provider),$unread,$cursor)+['count'=>$count,'notices'=>$notices];
    });
}
/** Only the reviewed keys are changed. A new arrival cannot be silently marked read. */
function activity_mark(string $audience,array $keys,bool $read): void {
    if(!array_is_list($keys)||count($keys)<1||count($keys)>30||count(array_unique($keys,SORT_REGULAR))!==count($keys))throw new InvalidArgumentException('Choose between 1 and 30 distinct activity items.');
    foreach($keys as $key){if(!is_string($key))throw new InvalidArgumentException('Invalid activity item.');activity_key($key);}
    staff_locked(fn()=>staff_transaction(function() use($audience,$keys,$read): void {
        $actor=activity_actor($audience);$projectKeys=array_values(array_filter($keys,static fn($key)=>!activity_provider_key($key)));$providerKeys=array_values(array_filter($keys,'activity_provider_key'));
        if($projectKeys!==[]){[$sql,$args]=activity_projection($actor);$marks=implode(',',array_fill(0,count($projectKeys),'?'));$rows=all('SELECT * FROM ('.$sql.') e WHERE event_key IN ('.$marks.')',[...$args,...$projectKeys]);if(count($rows)!==count($projectKeys))throw new RuntimeException('Activity changed or is unavailable. Refresh before trying again.');project_progress_activity_validate($audience,$rows);project_planning_activity_validate($audience,$rows);design_payment_activity_validate($audience,$rows);recurring_activity_validate($audience,$rows);}
        if($providerKeys!==[]){if($actor['type']!==$audience||$actor['provider_excluded'])throw new RuntimeException('Activity changed or is unavailable. Refresh before trying again.');$provider=activity_provider_rows_locked($audience,$actor);foreach($providerKeys as $key)if(!isset($provider[$key]))throw new RuntimeException('Activity changed or is unavailable. Refresh before trying again.');}
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
