<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function notifications_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {
        $actor=payments_owner($password);if(!attempts_ready())throw new RuntimeException('Enable sandbox trials first.');
        foreach(notifications_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){
                catalog_validate_schema($name,$sql,['notification_id'=>['notification_id']]);
                foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c)if(in_array($c['Field'],['notification_id','webhook_id','account_hash','body_hash','reference'],true)&&$c['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible notification identifiers.');
            }else db()->exec($sql);
        }
        staff_transaction(function() use($actor): void {
            if(setting('payment_notifications_schema_version')!=='1'&&(int)query('SELECT COUNT(*) FROM '.table('payment_notifications'))->fetchColumn())throw new RuntimeException('Unrecognized notification records exist. No data was adopted.');
            save_setting('payment_notifications_schema_version','1');payments_audit((int)$actor['id'],'notifications.setup','Additive sandbox inbox 1; receiver disabled until configured');
        });
    });
}
