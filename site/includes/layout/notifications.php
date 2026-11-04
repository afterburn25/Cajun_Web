<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/notifications/feed.php';
use CajunWebPro\Foundation\Notifications\{Notification,NotificationFeed};
function notification_kind_label(string $kind):string{return ['project-update'=>'Project update','approval-request'=>'Approval decision','download-ready'=>'Website download','support-reply'=>'Project reply','billing'=>'Billing','service-event'=>'New inquiry'][$kind]??'Notification';}
function notification_feed_list(NotificationFeed $feed,string $audience,string $action):void {
    echo '<p>'.(int)$feed->unreadCount().' unread in your recent notifications.</p>';
    if(!$feed->page()){echo '<p>No notifications are available for your current access.</p>';return;}
    foreach($feed->page(0,100) as $n){
        if($audience==='client')$n->assertClientVisible();$unread=$feed->isUnread($n->id);
        echo '<article class="workspace-version" data-notification="'.esc($n->id).'"><span class="eyebrow">'.esc(notification_kind_label($n->kind)).($unread?' · NEW':'').'</span><h3>'.esc($n->title).'</h3><div class="prose">'.nl2br(esc($n->body)).'</div><p><a class="btn outline" href="'.esc(notification_url($audience,$n->link)).'">Open related item</a> <small>'.esc(gmdate('Y-m-d H:i:s',$n->createdAt)).' UTC</small></p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="'.esc($action).'"><input type="hidden" name="id" value="'.esc($n->id).'"><input type="hidden" name="read" value="'.($unread?'1':'0').'"><button class="btn outline">'.($unread?'Mark read':'Mark unread').'</button></form></article>';
    }
}
function notification_prefs_form(string $audience,int $id,string $action):void {
    notification_actor($audience,$id);$prefs=notification_prefs_for($audience,$id);
    echo '<section class="workspace-card"><h2>Email preferences</h2><p>Your private in-app feed remains available. Email copies require owner-enabled delivery and your permission for that category.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="'.esc($action).'">';
    foreach(Notification::KINDS as $kind)echo '<label class="check"><input type="checkbox" name="email_'.esc($kind).'" value="1"'.($prefs->emailEnabled($kind)?' checked':'').'>'.esc(notification_kind_label($kind)).' by email</label>';
    echo '<button class="btn outline">Save email preferences</button></form></section>';
}
function notification_prefs_save(string $audience,int $id):void {
    staff_locked(fn()=>staff_transaction(function()use($audience,$id):void {
        notification_actor($audience,$id);
        foreach(Notification::KINDS as $kind)query('INSERT INTO '.table('notification_prefs').' (audience,recipient_id,kind,email,updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE email=VALUES(email),updated_at=VALUES(updated_at)',[$audience,$id,$kind,posted('email_'.$kind,1)==='1'?1:0,gmdate('Y-m-d H:i:s')]);
    }));
}
function notification_center_admin_action(string $action):never {
    if($action==='notifications_setup'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm that you saved a database and private-storage backup.');
        notification_feed_migrate();flash('Notification center enabled. Email remains separately controlled.');redirect(admin_url('notifications'));
    }
    if($action==='notification_email_configure'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm the notification email setting.');
        notification_email_configure(posted('enabled',1)==='1');flash('Notification email setting saved. Opening this page never sends mail.');redirect(admin_url('notifications'));
    }
    if($action==='notification_dispatch'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm processing up to three due email copies.');
        $result=notification_outbox_pump();flash(count($result).' queue items reviewed. Check the recorded outcome; provider acceptance is not inbox delivery.');redirect(admin_url('notifications'));
    }
    $actor=require_admin();$id=(int)$actor['id'];
    if($action==='notification_mark')notification_mark('staff',$id,posted('id',32),posted('read',1)==='1');
    elseif($action==='notification_mark_all')notification_mark_all('staff',$id);
    elseif($action==='notification_prefs')notification_prefs_save('staff',$id);
    else throw new InvalidArgumentException('Unknown notification action.');
    flash('Notification preferences or read state saved.');redirect(admin_url('notifications'));
}
function notification_center_portal_action(string $action,?array $client):never {
    if(!$client)throw new RuntimeException('Sign in to your notification center.');$id=(int)$client['id'];
    if($action==='notification_client_mark')notification_mark('client',$id,posted('id',32),posted('read',1)==='1');
    elseif($action==='notification_client_mark_all')notification_mark_all('client',$id);
    elseif($action==='notification_client_prefs')notification_prefs_save('client',$id);
    else throw new InvalidArgumentException('Unknown notification action.');
    flash('Saved.');redirect(studio_url(['notifications'=>1]));
}
function notification_center_admin_render(array $user,string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    if(support_current()||array_key_exists('support',$_GET))fail_page('Return to your own account before opening notifications.',403);
    admin_head('Notifications','notifications',$user);if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    if(!notifications_feed_ready()){
        echo '<section class="workspace-card"><h2>Enable the notification center</h2><p>Keep project, billing, inquiry and delivery updates in a private account feed. Setup adds dedicated tables without replacing existing records. Outbound email is off until an owner explicitly enables it.</p>';
        if(staff_can('staff.manage',$user)){echo '<form method="post">';admin_action('notifications_setup');admin_check('confirm','I saved a current database and private-storage backup.',false);echo '<button class="btn gold">Enable notifications</button></form>';}
        else echo '<p>An owner must prepare notification storage.</p>';
        echo '</section>';admin_foot();exit;
    }
    $feed=notification_feed('staff',(int)$user['id']);
    echo '<section class="workspace-card"><h2>Your notifications</h2><form method="post">';admin_action('notification_mark_all');echo '<button class="btn outline">Mark recent notifications read</button></form>';
    notification_feed_list($feed,'staff','notification_mark');echo '</section>';
    notification_prefs_form('staff',(int)$user['id'],'notification_prefs');
    if(staff_can('staff.manage',$user)){
        echo '<section class="workspace-card" data-notification-outbox><h2>Controlled email delivery</h2><p>Email is never sent by opening or refreshing this page. Enable the queue for future notifications, then explicitly process a small due batch. Pausing delivery preserves queued records.</p><form method="post">';admin_action('notification_email_configure');admin_check('enabled','Queue email copies of future eligible notifications',setting('notification_email_enabled','0')==='1');admin_check('confirm','I reviewed this email-delivery setting.',false);echo '<button class="btn outline">Save delivery setting</button></form>';
        echo '<form method="post">';admin_action('notification_dispatch');admin_check('confirm','Process up to three due email copies using configured outbound delivery.',false);echo '<button class="btn gold">Process due email</button></form><p>Sent means accepted by the configured transport, not verified inbox delivery. Sending or uncertain outcomes require provider review and are never automatically resent.</p>';
        $rows=all('SELECT state,COUNT(*) AS total FROM '.table('notification_deliveries')." WHERE channel='email' GROUP BY state ORDER BY state");
        if(!$rows)echo '<p>No email copies have been queued.</p>';else foreach($rows as $row)echo '<p>'.esc(ucfirst($row['state'])).': '.(int)$row['total'].'</p>';
        echo '</section>';
    }
    admin_foot();exit;
}
function notification_center_portal_render(string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    if(!notifications_feed_ready())fail_page('Notifications are not enabled yet.',404);
    if(support_current()||array_key_exists('support',$_GET)||!($client=studio_client()))fail_page('Sign in to your own account to view notifications.',403);
    if(array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION))fail_page('Notifications belong to the primary client account.',403);
    $feed=notification_feed('client',(int)$client['id']);site_head('Your notifications','portal');
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">PRIVATE ACCOUNT UPDATES</span><h1>Your notifications</h1><a class="btn outline" href="'.esc(studio_url()).'">Back to your workspace</a></div></section><section class="section"><div class="container">';show_flash();
    if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    echo '<section class="workspace-card"><h2>Directed to you</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="notification_client_mark_all"><button class="btn outline">Mark recent notifications read</button></form>';
    notification_feed_list($feed,'client','notification_client_mark');echo '</section>';notification_prefs_form('client',(int)$client['id'],'notification_client_prefs');echo '</div></section>';site_foot(false);exit;
}
