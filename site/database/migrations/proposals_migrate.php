<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/proposals_validate_schema.php';
function proposals_migrate(#[\SensitiveParameter] string $password=''): void {
    staff_locked(function() use($password): void {
        if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Leave support before setup.');
        $actor=admin_user();
        if(!staff_ready()||!$actor||($actor['role']??'')!=='owner'||!staff_can('staff.manage',$actor))throw new RuntimeException('An active owner session is required to prepare proposals.');
        if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $version=setting('proposals_schema_version');
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unsupported proposal schema version. Existing data was preserved.');
        $schemas=proposals_schema(config()['db']['prefix']);$missing=[];
        foreach($schemas as $name=>$sql){
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){$missing[$name]=$sql;continue;}
            proposals_validate_schema($name,$sql);
            if($version===''&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized proposal records exist. No data was adopted.');
        }
        if($version==='1'&&$missing)throw new RuntimeException('Restore the original proposal database. Missing history cannot be recreated as empty tables.');
        foreach($missing as $sql)db()->exec($sql);
        foreach($schemas as $name=>$sql)proposals_validate_schema($name,$sql);
        staff_transaction(function() use($actor): void {
            if(!proposals_ready())save_setting('proposals_schema_version','1');
            log_event('proposals.setup','Additive proposal schema verified by '.$actor['email']);
        });
    });
}
