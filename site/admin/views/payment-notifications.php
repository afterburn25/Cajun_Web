<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function notifications_admin_action(string $action): never {
    check_csrf();$password=posted('owner_password',1000);
    if($action==='payments_notification_setup'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm a current file and database backup.');
        notifications_migrate($password);flash('Sandbox notification inbox enabled. Configure receipt separately.');
    }elseif($action==='payments_notification_configure'){
        notifications_configure(gint($_POST,'revision',0,2147483647),gint($_POST,'gateway_revision',0,2147483647),posted('webhook_id',36),posted('enabled',1)==='1',$password);
        flash('Sandbox receiver settings saved. No webhook was registered or activated at Authorize.net.');
    }elseif($action==='payments_notification_verify'){
        notifications_verify(gint($_POST,'notification_id',1,2147483647),gint($_POST,'gateway_revision',1,2147483647),$password);
        flash('Notification verified against current sandbox transaction details. Real invoices are unchanged.');
    }else throw new InvalidArgumentException('Unknown notification action.');
    redirect(admin_url('payment-notifications'));
}
function notifications_admin_render(array $user,string $error): never {
    staff_require('staff.manage');admin_head('Sandbox notifications','payment-notifications',$user);
    if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    echo '<p class="notice">Sandbox only. Receipt does not confirm payment. Verify queued notifications below to check current transaction details. Production and client checkout remain disabled.</p>';
    if(support_current()){echo '<p>Leave the client support session before managing payment notifications.</p>';admin_foot();exit;}
    if(!notifications_ready()){
        if(attempts_ready()){?><section class="workspace-card"><h2>Enable sandbox inbox</h2><p>Add notification history while retaining all existing records.</p><form method="post"><?php admin_action('payments_notification_setup');admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','I have a current file and database backup.',false);?><button class="btn gold">Enable sandbox inbox</button></form></section><?php }
        else echo '<p>Enable payment preparation and sandbox trials first.</p>';
        admin_foot();exit;
    }
    $cfg=notifications_config();$g=payments_gateway('sandbox');$active=$cfg['enabled']&&$cfg['gateway_revision']===(int)$g['revision']&&$g['validation']==='authenticated';
    ?><section class="workspace-card"><h2>Sandbox receiver</h2><p><strong><?= $active?'Configured for receipt':($cfg['enabled']?'Paused: recheck credentials and save receiver settings':'Disabled') ?></strong></p><p>Endpoint: <code class="notification-endpoint"><?=esc(site_url('webhooks/authorize-net/index.php'))?></code></p><p>Set up this HTTPS endpoint in your Authorize.net sandbox account, then enter its webhook ID here. Use the matching sandbox Signature Key in Authorize.net settings. Saving or clearing credentials pauses receipt until you check authentication and save these settings again. This form does not register a provider webhook.</p><form method="post"><?php admin_action('payments_notification_configure');studio_hidden('revision',$cfg['revision']);studio_hidden('gateway_revision',$g['revision']);admin_input('webhook_id','Sandbox webhook ID',$cfg['webhook_id'],'text','maxlength="36"');admin_check('enabled','Receive signed sandbox notifications for this webhook.',(bool)$cfg['enabled']);admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');?><button class="btn gold">Save receiver settings</button></form></section>
    <section class="workspace-card"><h2>Notification inbox</h2><p>Queued means received with a matching signature. Verified means independently checked against current sandbox reporting. Ignored means an unsupported event; it changes nothing. Verification is an owner action, with no automatic worker. Amounts and statuses from notifications are never used as payment evidence.</p><?php
    $before=is_string($_GET['before']??null)&&ctype_digit($_GET['before'])?(int)$_GET['before']:PHP_INT_MAX;
    $rows=all('SELECT * FROM '.table('payment_notifications').' WHERE id<? ORDER BY id DESC LIMIT 50',[$before]);
    if(!$rows)echo '<p>No notifications on this page.</p>';
    foreach($rows as $n){?><article class="workspace-version"><h3>Notification <?=(int)$n['id']?> · <?=esc($n['status'])?></h3><p class="notification-identifiers"><?=esc($n['event_type'])?><br>Notification ID: <?=esc($n['notification_id'])?><br>Transaction: <?=esc($n['transaction_id']??'Not applicable')?><br>Received: <?=esc($n['received_at'])?> UTC</p><?php if($n['attempt_id']){?><p><a href="<?=esc(admin_url('payment-trials'))?>">Review sandbox attempt <?=(int)$n['attempt_id']?></a></p><?php }if($n['status']==='queued'){?><form method="post"><?php admin_action('payments_notification_verify');studio_hidden('notification_id',$n['id']);studio_hidden('gateway_revision',$g['revision']);admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');?><button class="btn outline">Verify notification <?=(int)$n['id']?></button></form><?php }?></article><?php }
    if(count($rows)===50)echo '<p><a href="'.esc(admin_url('payment-notifications',['before'=>end($rows)['id']])).'">Older notifications</a></p>';
    ?></section><?php admin_foot();exit;
}
