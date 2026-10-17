<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function activity_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {
        if(support_current())throw new RuntimeException('Leave support before setup.');$u=staff_owner($password);
        if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $keys=['activity_reads'=>['read_event'=>['actor_type','actor_id','event_key']],'activity_prefs'=>['pref_kind'=>['actor_type','actor_id','kind']]];
        foreach(activity_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){
                catalog_validate_schema($name,$sql,$keys[$name]);
                foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c)if($c['Field']==='event_key'&&$c['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible activity identifiers.');
            }else db()->exec($sql);
        }
        staff_transaction(function() use($u): void {
            if(!activity_ready()){
                if((int)query('SELECT COUNT(*) FROM '.table('activity_reads'))->fetchColumn())throw new RuntimeException('Unrecognized activity records exist. No data was adopted.');
                save_setting('activity_schema_version','1');
            }
            activity_audit(['type'=>'admin','id'=>(int)$u['id']],'setup','Additive read state verified; workspace records preserved');
        });
    });
}
/** Explicit audience: a simultaneous administrator login must never substitute for a client. */
