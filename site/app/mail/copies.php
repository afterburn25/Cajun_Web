<?php
/** Explicit, uncertainty-safe server Sent-folder copies of accepted submissions. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\MailTransport\{EndpointPolicy,EndpointResolver,ProbeFailure};
use CajunWebPro\Foundation\MailReceiving\{AppendReceipt,ReceivingFailure,SentCopy};
require_once CWP_CODE_ROOT.'/includes/foundation/mail-transport/EndpointPolicy.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-transport/EndpointResolver.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-transport/ProbeFailure.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-receiving/AppendReceipt.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-receiving/ReceivingFailure.php';
require_once CWP_CODE_ROOT.'/app/mail/accounts.php';
require_once CWP_CODE_ROOT.'/app/mail/submissions.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_copies_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_copies_migrate.php';

function mail_copies_ready():bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_copies_schema_version'])['value']??'')==='1';}
function mail_copy_outcomes():array {
    return [
        'appended'=>'A copy was saved in the server Sent folder.',
        'uncertain'=>'The server may have saved the copy after the literal began, but no final confirmation was received. Do not retry this submission.',
        'access_changed'=>'The account or your access changed before confirmation. No copy was started.',
        'authentication'=>'The server did not accept the saved mailbox sign-in.',
        'connection'=>'The server could not be reached before the copy began.',
        'credential'=>'The saved mailbox credentials are unavailable.',
        'destination'=>'The saved incoming server name is not usable. Check the saved settings.',
        'folder'=>'The server refused the configured Sent folder before accepting the message literal.',
        'limit'=>'The copy request exceeded a bounded limit.',
        'mechanism'=>'The server does not offer password sign-in.',
        'protocol'=>'The server reply was unexpected before the message literal began.',
        'timeout'=>'The server took too long to answer before the copy began.',
        'tls'=>'A verified TLS connection could not be established.',
        'upgrade'=>'The server did not accept the required TLS upgrade.',
    ];
}
function mail_copy_outcome(?string $code):string {return $code===null?'The copy request has no final recorded result. Do not retry until it is reviewed.':(mail_copy_outcomes()[$code]??'The saved copy result is not recognized. Do not retry.');}
/** Operation-scoped finite code map; unrelated receiving codes fail closed as protocol. */
function mail_copy_code(string $code,string $fallback='protocol'):string {return array_key_exists($code,mail_copy_outcomes())?$code:$fallback;}

/** Current per-account Sent folder name, or empty when copying is not configured. */
function mail_sent_folder(string $accountId):string {
    if(!mail_copies_ready())return '';
    mail_accounts_id($accountId);
    $row=one('SELECT sent_folder FROM '.table('mail_sent_folders').' WHERE id=?',[$accountId]);
    return $row===null?'':(string)$row['sent_folder'];
}

/** Current owner session sets one plain server folder; an empty value clears it. */
function mail_sent_folder_save(string $accountId,string $folder):void {
    if(!mail_copies_ready())throw new RuntimeException('Prepare Sent-copy storage before configuring a server folder.');
    if($folder!=='' && (!preg_match('/\A[\x20-\x7E]{1,64}\z/D',$folder)||str_contains($folder,'"')||str_contains($folder,'\\')||str_contains($folder,'%')||str_contains($folder,'*')))throw new InvalidArgumentException('Use a plain server folder name such as Sent or INBOX.Sent.');
    staff_locked(fn()=>staff_transaction(function()use($accountId,$folder):void {
        $actor=mail_accounts_owner('');mail_accounts_load($accountId,true);
        $old=one('SELECT sent_folder FROM '.table('mail_sent_folders').' WHERE id=? FOR UPDATE',[$accountId]);
        if($folder==='') {
            if($old)query('DELETE FROM '.table('mail_sent_folders').' WHERE id=?',[$accountId]);
        } elseif(($old['sent_folder']??null)!==$folder) {
            query('INSERT INTO '.table('mail_sent_folders').' (id,sent_folder,updated_by,updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE sent_folder=VALUES(sent_folder),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',[$accountId,$folder,(int)$actor['id'],gmdate('Y-m-d H:i:s')]);
        }
        mail_accounts_audit((int)$actor['id'],'sent_folder','Sent folder '.($folder===''?'cleared':'set').' account '.$accountId);
    }));
}

/** Latest attempt including an unresolved row whose result could not be recorded. */
function mail_copy_status(string $submissionId):?array {
    if(!mail_copies_ready())return null;
    mail_drafts_id($submissionId);
    $actor=mail_submissions_actor();$submission=mail_submissions_row($submissionId,(int)$actor['id']);mail_drafts_account($submission['account_id']);
    return one('SELECT a.folder,a.started_at,r.outcome,r.result_code,r.finished_at FROM '.table('mail_copy_attempts').' a LEFT JOIN '.table('mail_copy_results').' r ON r.id=a.id WHERE a.submission_id=? ORDER BY a.started_epoch DESC,a.id DESC LIMIT 1',[$submissionId]);
}

function mail_copy_record(array $started,string $code,int $elapsed):void {
    if(!array_key_exists($code,mail_copy_outcomes()))throw new LogicException('Invalid copy result.');
    $outcome=match($code){'appended'=>'success','uncertain'=>'unknown',default=>'failed'};
    $existing=one('SELECT id,outcome,result_code FROM '.table('mail_copy_results').' WHERE id=?',[$started['id']]);
    if($existing!==null) {
        if($existing['outcome']!==$outcome||$existing['result_code']!==$code)throw new RuntimeException('A different copy result is already recorded.');
        return;
    }
    query('INSERT INTO '.table('mail_copy_results').' (id,outcome,result_code,finished_at,elapsed_ms) VALUES (?,?,?,?,?)',[$started['id'],$outcome,$code,gmdate('Y-m-d H:i:s'),max(0,min($elapsed,4294967295))]);
}

