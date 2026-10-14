<?php
/** Native immutable offers, explicit financial consent and retained issuance; no gateway dispatch. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/payments.php';
require_once CWP_CODE_ROOT.'/app/projects/planning.php';
require_once CWP_CODE_ROOT.'/includes/foundation/design-payments/DesignPaymentSchedule.php';
require_once CWP_CODE_ROOT.'/database/schema/design_payments_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/design_payments_migrate.php';
use CajunWebPro\Foundation\DesignPayments\DesignPaymentSchedule;
const DESIGN_PAYMENTS_MAX_EVENTS=4096;
const DESIGN_PAYMENTS_MAX_BYTES=33554432;
const DESIGN_PAYMENTS_EVENT_BYTES=133120;
function design_payments_json(array $value):string{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
function design_payments_ready():bool{return (one('SELECT value FROM '.table('settings').' WHERE name=?',['design_payments_schema_version'])['value']??'')==='1';}
function design_payments_require_ready():void{if(!payments_ready()||!design_payments_ready())throw new RuntimeException('An owner must prepare financial schedules first.');}
function design_payments_staff(bool $ready=true):array{
    if(array_key_exists('support',$_GET))throw new RuntimeException('Financial changes are unavailable during support.');
    $user=studio_client_manager();
    if(!staff_ready()||!payments_ready()||!staff_can('payments.manage',$user))throw new RuntimeException('Current financial and client-management access is required.');
    if($ready)design_payments_require_ready();return ['type'=>'staff','id'=>(int)$user['id']];
}
function design_payments_client(int $id,bool $touch=true):array{
    design_payments_require_ready();project_progress_number($id);
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Financial decisions require the actual client session.');
    $row=project_progress_client_row($id);$now=time();
    if((int)($_SESSION['client_id']??0)!==$id||$now-(int)($_SESSION['client_last']??0)>1800||$now-(int)($_SESSION['client_login']??0)>28800||!hash_equals($row['auth_version'],(string)($_SESSION['client_auth']??'')))throw new RuntimeException('Sign in to review your current financial offer.');
    if($touch)$_SESSION['client_last']=$now;return ['type'=>'client','id'=>$id];
}
function design_payments_audit(array $actor,string $action,string $detail):void{
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor['type']==='staff'?$actor['id']:0,'design_payments.'.$action,$actor['type'].' '.$actor['id'].'; '.$detail,gmdate('Y-m-d H:i:s')]);
}
function design_payments_initial(array $job):string{return hash('sha256',design_payments_json(['format'=>1,'project_id'=>(int)$job['id'],'client_id'=>(int)$job['client_id'],'head'=>null]));}
function design_payments_command(int $job,int $client,array $actor,string $kind,string $expected,array $payload):string{
    return hash('sha256',design_payments_json(['format'=>1,'project_id'=>$job,'client_id'=>$client,'actor'=>$actor,'kind'=>$kind,'expected'=>$expected,'payload'=>$payload]));
}
function design_payments_comment(mixed $comment,bool $required):void{
    if(!is_string($comment)||strlen($comment)>5000||preg_match('//u',$comment)!==1||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$comment)||($required&&(strlen(trim($comment))<5||preg_match('/\S/u',$comment)!==1)))throw new InvalidArgumentException('Add bounded, meaningful financial feedback.');
}
function design_payments_proposal(array $proposal):string{
    return hash('sha256',design_payments_json(['id'=>(int)$proposal['id'],'project_id'=>(int)$proposal['job_id'],'kind'=>$proposal['kind'],'version'=>(int)$proposal['version_no'],'title'=>$proposal['title'],'body'=>$proposal['body'],'preview_url'=>$proposal['preview_url'],'created_at'=>$proposal['created_at']]));
}
function design_payments_source(array $job,DesignPaymentSchedule $schedule):void{
    $data=$schedule->data();
    if($data['project_id']!==(int)$job['id']||$data['client_id']!==(int)$job['client_id'])throw new RuntimeException('The financial offer belongs to another project or client.');
    $proposal=one('SELECT * FROM '.table('studio_versions')." WHERE job_id=? AND kind='proposal' ORDER BY version_no DESC LIMIT 1 FOR UPDATE",[$job['id']]);
    if(!$proposal||(int)$proposal['id']!==$data['proposal']['id']||(int)$proposal['version_no']!==$data['proposal']['version']||$proposal['decision']!=='approved'||(int)$proposal['decided_by']!==(int)$job['client_id']||!hash_equals($data['proposal']['content_sha256'],design_payments_proposal($proposal)))throw new RuntimeException('Review the latest approved proposal before publishing or deciding financial terms.');
    $milestones=[];foreach($schedule->stages() as $stage)if($stage['kind']==='milestone')$milestones[]=$stage['milestone_id'];
    if($milestones){
        project_planning_require_ready();$plan=project_planning_validate($job)['head']?->plan()->data();$available=[];
        foreach($plan['milestones']??[] as $milestone)if($milestone['state']!=='cancelled')$available[$milestone['id']]=true;
        foreach($milestones as $id)if(!isset($available[$id]))throw new RuntimeException('Choose current milestones from this project before approving its financial schedule.');
    }
}
/** Validate a transition without looking up today's proposal or permissions for historical events. */
function design_payments_transition(array $state,array $event):array{
    $payload=$event['payload'];$kind=$event['kind'];
    if($kind==='issued')return design_payment_invoice_transition($state,$event);
    if(($state['issued']??[])&&in_array($kind,['offered','withdrawn'],true))throw new RuntimeException('Issued financial terms are frozen. A reviewed amendment policy is required.');
    if($kind==='offered'){
        if($event['actor_type']!=='staff')throw new RuntimeException('Invalid financial publisher.');
        $schedule=DesignPaymentSchedule::fromArray($payload);$data=$schedule->data();
        if($data['id']!==$event['id']||$data['project_id']!==$event['project_id']||$data['client_id']!==$event['client_id'])throw new RuntimeException('Financial snapshot binding mismatch.');
        return ['schedule'=>$schedule,'status'=>'offered','consent_id'=>null,'issued'=>[]];
    }
    if(!in_array($kind,['accepted','declined','withdrawn'],true)||array_keys($payload)!==['schedule_id','schedule_sha256','comment']||!$state['schedule'])throw new RuntimeException('Invalid financial decision.');
    $schedule=$state['schedule'];
    if($payload['schedule_id']!==$schedule->data()['id']||$payload['schedule_sha256']!==$schedule->fingerprint())throw new RuntimeException('The decision does not bind the exact financial terms.');
    design_payments_comment($payload['comment'],$kind!=='accepted');
    if($kind==='withdrawn'){
        if($event['actor_type']!=='staff'||!in_array($state['status'],['offered','accepted'],true))throw new RuntimeException('This financial offer cannot be withdrawn.');
    }elseif($event['actor_type']!=='client'||$event['actor_id']!==$event['client_id']||$state['status']!=='offered')throw new RuntimeException('Only the current client can decide an undecided financial offer.');
    return ['schedule'=>$schedule,'status'=>$kind,'consent_id'=>$kind==='accepted'?$event['id']:null,'issued'=>[]];
}
function design_payments_validate(array $job,int $page=0):array{
    if($page<0||$page>410)throw new InvalidArgumentException('Financial history page unavailable.');
    $stats=one('SELECT COUNT(*) AS n,COALESCE(SUM(OCTET_LENGTH(event_json)),0) AS bytes FROM '.table('design_payment_events').' WHERE job_id=?',[$job['id']]);$count=(int)$stats['n'];$bytes=(int)$stats['bytes'];
    if($count>DESIGN_PAYMENTS_MAX_EVENTS||$bytes>DESIGN_PAYMENTS_MAX_BYTES)throw new RuntimeException('Financial history exceeds its supported bound.');
    $anchor=one('SELECT * FROM '.table('design_payment_heads').' WHERE id=?',[$job['id']]);
    if(($count===0&&$anchor)||($count>0&&(!$anchor||(int)$anchor['event_count']!==$count||(int)$anchor['retained_bytes']!==$bytes)))throw new RuntimeException('Financial history no longer matches its saved head.');
    $pages=max(1,(int)ceil($count/10));$selected=$page===0?$pages-1:$page-1;if($selected<0||$selected>=$pages)throw new InvalidArgumentException('Financial history page unavailable.');
    $state=['schedule'=>null,'status'=>'none','consent_id'=>null,'issued'=>[]];$previous=str_repeat('0',64);$expected=design_payments_initial($job);$lastTime=0;$lastId='';$items=[];$seen=0;
    for($offset=0;$offset<$count;$offset+=20){
        $rows=all('SELECT id,job_id,client_id,staff_id,client_actor_id,sequence,kind,created_at,event_sha256,input_sha256,CASE WHEN OCTET_LENGTH(event_json)<=? THEN event_json ELSE NULL END AS event_json FROM '.table('design_payment_events').' WHERE job_id=? ORDER BY sequence LIMIT 20 OFFSET '.(int)$offset,[DESIGN_PAYMENTS_EVENT_BYTES,$job['id']]);
        foreach($rows as $row){
            if(!is_string($row['event_json']))throw new RuntimeException('Financial event exceeds the supported size.');
            $event=json_decode($row['event_json'],true,20,JSON_THROW_ON_ERROR);
            if(!is_array($event)||array_keys($event)!==['format','id','project_id','client_id','sequence','kind','actor_type','actor_id','occurred_at','previous','expected','payload']||$event['format']!==1||$event['project_id']!==(int)$job['id']||$event['client_id']!==(int)$job['client_id']||$event['sequence']!==$seen||$event['previous']!==$previous||$event['expected']!==$expected||!in_array($event['actor_type'],['staff','client'],true)||!is_int($event['actor_id'])||$event['actor_id']<1||$event['actor_id']>2147483647||!is_int($event['occurred_at'])||$event['occurred_at']<$lastTime||!is_array($event['payload']))throw new RuntimeException('Invalid retained financial event.');
            project_progress_id($event['id']);$actor=['type'=>$event['actor_type'],'id'=>$event['actor_id']];$hash=hash('sha256',$row['event_json']);
            if(design_payments_json($event)!==$row['event_json']||$hash!==$row['event_sha256']||$row['id']!==$event['id']||(int)$row['job_id']!==$event['project_id']||(int)$row['client_id']!==$event['client_id']||(int)$row['sequence']!==$seen||$row['kind']!==$event['kind']||$row['created_at']!==gmdate('Y-m-d H:i:s',$event['occurred_at'])||($row['staff_id']===null?null:(int)$row['staff_id'])!==($actor['type']==='staff'?$actor['id']:null)||($row['client_actor_id']===null?null:(int)$row['client_actor_id'])!==($actor['type']==='client'?$actor['id']:null)||$row['input_sha256']!==design_payments_command((int)$job['id'],(int)$job['client_id'],$actor,$event['kind'],$event['expected'],$event['payload']))throw new RuntimeException('Financial event metadata or fingerprint mismatch.');
            $state=design_payments_transition($state,$event);if(intdiv($seen,10)===$selected)$items[]=$event;
            $previous=$expected=$hash;$lastTime=$event['occurred_at'];$lastId=$event['id'];$seen++;
        }
    }
    if($seen!==$count||($anchor&&($anchor['event_id']!==$lastId||$anchor['event_sha256']!==$previous)))throw new RuntimeException('Financial history anchor is inconsistent.');
    design_payment_invoices_validate($job,$state);
    return $state+['expected'=>$expected,'previous'=>$previous,'time'=>$lastTime,'count'=>$count,'bytes'=>$bytes,'items'=>$items,'page'=>$selected+1,'pages'=>$pages];
}
require_once CWP_CODE_ROOT.'/app/billing/design_payment_invoices.php';
function design_payments_append(array $job,array $actor,string $request,string $expected,string $kind,array $payload,?int $occurredAt=null):array{
    project_progress_id($request);if(!preg_match('/\A[a-f0-9]{64}\z/',$expected))throw new InvalidArgumentException('Keep the original financial version.');
    $state=design_payments_validate($job);$input=design_payments_command((int)$job['id'],(int)$job['client_id'],$actor,$kind,$expected,$payload);
    $prior=one('SELECT job_id,input_sha256 FROM '.table('design_payment_events').' WHERE id=?',[$request]);
    if($prior){if((int)$prior['job_id']!==(int)$job['id']||!hash_equals($prior['input_sha256'],$input))throw new RuntimeException('This financial request was already used with different details.');return ['job_id'=>(int)$job['id'],'event_id'=>$request,'replayed'=>true];}
    if(!hash_equals($state['expected'],$expected))throw new RuntimeException('The financial offer changed. Review its current terms before submitting.');
    $event=['format'=>1,'id'=>$request,'project_id'=>(int)$job['id'],'client_id'=>(int)$job['client_id'],'sequence'=>$state['count'],'kind'=>$kind,'actor_type'=>$actor['type'],'actor_id'=>$actor['id'],'occurred_at'=>$occurredAt??time(),'previous'=>$state['previous'],'expected'=>$expected,'payload'=>$payload];
    if($event['occurred_at']<$state['time'])throw new RuntimeException('The server clock precedes the saved financial history.');
    $next=design_payments_transition($state,$event);
    if($kind!=='withdrawn')design_payments_source($job,$next['schedule']);
    $json=design_payments_json($event);$bytes=$state['bytes']+strlen($json);
    if(strlen($json)>DESIGN_PAYMENTS_EVENT_BYTES||$state['count']>=DESIGN_PAYMENTS_MAX_EVENTS||$bytes>DESIGN_PAYMENTS_MAX_BYTES)throw new RuntimeException('Financial history is full. Existing terms were preserved.');
    $hash=hash('sha256',$json);query('INSERT INTO '.table('design_payment_events').' (id,job_id,client_id,staff_id,client_actor_id,sequence,kind,created_at,event_json,event_sha256,input_sha256) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$request,$job['id'],$job['client_id'],$actor['type']==='staff'?$actor['id']:null,$actor['type']==='client'?$actor['id']:null,$state['count'],$kind,gmdate('Y-m-d H:i:s',$event['occurred_at']),$json,$hash,$input]);
    query('INSERT INTO '.table('design_payment_heads').' (id,event_id,event_sha256,event_count,retained_bytes) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE event_id=VALUES(event_id),event_sha256=VALUES(event_sha256),event_count=VALUES(event_count),retained_bytes=VALUES(retained_bytes)',[$job['id'],$request,$hash,$state['count']+1,$bytes]);
    design_payments_audit($actor,$kind,'Project '.$job['id'].'; event '.$request);return ['job_id'=>(int)$job['id'],'event_id'=>$request,'replayed'=>false];
}
function design_payments_publish(int $job,int $client,string $request,string $expected,array $fields):array{
    return project_progress_locked(function()use($job,$client,$request,$expected,$fields):array{
        $actor=design_payments_staff();project_progress_client_row($client);$row=project_progress_job($job,$client);$schedule=DesignPaymentSchedule::fromArray($fields);
        return design_payments_append($row,$actor,$request,$expected,'offered',$schedule->data());
    });
}
function design_payments_decide(int $job,int $client,string $request,string $expected,string $schedule,string $fingerprint,string $choice,string $comment=''):array{
    if(!in_array($choice,['accepted','declined'],true))throw new InvalidArgumentException('Choose a financial decision.');
    return project_progress_locked(function()use($job,$client,$request,$expected,$schedule,$fingerprint,$choice,$comment):array{
        $actor=design_payments_client($client);$row=project_progress_job($job,$client);
        return design_payments_append($row,$actor,$request,$expected,$choice,['schedule_id'=>$schedule,'schedule_sha256'=>$fingerprint,'comment'=>$comment]);
    });
}
function design_payments_withdraw(int $job,int $client,string $request,string $expected,string $schedule,string $fingerprint,string $reason):array{
    return project_progress_locked(function()use($job,$client,$request,$expected,$schedule,$fingerprint,$reason):array{
        $actor=design_payments_staff();project_progress_client_row($client);$row=project_progress_job($job,$client);
        return design_payments_append($row,$actor,$request,$expected,'withdrawn',['schedule_id'=>$schedule,'schedule_sha256'=>$fingerprint,'comment'=>$reason]);
    });
}
function design_payments_view(array $job,array $state,bool $staff):array{
    $history=[];foreach($state['items'] as $event){$payload=$event['payload'];if(!$staff&&$event['kind']==='issued')unset($payload['request_sha256']);$entry=['id'=>$event['id'],'kind'=>$event['kind'],'occurred_at'=>$event['occurred_at'],'actor'=>$event['actor_type']==='staff'?'studio':'client','payload'=>$payload];if($staff)$entry['actor_id']=$event['actor_id'];$history[]=$entry;}
    $schedule=$state['schedule'];return ['project'=>['id'=>(int)$job['id'],'title'=>$job['title']],'status'=>$state['status'],'schedule'=>$schedule?->data(),'fingerprint'=>$schedule?->fingerprint(),'quote'=>$schedule?->quote(),'stages'=>$schedule?->stages(),'expected'=>$state['expected'],'count'=>$state['count'],'items'=>$history,'page'=>$state['page'],'pages'=>$state['pages']];
}
function design_payments_staff_get(int $job,int $page=0,bool $touch=true):array{
    return project_progress_touch(fn()=>project_progress_locked(function()use($job,$page):array{design_payments_staff();$row=project_progress_job($job);project_progress_client_row((int)$row['client_id'],false);return design_payments_view($row,design_payments_validate($row,$page),true);}),$touch);
}
function design_payments_client_get(int $job,int $client,int $page=0,?string $support=null,bool $touch=true):array{
    return project_progress_touch(fn()=>project_progress_locked(function()use($job,$client,$page,$support,$touch):array{
        design_payments_require_ready();$ctx=null;
        if($support!==null||support_current()||array_key_exists('support',$_GET)){
            if($support===null||$support==='')throw new RuntimeException('A current scoped support session is required.');$ctx=support_check($support,$touch);
            if(!$ctx||(int)$ctx['client']['id']!==$client)throw new RuntimeException('Current support access to this client is required.');
        }else design_payments_client($client,$touch);
        $row=project_progress_job($job,$client);$view=design_payments_view($row,design_payments_validate($row,$page),false);
        if($ctx&&$touch){design_payments_audit(['type'=>'staff','id'=>(int)$ctx['actor']['id']],'support_read','Project '.$job.'; support '.$ctx['session']['id']);support_audit($ctx['session'],'financial.read','project',(string)$job);}
        return $view;
    }),$touch);
}
