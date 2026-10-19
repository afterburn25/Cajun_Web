<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/notifications/feed.php';
use CajunWebPro\Foundation\Notifications\{Notification,NotificationFeed};

function notification_kind_label(string $kind): string {return ['project-update'=>'Project update','approval-request'=>'Approval decision','download-ready'=>'Website download','support-reply'=>'Support reply','billing'=>'Billing','service-event'=>'Service event'][$kind]??$kind;}

/** Shared feed card list; $links maps notification id => destination URL. */
function notification_feed_list(NotificationFeed $feed,string $audience,string $markAction='notification_mark'): void {
    if($feed->unreadCount()===0&&!$feed->page()){echo '<p>No notifications yet.</p>';return;}
    echo '<p>'.$feed->unreadCount().' unread</p>';
    foreach($feed->page() as $n){
        if($audience===Notification::AUDIENCE_CLIENT)$n->assertClientVisible();
        $unread=$feed->isUnread($n->id);
        echo '<article class="workspace-version'.($unread?'':' read-item').'"><span class="eyebrow">'.esc(notification_kind_label($n->kind)).($unread?' · NEW':'').'</span><h3>'.esc($n->title).'</h3><div class="prose">'.nl2br(esc($n->body)).'</div><p><a class="btn outline" href="'.esc(notification_url($audience,$n->link)).'">Open related item →</a> <small>'.esc(gmdate('Y-m-d H:i:s',$n->createdAt)).' UTC</small></p><form method="post" class="inline">';echo csrf_field();echo '<input type="hidden" name="action" value="'.esc($markAction).'"><input type="hidden" name="id" value="'.esc($n->id).'"><input type="hidden" name="read" value="'.($unread?'1':'0').'"><button class="btn outline">'.($unread?'Mark read':'Mark unread').'</button></form></article>';
    }
}
/** Per-kind email opt-out controls — portal feed is always delivered. */
function notification_prefs_form(string $audience,int $recipientId,string $action): void {
    $prefs=notification_prefs_for($audience,$recipientId);
    echo '<div class="workspace-card"><h3>Email preferences</h3><p>In-app notifications always appear. Uncheck a kind to stop its email copy.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="'.esc($action).'">';
    foreach(Notification::KINDS as $kind)echo '<label class="check"><input type="checkbox" name="email_'.esc($kind).'" value="1"'.($prefs->emailEnabled($kind)?' checked':'').'>'.esc(notification_kind_label($kind)).' by email</label>';
    echo '<button class="btn outline">Save email preferences</button></form></div>';
}
/** Persist the checkbox set as per-kind email opt-outs; the model enforces recipient-only changes. */
function notification_prefs_save(string $audience,int $recipientId,string $actor): void {
    foreach(Notification::KINDS as $kind)notification_pref_email($audience,$recipientId,$kind,isset($_POST['email_'.$kind]),$actor);
}

function notifications_admin_action(string $action): never {
    if($action==='notifications_setup'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm that you saved a current database and private-storage backup.');
        notification_feed_migrate(posted('owner_password',72));flash('Notification center enabled.');redirect(admin_url('notifications'));
    }
    $actor=require_admin();
    if($action==='notification_mark')notification_mark(Notification::AUDIENCE_STAFF,(int)$actor['id'],posted('id',32),posted('read',1)==='1');
    elseif($action==='notification_mark_all')notification_mark_all(Notification::AUDIENCE_STAFF,(int)$actor['id']);
    elseif($action==='notification_prefs')notification_prefs_save(Notification::AUDIENCE_STAFF,(int)$actor['id'],(string)$actor['id']);
    elseif($action==='notification_announce'){
        staff_require('clients.manage');if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm this service announcement goes to every active client.');
        $id=notification_announce(posted('title',200),posted('body',1000),$actor);flash('Announcement sent to every active client.');redirect(admin_url('notifications'));
    }
    else throw new InvalidArgumentException('Unknown notification action.');
    flash('Saved.');redirect(admin_url('notifications'));
}

