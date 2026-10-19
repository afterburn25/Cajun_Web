<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
use CajunWebPro\Foundation\MailTransport\{EndpointPolicy,EndpointResolver,ProbeFailure};
use CajunWebPro\Foundation\MailReceiving\{SentCopy,ReceivingFailure};
use CajunWebPro\Foundation\MailSubmission\PreparedMime;
require_once CWP_CODE_ROOT.'/includes/foundation/mail-transport/EndpointPolicy.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-transport/EndpointResolver.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-transport/ProbeFailure.php';
require_once CWP_CODE_ROOT.'/app/mail/accounts.php';
require_once CWP_CODE_ROOT.'/app/mail/submissions.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_copies_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_copies_migrate.php';
function mail_copies_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_copies_schema_version'])['value']??'')==='1';}
function mail_copy_outcomes(): array {
    return ['appended'=>'A copy was saved in the server Sent folder.','access_changed'=>'The account or your access changed during the request. No copy was recorded.','authentication'=>'The server did not accept the saved mailbox sign-in.','connection'=>'The server could not be reached for this copy request.','credential'=>'The saved mailbox credentials are unavailable.','destination'=>'The saved incoming server name is not usable. Check the saved settings.','folder'=>'The server did not accept the configured Sent folder. Check the folder name on this account.','limit'=>'The copy request exceeded a bounded limit.','mechanism'=>'The server does not offer password sign-in.','protocol'=>'The server reply was unexpected. No copy was recorded.','timeout'=>'The server took too long to answer. No copy was recorded.','tls'=>'A verified TLS connection could not be established.','upgrade'=>'The server did not accept the required TLS upgrade.'];
}
function mail_copy_outcome(string $code): string {return mail_copy_outcomes()[$code]??'No final result was recorded. Try again.';}
/** Current per-account Sent folder name, or '' when copying is not configured. */
function mail_sent_folder(string $accountId): string {
    if(!mail_copies_ready())return '';
    mail_accounts_id($accountId);
    $row=one('SELECT sent_folder FROM '.table('mail_sent_folders').' WHERE id=?',[$accountId]);
    return $row===null?'':(string)$row['sent_folder'];
}
/** An owner sets the destination folder with their current password; '' clears it. */
function mail_sent_folder_save(string $accountId,string $folder,#[\SensitiveParameter] string $password): void {
    if($folder!=='' && (!preg_match('/\A[\x20-\x7E]{1,64}\z/D',$folder) || str_contains($folder,'"') || str_contains($folder,'\\') || str_contains($folder,'%') || str_contains($folder,'*')))throw new InvalidArgumentException('Use a plain server folder name such as Sent or INBOX.Sent.');
    staff_locked(function()use($accountId,$folder,$password):void {
        staff_transaction(function()use($accountId,$folder,$password):void {
            $actor=mail_accounts_owner($password);
            $row=mail_accounts_load($accountId);
            if($folder==='')query('DELETE FROM '.table('mail_sent_folders').' WHERE id=?',[$accountId]);
            elseif(mail_sent_folder($accountId)===$folder)return;
            else query('INSERT INTO '.table('mail_sent_folders').' (id,sent_folder,updated_by,updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE sent_folder=VALUES(sent_folder),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',[$accountId,$folder,(int)$actor['id'],gmdate('Y-m-d H:i:s')]);
            mail_accounts_audit((int)$actor['id'],'sent_folder','Sent folder '.($folder===''?'cleared':'set').' account '.$accountId);
        });
    });
}
/** One copy request outcome for a submission, or null before any attempt. */
function mail_copy_status(string $submissionId): ?array {
    if(!mail_copies_ready())return null;
    mail_drafts_id($submissionId);
    return one('SELECT a.folder,r.outcome,r.result_code,r.finished_at FROM '.table('mail_copy_attempts').' a JOIN '.table('mail_copy_results').' r ON r.id=a.id WHERE a.submission_id=? ORDER BY a.started_epoch DESC LIMIT 1',[$submissionId]);
}
function mail_copy_record(array $started,string $code,int $elapsed): void {
    if(!array_key_exists($code,mail_copy_outcomes()))throw new LogicException('Invalid copy result.');
    $existing=one('SELECT id,outcome,result_code FROM '.table('mail_copy_results').' WHERE id=?',[$started['id']]);
    $data=['id'=>$started['id'],'outcome'=>$code==='appended'?'success':'failed','result_code'=>$code];
    if($existing!==null) {
        if($existing['outcome']!==$data['outcome']||$existing['result_code']!==$data['result_code'])throw new RuntimeException('A different copy result is already recorded.');
        return;
    }
    query('INSERT INTO '.table('mail_copy_results').' (id,outcome,result_code,finished_at,elapsed_ms) VALUES (?,?,?,?,?)',[$started['id'],$data['outcome'],$data['result_code'],gmdate('Y-m-d H:i:s'),$elapsed]);
}
function mail_copy_transport(array $row,#[\SensitiveParameter] array $secrets,string $address,string $folder,#[\SensitiveParameter] string $mime,callable $authorize): void {
    require_once CWP_CODE_ROOT.'/includes/foundation/mail-receiving/bootstrap.php';
    SentCopy::append($row['imap_host'],(int)$row['imap_port'],$row['imap_encryption'],$address,$row['imap_username'],$secrets['imap_password'],$folder,$mime,$authorize,PreparedMime::MAX_BYTES);
}
/** Copy one own accepted submission into the configured server Sent folder. Every request is recorded. */
function mail_submission_copy(string $submissionId,?callable $resolver=null,?callable $transport=null): array {
    mail_drafts_id($submissionId);
    if(!mail_copies_ready())throw new RuntimeException('An owner must prepare Sent-copy storage in Email accounts first.');
    if(db()->inTransaction())throw new LogicException('Copy requests cannot run inside a transaction.');
    [$started,$row,$folder,$mime]=staff_locked(fn()=>staff_transaction(function()use($submissionId):array {
        $actor=mail_submissions_actor();
        $row=mail_submissions_row($submissionId,(int)$actor['id']);
        $result=one('SELECT status FROM '.table('mail_submission_results').' WHERE submission_id=?',[$submissionId]);
        if(($result['status']??'')!=='accepted')throw new InvalidArgumentException('Only a submission the server accepted can be copied to a Sent folder.');
        mail_drafts_account($row['account_id'],(int)$row['account_revision']);
        $folder=mail_sent_folder($row['account_id']);
        if($folder==='')throw new RuntimeException('Set the server Sent folder on this email account first.');
        if(one('SELECT r.id FROM '.table('mail_copy_attempts').' a JOIN '.table('mail_copy_results').' r ON r.id=a.id WHERE a.submission_id=? AND r.result_code=? LIMIT 1',[$submissionId,'appended']))throw new RuntimeException('This submission already has a recorded Sent copy.');
        $now=time();
        if(one('SELECT a.id FROM '.table('mail_copy_attempts').' a WHERE a.account_id=? AND a.started_epoch>? LIMIT 1',[$row['account_id'],$now-2]))throw new RuntimeException('Wait 2 seconds between copy requests for this account.');
        if((int)query('SELECT COUNT(*) FROM '.table('mail_copy_attempts').' WHERE actor_id=? AND started_epoch>?',[$actor['id'],$now-3600])->fetchColumn()>=60)throw new RuntimeException('The hourly copy request limit has been reached. Try again later.');
        $mime=mail_submissions_message($row);
        $started=['id'=>bin2hex(random_bytes(16)),'submission_id'=>$submissionId,'account_id'=>$row['account_id'],'account_revision'=>(int)$row['account_revision'],'actor_id'=>(int)$actor['id'],'folder'=>$folder,'mime_sha256'=>$mime->sha256,'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now];
        query('INSERT INTO '.table('mail_copy_attempts').' (id,submission_id,account_id,account_revision,actor_id,folder,mime_sha256,started_at,started_epoch) VALUES (?,?,?,?,?,?,?,?,?)',array_values($started));
        mail_accounts_audit((int)$actor['id'],'copy_started','Sent copy '.$started['id'].' submission '.$submissionId.' account '.$row['account_id']);
        return [$started,mail_accounts_load($row['account_id']),$folder,$mime->bytes];
    }));
    $begin=hrtime(true);$code='appended';
    try {$addresses=EndpointPolicy::addresses(($resolver??[EndpointResolver::class,'resolve'])($row['imap_host']));}
    catch(InvalidArgumentException) {$code='destination';}
    catch(ProbeFailure $e) {$code=$e->getMessage();}
    catch(Throwable) {$code='connection';}
    return staff_locked(function()use($started,$code,$addresses,$begin,$transport,$folder,$mime):array {
        $authorize=static function()use($started):void {
            try {
                $actor=mail_accounts_actor();
                if((int)$actor['id']!==$started['actor_id'])throw new RuntimeException('Changed actor.');
                mail_drafts_account($started['account_id'],$started['account_revision']);
                $result=one('SELECT status FROM '.table('mail_submission_results').' WHERE submission_id=?',[$started['submission_id']]);
                if(($result['status']??'')!=='accepted'||mail_sent_folder($started['account_id'])!==$started['folder'])throw new RuntimeException('Changed state.');
                $row=mail_submissions_row($started['submission_id'],(int)$actor['id']);
                if(!hash_equals($started['mime_sha256'],mail_submissions_message($row)->sha256))throw new RuntimeException('Changed content.');
            }catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;throw new ReceivingFailure('access_changed');}
        };
        try {
            $authorize();
            if($code==='appended') {
                try {$current=mail_accounts_load($started['account_id']);$secrets=mail_accounts_unseal($current['id'],$current['credentials']);mail_accounts_passwords($secrets);}
                catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;$code='credential';}
                if($code==='appended') {
                    try {($transport??'mail_copy_transport')($current,$secrets,$addresses[0],$folder,$mime,$authorize);}
                    catch(ReceivingFailure $e) {$code=$e->getMessage();}
                    catch(Throwable $e) {if($e instanceof PDOException)throw $e;$code='connection';}
                    finally {unset($secrets);}
                }
            }
        }catch(ReceivingFailure $e) {$code=$e->getMessage();}
        staff_transaction(function()use($started,$code,$begin):void {
            mail_copy_record($started,$code,(int)((hrtime(true)-$begin)/1e6));
            mail_accounts_audit($started['actor_id'],'copy_'.($code==='appended'?'appended':'failed'),'Sent copy '.$started['id'].' code '.$code);
        });
        return ['code'=>$code,'attempt_id'=>$started['id'],'submission_id'=>$started['submission_id'],'folder'=>$started['folder'],'finished_at'=>gmdate('Y-m-d H:i:s')];
    });
}
