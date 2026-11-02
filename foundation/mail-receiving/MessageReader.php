<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Production entry: pinned public destination and exact immutable message identity. */
final class MessageReader
{
    public static function read(string $host,int $port,string $encryption,string $address,
        #[\SensitiveParameter] string $user,#[\SensitiveParameter] string $password,
        string $validity,string $uid,callable $authorize,string $folder='INBOX'): array
    {
        $stream=null;
        try {
            try {$authorize();}catch(\Throwable){throw new ReceivingFailure('access_changed');}
            ReadSession::identity($validity,$uid); ReadSession::folder($folder);
            $stream=new ReceivingStream($host,$port,$encryption,$address,ReadProfile::Message);
            $stream->open($encryption,$host,$port,20);
            return (new ReadSession($stream,$authorize,ReadProfile::Message))->message($encryption,$user,$password,$validity,$uid,$folder);
        } catch(ReceivingFailure $e) {throw $e;}
        catch(\Throwable) {throw new ReceivingFailure('connection');}
        finally {$stream?->close();}
    }
}
