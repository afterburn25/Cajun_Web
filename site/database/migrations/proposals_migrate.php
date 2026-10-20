<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function proposals_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {if(support_current())throw new RuntimeException('Leave support before setup.');$actor=staff_owner($password);if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        foreach(proposals_schema(config()['db']['prefix']) as $name=>$sql){if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){catalog_validate_schema($name,$sql,$name==='proposals'?['request_key'=>['request_key']]:(($k=$name==='proposal_versions'?['proposal_version'=>['proposal_id','version'],'proposal_content'=>['proposal_id','content_hash']]:($name==='proposal_decisions'?['proposal_version_decision'=>['proposal_id','version']]:[]))));}else db()->exec($sql);}
        staff_transaction(function() use($actor): void {if(!proposals_ready()){foreach(['proposals','proposal_versions','proposal_events','proposal_decisions'] as $t)if((int)query('SELECT COUNT(*) FROM '.table($t))->fetchColumn())throw new RuntimeException('Unrecognized proposal records exist. No data was adopted.');save_setting('proposals_schema_version','1');}log_event('proposals.setup','Additive proposal schema verified by '.$actor['email']);});
    });
}
