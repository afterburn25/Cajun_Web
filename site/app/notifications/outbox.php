<?php
/** Explicit owner dispatch. A durable claim precedes transport; uncertain sends never auto-retry. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
use CajunWebPro\Foundation\Notifications\DeliveryAttempt;
function notification_email_configure(bool $enabled):void {
    staff_locked(fn()=>staff_transaction(function()use($enabled):void {
        $owner=notification_owner();notifications_feed_require();save_setting('notification_email_enabled',$enabled?'1':'0');
        log_event('notifications.email.configure',($enabled?'Enabled':'Paused').' future notification email queue by administrator '.$owner['id']);
    }));
}
function notification_outbox_pump(int $limit=3,?callable $transport=null):array {
    notification_owner();notifications_feed_require();
    if($limit<1||$limit>3)throw new InvalidArgumentException('Process between one and three messages at a time.');
    if(setting('notification_email_enabled','0')!=='1'||setting('email_mode','disabled')==='disabled')throw new RuntimeException('Enable notification email and configure outbound delivery before processing the queue.');
    $ids=all('SELECT notification_id FROM '.table('notification_deliveries')." WHERE channel='email' AND state IN ('pending','retrying') AND next_at IS NOT NULL AND next_at<=? ORDER BY next_at,id LIMIT ".$limit,[time()]);$results=[];
    foreach($ids as $row)$results[$row['notification_id']]=notification_outbox_one($row['notification_id'],$transport);
    return $results;
}
/** Callable is an internal transport boundary used by CLI tests, never supplied by HTTP fields. */
function notification_outbox_one(string $id,?callable $transport=null):string {
    return staff_locked(function()use($id,$transport):string {
        $owner=notification_owner();notifications_feed_require();
        if(setting('notification_email_enabled','0')!=='1'||setting('email_mode','disabled')==='disabled')return 'paused';
        $claim=staff_transaction(function()use($id,$owner):?array {
            $d=one('SELECT d.*,f.audience,f.recipient_id,f.kind,f.ref,f.title,f.body FROM '.table('notification_deliveries').' d JOIN '.table('notification_feed')." f ON f.id=d.notification_id WHERE d.notification_id=? AND d.channel='email' FOR UPDATE",[$id]);
            if(!$d||!in_array($d['state'],['pending','retrying'],true)||$d['next_at']===null||(int)$d['next_at']>time())return null;
            try{$model=DeliveryAttempt::restore($id,'email',3,(int)$d['attempts'],$d['state'],$d['sent_at']===null?null:(int)$d['sent_at'],$d['last_error']===''?null:$d['last_error'],(int)$d['next_at']);}
            catch(InvalidArgumentException){query('UPDATE '.table('notification_deliveries')." SET state='failed',next_at=NULL,last_error=?,updated_at=? WHERE id=?",['Invalid stored delivery state; no transport attempted.',gmdate('Y-m-d H:i:s'),$d['id']]);return null;}
            $recipient=notification_recipient($d['audience'],(int)$d['recipient_id'],$d['ref'],$d['kind']);
            if(!$recipient||!notification_prefs_for($d['audience'],(int)$d['recipient_id'])->emailEnabled($d['kind'])||!filter_var($recipient['email']??'',FILTER_VALIDATE_EMAIL)){
                query('UPDATE '.table('notification_deliveries')." SET state='skipped',next_at=NULL,last_error=?,updated_at=? WHERE id=?",['Recipient access or email preference no longer permits this notification.',gmdate('Y-m-d H:i:s'),$d['id']]);return null;
            }
            query('UPDATE '.table('notification_deliveries')." SET state='sending',attempts=attempts+1,next_at=NULL,last_error='',updated_at=? WHERE id=? AND state=? AND attempts=?",[gmdate('Y-m-d H:i:s'),$d['id'],$d['state'],(int)$d['attempts']]);
            log_event('notifications.email.claim','Notification '.$id.' claimed by administrator '.$owner['id']);
            $d['recipient_email']=$recipient['email'];$d['model']=$model;return $d;
        });
        if($claim===null)return 'not_due';
        // This claim is committed before external I/O. A crash or final-write failure
        // leaves sending/uncertain, neither of which is selected for another send.
        $now=time();$state='uncertain';$sent=null;$next=null;$error='Delivery acceptance is unknown. Review provider records before any separate resend.';
        try{
            notification_owner();
            if($transport===null){require_once CWP_CODE_ROOT.'/app/mail/transport.php';$transport=static fn(string $to,string $subject,string $body):string=>send_message($to,$subject,$body);}
            $outcome=$transport($claim['recipient_email'],$claim['title'],"A new private account notification is available. Sign in to review it.\n\n".($claim['audience']==='client'?site_url('portal/index.php?notifications=1'):admin_url('notifications')));
            if($outcome==='accepted'){$state='sent';$sent=$now;$error='';}
            elseif($outcome==='not_configured'){$model=$claim['model'];$model->recordFailure($now,'Delivery was not attempted because outbound mail became unavailable.');$state=$model->state();$next=$model->nextAttemptAt();$error=$model->lastError();}
        }catch(Throwable){/* No provider error text or credentials enter the UI. Unknown acceptance never retries. */}
        staff_transaction(function()use($claim,$state,$sent,$next,$error):void {
            $changed=query('UPDATE '.table('notification_deliveries')." SET state=?,sent_at=?,next_at=?,last_error=?,updated_at=? WHERE id=? AND state='sending' AND attempts=?",[$state,$sent,$next,$error,gmdate('Y-m-d H:i:s'),$claim['id'],(int)$claim['attempts']+1])->rowCount();
            if($changed!==1)throw new RuntimeException('Delivery outcome could not be recorded. Do not resend until its retained claim is reviewed.');
        });
        return $state;
    });
}
