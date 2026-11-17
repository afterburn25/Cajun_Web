<?php
/** Isolated immutable private project notes for directly authenticated staff. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Projects\ProjectPrivateNote;

require_once CWP_CODE_ROOT.'/includes/foundation/projects/ProjectPrivateNote.php';
require_once CWP_CODE_ROOT.'/database/schema/project_private_notes_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/project_private_notes_migrate.php';

function project_private_notes_ready(): bool
{
    return setting('project_private_notes_schema_version')==='1';
}

function project_private_notes_actor(): array
{
    if(support_current()||array_key_exists('support',$_GET)||(function_exists('support_binding')&&support_binding()!==''))throw new RuntimeException('Private project notes are unavailable during client support.');
    if(!studio_ready()||!project_private_notes_ready())throw new RuntimeException('Prepare private project note storage under Version & database first.');
    $actor=staff_require('clients.manage');
    if(!admin_session_matches($actor))throw new RuntimeException('Your administrator session changed. Sign in again.');
    return $actor;
}

function project_private_notes_project(int $projectId,bool $lock=false): array
{
    if($projectId<1||$projectId>2147483647)throw new InvalidArgumentException('Project not found.');
    $project=one('SELECT id,client_id,title FROM '.table('studio_jobs').' WHERE id=?'.($lock?' FOR UPDATE':''),[$projectId]);
    if(!$project)throw new InvalidArgumentException('Project not found.');
    return $project;
}

function project_private_notes_record(array $row): ProjectPrivateNote
{
    foreach(['id','project_id','staff_id','created_epoch'] as $field){
        $raw=(string)($row[$field]??'');$value=(int)$raw;
        if(!preg_match('/\A(?:0|[1-9][0-9]*)\z/',$raw)||(string)$value!==$raw)throw new RuntimeException('Private project note storage needs recovery.');
        $row[$field]=$value;
    }
    if(!is_string($row['body']??null))throw new RuntimeException('Private project note storage needs recovery.');
    try{
        $canonical=ProjectPrivateNote::canonicalBody($row['body']);
        if(!hash_equals($canonical,$row['body']))throw new RuntimeException('Private project note storage needs recovery.');
        return ProjectPrivateNote::create($row['id'],$row['project_id'],$row['staff_id'],$row['created_epoch'],$row['body']);
    }catch(InvalidArgumentException|JsonException $error){throw new RuntimeException('Private project note storage needs recovery.',0,$error);}
}

/** @return list<array{note:ProjectPrivateNote,staff_name:string}> */
function project_private_notes_list(int $projectId): array
{
    return staff_locked(function()use($projectId): array {
        project_private_notes_actor();project_private_notes_project($projectId);
        $rows=all('SELECT n.*,u.name AS staff_name FROM '.table('project_private_notes').' n JOIN '.table('users').' u ON u.id=n.staff_id WHERE n.project_id=? ORDER BY n.id DESC LIMIT '.ProjectPrivateNote::MAX_PAGE,[$projectId]);
        $notes=[];$models=[];
        foreach($rows as $row){$note=project_private_notes_record($row);$models[]=$note;$notes[]=['note'=>$note,'staff_name'=>(string)$row['staff_name']];}
        ProjectPrivateNote::verifyStaffPage($models,$projectId);
        project_private_notes_actor();
        return $notes;
    });
}

function project_private_notes_create(int $projectId,string $body): ProjectPrivateNote
{
    return staff_locked(function()use($projectId,$body): ProjectPrivateNote {
        project_private_notes_actor();$body=ProjectPrivateNote::canonicalBody($body);
        return staff_transaction(function()use($projectId,$body): ProjectPrivateNote {
            $actor=project_private_notes_actor();project_private_notes_project($projectId,true);$created=time();
            query('INSERT INTO '.table('project_private_notes').' (project_id,staff_id,created_epoch,body) VALUES (?,?,?,?)',[$projectId,(int)$actor['id'],$created,$body]);
            $note=ProjectPrivateNote::create((int)db()->lastInsertId(),$projectId,(int)$actor['id'],$created,$body);
            $audit=$note->auditReference();
            query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[(int)$actor['id'],'project.private_note.create',json_encode($audit,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),gmdate('Y-m-d H:i:s')]);
            return $note;
        });
    });
}
