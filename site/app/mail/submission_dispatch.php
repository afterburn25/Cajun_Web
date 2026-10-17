<?php
/** Server-only coordinator for one explicit reviewed admin submission; no automatic retry. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/mail/submissions.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-transport/EndpointResolver.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-submission/SubmissionChannel.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-submission/SubmissionTransport.php';
use CajunWebPro\Foundation\MailTransport\{EndpointPolicy,EndpointResolver,ProbeFailure};
use CajunWebPro\Foundation\MailSubmission\{PreparedMime,TransportOutcome,SubmissionChannel,SubmissionTransport};

/** Internal only: selected saved profile, committed fence and current authority required. */
function mail_submissions_transport(array $profile,PreparedMime $message,string $attempt,#[\SensitiveParameter] array $secrets,string $address,Closure $authorize):TransportOutcome {
    // No SMTP command or credential can have been sent if channel opening fails.
    try {
        $authorize();
        $channel=SubmissionChannel::open($profile['smtp_host'],(int)$profile['smtp_port'],$profile['smtp_encryption'],$address);
    }catch(Throwable $e) {
        if($e instanceof PDOException)throw $e;
        return new TransportOutcome($message,$attempt,'not_submitted','prepare',$e instanceof ProbeFailure?$e->getMessage():'connection');
    }
    return (new SubmissionTransport())->submit($message,$attempt,$channel,$profile['smtp_encryption'],$profile['smtp_username'],$secrets['smtp_password'],$authorize);
}

/**
 * Test callables are server-only; HTTP/configuration must never select a resolver or transport.
 * A replay, including an abandoned preparation, returns its identity without resuming work.
 */
function mail_submissions_send(string $id,string $draft,int $draftRevision,int $accountRevision,?callable $resolver=null,?callable $transport=null):string {
    if(db()->inTransaction())throw new LogicException('Submission cannot run inside a transaction.');
    $intent=mail_submissions_prepare_intent($id,$draft,$draftRevision,$accountRevision);
    if(!$intent['created'])return $intent['id'];
    $row=$intent['row'];$profile=$intent['profile'];$reason=null;$addresses=[];
    // Preparation and its strict audit are committed before DNS; no staff mutex or transaction.
    try {$addresses=EndpointPolicy::addresses(($resolver??[EndpointResolver::class,'resolve'])($profile['smtp_host']));}
    catch(InvalidArgumentException) {$reason='destination';}
    catch(ProbeFailure $e) {$reason=in_array($e->getMessage(),['connection','protocol','limit','timeout'],true)?$e->getMessage():'connection';}
    catch(Throwable $e) {if($e instanceof PDOException)throw $e;$reason='connection';}
    if($reason!==null) {
        mail_submissions_refuse($id,$row['attempt_id'],$row['snapshot_fingerprint'],$row['mime_sha256'],$reason);return $id;
    }
    return staff_locked(function()use($id,$row,$profile,$addresses,$transport):string {
        try {$claim=mail_submissions_claim($id);}
        catch(Throwable $e) {
            if($e instanceof PDOException)throw $e;
            mail_submissions_refuse($id,$row['attempt_id'],$row['snapshot_fingerprint'],$row['mime_sha256'],$e instanceof ProbeFailure&&$e->getMessage()==='limit'?'limit':'access_changed');return $id;
        }
        if($claim===null)return $id;
        if($claim['row']!==$row)throw new RuntimeException('The immutable submission changed. Its dispatch remains unresolved.');
        $message=$claim['message'];$attempt=$claim['row']['attempt_id'];
        $authorize=static function()use($row,$message,$profile):void {
            try {
                $current=mail_submissions_authorize($row,$message);
                // The resolved host and every sending/credential setting belong to this reviewed account.
                foreach(['id','revision','address','smtp_host','smtp_port','smtp_encryption','smtp_username','credentials'] as $key) {
                    if($current[$key]!==$profile[$key])throw new ProbeFailure('access_changed');
                }
            }catch(Throwable $e) {if($e instanceof PDOException)throw $e;throw new ProbeFailure('access_changed');}
        };
        $outcome=null;
        try {$authorize();}
        catch(ProbeFailure) {$outcome=new TransportOutcome($message,$attempt,'not_submitted','prepare','access_changed');}
        if($outcome===null) {
            // The durable, audited fence is committed before plaintext credentials are opened.
            try {$secrets=mail_accounts_unseal($profile['id'],$profile['credentials']);}
            catch(Throwable $e) {if($e instanceof PDOException)throw $e;$outcome=new TransportOutcome($message,$attempt,'not_submitted','prepare','credential');}
            if($outcome===null) {
                try {$outcome=($transport??'mail_submissions_transport')($profile,$message,$attempt,$secrets,$addresses[0],$authorize);}
                catch(Throwable) {
                    // An escaping driver exception cannot prove the DATA terminator was not sent.
                    $outcome=new TransportOutcome($message,$attempt,'unknown','confirmation','connection');
                }finally {unset($secrets);}
            }
        }
        // Do not reauthorize or relabel a verified final response here. Disclosure has its own gate.
        // Invalid receipts or storage/audit errors leave the committed fence unresolved.
        if(!$outcome instanceof TransportOutcome || $outcome->submissionId!==$id || $outcome->attemptId!==$attempt
            || !hash_equals($message->snapshot->fingerprint,$outcome->snapshotFingerprint) || !hash_equals($message->sha256,$outcome->mimeSha256))throw new InvalidArgumentException('Submission transport returned a different attempt.');
        mail_submissions_record($outcome);return $id;
    });
}
