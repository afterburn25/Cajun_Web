<?php
/** Private draft attachments: bounded blobs copied verbatim into immutable submissions. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/mail/replies.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-compose/DraftAttachment.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_files_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_files_migrate.php';
use CajunWebPro\Foundation\MailCompose\DraftAttachment;

function mail_files_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_files_schema_version'])['value']??'')==='1';}
function mail_files_actor(): array {
    $actor=mail_drafts_actor();if(!mail_files_ready())throw new RuntimeException('An owner must prepare attachment storage in Email drafts first.');return $actor;
}
/** Attachment metadata for the editor; no bytes are loaded or displayed. */
function mail_drafts_files(string $draftId): array {
    if(!mail_files_ready())return [];
    return staff_locked(function()use($draftId):array {
        $actor=mail_files_actor();mail_drafts_row($draftId,(int)$actor['id']);
        return all('SELECT id,slot,name,mime,bytes,created_at FROM '.table('mail_draft_files').' WHERE draft_id=? ORDER BY slot,id',[$draftId]);
    });
}
/** The author's own stored bytes for an explicit download. */
function mail_drafts_file(string $draftId,string $fileId): array {
    return staff_locked(function()use($draftId,$fileId):array {
        $actor=mail_files_actor();mail_drafts_row($draftId,(int)$actor['id']);
        $row=one('SELECT * FROM '.table('mail_draft_files').' WHERE id=? AND draft_id=?',[$fileId,$draftId]);
        if(!$row||!hash_equals($row['sha256'],hash('sha256',$row['data'])))throw new RuntimeException('This attachment is unavailable.');
        return $row;
    });
}
/** Internal paired revision bump; copied drafts keep their protected fingerprints. */
function mail_drafts_bump(array $actor,array $base,string $action): void {
    if($base['status']==='source') {
        $loaded=mail_reply_drafts_load($base['id'],$actor,true);
        mail_reply_drafts_write($loaded,$actor,$base['account_id'],(int)$base['account_revision'],$loaded['content'],'draft',$action);
        return;
    }
    query('UPDATE '.table('mail_drafts').' SET revision=revision+1,updated_at=? WHERE id=?',[gmdate('Y-m-d H:i:s'),$base['id']]);
    mail_drafts_snapshot(mail_drafts_row($base['id'],(int)$actor['id']),(int)$actor['id'],$action);
}
/** One editable draft row under lock, requiring draft status and exact revision. */
function mail_drafts_file_base(int $actorId,string $draftId,int $revision): array {
    $row=mail_drafts_row($draftId,$actorId,true);
    if($revision<1||$revision>=2147483647||(int)$row['revision']!==$revision)throw new RuntimeException('The draft changed. Reload before adding files.');
    if($row['status']==='source') {
        $loaded=mail_reply_drafts_load($draftId,mail_files_actor(),true);
        if($loaded['row']['status']!=='draft')throw new RuntimeException('Only a saved draft can hold attachments.');
    }elseif($row['status']!=='draft')throw new RuntimeException('Only a saved draft can hold attachments.');
    return $row;
}
function mail_drafts_attach(string $draftId,int $revision,DraftAttachment $file): void {
    staff_locked(fn()=>staff_transaction(function()use($draftId,$revision,$file):void {
        $actor=mail_files_actor();$base=mail_drafts_file_base((int)$actor['id'],$draftId,$revision);
        mail_drafts_account($base['account_id']);
        $files=all('SELECT id,bytes FROM '.table('mail_draft_files').' WHERE draft_id=? FOR UPDATE',[$draftId]);
        $total=strlen($file->bytes);
        foreach($files as $existing)$total+=(int)$existing['bytes'];
        if(count($files)>=DraftAttachment::MAX_FILES)throw new InvalidArgumentException('A draft supports at most 4 attachments. Remove one first.');
        if($total>DraftAttachment::MAX_TOTAL_BYTES)throw new RuntimeException('Attachments are limited to 512 KiB in total.');
        $slot=(int)query('SELECT COALESCE(MAX(slot),0)+1 FROM '.table('mail_draft_files').' WHERE draft_id=?',[$draftId])->fetchColumn();
        query('INSERT INTO '.table('mail_draft_files').' (id,slot,name,mime,bytes,sha256,data,created_at,draft_id) VALUES (?,?,?,?,?,?,?,?,?)',
            [bin2hex(random_bytes(16)),$slot,$file->name,$file->mime,strlen($file->bytes),$file->sha256,$file->bytes,gmdate('Y-m-d H:i:s'),$draftId]);
        mail_drafts_bump($actor,$base,'attach');
    }));
}
function mail_drafts_detach(string $draftId,int $revision,string $fileId): void {
    staff_locked(fn()=>staff_transaction(function()use($draftId,$revision,$fileId):void {
        $actor=mail_files_actor();$base=mail_drafts_file_base((int)$actor['id'],$draftId,$revision);
        mail_drafts_account($base['account_id']);
        if(!preg_match('/^[a-f0-9]{32}$/D',$fileId)||!one('SELECT id FROM '.table('mail_draft_files').' WHERE id=? AND draft_id=? FOR UPDATE',[$fileId,$draftId]))throw new InvalidArgumentException('This attachment is unavailable.');
        query('DELETE FROM '.table('mail_draft_files').' WHERE id=?',[$fileId]);
        mail_drafts_bump($actor,$base,'detach');
    }));
}
/** Immutable submission-bound copies; internal only, rebuilt against payload descriptors. */
function mail_submission_files(string $submissionId): array {
    $rows=all('SELECT name,mime,bytes,sha256,data FROM '.table('mail_submission_files').' WHERE submission_id=? ORDER BY slot',[$submissionId]);
    return array_map(static function(array $row):DraftAttachment {
        if(!hash_equals($row['sha256'],hash('sha256',$row['data']))||(int)$row['bytes']!==strlen($row['data']))throw new RuntimeException('Saved submission files are damaged. Existing data was preserved.');
        try {return new DraftAttachment($row['name'],$row['mime'],$row['data']);}
        catch(InvalidArgumentException) {throw new RuntimeException('Saved submission files are damaged. Existing data was preserved.');}
    },$rows);
}
