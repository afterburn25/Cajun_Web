<?php
/** Staff-only project notes and the fail-closed public conversation projection. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function project_internal_note_body(mixed $body): string {
    if(!is_string($body)||strlen($body)>5000||preg_match('//u',$body)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$body)===1)throw new InvalidArgumentException('Write a valid staff note.');
    $body=trim($body);
    if(strlen($body)<2)throw new InvalidArgumentException('Write a staff note.');
    return $body;
}

/** The note and its privacy-safe audit row are one transaction or neither exists. */
function project_internal_note_save(int $jobId,mixed $body): int {
    if($jobId<1)throw new InvalidArgumentException('Project not found.');
    $body=project_internal_note_body($body);
    return staff_locked(fn()=>staff_transaction(function()use($jobId,$body):int{
        $actor=staff_require('clients.manage');
        if(support_current())throw new RuntimeException('Leave support mode before writing staff-only notes.');
        if(!one('SELECT id FROM '.table('studio_jobs').' WHERE id=? FOR UPDATE',[$jobId]))throw new InvalidArgumentException('Project not found.');
        $created=gmdate('Y-m-d H:i:s');
        query('INSERT INTO '.table('studio_messages').' (job_id,author_type,author_id,body,created_at) VALUES (?,\'internal\',?,?,?)',[$jobId,(int)$actor['id'],$body,$created]);
        $noteId=(int)db()->lastInsertId();
        if($noteId<1)throw new RuntimeException('The staff note identity was not recorded.');
        query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,\'studio.note\',?,?)',[(int)$actor['id'],'Project '.$jobId.' internal note '.$noteId,$created]);
        return $noteId;
    }));
}

/** Only deliberately public author types can reach a client, contact or support portal. */
function project_public_messages(int $jobId,int $focus,bool $withContacts): array {
    if($jobId<1||$focus<0)throw new InvalidArgumentException('Invalid project conversation.');
    if($withContacts)return all('SELECT m.*,ct.name AS contact_name FROM '.table('studio_messages').' m LEFT JOIN '.table('studio_contacts').' ct ON m.author_type=\'contact\' AND ct.id=m.author_id WHERE m.job_id=? AND m.author_type IN (\'admin\',\'client\',\'contact\') ORDER BY (m.id=?) DESC,m.id DESC LIMIT 100',[$jobId,$focus]);
    return all('SELECT * FROM '.table('studio_messages').' WHERE job_id=? AND author_type IN (\'admin\',\'client\',\'contact\') ORDER BY (id=?) DESC,id DESC LIMIT 100',[$jobId,$focus]);
}

/** Internal rows are available only to current project staff outside support mode. */
function project_internal_notes(int $jobId): array {
    if($jobId<1)throw new InvalidArgumentException('Project not found.');
    staff_require('clients.manage');
    if(support_current())throw new RuntimeException('Staff-only notes are unavailable in support mode.');
    if(!one('SELECT id FROM '.table('studio_jobs').' WHERE id=?',[$jobId]))throw new InvalidArgumentException('Project not found.');
    return all('SELECT m.*,u.name AS staff_name FROM '.table('studio_messages').' m LEFT JOIN '.table('users').' u ON u.id=m.author_id WHERE m.job_id=? AND m.author_type=\'internal\' ORDER BY m.id DESC LIMIT 100',[$jobId]);
}
