<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailSubmission;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/SmtpSession.php';
require_once __DIR__.'/TransportOutcome.php';
use CajunWebPro\Foundation\MailTransport\ProbeChannel;
use CajunWebPro\Foundation\MailTransport\ProbeFailure;

/**
 * One explicit attempt. Caller must commit a durable intent and enforce current
 * authority; this isolated driver supplies neither storage nor permission.
 * No DNS/opener is supplied here. Production wiring requires pinned bounded TLS.
 */
final class SubmissionTransport
{
    public function submit(
        PreparedMime $message,string $attemptId,ProbeChannel $channel,string $encryption,
        #[\SensitiveParameter] string $username,#[\SensitiveParameter] string $password,\Closure $authorize,
    ):TransportOutcome {
        if(!preg_match('/\A[a-f0-9]{32}\z/',$attemptId)) {
            try {$channel->close();} catch(\Throwable) {}
            throw new \InvalidArgumentException('Invalid attempt identity.');
        }
        $smtp=new SmtpSession($channel,$authorize);$phase='prepare';
        $result=function(string $status,string $reason,?int $code=null)use($message,$attemptId,&$phase):TransportOutcome {
            return new TransportOutcome($message,$attemptId,$status,$phase,$reason,$code);
        };
        $refused=function()use($smtp,$result):TransportOutcome {
            $code=$smtp->lastCode();
            return $result('not_submitted',$code!==null&&$code>=400?'server_rejected':'protocol',$code);
        };
        try {
            if(!in_array($encryption,['ssl','starttls'],true))throw new ProbeFailure('tls');
            if(!str_ends_with($message->bytes,"\r\n"))throw new ProbeFailure('protocol');
            if($username==='' || $password==='' || strlen($username)>190 || strlen($password)>1024
                || str_contains($username,"\0") || str_contains($password,"\0"))throw new ProbeFailure('credential');
            if(($encryption==='ssl')!==$channel->isTls())throw new ProbeFailure('tls');
            $authorize();
            $phase='greeting';if(!$smtp->begin())return $result('not_submitted','protocol',$smtp->lastCode());
            $phase='hello';if(!$smtp->hello())return $result('not_submitted','protocol',$smtp->lastCode());
            if($encryption==='starttls') {
                $phase='upgrade';if(!$smtp->startTLS())return $result('not_submitted','upgrade',$smtp->lastCode());
                $phase='hello';if(!$smtp->hello())return $result('not_submitted','protocol',$smtp->lastCode());
            }
            $phase='authentication';if(!$smtp->authenticate($username,$password))return $result('not_submitted','authentication',$smtp->lastCode());
            $phase='sender';if(!$smtp->mail($message->snapshot->from))return $refused();
            foreach($message->snapshot->envelopeRecipients() as $recipient) {
                $phase='recipient';
                if(!$smtp->recipient($recipient))return $refused();
            }
            // The maintained iterator emits CRLF for every logical line,
            // including an empty final element. Remove exactly one terminal
            // CRLF from its input so DATA equals the saved MIME byte-for-byte.
            $phase='data';$ok=$smtp->data(substr($message->bytes,0,-2));
            if($smtp->terminatorAttempted()) {
                $phase='confirmation';$code=$smtp->lastCode();
                if($ok && $code===250)return $result('accepted','accepted',250);
                if($code!==null && $code>=400 && $code<=599)return $result('rejected','server_rejected',$code);
                return $result('unknown','protocol',$code);
            }
            return $refused();
        } catch(\Throwable $e) {
            if($smtp->terminatorAttempted())$phase='confirmation';
            $reason=$e instanceof ProbeFailure?$e->getMessage():'connection';
            return $result($smtp->terminatorAttempted()?'unknown':'not_submitted',$reason);
        } finally {
            // Closing cannot undo a verified final250 or replace an uncertain
            // outcome. Do not QUIT while the peer might still be reading DATA.
            try {$smtp->close();} catch(\Throwable) {}
        }
    }
}
