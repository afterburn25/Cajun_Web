<?php
/** Current-authorized read models for private review/history screens. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/mail/submissions.php';

/** No raw SMTP response, diagnostic exception or caller-supplied label is displayed. */
function mail_submissions_status(?array $result,?array $dispatch):array {
    if($result===null)return $dispatch===null
        ?['title'=>'Preparation incomplete','text'=>'No sending attempt was recorded. This preparation will not resume automatically.']
        :['title'=>'Submission unresolved','text'=>'A sending attempt started, but its final result was not saved. The message may have been accepted. Do not resend it.'];
    return match($result['status']??'') {
        'accepted'=>['title'=>'Server accepted','text'=>'The sending server accepted responsibility for this message. This does not confirm recipient delivery.'],
        'rejected'=>['title'=>'Server rejected','text'=>'The sending server rejected the completed submission. Review the message and account before preparing a new saved version.'],
        'not_submitted'=>['title'=>'Not submitted','text'=>match($result['reason']??'') {
            'destination'=>'The saved server hostname or its DNS answers are outside the supported public mail-server policy.',
            'access_changed'=>'Your access, saved draft or sending account changed. Open the current draft and review it again.',
            'credential'=>'The saved password could not be used. Ask an owner to check the account and its protected credentials.',
            'authentication','mechanism'=>'The server did not accept a supported password sign-in. Ask an owner to check the saved account.',
            'tls','upgrade'=>'The required verified encrypted connection could not be established.',
            'limit'=>'A sending or server-response limit was reached. Review the account and wait before preparing another saved version.',
            'server_rejected'=>'The server refused the sender, a recipient or the start of the message. No completed message was submitted.',
            default=>'The message was not submitted. Review the saved account and message before preparing a new saved version.',
        }],
        default=>['title'=>'Confirmation missing','text'=>'The server’s final acceptance could not be confirmed. The message may have been accepted. Do not resend it.'],
    };
}
function mail_submissions_list(string $account,int $page=1):array {
    return staff_locked(function()use($account,$page):array {
        $actor=mail_submissions_actor();mail_drafts_account($account);$page=max(1,min($page,100000));
        $rows=all('SELECT s.*,d.started_at AS dispatch_started,r.status AS result_status,r.reason AS result_reason FROM '.table('mail_submissions').' s LEFT JOIN '.table('mail_submission_dispatches').' d ON d.submission_id=s.id LEFT JOIN '.table('mail_submission_results').' r ON r.submission_id=s.id WHERE s.owner_id=? AND s.account_id=? ORDER BY s.created_at DESC,s.id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$actor['id'],$account]);
        $result=[];
        foreach($rows as $row) {
            $message=mail_submissions_message($row);$status=mail_submissions_status($row['result_status']===null?null:['status'=>$row['result_status'],'reason'=>$row['result_reason']],$row['dispatch_started']===null?null:['started_at'=>$row['dispatch_started']]);
            $result[]=['id'=>$row['id'],'draft_id'=>$row['draft_id'],'draft_revision'=>(int)$row['draft_revision'],'created_at'=>$row['created_at'],'subject'=>$message->snapshot->content->subject,'status'=>$status];
        }
        return $result;
    });
}
/** GET/read only: validate saved content without creating an intent, DNS or credentials. */
function mail_submissions_review(string $draft,?int $revision=null,?int $accountRevision=null):array {
    return staff_locked(function()use($draft,$revision,$accountRevision):array {
        $actor=mail_submissions_actor();$saved=mail_drafts_get($draft);$thread=null;$originFingerprint=null;
        if($saved['status']==='source') {
            // Copied-message content lives only in prepared protected storage.
            if(!mail_replies_ready())throw new RuntimeException('This copied-message draft requires prepared Reply/Forward storage.');
            $saved=mail_reply_drafts_get($draft);
            $copied=mail_reply_drafts_for_submission($draft,$actor);
            $thread=$copied['thread'];$originFingerprint=$copied['origin_fingerprint'];
        }
        // Preserve already prepared history, including an interrupted submission after a failed POST.
        $existing=one('SELECT id FROM '.table('mail_submissions').' WHERE draft_id=? AND draft_revision=? AND owner_id=?',[$draft,$revision??(int)$saved['revision'],$actor['id']]);
        if($existing)return ['existing'=>mail_submissions_get($existing['id'])];
        if($saved['status']!=='draft' || ($revision!==null&&$revision!==(int)$saved['revision']) || ($accountRevision!==null&&$accountRevision!==(int)$saved['account_revision']))throw new RuntimeException('The reviewed draft changed. Reopen the saved draft before sending.');
        mail_submissions_prior_attempt($draft);$account=mail_drafts_account($saved['account_id'],(int)$saved['account_revision']);
        // Same maintained preparation gate as dispatch; the preview identity is never persisted.
        $content=new \CajunWebPro\Foundation\MailCompose\DraftContent($saved['to'],$saved['cc'],$saved['bcc'],$saved['subject'],$saved['body']);
        \CajunWebPro\Foundation\MailSubmission\PreparedMime::fromSnapshot(new \CajunWebPro\Foundation\MailSubmission\SubmissionSnapshot(str_repeat('0',32),(int)$actor['id'],$draft,(int)$saved['revision'],$account['id'],$account['revision'],$account['address'],$content,time(),$thread,$originFingerprint));
        return ['existing'=>null,'draft'=>$saved,'account'=>$account];
    });
}
