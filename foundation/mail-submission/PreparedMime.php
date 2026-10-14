<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailSubmission;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/SubmissionSnapshot.php';
require_once __DIR__.'/PreparationFailure.php';

/** Private exact MIME bytes and immutable envelope; no persistence or dispatch. */
final readonly class PreparedMime
{
    public const MAX_BYTES=65536;
    private function __construct(public SubmissionSnapshot $snapshot,public string $bytes,public string $sha256) {}

    public static function fromSnapshot(SubmissionSnapshot $snapshot):self
    {
        foreach(['ctype_alnum','filter_var','hash','json_encode'] as $function) {
            if(!function_exists($function))throw new PreparationFailure('unsupported_runtime');
        }
        require_once __DIR__.'/OfflineMailer.php';
        if(OfflineMailer::VERSION!=='7.1.1')throw new PreparationFailure('unsupported_runtime');
        try {
            $mail=new OfflineMailer();
            $mail->setFrom($snapshot->from,'',false);
            $mail->Sender=$snapshot->from;
            $mail->MessageID=$snapshot->messageId;
            $mail->MessageDate=gmdate('D, d M Y H:i:s',$snapshot->preparedEpoch).' +0000';
            foreach(['to'=>'addAddress','cc'=>'addCC','bcc'=>'addBCC'] as $field=>$method) {
                foreach($snapshot->content->$field as $address) {
                    if(!$mail->$method($address))throw new PreparationFailure('recipient_mismatch');
                }
            }
            // PHPMailer deduplicates local parts case-insensitively. Refuse that
            // unsupported combination explicitly rather than drop any recipient.
            foreach(['to'=>'getToAddresses','cc'=>'getCcAddresses','bcc'=>'getBccAddresses'] as $field=>$method) {
                if(array_column($mail->$method(),0)!==$snapshot->content->$field)throw new PreparationFailure('recipient_mismatch');
            }
            $mail->Subject=$mail->literalSubject($snapshot->content->subject);
            $mail->Body=str_replace("\n","\r\n",$snapshot->content->body);
            $mail->isHTML(false);
            if(!$mail->preSend() || $mail->getLastMessageID()!==$snapshot->messageId)throw new PreparationFailure('encoding_failed');
            $bytes=$mail->getSentMIMEMessage();
            if(strlen($bytes)>self::MAX_BYTES || !str_contains($bytes,"\r\n\r\n")
                || preg_match('/(?<!\r)\n|\r(?!\n)|[^\x09\x0A\x0D\x20-\x7E]/',$bytes))throw new PreparationFailure('message_bounds');
            foreach(explode("\r\n",$bytes) as $line)if(strlen($line)>998)throw new PreparationFailure('message_bounds');
            $headers=explode("\r\n\r\n",$bytes,2)[0];
            if(preg_match('/^(?:Bcc|Resent-Bcc|Reply-To|Return-Path|Disposition-Notification-To):/mi',$headers))throw new PreparationFailure('encoding_failed');
            return new self($snapshot,$bytes,hash('sha256',$bytes));
        } catch(PreparationFailure $e) {
            throw $e;
        } catch(\Throwable) {
            // Dependency exceptions may contain addresses or message content.
            // Do not retain them as previous exceptions or diagnostic payloads.
            throw new PreparationFailure('encoding_failed');
        }
    }
}
