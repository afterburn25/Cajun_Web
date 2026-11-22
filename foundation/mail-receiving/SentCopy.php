<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/AppendReceipt.php';
require_once __DIR__.'/AppendSession.php';

/** Production entry: pinned public destination and one exact bounded MIME copy. */
final class SentCopy
{
    public const MAX_MESSAGE_BYTES = 1048576;

    public static function append(string $host, int $port, string $encryption, string $address,
        #[\SensitiveParameter] string $user, #[\SensitiveParameter] string $password,
        string $folder, #[\SensitiveParameter] string $message, callable $authorize): AppendReceipt
    {
        $stream=null;
        try {
            try { $authorize(); } catch (\Throwable) { throw new ReceivingFailure('access_changed'); }
            AppendSession::folder($folder);
            AppendSession::message($message,self::MAX_MESSAGE_BYTES);
            $stream=new ReceivingStream($host,$port,$encryption,$address,ReadProfile::Append);
            $stream->open($encryption,$host,$port,20);
            return (new AppendSession($stream,$authorize,self::MAX_MESSAGE_BYTES))
                ->append($encryption,$user,$password,$folder,$message);
        } catch (ReceivingFailure $e) { throw $e; }
        catch (\Throwable) { throw new ReceivingFailure('connection'); }
        finally { $stream?->close(); }
    }
}
