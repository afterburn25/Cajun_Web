<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Production entry: a previously validated public DNS address and current authority are required. */
final class InboxReader
{
    public static function read(string $host, int $port, string $encryption, string $address,
        #[\SensitiveParameter] string $user, #[\SensitiveParameter] string $password, callable $authorize): array
    {
        $stream=null;
        try {
            try { $authorize(); } catch(\Throwable) { throw new ReceivingFailure('access_changed'); }
            $stream=new ReceivingStream($host,$port,$encryption,$address);
            $stream->open($encryption,$host,$port,20);
            return (new HeaderSession($stream,$authorize))->read($encryption,$user,$password);
        } catch(ReceivingFailure $e) { throw $e; }
        catch(\Throwable) { throw new ReceivingFailure('connection'); }
        finally { $stream?->close(); }
    }
}
