<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/project_private_notes_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/project_private_notes_validate_schema.php';

function project_private_notes_setup_owner(): array
{
    if(support_current()||array_key_exists('support',$_GET)||(function_exists('support_binding')&&support_binding()!==''))throw new RuntimeException('Leave client support before preparing private project notes.');
    $actor=staff_require('staff.manage');
    if(($actor['role']??'')!=='owner'||!admin_session_matches($actor))throw new RuntimeException('An active owner session is required to prepare private project notes.');
    return $actor;
}

function project_private_notes_migrate(#[\SensitiveParameter] string $password=''): void
{
    if(db()->inTransaction())throw new LogicException('Private project note setup cannot inherit a transaction.');
    staff_locked(function(): void {
        $actor=project_private_notes_setup_owner();
        if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $version=setting('project_private_notes_schema_version');
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unrecognized private project note storage version. Existing data was preserved.');
        $exists=(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'project_private_notes']);
        if($version==='1'&&!$exists)throw new RuntimeException('Established private project note storage is missing. Restore the original database before continuing.');
        if($exists){
            project_private_notes_validate_schema();
            if($version===''&&(int)query('SELECT COUNT(*) FROM '.table('project_private_notes'))->fetchColumn())throw new RuntimeException('Unrecognized private project notes exist. No data was adopted.');
        }else{
            db()->exec(project_private_notes_schema(config()['db']['prefix'])['project_private_notes']);
            project_private_notes_validate_schema();
        }
        staff_transaction(function()use($actor): void {
            $current=project_private_notes_setup_owner();
            if((int)$current['id']!==(int)$actor['id']||!hash_equals((string)$current['auth_version'],(string)$actor['auth_version']))throw new RuntimeException('Owner authority changed during private project note preparation.');
            if(setting('project_private_notes_schema_version')!=='1'){
                if((int)query('SELECT COUNT(*) FROM '.table('project_private_notes'))->fetchColumn())throw new RuntimeException('Unrecognized private project notes exist. No data was adopted.');
                save_setting('project_private_notes_schema_version','1');
            }
            query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[(int)$actor['id'],'project.private_notes.setup','Additive isolated storage verified; existing workspace records preserved',gmdate('Y-m-d H:i:s')]);
        });
    });
}
