<?php
/** Isolated MariaDB per-recipient notification feed; no live mail, customers or providers. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require __DIR__.'/../site/includes/layout/notifications.php';
use CajunWebPro\Foundation\Notifications\{Notification,NotificationFeed};
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='notifci_';
$private=__DIR__.'/../test-runtime/notifications-native-private';if(!is_dir($private))mkdir($private,0700);$GLOBALS['cwp_config']['storage']=realpath($private);
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$password='Synthetic notification owner passphrase';$checks=[];
function n_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function n_deny(string $name,callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;n_ok($name,true);return;}n_ok($name,false);}
function n_actor(int $id=1): void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
try{
    foreach(schema_statements('notifci_') as $sql)db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);save_setting('studio_schema_version','2');save_setting('staff_schema_version','1');
    $now=gmdate('Y-m-d H:i:s');for($id=1;$id<=3;$id++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Notify staff '.$id,'notify'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),$now]);staff_seed_owner(1);
    foreach([2=>['clients.manage'],3=>['website.manage']] as $id=>$grants)query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (?,'administrator',1,?)",[$id,json_encode($grants)]);n_actor();
    query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Notify client','notify-client@example.invalid',str_repeat('c',32),$now]);
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (1,?,?,?,?,?)',['Notify project','Summary','Milestones',$now,$now]);
    notification_emit('staff',1,'service-event','inquiry:1','Pre-setup emit','Never stored');n_ok('Emit before setup is a safe no-op',all('SELECT * FROM '.table('notification_feed'))===[]);
    n_deny('Setup requires owner reauthentication',fn()=>notification_feed_migrate('wrong'));
    n_deny('Setup requires a staff actor',function() use($password){$_SESSION=[];try{notification_feed_migrate($password);}finally{n_actor();}});
    notification_feed_migrate($password);n_ok('Setup creates additive feed schema',notifications_feed_ready());notification_feed_migrate($password);n_ok('Repeated setup is safe',notifications_feed_ready());
    n_deny('Unknown audience rejected',fn()=>notification_emit('public',1,'service-event','inquiry:1','x','y'));
    n_deny('Unknown kind rejected',fn()=>notification_emit('staff',1,'lottery','inquiry:1','x','y'));
    n_deny('Untyped link rejected',fn()=>notification_emit('staff',1,'service-event','not-a-ref','x','y'));
    n_ok('Website-manage staff resolves as lead recipients',notification_staff_ids('website.manage')===[1,3]&&notification_staff_ids('clients.manage')===[1,2]);
    notification_emit('staff',1,'service-event','inquiry:7','New project inquiry · CWP-TEST','Synthetic inquiry');
    $feed=notification_feed('staff',1);n_ok('Recipient feed contains the notification',$feed->unreadCount()===1);
    n_ok('Portal delivery recorded as confirmed',one("SELECT state FROM ".table('notification_deliveries')." WHERE channel='portal'")['state']==='sent');
    n_ok('Email skipped while unconfigured',one("SELECT id FROM ".table('notification_deliveries')." WHERE channel='email'")===null);
    $n1=$feed->page()[0]->id;
    notification_emit('staff',2,'approval-request','job:1','Client decision','approved');
    n_ok('Other recipients never see foreign notifications',notification_feed('staff',2)->unreadCount()===1&&notification_feed('staff',3)->unreadCount()===0);
    n_deny('Foreign feed rows are rejected at construction',fn()=>new NotificationFeed('2','staff',[new Notification($n1,'staff','1','service-event','x','y','inquiry:7',time())]));
    n_deny('Recipient cannot mark another staff feed item',fn()=>notification_mark('staff',2,$n1,true));
    notification_mark('staff',1,$n1,true);n_ok('Mark read persists',notification_feed('staff',1)->unreadCount()===0&&one('SELECT read_at FROM '.table('notification_feed').' WHERE id=?',[$n1])['read_at']!==null);
    notification_mark('staff',1,$n1,false);n_ok('Mark unread restores the badge',notification_feed('staff',1)->unreadCount()===1);
    notification_mark_all('staff',1);n_ok('Mark all read clears the badge',notification_feed('staff',1)->unreadCount()===0);
    n_deny('Preferences reject other actors',fn()=>notification_pref_email('staff',1,'billing',false,'2'));
    n_deny('Unknown preference kinds rejected',fn()=>notification_pref_email('staff',1,'lottery',false,'1'));
    notification_pref_email('staff',1,'service-event',false,'1');
    n_ok('Per-kind email opt-out persists',!notification_prefs_for('staff',1)->emailEnabled('service-event')&&notification_prefs_for('staff',1)->emailEnabled('billing'));
    save_setting('email_mode','smtp');save_setting('smtp_host','127.0.0.1');save_setting('mail_from','studio@example.invalid');
    notification_emit('staff',1,'service-event','inquiry:8','Opted out kind','No email attempt expected');
    n_ok('Opt-out suppresses the email attempt',one('SELECT d.state FROM '.table('notification_deliveries').' d JOIN '.table('notification_feed').' f ON f.id=d.notification_id WHERE d.channel=? AND f.ref=?',['email','inquiry:8'])===null);
    notification_emit('staff',1,'billing','invoice:5','Invoice recorded as paid','Synthetic invoice');
    $d=one('SELECT * FROM '.table('notification_deliveries').' d JOIN '.table('notification_feed').' f ON f.id=d.notification_id WHERE d.channel=? AND f.ref=?',['email','invoice:5']);
    n_ok('Failed email attempt is honestly recorded', $d!==null && $d['state']==='failed' && $d['last_error']!=='' && (int)$d['attempts']===1);
    n_ok('Client feed is audience-isolated',notification_feed('client',1)->unreadCount()===0);
    notification_emit('client',1,'download-ready','release:'.str_repeat('a',32),'Website download published','v1.0.0');
    n_ok('Client notification lands on the owning feed',notification_feed('client',1)->unreadCount()===1);
    $GLOBALS['cwp_support']=['session'=>['id'=>'synthetic'],'actor'=>['id'=>1]];
    n_deny('Support sessions cannot run feed setup',fn()=>notification_feed_migrate($password));unset($GLOBALS['cwp_support']);
    $kept=all('SELECT * FROM '.table('notification_feed'));notification_feed_migrate($password);n_ok('Re-running setup preserves every notification',all('SELECT * FROM '.table('notification_feed'))===$kept);
}catch(Throwable $e){echo 'FAILED: '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine()."\n";exit(1);}
echo 'notification feed native: '.count($checks).' checks, 0 failures'."\n";
