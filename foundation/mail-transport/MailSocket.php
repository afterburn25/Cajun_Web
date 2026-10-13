<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\MailTransport;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * Transport boundary for mail protocols: an ordered byte stream that can
 * upgrade to TLS in place. Production adapters inject the stream-socket
 * implementation; tests inject scripted transcripts — no network access is
 * performed by the protocol layer itself.
 */
interface MailSocket
{
    /**
     * Read one CRLF-terminated line (CRLF stripped). Throws
     * MailTransportException on timeout, closed peer, or line over the cap.
     */
    public function readLine(int $maxBytes): string;

    /**
     * Read exactly $count bytes (IMAP literals can hold arbitrary binary).
     * Throws MailTransportException on timeout or short read.
     */
    public function readBytes(int $count): string;

    public function write(string $data): void;

    /**
     * Upgrade this connection to TLS. The implementation must verify the
     * peer certificate and name; a failed or unverified upgrade throws.
     */
    public function startTls(): void;

    public function close(): void;

    public function isOpen(): bool;
}
