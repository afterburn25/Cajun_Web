<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/includes/foundation/notifications/Notification.php';
require_once CWP_CODE_ROOT.'/includes/foundation/notifications/NotificationFeed.php';
require_once CWP_CODE_ROOT.'/includes/foundation/notifications/NotificationPreferences.php';
require_once CWP_CODE_ROOT.'/includes/foundation/notifications/DeliveryAttempt.php';
require_once CWP_CODE_ROOT.'/database/schema/notification_feed_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/notification_feed_migrate.php';
use CajunWebPro\Foundation\Notifications\{Notification,NotificationFeed,NotificationPreferences,DeliveryAttempt};

function notifications_feed_ready(): bool {return staff_ready()&&studio_ready()&&setting('notification_feed_version')==='1';}
function notifications_feed_require(): void {if(!notifications_feed_ready())throw new RuntimeException('An owner must enable notifications first.');}

/** Staff ids holding a permission — owners implicitly hold all grants. */
function notification_staff_ids(string $permission): array {
    $ids=[];foreach(all('SELECT u.id,p.role,p.permissions FROM '.table('users').' u JOIN '.table('staff_profiles').' p ON p.user_id=u.id WHERE p.enabled=1') as $s){
        $perms=json_decode((string)$s['permissions'],true)?:[];
        if($s['role']==='owner'||in_array($permission,$perms,true))$ids[]=(int)$s['id'];
    }
    return $ids;
}
function notification_recipient_email(string $audience,int $recipientId): ?string {
    $email=$audience===Notification::AUDIENCE_CLIENT?(one('SELECT email FROM '.table('studio_clients').' WHERE id=?',[$recipientId])['email']??null):(one('SELECT email FROM '.table('users').' WHERE id=?',[$recipientId])['email']??null);
    return $email?:null;
}

/** The recipient's own email preference overrides as the foundation model. */
function notification_prefs_for(string $audience,int $recipientId): NotificationPreferences {
    $email=[];foreach(all('SELECT kind,email FROM '.table('notification_prefs').' WHERE audience=? AND recipient_id=?',[$audience,$recipientId]) as $r)$email[$r['kind']]=(bool)$r['email'];
    return new NotificationPreferences((string)$recipientId,$audience,$email);
}
/** Only the recipient may change their own preference — enforced by the model before persisting. */
function notification_pref_email(string $audience,int $recipientId,string $kind,bool $enabled,string $actor): void {
    $prefs=notification_prefs_for($audience,$recipientId);$prefs->setEmail($kind,$enabled,$actor);
    query('INSERT INTO '.table('notification_prefs').' (audience,recipient_id,kind,email,updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE email=VALUES(email),updated_at=VALUES(updated_at)',[$audience,$recipientId,$kind,$enabled?1:0,gmdate('Y-m-d H:i:s')]);
}
function notification_persist_delivery(string $notificationId,DeliveryAttempt $attempt): void {
    query('INSERT INTO '.table('notification_deliveries').' (notification_id,channel,state,attempts,last_error,sent_at,next_at,updated_at) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE state=VALUES(state),attempts=VALUES(attempts),last_error=VALUES(last_error),sent_at=VALUES(sent_at),next_at=VALUES(next_at),updated_at=VALUES(updated_at)',[$notificationId,$attempt->channel,$attempt->state(),$attempt->attempts(),$attempt->lastError()??'',$attempt->sentAt(),$attempt->nextAttemptAt(),gmdate('Y-m-d H:i:s')]);
}
/**
 * Record a directed notification for one recipient: the portal feed row is the
 * unconditional channel; an email delivery attempt is recorded and tried only
 * when outbound mail is configured and the recipient has not opted out of the
 * kind. Synchronous send — maxAttempts 1, failures record honestly (no daemon).
 */
