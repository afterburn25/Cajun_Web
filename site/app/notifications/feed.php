<?php
/** Recipient-scoped notifications. Emission queues locally; rendering never sends mail. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
foreach(['Notification','NotificationFeed','NotificationPreferences','DeliveryAttempt'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/notifications/'.$class.'.php';
require_once CWP_CODE_ROOT.'/database/schema/notification_feed_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/notification_feed_migrate.php';
use CajunWebPro\Foundation\Notifications\{Notification,NotificationFeed,NotificationPreferences,DeliveryAttempt};
function notifications_feed_ready():bool{return staff_ready()&&studio_ready()&&setting('notification_feed_version')==='1';}
function notifications_feed_require():void{if(!notifications_feed_ready())throw new RuntimeException('An owner must enable notifications first.');}
function notification_owner():array {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Leave client support before managing notifications.');
    $actor=admin_user();
    if(!staff_ready()||!$actor||($actor['role']??'')!=='owner'||!admin_session_matches($actor)||!staff_can('staff.manage',$actor))throw new RuntimeException('A current owner session is required to manage notification delivery.');
    return $actor;
}
function notification_actor(string $audience,int $id):array {
    notifications_feed_require();
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Return to your own account before opening notifications.');
    if($audience==='staff'){$actor=admin_user();if(!$actor||!admin_session_matches($actor))throw new RuntimeException('Sign in to your notification center.');}
    elseif($audience==='client'){
        $actor=studio_client();
        if(array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION))throw new RuntimeException('Notifications belong to the primary client account.');
    }else throw new InvalidArgumentException('Unknown notification audience.');
    if(!$actor||$id<1||(int)$actor['id']!==$id)throw new RuntimeException('Notifications belong to the currently signed-in recipient.');
    return $actor;
}
function notification_staff_ids(string $permission):array {
    if(!staff_ready())return [];
    $ids=[];
    foreach(all('SELECT u.id,p.role,p.permissions FROM '.table('users').' u JOIN '.table('staff_profiles').' p ON p.user_id=u.id WHERE p.enabled=1') as $s){
        $perms=json_decode((string)$s['permissions'],true);
        if($s['role']==='owner'||($s['role']==='administrator'&&is_array($perms)&&in_array($permission,$perms,true)))$ids[]=(int)$s['id'];
    }
    return $ids;
}
/** Recheck the current resource owner/permission; an old feed row is not an access grant. */
function notification_recipient(string $audience,int $id,string $ref,string $kind):?array {
    if(!preg_match('/\A(job|invoice|release|inquiry|ticket):([a-zA-Z0-9_-]+)\z/',$ref,$m)||!in_array($kind,Notification::KINDS,true))return null;
    [$unused,$type,$key]=$m;
    if($audience==='client'){
        $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1',[$id]);if(!$client)return null;
        if($type==='job'&&ctype_digit($key))$ok=one('SELECT id FROM '.table('studio_jobs').' WHERE id=? AND client_id=?',[(int)$key,$id]);
        elseif($type==='invoice'&&ctype_digit($key))$ok=one('SELECT i.id FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON j.id=i.job_id WHERE i.id=? AND j.client_id=?',[(int)$key,$id]);
        elseif($type==='release'&&function_exists('delivery_ready')&&delivery_ready())$ok=one('SELECT r.id FROM '.table('website_releases').' r JOIN '.table('studio_jobs').' j ON j.id=r.job_id WHERE r.id=? AND r.client_id=? AND j.client_id=? AND r.status=?',[$key,$id,$id,'published']);
        elseif($type==='ticket'&&preg_match('/\A[a-f0-9]{32}\z/',$key))$ok=one('SELECT id FROM '.table('support_tickets').' WHERE id=? AND client_id=?',[$key,$id]);
        else $ok=null;
        return $ok?$client:null;
    }
    if($audience!=='staff')return null;
    $user=one('SELECT u.*,p.role,p.enabled,p.permissions FROM '.table('users').' u JOIN '.table('staff_profiles').' p ON p.user_id=u.id WHERE u.id=? AND p.enabled=1',[$id]);
    if(!$user||!in_array($user['role'],['owner','administrator'],true))return null;
    $permissions=json_decode($user['permissions'],true);if(!is_array($permissions)||!array_is_list($permissions))return null;
    $required=$type==='inquiry'?['website.manage']:($type==='invoice'?['clients.manage','payments.manage']:($type==='ticket'?['support.tickets']:['clients.manage']));
    if($user['role']!=='owner'&&array_diff($required,$permissions))return null;
    if($type==='job'&&ctype_digit($key))$exists=one('SELECT id FROM '.table('studio_jobs').' WHERE id=?',[(int)$key]);
    elseif($type==='invoice'&&ctype_digit($key))$exists=one('SELECT id FROM '.table('studio_invoices').' WHERE id=?',[(int)$key]);
    elseif($type==='inquiry'&&ctype_digit($key))$exists=one('SELECT id FROM '.table('leads').' WHERE id=?',[(int)$key]);
    elseif($type==='release'&&function_exists('delivery_ready')&&delivery_ready())$exists=one('SELECT id FROM '.table('website_releases').' WHERE id=?',[$key]);
    elseif($type==='ticket'&&preg_match('/\A[a-f0-9]{32}\z/',$key))$exists=one('SELECT t.id FROM '.table('support_tickets').' t JOIN '.table('studio_clients').' c ON c.id=t.client_id WHERE t.id=? AND c.active=1',[$key]);
    else $exists=null;
    return $exists?$user:null;
}
/** Internal projection used by emitter and dispatcher, never a caller-supplied identity grant. */
function notification_prefs_for(string $audience,int $id):NotificationPreferences {
    $email=[];foreach(all('SELECT kind,email FROM '.table('notification_prefs').' WHERE audience=? AND recipient_id=?',[$audience,$id]) as $r)$email[$r['kind']]=(bool)$r['email'];
    return new NotificationPreferences((string)$id,$audience,$email);
}
function notification_pref_email(string $audience,int $id,string $kind,bool $enabled,string $actor):void {
    staff_locked(fn()=>staff_transaction(function()use($audience,$id,$kind,$enabled,$actor):void {
        notification_actor($audience,$id);$prefs=notification_prefs_for($audience,$id);$prefs->setEmail($kind,$enabled,$actor);
        query('INSERT INTO '.table('notification_prefs').' (audience,recipient_id,kind,email,updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE email=VALUES(email),updated_at=VALUES(updated_at)',[$audience,$id,$kind,(int)$enabled,gmdate('Y-m-d H:i:s')]);
    }));
}
/** $eventKey binds an immutable business event so a replay does not queue another email. */
function notification_emit(string $audience,int $id,string $kind,string $ref,string $title,string $body,string $eventKey=''):void {
    if(!notifications_feed_ready())return;
    $notificationId=$eventKey===''?bin2hex(random_bytes(16)):substr(hash('sha256',json_encode([$audience,$id,$kind,$ref,$eventKey],JSON_THROW_ON_ERROR)),0,32);
    $n=new Notification($notificationId,$audience,(string)$id,$kind,substr($title,0,200),substr($body,0,1000),$ref,time());
    if(!notification_recipient($audience,$id,$ref,$kind))return;
    staff_transaction(function()use($n,$audience,$id,$kind,$ref):void {
        $old=one('SELECT * FROM '.table('notification_feed').' WHERE id=? FOR UPDATE',[$n->id]);
        if($old){if($old['audience']!==$audience||(int)$old['recipient_id']!==$id||$old['kind']!==$kind||$old['ref']!==$ref)throw new RuntimeException('Conflicting notification event identity.');return;}
        query('INSERT INTO '.table('notification_feed').' (id,audience,recipient_id,kind,ref,title,body,created_at) VALUES (?,?,?,?,?,?,?,?)',[$n->id,$audience,$id,$kind,$ref,$n->title,$n->body,gmdate('Y-m-d H:i:s',$n->createdAt)]);
        query('INSERT INTO '.table('notification_deliveries')." (notification_id,channel,state,attempts,last_error,sent_at,next_at,updated_at) VALUES (?,'portal','sent',1,'',?,NULL,?)",[$n->id,$n->createdAt,gmdate('Y-m-d H:i:s')]);
        // A prior page/request-cache read cannot authorize a future email projection.
        $emailSetting=one('SELECT value FROM '.table('settings').' WHERE name=?',['notification_email_enabled']);
        if(($emailSetting['value']??'0')==='1'&&notification_prefs_for($audience,$id)->emailEnabled($kind))query('INSERT INTO '.table('notification_deliveries')." (notification_id,channel,state,attempts,last_error,sent_at,next_at,updated_at) VALUES (?,'email','pending',0,'',NULL,?,?)",[$n->id,$n->createdAt,gmdate('Y-m-d H:i:s')]);
    });
}
function notification_feed(string $audience,int $id,int $limit=100):NotificationFeed {
    notification_actor($audience,$id);$items=[];$read=[];
    $rows=all('SELECT * FROM '.table('notification_feed').' WHERE audience=? AND recipient_id=? ORDER BY created_at DESC,id DESC LIMIT '.max(1,min(500,$limit)),[$audience,$id]);
    foreach($rows as $r){if(!notification_recipient($audience,$id,$r['ref'],$r['kind']))continue;$items[]=new Notification($r['id'],$audience,(string)$id,$r['kind'],$r['title'],$r['body'],$r['ref'],(int)strtotime($r['created_at'].' UTC'));if($r['read_at']!==null)$read[$r['id']]=true;}
    return new NotificationFeed((string)$id,$audience,$items,$read);
}
function notification_mark(string $audience,int $id,string $notification,bool $read):void {
    staff_locked(fn()=>staff_transaction(function()use($audience,$id,$notification,$read):void {
        notification_actor($audience,$id);$row=one('SELECT * FROM '.table('notification_feed').' WHERE id=? AND audience=? AND recipient_id=? FOR UPDATE',[$notification,$audience,$id]);
        if(!$row||!notification_recipient($audience,$id,$row['ref'],$row['kind']))throw new RuntimeException('Notification unavailable.');
        query('UPDATE '.table('notification_feed').' SET read_at=? WHERE id=? AND audience=? AND recipient_id=?',[$read?gmdate('Y-m-d H:i:s'):null,$notification,$audience,$id]);
    }));
}
function notification_mark_all(string $audience,int $id):void {
    staff_locked(fn()=>staff_transaction(function()use($audience,$id):void {
        $feed=notification_feed($audience,$id,500);foreach($feed->page(0,500) as $n)query('UPDATE '.table('notification_feed').' SET read_at=? WHERE id=? AND audience=? AND recipient_id=?',[gmdate('Y-m-d H:i:s'),$n->id,$audience,$id]);
    }));
}
function notification_url(string $audience,string $ref):string {
    if(!preg_match('/\A(job|invoice|release|inquiry|ticket):([a-zA-Z0-9_-]+)\z/',$ref,$m))return '#';
    if($audience==='client')return match($m[1]){'job'=>studio_url(['job'=>(int)$m[2]]),'release'=>studio_url(['release'=>$m[2]]),'invoice'=>studio_url(['invoice'=>(int)$m[2]]),'ticket'=>studio_url(['ticket'=>$m[2]]),default=>studio_url()};
    return match($m[1]){'inquiry'=>admin_url('lead',['id'=>(int)$m[2]]),'job'=>admin_url('studio-job',['id'=>(int)$m[2]]),'invoice'=>admin_url('invoice-payments',['invoice_id'=>(int)$m[2]]),'ticket'=>admin_url('ticket',['ticket'=>$m[2]]),default=>admin_url('notifications')};
}
require_once CWP_CODE_ROOT.'/app/notifications/outbox.php';
