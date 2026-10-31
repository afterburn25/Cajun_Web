<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function support_migrate(): void {
    staff_locked(function(): void {
        $actor=support_owner_required();
        if(!studio_ready())throw new RuntimeException('Enable the client workspace in Version & database first.');
        $missing=[];foreach(support_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))support_schema_check($name,$sql);
            else $missing[$name]=$sql;
        }
        foreach($missing as $sql)db()->exec($sql);
        staff_transaction(function() use($actor): void {
            if(setting('support_schema_version')!=='1')foreach(array_keys(support_schema(config()['db']['prefix'])) as $name){
                if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0)throw new RuntimeException('Unrecognized support records exist. No data was adopted.');
            }
            save_setting('support_schema_version','1');save_setting('support_policy_confirmed','1');
            support_audit(['actor_id'=>$actor['id'],'client_id'=>0],'policy.confirmed');
        });
    });
}