function notifications_admin_render(array $user,string $error): never {
    admin_head('Notifications','notifications',$user);if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    echo '<section class="section"><div class="container">';
    if(!notifications_feed_ready()){
        echo '<div class="workspace-card"><h2>Enable the notification center</h2><p>Directed notifications give each staff and client account a private, recipient-scoped feed with per-kind email opt-outs and honestly recorded delivery attempts. Setup adds notification tables only and fails if unrecognized notification records already exist.</p>';
        if(staff_can('staff.manage')){echo '<form method="post">';admin_action('notifications_setup');admin_check('confirm','I saved a current database and private-storage backup.',false);admin_input('owner_password','Current owner password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Enable notifications</button></form>';}
        else echo '<p>An owner must enable notifications.</p>';
        echo '</div></div></section>';admin_foot();exit;
    }
    $feed=notification_feed(Notification::AUDIENCE_STAFF,(int)$user['id']);
    echo '<div class="workspace-card"><h2>Your notifications</h2><form method="post" class="inline">'.csrf_field().'<input type="hidden" name="action" value="notification_mark_all"><button class="btn outline">Mark all read</button></form>';
    notification_feed_list($feed,Notification::AUDIENCE_STAFF);
    echo '</div>';
    notification_prefs_form(Notification::AUDIENCE_STAFF,(int)$user['id'],'notification_prefs');
    if(staff_can('clients.manage')){
        echo '<div class="workspace-card"><h2>Service announcements</h2><p>Send a scoped maintenance or service notice to every active client. Each client receives the notification in their portal feed and an email copy unless they opted out of service events. Announcements are informational — they never promise unverified uptime or service changes.</p>';
        echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="notification_announce">';admin_input('title','Announcement title','','text','required maxlength="200"');echo '<label>Message<textarea name="body" maxlength="1000" required></textarea></label>';admin_check('confirm','Send this announcement to every active client.',false);echo '<button class="btn gold">Send announcement</button></form>';
        foreach(notification_announcements() as $a)echo '<article class="workspace-version read-item"><span class="eyebrow">'.esc($a['actor_name']??'Staff').' · '.esc($a['created_at']).' UTC · '.$a['recipients'].' recipients</span><h3>'.esc($a['title']).'</h3><div class="prose">'.nl2br(esc($a['body'])).'</div></article>';
        echo '</div>';
    }
    echo '</div></section>';admin_foot();exit;
}

/** Portal notification feed for the signed-in client (read-only under support). */
function notifications_portal_render(string $error): never {
    if(!notifications_feed_ready())fail_page('Notifications are not enabled yet.',404);
    if(!$client=studio_client())fail_page('Sign in to view notifications.',403);
    site_head('Your notifications','portal');support_banner();
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">PRIVATE NOTIFICATIONS</span><h1>Your notifications</h1><p><a class="btn outline" href="'.esc(studio_url()).'">← Your workspace</a></p></div></section><section class="section"><div class="container">';
    show_flash();if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    $feed=notification_feed(Notification::AUDIENCE_CLIENT,(int)$client['id']);
    echo '<div class="workspace-card"><h2>Directed to you</h2>';
    if(support_current()){echo '<p class="hint">Read state and preferences are unavailable during support access.</p>';foreach($feed->page() as $n){$n->assertClientVisible();echo '<article class="workspace-version"><span class="eyebrow">'.esc(notification_kind_label($n->kind)).'</span><h3>'.esc($n->title).'</h3><div class="prose">'.nl2br(esc($n->body)).'</div><p><a class="btn outline" href="'.esc(notification_url(Notification::AUDIENCE_CLIENT,$n->link)).'">Open related item →</a></p></article>';}if(!$feed->page())echo '<p>No notifications yet.</p>';}
    else{echo '<form method="post" class="inline">'.csrf_field().'<input type="hidden" name="action" value="notification_client_mark_all"><button class="btn outline">Mark all read</button></form>';notification_feed_list($feed,Notification::AUDIENCE_CLIENT,'notification_client_mark');}
    echo '</div>';
    if(!support_current())notification_prefs_form(Notification::AUDIENCE_CLIENT,(int)$client['id'],'notification_client_prefs');
    echo '</div></section>';site_foot(false);exit;
}
