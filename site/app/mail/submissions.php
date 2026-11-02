<?php
/** Private submission storage. These functions do not resolve, decrypt or send. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/mail/drafts.php';
require_once CWP_CODE_ROOT.'/app/mail/replies.php';
require_once CWP_CODE_ROOT.'/app/mail/files.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-submission/TransportOutcome.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-transport/EndpointPolicy.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-transport/ProbeFailure.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_submissions_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_submissions_migrate.php';
use CajunWebPro\Foundation\MailCompose\{DraftAttachment,DraftContent,MessageThread};
use CajunWebPro\Foundation\MailSubmission\{SubmissionSnapshot,PreparedMime,TransportOutcome};
use CajunWebPro\Foundation\MailTransport\{EndpointPolicy,ProbeFailure};

function mail_submissions_ready():bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_submissions_schema_version'])['value']??'')==='1';}
function mail_submissions_actor():array {
    $actor=mail_drafts_actor();if(!mail_submissions_ready())throw new RuntimeException('An owner must prepare email submission storage first.');return $actor;
}
function mail_submissions_audit(int $actor,string $action,string $detail):void {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'mail_submissions.'.$action,$detail,gmdate('Y-m-d H:i:s')]);
}
/** Internal only: routes must use author/current-mailbox checked getters. */
function mail_submissions_row(string $id,?int $actor=null):array {
    mail_drafts_id($id);$row=one('SELECT * FROM '.table('mail_submissions').' WHERE id=?'.($actor===null?'':' AND owner_id=?'),$actor===null?[$id]:[$id,$actor]);
    if(!$row)throw new InvalidArgumentException('Submission unavailable.');return $row;
}
/** Verify canonical private fields and exact saved MIME before trusting a stored intent. */
function mail_submissions_message(array $row):PreparedMime {
    try {
        $p=json_decode($row['payload_json'],true,16,JSON_THROW_ON_ERROR);
        $base=['format','id','author_id','draft_id','draft_revision','account_id','account_revision','from','message_id','prepared_epoch','to','cc','bcc','subject','body'];
        $format=is_array($p)?($p['format']??null):null;$thread=null;$origin=null;$attachments=[];
        if(!is_int($format)||$format<1||$format>8)throw new InvalidArgumentException('Invalid preparation format.');
        // Formats are 1 + thread(1) + attachments(2) + formatted alternative(4).
        $html=$format>=5;$hasThread=$format%2===0;$hasAttach=in_array($format,[3,4,7,8],true);$keys=$base;
        if($html)$keys[]='body_html';
        if(!$hasThread) {
            if($hasAttach)$keys[]='attachments';
        }else {
            $keys[]='thread';$keys[]='origin_fingerprint';
            if($hasAttach)$keys[]='attachments';
            if(!is_string($p['origin_fingerprint']??null))throw new InvalidArgumentException('Invalid copied draft binding.');
            $thread=MessageThread::fromStored(is_array($p['thread']??null)?$p['thread']:[]);
            $origin=$p['origin_fingerprint'];
        }
        if(array_keys($p)!==$keys)throw new InvalidArgumentException('Invalid preparation format.');
        if($hasAttach) {
            if(!is_array($p['attachments'])||!array_is_list($p['attachments']))throw new InvalidArgumentException('Invalid attachment descriptors.');
            $stored=mail_submission_files($row['id']);
            if(count($stored)!==count($p['attachments']))throw new InvalidArgumentException('Invalid attachment set.');
            foreach($stored as $i=>$file)if($p['attachments'][$i]!==$file->descriptor())throw new InvalidArgumentException('Invalid attachment set.');
            $attachments=$stored;
        }
        foreach(['from','subject','body'] as $field)if(!is_string($p[$field]))throw new InvalidArgumentException('Invalid private text.');
        if($html&&!is_string($p['body_html']))throw new InvalidArgumentException('Invalid private text.');
        foreach(['to','cc','bcc'] as $field)if(!is_array($p[$field]))throw new InvalidArgumentException('Invalid private recipients.');
        $snapshot=new SubmissionSnapshot($row['id'],(int)$row['owner_id'],$row['draft_id'],(int)$row['draft_revision'],$row['account_id'],(int)$row['account_revision'],$p['from'],new DraftContent($p['to'],$p['cc'],$p['bcc'],$p['subject'],$p['body'],$html?$p['body_html']:null),(int)$row['prepared_epoch'],$thread,$origin,$attachments);
        if($p!==$snapshot->privatePayload() || $row['payload_json']!==json_encode($snapshot->privatePayload(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) || !hash_equals($snapshot->fingerprint,$row['snapshot_fingerprint']))throw new InvalidArgumentException('Invalid private fingerprint.');
        $mime=PreparedMime::fromSnapshot($snapshot);
        if(!hash_equals($mime->sha256,$row['mime_sha256']) || !hash_equals($mime->bytes,$row['mime_bytes']))throw new InvalidArgumentException('Invalid saved MIME.');
        return $mime;
    }catch(Throwable $e){if($e instanceof PDOException)throw $e;throw new RuntimeException('Saved submission content is damaged or unsupported. Existing data was preserved.');}
}
/** Stored copied content stays private only while current origin Read authority holds. */
function mail_submissions_origin(array $row,PreparedMime $mime,array $actor):void {
    if($mime->snapshot->thread===null)return;
    if(!mail_replies_ready())throw new RuntimeException('Copied-message storage is unavailable. Existing data was preserved.');
    $loaded=mail_reply_drafts_for_submission($row['draft_id'],$actor);
    if(!hash_equals($loaded['origin_fingerprint'],(string)$mime->snapshot->originFingerprint)||$loaded['thread']->toArray()!==$mime->snapshot->thread->toArray())throw new RuntimeException('The copied draft origin changed. Existing data was preserved.');
}
function mail_submissions_get(string $id):array {
    return staff_locked(function()use($id):array {
        $actor=mail_submissions_actor();$row=mail_submissions_row($id,(int)$actor['id']);mail_drafts_account($row['account_id']);
        $message=mail_submissions_message($row);mail_submissions_origin($row,$message,$actor);
        return ['id'=>$row['id'],'draft_id'=>$row['draft_id'],'draft_revision'=>(int)$row['draft_revision'],'account_id'=>$row['account_id'],'account_revision'=>(int)$row['account_revision'],'created_at'=>$row['created_at'],
            'content'=>$message->snapshot->privatePayload(),'dispatch'=>one('SELECT started_at FROM '.table('mail_submission_dispatches').' WHERE submission_id=?',[$id]),
            'result'=>one('SELECT scope,status,phase,reason,smtp_code,finished_at FROM '.table('mail_submission_results').' WHERE submission_id=?',[$id])];
    });
}
/** A sent/uncertain message must not be resent merely by editing its draft. */
function mail_submissions_prior_attempt(string $draft,string $except=''):void {
    if(one('SELECT s.id FROM '.table('mail_submissions').' s LEFT JOIN '.table('mail_submission_dispatches').' d ON d.submission_id=s.id LEFT JOIN '.table('mail_submission_results')." r ON r.submission_id=s.id WHERE s.draft_id=? AND s.id<>? AND ((d.id IS NOT NULL AND r.id IS NULL) OR r.status IN ('accepted','unknown')) LIMIT 1",[$draft,$except]))throw new RuntimeException('This draft has an accepted or unresolved submission. Do not resend it.');
}
function mail_submissions_prepare(string $id,string $draft,int $draftRevision,int $accountRevision):string {
    return mail_submissions_prepare_intent($id,$draft,$draftRevision,$accountRevision)['id'];
}
/** Internal creation capability: only this invocation's newly committed intent may continue. */
function mail_submissions_prepare_intent(string $id,string $draft,int $draftRevision,int $accountRevision):array {
    return staff_locked(fn()=>staff_transaction(function()use($id,$draft,$draftRevision,$accountRevision):array {
        $actor=mail_submissions_actor();mail_drafts_id($id);mail_drafts_id($draft);
        if($draftRevision<1 || $accountRevision<1)throw new InvalidArgumentException('Review a saved draft and sending account.');
        $existing=one('SELECT * FROM '.table('mail_submissions').' WHERE id=?',[$id]);
        if($existing) {
            if((int)$existing['owner_id']!==(int)$actor['id'] || $existing['draft_id']!==$draft || (int)$existing['draft_revision']!==$draftRevision || (int)$existing['account_revision']!==$accountRevision)throw new InvalidArgumentException('Submission identity was already used.');
            mail_drafts_account($existing['account_id']);mail_submissions_origin($existing,mail_submissions_message($existing),$actor);return ['id'=>$id,'created'=>false];
        }
        $saved=mail_drafts_row($draft,(int)$actor['id'],true);$account=mail_drafts_account($saved['account_id'],$accountRevision);
        $thread=null;$originFingerprint=null;$content=null;
        if($saved['status']==='source') {
            // A copied-message draft keeps its content and immutable origin in
            // protected storage; current source Read authority is revalidated here.
            $copied=mail_reply_drafts_for_submission($draft,$actor,true);
            if($copied['row']['status']!=='draft' || (int)$copied['base']['revision']!==$draftRevision || (int)$copied['base']['account_revision']!==$accountRevision || $copied['base']['account_id']!==$account['id'])throw new RuntimeException('The draft or sender changed. Reload and review before submitting.');
            $content=$copied['content'];$thread=$copied['thread'];$originFingerprint=$copied['origin_fingerprint'];
        }else {
            if($saved['status']!=='draft' || (int)$saved['revision']!==$draftRevision || (int)$saved['account_revision']!==$accountRevision)throw new RuntimeException('The draft or sender changed. Reload and review before submitting.');
            $content=mail_drafts_content($saved);
        }
        $existing=one('SELECT * FROM '.table('mail_submissions').' WHERE draft_id=? AND draft_revision=?',[$draft,$draftRevision]);
        if($existing) {
            if((int)$existing['owner_id']!==(int)$actor['id'] || $existing['account_id']!==$account['id'] || (int)$existing['account_revision']!==$accountRevision)throw new RuntimeException('Saved submission identity is inconsistent. Existing data was preserved.');
            mail_submissions_origin($existing,mail_submissions_message($existing),$actor);return ['id'=>$existing['id'],'created'=>false];
        }
        mail_submissions_prior_attempt($draft);
        $profile=mail_accounts_load($account['id']);EndpointPolicy::host($profile['smtp_host']);EndpointPolicy::port('smtp',(int)$profile['smtp_port'],$profile['smtp_encryption']);
        $now=time();
        if((int)query('SELECT COUNT(*) FROM '.table('mail_submissions').' WHERE owner_id=? AND prepared_epoch>?',[$actor['id'],$now-3600])->fetchColumn()>=100)throw new RuntimeException('Submission preparation limit reached. Try again later.');
        // Saved attachment bytes are copied verbatim into immutable submission storage.
        $attachments=[];
        if(mail_files_ready()) {
            foreach(all('SELECT * FROM '.table('mail_draft_files').' WHERE draft_id=? ORDER BY slot',[$draft]) as $stored) {
                if(!hash_equals($stored['sha256'],hash('sha256',$stored['data']))||(int)$stored['bytes']!==strlen($stored['data']))throw new RuntimeException('Saved draft attachments are damaged. Existing data was preserved.');
                $attachments[]=new DraftAttachment($stored['name'],$stored['mime'],$stored['data']);
            }
        }
        $mime=PreparedMime::fromSnapshot(new SubmissionSnapshot($id,(int)$actor['id'],$draft,$draftRevision,$account['id'],$accountRevision,$account['address'],$content,$now,$thread,$originFingerprint,$attachments));
        query('INSERT INTO '.table('mail_submissions').' (id,attempt_id,owner_id,draft_id,draft_revision,account_id,account_revision,prepared_epoch,payload_json,snapshot_fingerprint,mime_bytes,mime_sha256,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$id,bin2hex(random_bytes(16)),$actor['id'],$draft,$draftRevision,$account['id'],$accountRevision,$now,json_encode($mime->snapshot->privatePayload(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$mime->snapshot->fingerprint,$mime->bytes,$mime->sha256,gmdate('Y-m-d H:i:s',$now)]);
        $slot=0;
        foreach($attachments as $file)query('INSERT INTO '.table('mail_submission_files').' (id,slot,name,mime,bytes,sha256,data,created_at,submission_id) VALUES (?,?,?,?,?,?,?,?,?)',
            [bin2hex(random_bytes(16)),++$slot,$file->name,$file->mime,strlen($file->bytes),$file->sha256,$file->bytes,gmdate('Y-m-d H:i:s',$now),$id]);
        mail_submissions_audit((int)$actor['id'],'prepared','Submission '.$id.' draft '.$draft.' revision '.$draftRevision);
        return ['id'=>$id,'created'=>true,'row'=>mail_submissions_row($id),'profile'=>$profile];
    }));
}
/** Current authority and reviewed fields; caller holds the staff mutex, no network here. */
function mail_submissions_authorize(array $row,PreparedMime $mime):array {
    $actor=mail_submissions_actor();
    if((int)$actor['id']!==(int)$row['owner_id'])throw new RuntimeException('Current submission author required.');
    $saved=mail_drafts_row($row['draft_id'],(int)$actor['id'],db()->inTransaction());$account=mail_drafts_account($row['account_id'],(int)$row['account_revision']);
    if((new DraftContent([$account['address']],[],[],'',''))->to[0]!==$mime->snapshot->from)throw new RuntimeException('The reviewed sender changed. No message was submitted.');
    $copied=$saved['status']==='source';
    if($copied!==($mime->snapshot->thread!==null))throw new RuntimeException('The reviewed draft or sender changed. No message was submitted.');
    if($copied) {
        // Origin Read authority, integrity fingerprints and revision are all
        // rechecked here; revoked access refuses dispatch of copied content.
        $loaded=mail_reply_drafts_for_submission($row['draft_id'],$actor,db()->inTransaction());
        if($loaded['row']['status']!=='draft' || (int)$loaded['base']['revision']!==(int)$row['draft_revision'] || $loaded['base']['account_id']!==$row['account_id'] || (int)$loaded['base']['account_revision']!==(int)$row['account_revision'])throw new RuntimeException('The reviewed draft or sender changed. No message was submitted.');
        if(!hash_equals($loaded['origin_fingerprint'],(string)$mime->snapshot->originFingerprint) || $loaded['thread']->toArray()!==$mime->snapshot->thread->toArray())throw new RuntimeException('The reviewed draft origin changed. No message was submitted.');
        $content=$loaded['content'];
    }else {
        if($saved['status']!=='draft' || (int)$saved['revision']!==(int)$row['draft_revision'] || $saved['account_id']!==$row['account_id'] || (int)$saved['account_revision']!==(int)$row['account_revision'])throw new RuntimeException('The reviewed draft or sender changed. No message was submitted.');
        $content=mail_drafts_content($saved);
    }
    // A changed saved row at an unchanged revision is damaged, not a new review.
    $p=$mime->snapshot->content;
    if([$content->to,$content->cc,$content->bcc,$content->subject,$content->body,$content->html]!==[$p->to,$p->cc,$p->bcc,$p->subject,$p->body,$p->html])throw new RuntimeException('The reviewed draft content changed. No message was submitted.');
    // Attachment changes always bump the saved revision; a same-revision
    // difference means damaged storage rather than a reviewed edit.
    if($mime->snapshot->attachments!==[]&&!mail_files_ready())throw new RuntimeException('Saved submission files are unsupported. Existing data was preserved.');
    if(mail_files_ready()) {
        $current=array_map(static fn(array $f):array=>['name'=>$f['name'],'mime'=>$f['mime'],'sha256'=>$f['sha256'],'bytes'=>(int)$f['bytes']],all('SELECT name,mime,bytes,sha256 FROM '.table('mail_draft_files').' WHERE draft_id=? ORDER BY slot',[$row['draft_id']]));
        if($current!==array_map(static fn(DraftAttachment $a):array=>$a->descriptor(),$mime->snapshot->attachments))throw new RuntimeException('The reviewed draft files changed. No message was submitted.');
    }
    return mail_accounts_load($row['account_id']);
}
/** Internal dispatcher claim, atomically audited. A replay never grants another attempt. */
function mail_submissions_claim(string $id):?array {
    return staff_locked(fn()=>staff_transaction(function()use($id):?array {
        $actor=mail_submissions_actor();$row=mail_submissions_row($id,(int)$actor['id']);mail_drafts_account($row['account_id']);
        $mime=mail_submissions_message($row);
        if(one('SELECT id FROM '.table('mail_submission_dispatches').' WHERE submission_id=?',[$id]) || one('SELECT id FROM '.table('mail_submission_results').' WHERE submission_id=?',[$id]))return null;
        mail_submissions_authorize($row,$mime);
        mail_submissions_prior_attempt($row['draft_id'],$id);$now=time();
        if(one('SELECT d.id FROM '.table('mail_submission_dispatches').' d JOIN '.table('mail_submissions').' s ON s.id=d.submission_id WHERE s.account_id=? AND d.started_epoch>? LIMIT 1',[$row['account_id'],$now-30]))throw new ProbeFailure('limit');
        if((int)query('SELECT COUNT(*) FROM '.table('mail_submission_dispatches').' d JOIN '.table('mail_submissions').' s ON s.id=d.submission_id WHERE s.owner_id=? AND d.started_epoch>?',[$actor['id'],$now-3600])->fetchColumn()>=20)throw new ProbeFailure('limit');
        query('INSERT INTO '.table('mail_submission_dispatches').' (id,submission_id,snapshot_fingerprint,mime_sha256,started_epoch,started_at) VALUES (?,?,?,?,?,?)',[$row['attempt_id'],$id,$row['snapshot_fingerprint'],$row['mime_sha256'],$now,gmdate('Y-m-d H:i:s',$now)]);
        mail_submissions_audit((int)$row['owner_id'],'dispatched','Submission '.$id.' attempt '.$row['attempt_id']);return ['row'=>$row,'message'=>$mime];
    }));
}
/** Strict finite result combinations before accepting an internal transport observation. */
function mail_submissions_validate_outcome(TransportOutcome $outcome):void {
    $o=$outcome;$negative=$o->smtpCode!==null && $o->smtpCode>=400;
    $valid=match($o->status) {
        'accepted'=>$o->phase==='confirmation'&&$o->reason==='accepted'&&$o->smtpCode===250,
        'rejected'=>$o->phase==='confirmation'&&$o->reason==='server_rejected'&&$negative,
        'unknown'=>$o->phase==='confirmation'&&in_array($o->reason,['connection','tls','protocol','limit','timeout'],true)&&($o->smtpCode===null||($o->reason==='protocol'&&$o->smtpCode<400&&$o->smtpCode!==250)),
        'not_submitted'=>$o->phase!=='confirmation'&&$o->reason!=='accepted'&&($o->reason!=='server_rejected'||$negative),
        default=>false,
    };
    if(!$valid)throw new InvalidArgumentException('Invalid submission observation.');
}
/** Internal only, with staff mutex/transaction held; never a route or generic callback. */
function mail_submissions_insert_result(array $row,string $attempt,string $fingerprint,string $mimeHash,string $scope,string $status,string $phase,string $reason,?int $code):void {
    if(!hash_equals($row['attempt_id'],$attempt) || !hash_equals($row['snapshot_fingerprint'],$fingerprint) || !hash_equals($row['mime_sha256'],$mimeHash))throw new InvalidArgumentException('Submission observation does not match its exact attempt.');
    $data=['id'=>$attempt,'submission_id'=>$row['id'],'snapshot_fingerprint'=>$fingerprint,'mime_sha256'=>$mimeHash,'scope'=>$scope,'status'=>$status,'phase'=>$phase,'reason'=>$reason,'smtp_code'=>$code];
    $old=one('SELECT id,submission_id,snapshot_fingerprint,mime_sha256,scope,status,phase,reason,smtp_code FROM '.table('mail_submission_results').' WHERE submission_id=?',[$row['id']]);
    if($old) {
        if($old['smtp_code']!==null)$old['smtp_code']=(int)$old['smtp_code'];
        if($old!==$data)throw new InvalidArgumentException('A different submission outcome is already recorded.');return;
    }
    $now=time();query('INSERT INTO '.table('mail_submission_results').' (id,submission_id,snapshot_fingerprint,mime_sha256,scope,status,phase,reason,smtp_code,finished_epoch,finished_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[...array_values($data),$now,gmdate('Y-m-d H:i:s',$now)]);
    mail_submissions_audit((int)$row['owner_id'],'finished','Submission '.$row['id'].' attempt '.$attempt.' '.$status.' '.$phase.' '.$reason);
}
/** Internal finalization must persist actual acceptance even if the session/grant just expired. */
function mail_submissions_record(TransportOutcome $outcome):void {
    mail_submissions_validate_outcome($outcome);
    staff_locked(fn()=>staff_transaction(function()use($outcome):void {
        $row=mail_submissions_row($outcome->submissionId);mail_submissions_message($row);
        $dispatch=one('SELECT * FROM '.table('mail_submission_dispatches').' WHERE submission_id=?',[$row['id']]);
        if(!$dispatch || $dispatch['id']!==$row['attempt_id'] || !hash_equals($dispatch['snapshot_fingerprint'],$row['snapshot_fingerprint']) || !hash_equals($dispatch['mime_sha256'],$row['mime_sha256']))throw new InvalidArgumentException('No matching committed dispatch exists.');
        mail_submissions_insert_result($row,$outcome->attemptId,$outcome->snapshotFingerprint,$outcome->mimeSha256,'dispatch',$outcome->status,$outcome->phase,$outcome->reason,$outcome->smtpCode);
    }));
}
/** Internal preparation refusal. It cannot resolve an already dispatched attempt. */
function mail_submissions_refuse(string $id,string $attempt,string $fingerprint,string $mimeHash,string $reason):void {
    if(!in_array($reason,['destination','access_changed','credential','connection','limit','timeout','protocol'],true))throw new InvalidArgumentException('Unknown preparation refusal.');
    staff_locked(fn()=>staff_transaction(function()use($id,$attempt,$fingerprint,$mimeHash,$reason):void {
        $row=mail_submissions_row($id);mail_submissions_message($row);
        if(one('SELECT id FROM '.table('mail_submission_dispatches').' WHERE submission_id=?',[$id]))throw new InvalidArgumentException('A dispatched submission cannot be resolved as an unused preparation.');
        mail_submissions_insert_result($row,$attempt,$fingerprint,$mimeHash,'preparation','not_submitted','prepare',$reason,null);
    }));
}