function notification_emit(string $audience,int $recipientId,string $kind,string $link,string $title,string $body,?string $dedupe=null): void {
    if(!notifications_feed_ready())return;
    $id=$dedupe===null?bin2hex(random_bytes(16)):substr(hash('sha256','cwp-notification|'.$dedupe),0,32);
    $notification=new Notification($id,$audience,(string)$recipientId,$kind,substr($title,0,200),substr($body,0,1000),$link,time());
    $fresh=staff_transaction(function() use($notification,$audience,$recipientId,$kind,$link,$dedupe): bool {
        $insert='INSERT '.($dedupe===null?'':'IGNORE ').'INTO '.table('notification_feed').' (id,audience,recipient_id,kind,ref,title,body,created_at) VALUES (?,?,?,?,?,?,?,?)';
        $written=query($insert,[$notification->id,$audience,$recipientId,$kind,$link,$notification->title,$notification->body,gmdate('Y-m-d H:i:s',$notification->createdAt)])->rowCount()===1;
        if($written){$portal=new DeliveryAttempt($notification->id,DeliveryAttempt::CHANNEL_PORTAL,1);$portal->recordSent($notification->createdAt);notification_persist_delivery($notification->id,$portal);}
        return $written;
    });
    if(!$fresh)return;
    if(!notification_prefs_for($audience,$recipientId)->emailEnabled($kind)||setting('email_mode','disabled')==='disabled')return;
    $email=notification_recipient_email($audience,$recipientId);if($email===null)return;
    require_once CWP_CODE_ROOT.'/app/mail/transport.php';
    $attempt=new DeliveryAttempt($notification->id,DeliveryAttempt::CHANNEL_EMAIL,1);
    try{if(send_message($email,$title,$body."\n\nOpen your account: ".($audience===Notification::AUDIENCE_CLIENT?site_url('portal/index.php'):admin_url('notifications')))==='accepted')$attempt->recordSent(time());else $attempt->recordFailure(time(),'Outbound mail is not configured.');}
    catch(Throwable $e){$attempt->recordFailure(time(),substr($e->getMessage(),0,300));}
    notification_persist_delivery($notification->id,$attempt);
}
/** One recipient's feed — construction rejects any foreign or wrong-audience row. */
function notification_feed(string $audience,int $recipientId,int $limit=100): NotificationFeed {
    $rows=all('SELECT * FROM '.table('notification_feed').' WHERE audience=? AND recipient_id=? ORDER BY created_at DESC, id DESC LIMIT '.max(1,min(500,$limit)),[$audience,$recipientId]);
    $items=[];$read=[];
    foreach($rows as $r){$items[]=new Notification($r['id'],$r['audience'],(string)$r['recipient_id'],$r['kind'],$r['title'],$r['body'],$r['ref'],(int)strtotime($r['created_at'].' UTC'));if($r['read_at']!==null)$read[$r['id']]=true;}
    return new NotificationFeed((string)$recipientId,$audience,$items,$read);
}
/** Recipient-scoped read-state mutation — the model denies any other actor. */
function notification_mark(string $audience,int $recipientId,string $id,bool $read): void {
    $feed=notification_feed($audience,$recipientId);
    if($read)$feed->markRead($id,(string)$recipientId);else $feed->markUnread($id,(string)$recipientId);
    query('UPDATE '.table('notification_feed').' SET read_at=? WHERE id=?',[$read?gmdate('Y-m-d H:i:s'):null,$id]);
}
function notification_mark_all(string $audience,int $recipientId): void {
    notification_feed($audience,$recipientId)->markAllRead((string)$recipientId);
    query('UPDATE '.table('notification_feed').' SET read_at=? WHERE audience=? AND recipient_id=? AND read_at IS NULL',[gmdate('Y-m-d H:i:s'),$audience,$recipientId]);
}
/** Typed ref → authorized destination. The renderer authorizes before linking. */
function notification_url(string $audience,string $ref): string {
    if(!preg_match('/^([a-z][a-z0-9-]*):([A-Za-z0-9_-]+)$/',$ref,$m))return '#';
    if($audience===Notification::AUDIENCE_CLIENT)return match($m[1]){'job'=>studio_url(['job'=>(int)$m[2]]),'release'=>studio_url(['release'=>$m[2]]),'invoice'=>studio_url(['invoice'=>(int)$m[2]]),'ticket'=>studio_url(['ticket'=>$m[2]]),default=>studio_url()};
    return match($m[1]){'inquiry'=>admin_url('lead',['id'=>(int)$m[2]]),'job'=>admin_url('studio-job',['id'=>(int)$m[2]]),'invoice'=>admin_url('invoice-payments',['invoice_id'=>(int)$m[2]]),'ticket'=>admin_url('ticket',['ticket'=>$m[2]]),default=>admin_url('notifications')};
}