function mail_copy_transport(array $row,#[\SensitiveParameter] array $secrets,string $address,string $folder,#[\SensitiveParameter] string $mime,callable $authorize):AppendReceipt {
    require_once CWP_CODE_ROOT.'/includes/foundation/mail-receiving/bootstrap.php';
    return SentCopy::append($row['imap_host'],(int)$row['imap_port'],$row['imap_encryption'],$address,$row['imap_username'],$secrets['imap_password'],$folder,$mime,$authorize);
}

/** Copy one own accepted submission. Literal-start uncertainty permanently blocks replay. */
function mail_submission_copy(string $submissionId,?callable $resolver=null,?callable $transport=null):array {
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
        $prior=one('SELECT r.result_code FROM '.table('mail_copy_attempts').' a LEFT JOIN '.table('mail_copy_results').' r ON r.id=a.id WHERE a.submission_id=? AND (r.id IS NULL OR r.result_code IN (?,?)) LIMIT 1',[$submissionId,'appended','uncertain']);
        if($prior)throw new RuntimeException('This submission already has an accepted or unresolved Sent-copy attempt. Do not retry it.');
        $now=time();
        if(one('SELECT id FROM '.table('mail_copy_attempts').' WHERE account_id=? AND started_epoch>? LIMIT 1',[$row['account_id'],$now-2]))throw new RuntimeException('Wait 2 seconds between copy requests for this account.');
        if((int)query('SELECT COUNT(*) FROM '.table('mail_copy_attempts').' WHERE actor_id=? AND started_epoch>?',[$actor['id'],$now-3600])->fetchColumn()>=60)throw new RuntimeException('The hourly copy request limit has been reached. Try again later.');
        $prepared=mail_submissions_message($row);
        $started=['id'=>bin2hex(random_bytes(16)),'submission_id'=>$submissionId,'account_id'=>$row['account_id'],'account_revision'=>(int)$row['account_revision'],'actor_id'=>(int)$actor['id'],'folder'=>$folder,'mime_sha256'=>$prepared->sha256,'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now];
        query('INSERT INTO '.table('mail_copy_attempts').' (id,submission_id,account_id,account_revision,actor_id,folder,mime_sha256,started_at,started_epoch) VALUES (?,?,?,?,?,?,?,?,?)',array_values($started));
        mail_accounts_audit((int)$actor['id'],'copy_started','Sent copy '.$started['id'].' submission '.$submissionId.' account '.$row['account_id']);
        return [$started,mail_accounts_load($row['account_id']),$folder,$prepared->bytes];
    }));
    $begin=hrtime(true);$code='appended';$addresses=[];
    try {$addresses=EndpointPolicy::addresses(($resolver??[EndpointResolver::class,'resolve'])($row['imap_host']));}
    catch(InvalidArgumentException) {$code='destination';}
    catch(ProbeFailure $e) {$code=mail_copy_code($e->getMessage(),'connection');}
    catch(Throwable) {$code='connection';}
    return staff_locked(function()use($started,$row,$code,$addresses,$begin,$transport,$folder,$mime):array {
        $authorize=static function()use($started):void {
            try {
                $actor=mail_accounts_actor();
                if((int)$actor['id']!==$started['actor_id'])throw new RuntimeException('Changed actor.');
                mail_drafts_account($started['account_id'],$started['account_revision']);
                $result=one('SELECT status FROM '.table('mail_submission_results').' WHERE submission_id=?',[$started['submission_id']]);
                if(($result['status']??'')!=='accepted'||mail_sent_folder($started['account_id'])!==$started['folder'])throw new RuntimeException('Changed state.');
                $saved=mail_submissions_row($started['submission_id'],(int)$actor['id']);
                if(!hash_equals($started['mime_sha256'],mail_submissions_message($saved)->sha256))throw new RuntimeException('Changed content.');
            } catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;throw new ReceivingFailure('access_changed');}
        };
        try {
            $authorize();
            if($code==='appended') {
                try {$current=mail_accounts_load($started['account_id']);$secrets=mail_accounts_unseal($current['id'],$current['credentials']);mail_accounts_passwords($secrets);}
                catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;$code='credential';}
                if($code==='appended') {
                    try {
                        $receipt=($transport??'mail_copy_transport')($current,$secrets,$addresses[0],$folder,$mime,$authorize);
                        if(!$receipt instanceof AppendReceipt||!$receipt->accepted)throw new ReceivingFailure('protocol');
                    } catch(ReceivingFailure $e) {$code=mail_copy_code($e->getMessage());}
                    catch(Throwable $e) {if($e instanceof PDOException)throw $e;$code='connection';}
                    finally {unset($secrets);}
                }
            }
        } catch(ReceivingFailure $e) {$code=mail_copy_code($e->getMessage());}
        staff_transaction(function()use($started,$code,$begin):void {
            mail_copy_record($started,$code,(int)((hrtime(true)-$begin)/1e6));
            mail_accounts_audit($started['actor_id'],'copy_'.$code,'Sent copy '.$started['id'].' code '.$code);
        });
        return ['code'=>$code,'attempt_id'=>$started['id'],'submission_id'=>$started['submission_id'],'folder'=>$started['folder'],'finished_at'=>gmdate('Y-m-d H:i:s')];
    });
}
