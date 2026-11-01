<?php
/** Additive, owner-authorized setup; never adopts unknown history. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function notification_feed_migrate(#[\SensitiveParameter] string $unused=''): void {
    staff_locked(function():void {
        $actor=notification_owner();
        if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $version=setting('notification_feed_version');
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unsupported notification schema. Existing history was preserved.');
        $schemas=notification_feed_schema(config()['db']['prefix']);$missing=[];
        $keys=['notification_feed'=>['feed_recipient'=>['audience','recipient_id','id']],'notification_prefs'=>['prefs_scope'=>['audience','recipient_id','kind']],'notification_deliveries'=>['delivery_channel'=>['notification_id','channel']]];
        foreach($schemas as $name=>$sql){
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){$missing[$name]=$sql;continue;}
            catalog_validate_schema($name,$sql,$keys[$name]);
            if($version===''&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized notification records exist. No data was adopted.');
        }
        if($version==='1'&&$missing)throw new RuntimeException('Restore the original notification history. Missing tables cannot be replaced with empty history.');
        foreach($missing as $sql)db()->exec($sql);
        foreach($schemas as $name=>$sql)catalog_validate_schema($name,$sql,$keys[$name]);
        staff_transaction(function()use($actor):void {
            notification_owner();save_setting('notification_feed_version','1');
            log_event('notifications.setup','Notification storage verified by administrator '.$actor['id']);
        });
    });
}
