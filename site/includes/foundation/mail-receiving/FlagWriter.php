<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Production entry: pinned public destination and a fixed \Seen write to one exact message. */
final class FlagWriter
{
    public static function mark(string $host,int $port,string $encryption,string $address,
        #[\SensitiveParameter] string $user,#[\SensitiveParameter] string $password,
        string $folder,string $validity,string $uid,bool $seen,callable $authorize): array
    {
        $stream=null;
        try {
            try {$authorize();}catch(\Throwable){throw new ReceivingFailure('access_changed');}
            ReadSession::folder($folder);ReadSession::identity($validity,$uid);
            $stream=new ReceivingStream($host,$port,$encryption,$address,ReadProfile::Flags);
            $stream->open($encryption,$host,$port,20);
            return (new FlagSession($stream,$authorize))->mark($encryption,$user,$password,$folder,$validity,$uid,$seen);
        } catch(ReceivingFailure $e) {throw $e;}
        catch(\Throwable $e) {\error_log('flag transport refused: '.$e->getMessage().' @ '.basename((string)$e->getFile()).':'.$e->getLine());throw new ReceivingFailure('connection');}
        finally {$stream?->close();}
    }
}
