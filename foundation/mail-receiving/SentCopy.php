<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Production entry: pinned public destination and an exact bounded MIME copy to one folder. */
final class SentCopy
{
    public static function append(string $host,int $port,string $encryption,string $address,
        #[\SensitiveParameter] string $user,#[\SensitiveParameter] string $password,
        string $folder,#[\SensitiveParameter] string $message,callable $authorize,int $maxBytes=65536): void
    {
        $stream=null;
        try {
            try {$authorize();}catch(\Throwable){throw new ReceivingFailure('access_changed');}
            AppendSession::folder($folder);AppendSession::message($message,$maxBytes);
            $stream=new ReceivingStream($host,$port,$encryption,$address,ReadProfile::Append);
            $stream->open($encryption,$host,$port,20);
            (new AppendSession($stream,$authorize,$maxBytes))->append($encryption,$user,$password,$folder,$message);
        } catch(ReceivingFailure $e) {throw $e;}
        catch(\Throwable) {throw new ReceivingFailure('connection');}
        finally {$stream?->close();}
    }
}
