<?php
/** Local best-effort notification projection after committed business events; never sends email. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/notifications/feed.php';
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
