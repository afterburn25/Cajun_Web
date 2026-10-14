<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailSubmission;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/PreparedMime.php';

/** Observed single-process result, not durable state, permission or delivery proof. */
final readonly class TransportOutcome
{
    public string $submissionId;
    public string $snapshotFingerprint;
    public string $mimeSha256;
    public function __construct(
        PreparedMime $message,
        public string $attemptId,
        public string $status,
        public string $phase,
        public string $reason,
        public ?int $smtpCode=null,
    ) {
        if(!preg_match('/\A[a-f0-9]{32}\z/',$attemptId)
            || !in_array($status,['accepted','rejected','not_submitted','unknown'],true)
            || !in_array($phase,['prepare','greeting','hello','upgrade','authentication','sender','recipient','data','confirmation'],true)
            || !in_array($reason,['accepted','server_rejected','connection','tls','protocol','limit','timeout','authentication','mechanism','credential','upgrade','access_changed'],true)
            || ($smtpCode!==null && ($smtpCode<200 || $smtpCode>599))) throw new \InvalidArgumentException('Invalid finite transport outcome.');
        $this->submissionId=$message->snapshot->id;
        $this->snapshotFingerprint=$message->snapshot->fingerprint;
        $this->mimeSha256=$message->sha256;
    }
}
