<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function notification_feed_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {if(support_current())throw new RuntimeException('Leave support before setup.');$actor=staff_owner($password);
        foreach(notification_feed_schema(config()['db']['prefix']) as $name=>$sql){if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){catalog_validate_schema($name,$sql,match($name){'notification_feed'=>['feed_recipient'=>['audience','recipient_id','id']],'notification_prefs'=>['prefs_scope'=>['audience','recipient_id','kind']],'notification_deliveries'=>['delivery_channel'=>['notification_id','channel']],default=>[]});}else db()->exec($sql);}
        staff_transaction(function() use($actor): void {if(!notifications_feed_ready()){foreach(['notification_feed','notification_prefs','notification_deliveries','notification_announcements'] as $t)if((int)query('SELECT COUNT(*) FROM '.table($t))->fetchColumn())throw new RuntimeException('Unrecognized notification records exist. No data was adopted.');save_setting('notification_feed_version','1');}log_event('notifications.setup','Additive notification feed schema verified by '.$actor['email']);});
    });
}
