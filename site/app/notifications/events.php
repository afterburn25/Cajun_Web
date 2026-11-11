<?php
/** Local best-effort notification projection after committed business events; never sends email. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/notifications/feed.php';
/** Privacy-minimal projection plan for a committed immutable ticket event. */
function notification_ticket_projection(string $ticket,int $client,string $event,string $actor,string $command):array {
    if(!preg_match('/\A[a-f0-9]{32}\z/',$ticket)||!preg_match('/\A[a-f0-9]{32}\z/',$event)||$client<1)throw new InvalidArgumentException('Invalid ticket notification identity.');
    if(!in_array($actor,['staff','client','support'],true)||!in_array($command,['opened','reply','status','note','assign'],true))throw new InvalidArgumentException('Invalid ticket notification event.');
    if($actor==='support'||in_array($command,['note','assign'],true))return [];
    $toClient=$actor==='staff';
    $title=$toClient?match($command){'opened'=>'Support conversation opened','reply'=>'New support reply',default=>'Support conversation updated'}:match($command){'opened'=>'New support conversation','reply'=>'New client support reply',default=>'Client updated support conversation'};
    return ['audience'=>$toClient?'client':'staff','recipient_id'=>$toClient?$client:null,'permission'=>$toClient?null:'support.tickets','kind'=>'support-reply','ref'=>'ticket:'.$ticket,'title'=>$title,'body'=>$toClient?'Sign in to your account to review the support conversation.':'A client support conversation has new activity.','event_key'=>'ticket-event:'.$event];
}
/** Best-effort only after the ticket transaction commits; never persists conversation content. */
function notification_ticket_event(string $ticket,int $client,string $event,string $actor,string $command):void {
    try{
        if(!notifications_feed_ready())return;
        $plan=notification_ticket_projection($ticket,$client,$event,$actor,$command);if(!$plan)return;
        $recipients=$plan['audience']==='staff'?notification_staff_ids($plan['permission']):[$plan['recipient_id']];
        foreach($recipients as $id)notification_emit($plan['audience'],(int)$id,$plan['kind'],$plan['ref'],$plan['title'],$plan['body'],$plan['event_key']);
    }catch(Throwable $e){report_error($e);}
}
/** Privacy-minimal recipient plan for one immutable project event. */
function notification_project_projection(int $job,int $client,string $event,string $actor):array {
    if($job<1||$client<1||!preg_match('/\A(created|version|file):([a-z0-9]+)\z/',$event,$match))throw new InvalidArgumentException('Invalid project notification identity.');
    $type=$match[1];$identity=$match[2];if(($type==='file'&&!preg_match('/\A[a-f0-9]{32}\z/',$identity))||($type!=='file'&&(!preg_match('/\A[1-9][0-9]{0,9}\z/',$identity)||(int)$identity>2147483647))||($type==='created'&&(int)$identity!==$job))throw new InvalidArgumentException('Invalid project notification identity.');
    if(!in_array($actor,['staff','client','contact','support'],true)||($type!=='file'&&$actor!=='staff'))throw new InvalidArgumentException('Invalid project notification actor.');
    if($actor==='support')return [];$toClient=$actor==='staff';
    $title=match($type){'created'=>'Project workspace opened','version'=>'Project review ready',default=>$toClient?'New project file':'New client project file'};
    $body=match($type){'created'=>'Sign in to review your project workspace.','version'=>'Sign in to review the latest project version.',default=>$toClient?'Sign in to review the new private project file.':'A client shared a file with a project you can manage.'};
    return ['audience'=>$toClient?'client':'staff','recipient_id'=>$toClient?$client:null,'permission'=>$toClient?null:'clients.manage','kind'=>$type==='version'?'approval-request':'project-update','ref'=>'job:'.$job,'title'=>$title,'body'=>$body,'event_key'=>match($type){'created'=>'project-created:'.$identity,'version'=>'version-ready:'.$identity,default=>'file-shared:'.$identity}];
}
function notification_project_emit(array $plan):void {
    $recipients=$plan['audience']==='staff'?notification_staff_ids($plan['permission']):[$plan['recipient_id']];
    foreach($recipients as $id)notification_emit($plan['audience'],(int)$id,$plan['kind'],$plan['ref'],$plan['title'],$plan['body'],$plan['event_key']);
}
function notification_project_created(int $job):void {
    try{if(support_current()||!notifications_feed_ready())return;$row=one('SELECT client_id FROM '.table('studio_jobs').' WHERE id=?',[$job]);if($row)notification_project_emit(notification_project_projection($job,(int)$row['client_id'],'created:'.$job,'staff'));}catch(Throwable $e){report_error($e);}
}
function notification_project_version_ready(int $job,int $version):void {
    try{if(support_current()||!notifications_feed_ready())return;$row=one('SELECT j.client_id FROM '.table('studio_versions').' v JOIN '.table('studio_jobs')." j ON j.id=v.job_id WHERE v.id=? AND v.job_id=? AND v.decision='pending'",[$version,$job]);if($row)notification_project_emit(notification_project_projection($job,(int)$row['client_id'],'version:'.$version,'staff'));}catch(Throwable $e){report_error($e);}
}
function notification_project_file(int $job,string $file,string $actor):void {
    try{if(!notifications_feed_ready())return;if(!preg_match('/\A[a-f0-9]{32}\z/',$file)||!in_array($actor,['staff','client','contact','support'],true))throw new InvalidArgumentException('Invalid project file notification.');if($actor==='support')return;
        $storedActor=$actor==='staff'?'admin':$actor;$row=one('SELECT j.client_id FROM '.table('studio_files').' f JOIN '.table('studio_jobs').' j ON j.id=f.job_id WHERE f.id=? AND f.job_id=? AND f.author_type=?',[$file,$job,$storedActor]);if($row)notification_project_emit(notification_project_projection($job,(int)$row['client_id'],'file:'.$file,$actor));
    }catch(Throwable $e){report_error($e);}
}
function notification_invoice_projection(int $job,int $invoice,int $client):array {
    if($job<1||$invoice<1||$client<1)throw new InvalidArgumentException('Invalid invoice notification identity.');
    return ['audience'=>'client','recipient_id'=>$client,'kind'=>'billing','ref'=>'invoice:'.$invoice,'title'=>'New invoice available','body'=>'Sign in to review the new invoice in your account.','event_key'=>'invoice-issued:'.$invoice];
}
function notification_invoice_issued(int $job,int $invoice):void {
    try{if(support_current()||!notifications_feed_ready())return;$row=one('SELECT j.client_id FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs')." j ON j.id=i.job_id WHERE i.id=? AND i.job_id=? AND i.status='issued'",[$invoice,$job]);if($row){$plan=notification_invoice_projection($job,$invoice,(int)$row['client_id']);notification_emit($plan['audience'],$plan['recipient_id'],$plan['kind'],$plan['ref'],$plan['title'],$plan['body'],$plan['event_key']);}}catch(Throwable $e){report_error($e);}
}
function notification_project_message(int $jobId,int $messageId,string $from):void {
    if(!notifications_feed_ready())return;
    try{$job=one('SELECT client_id FROM '.table('studio_jobs').' WHERE id=?',[$jobId]);if(!$job)return;
        if($from==='staff')notification_emit('client',(int)$job['client_id'],'project-update','job:'.$jobId,'New project message','Sign in to your project to review the latest studio message.','message:'.$messageId);
        elseif(in_array($from,['client','contact'],true))foreach(notification_staff_ids('clients.manage') as $sid)notification_emit('staff',$sid,'support-reply','job:'.$jobId,'New client project reply','A client posted a reply to a project you can manage.','message:'.$messageId);
    }catch(Throwable $e){report_error($e);}
}
function notification_invoice_paid(int $invoice):void {
    if(!notifications_feed_ready())return;
    try{$r=one('SELECT i.paid_at,j.client_id FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs')." j ON j.id=i.job_id WHERE i.id=? AND i.status='paid'",[$invoice]);if($r)notification_emit('client',(int)$r['client_id'],'billing','invoice:'.$invoice,'Invoice recorded as paid','Review the recorded payment in your account.','paid:'.$r['paid_at']);}catch(Throwable $e){report_error($e);}
}
