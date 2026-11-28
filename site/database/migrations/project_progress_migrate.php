<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/project_progress_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/project_progress_validate_schema.php';

function project_progress_setup_owner(): array
{
    if(support_current()||array_key_exists('support',$_GET)||(function_exists('support_binding')&&support_binding()!==''))throw new RuntimeException('Leave client support before preparing project progress.');
    $actor=staff_require('staff.manage');
    if(($actor['role']??'')!=='owner'||!admin_session_matches($actor))throw new RuntimeException('An active owner session is required to prepare project progress.');
    return $actor;
}
function project_progress_migrate(): void
{
    if(db()->inTransaction())throw new LogicException('Project progress setup cannot inherit a transaction.');
    staff_locked(function(): void {
        $actor=project_progress_setup_owner();
        if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $version=setting('project_progress_schema_version');
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unrecognized project progress storage version. Existing data was preserved.');
        $schema=project_progress_schema(config()['db']['prefix']);$existing=[];
        foreach(array_keys($schema) as $name)$existing[$name]=(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
        if($version==='1'&&in_array(false,$existing,true))throw new RuntimeException('Established project progress storage is missing. Restore the original database before continuing.');
        foreach($schema as $name=>$sql){
            if($existing[$name]){
                project_progress_validate_schema($name);
                if($version===''&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized project progress exists. No data was adopted.');
            }
        }
        $workspaceIndex=[];
        foreach(all('SHOW INDEX FROM '.table('studio_jobs')) as $item)if((string)$item['Key_name']==='project_progress_owner')$workspaceIndex[]=$item;
        if(!$workspaceIndex)db()->exec('ALTER TABLE '.table('studio_jobs').' ADD UNIQUE KEY project_progress_owner(id,client_id)');
        project_progress_validate_workspace_identity();
        foreach($schema as $name=>$sql){
            if(!$existing[$name]){
                db()->exec($sql);
                project_progress_validate_schema($name);
            }
        }
        staff_transaction(function()use($actor,$version,$schema): void {
            $current=project_progress_setup_owner();
            if((int)$current['id']!==(int)$actor['id']||!hash_equals((string)$current['auth_version'],(string)$actor['auth_version']))throw new RuntimeException('Owner authority changed during project progress preparation.');
            if(setting('project_progress_schema_version')!==$version)throw new RuntimeException('Project progress storage changed during preparation.');
            project_progress_lock_and_validate($schema);
            if($version===''){
                foreach(array_keys($schema) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized project progress exists. No data was adopted.');
                save_setting('project_progress_schema_version','1');
            }
            query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[(int)$actor['id'],'project.progress.setup','Additive project progress storage verified; existing workspace records preserved',gmdate('Y-m-d H:i:s')]);
        });
    });
}
function project_progress_lock_and_validate(array $schema): void
{
    if(!db()->inTransaction())throw new LogicException('Project progress activation validation requires a transaction.');
    query('SELECT id FROM '.table('studio_jobs').' ORDER BY id LIMIT 1 FOR UPDATE')->fetchColumn();
    foreach(array_keys($schema) as $name){
        query('SELECT id FROM '.table($name).' ORDER BY id LIMIT 1 FOR UPDATE')->fetchColumn();
        project_progress_validate_schema($name);
    }
    project_progress_validate_workspace_identity();
}
