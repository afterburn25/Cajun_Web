<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function pipeline_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {if(support_current())throw new RuntimeException('Leave support before setup.');$actor=staff_owner($password);
        foreach(pipeline_schema(config()['db']['prefix']) as $name=>$sql){if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))catalog_validate_schema($name,$sql,[]);else db()->exec($sql);}
        staff_transaction(function() use($actor): void {if(!pipeline_ready()){if((int)query('SELECT COUNT(*) FROM '.table('lead_stage_history'))->fetchColumn())throw new RuntimeException('Unrecognized pipeline history exists. No data was adopted.');save_setting('pipeline_schema_version','1');}log_event('pipeline.setup','Additive pipeline history schema verified by '.$actor['email']);});
    });
}
