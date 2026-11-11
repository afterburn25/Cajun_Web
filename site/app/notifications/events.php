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
