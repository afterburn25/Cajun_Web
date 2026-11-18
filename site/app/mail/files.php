<?php
/** Private draft attachments: bounded blobs copied verbatim into immutable submissions. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/mail/replies.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-compose/DraftAttachment.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_files_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_files_migrate.php';
use CajunWebPro\Foundation\MailCompose\DraftAttachment;

function mail_files_ready():bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_files_schema_version'])['value']??'')==='1';}
function mail_files_actor():array {
    $actor=mail_drafts_actor();if(!mail_files_ready())throw new RuntimeException('An owner must prepare attachment storage in Email drafts first.');return $actor;
}
/** Current author, sender and (for copied drafts) source-message authority. */
function mail_drafts_file_authority(string $draftId,array $actor):array {
    $row=mail_drafts_row($draftId,(int)$actor['id']);
    if($row['status']==='source')mail_reply_drafts_load($draftId,$actor);
    else {
        if(!in_array($row['status'],['draft','discarded'],true))throw new RuntimeException('Saved draft files are unavailable.');
        mail_drafts_account($row['account_id']);
    }
    return $row;
}
/** Internal exact-content loader. Caller supplies the current-authorized draft boundary. */
function mail_draft_files_content(string $draftId):array {
    if(!mail_files_ready())return [];
    $files=[];
    foreach(all('SELECT id,slot,name,mime,bytes,sha256,data,created_at FROM '.table('mail_draft_files').' WHERE draft_id=? ORDER BY slot,id',[$draftId]) as $row) {
        if(!preg_match('/\A[a-f0-9]{32}\z/D',$row['id'])||(int)$row['slot']<1||(int)$row['bytes']!==strlen($row['data'])||!hash_equals($row['sha256'],hash('sha256',$row['data'])))throw new RuntimeException('Saved draft files are damaged. Existing data was preserved.');
        try {$file=new DraftAttachment($row['name'],$row['mime'],$row['data']);}
        catch(InvalidArgumentException){throw new RuntimeException('Saved draft files are damaged. Existing data was preserved.');}
        if($file->descriptor()!==['name'=>$row['name'],'mime'=>$row['mime'],'sha256'=>$row['sha256'],'bytes'=>(int)$row['bytes']])throw new RuntimeException('Saved draft files are damaged. Existing data was preserved.');
        $files[]=['row'=>$row,'file'=>$file];
    }
    try {DraftAttachment::list(array_column($files,'file'));}
    catch(InvalidArgumentException){throw new RuntimeException('Saved draft files are damaged. Existing data was preserved.');}
    return $files;
}
/** Attachment metadata for the editor; raw bytes never cross this display boundary. */
function mail_drafts_files(string $draftId):array {
    if(!mail_files_ready())return [];
    return staff_locked(function()use($draftId):array {
        $actor=mail_files_actor();mail_drafts_file_authority($draftId,$actor);$result=[];
        foreach(mail_draft_files_content($draftId) as $item)$result[]=array_intersect_key($item['row'],array_flip(['id','slot','name','mime','bytes','created_at']));
        return $result;
    });
}
/** The author's own exact stored bytes for one explicit POST download. */
function mail_drafts_file(string $draftId,string $fileId):array {
    if(!preg_match('/\A[a-f0-9]{32}\z/D',$fileId))throw new InvalidArgumentException('This attachment is unavailable.');
    return staff_locked(function()use($draftId,$fileId):array {
        $actor=mail_files_actor();mail_drafts_file_authority($draftId,$actor);
        foreach(mail_draft_files_content($draftId) as $item)if(hash_equals($item['row']['id'],$fileId))return $item['row'];
        throw new InvalidArgumentException('This attachment is unavailable.');
    });
}
/** Internal paired revision bump; copied drafts keep their protected fingerprints. */
function mail_drafts_bump(array $actor,array $base,string $action):void {
    if($base['status']==='source') {
        $loaded=mail_reply_drafts_load($base['id'],$actor,true);
        mail_reply_drafts_write($loaded,$actor,$base['account_id'],(int)$base['account_revision'],$loaded['content'],'draft',$action);
        return;
    }
    query('UPDATE '.table('mail_drafts').' SET revision=revision+1,updated_at=? WHERE id=?',[gmdate('Y-m-d H:i:s'),$base['id']]);
    mail_drafts_snapshot(mail_drafts_row($base['id'],(int)$actor['id']),(int)$actor['id'],$action);
}
/** One editable draft row under lock, requiring draft status and exact revision. */
function mail_drafts_file_base(array $actor,string $draftId,int $revision):array {
    $row=mail_drafts_row($draftId,(int)$actor['id'],true);
    if($revision<1||$revision>=2147483647||(int)$row['revision']!==$revision)throw new RuntimeException('The draft changed. Reload before adding files.');
    if($row['status']==='source') {
        $loaded=mail_reply_drafts_load($draftId,$actor,true);
        if($loaded['row']['status']!=='draft')throw new RuntimeException('Only a saved draft can hold attachments.');
    }elseif($row['status']!=='draft')throw new RuntimeException('Only a saved draft can hold attachments.');
    return $row;
}
function mail_drafts_attach(string $draftId,int $revision,DraftAttachment $file):void {
    staff_locked(fn()=>staff_transaction(function()use($draftId,$revision,$file):void {
        $actor=mail_files_actor();$base=mail_drafts_file_base($actor,$draftId,$revision);mail_drafts_account($base['account_id']);
        $stored=mail_draft_files_content($draftId);$files=array_column($stored,'file');
        try {DraftAttachment::list([...$files,$file]);}
        catch(InvalidArgumentException $e){throw new InvalidArgumentException($e->getMessage());}
        $slot=$stored?max(array_map(static fn(array $item):int=>(int)$item['row']['slot'],$stored))+1:1;$bytes=$file->bytes();
        query('INSERT INTO '.table('mail_draft_files').' (id,slot,name,mime,bytes,sha256,data,created_at,draft_id) VALUES (?,?,?,?,?,?,?,?,?)',[bin2hex(random_bytes(16)),$slot,$file->name,$file->mime,strlen($bytes),$file->sha256,$bytes,gmdate('Y-m-d H:i:s'),$draftId]);
        mail_drafts_bump($actor,$base,'attach');
    }));
}
function mail_drafts_detach(string $draftId,int $revision,string $fileId):void {
    if(!preg_match('/\A[a-f0-9]{32}\z/D',$fileId))throw new InvalidArgumentException('This attachment is unavailable.');
    staff_locked(fn()=>staff_transaction(function()use($draftId,$revision,$fileId):void {
        $actor=mail_files_actor();$base=mail_drafts_file_base($actor,$draftId,$revision);mail_drafts_account($base['account_id']);mail_draft_files_content($draftId);
        if(!one('SELECT id FROM '.table('mail_draft_files').' WHERE id=? AND draft_id=? FOR UPDATE',[$fileId,$draftId]))throw new InvalidArgumentException('This attachment is unavailable.');
        query('DELETE FROM '.table('mail_draft_files').' WHERE id=? AND draft_id=?',[$fileId,$draftId]);mail_drafts_bump($actor,$base,'detach');
    }));
}
/** Immutable submission-bound copies; internal only, rebuilt against payload descriptors. */
function mail_submission_files(string $submissionId):array {
    if(!mail_files_ready())return [];$files=[];
    foreach(all('SELECT id,slot,name,mime,bytes,sha256,data FROM '.table('mail_submission_files').' WHERE submission_id=? ORDER BY slot,id',[$submissionId]) as $row) {
        if(!preg_match('/\A[a-f0-9]{32}\z/D',$row['id'])||(int)$row['slot']<1||(int)$row['bytes']!==strlen($row['data'])||!hash_equals($row['sha256'],hash('sha256',$row['data'])))throw new RuntimeException('Saved submission files are damaged. Existing data was preserved.');
        try {$file=new DraftAttachment($row['name'],$row['mime'],$row['data']);}
        catch(InvalidArgumentException){throw new RuntimeException('Saved submission files are damaged. Existing data was preserved.');}
        if($file->descriptor()!==['name'=>$row['name'],'mime'=>$row['mime'],'sha256'=>$row['sha256'],'bytes'=>(int)$row['bytes']])throw new RuntimeException('Saved submission files are damaged. Existing data was preserved.');
        $files[]=$file;
    }
    try {return DraftAttachment::list($files);}
    catch(InvalidArgumentException){throw new RuntimeException('Saved submission files are damaged. Existing data was preserved.');}
}
