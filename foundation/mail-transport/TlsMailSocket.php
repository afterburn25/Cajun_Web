<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\MailTransport;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * Production `MailSocket` over PHP stream sockets. TLS peer verification is
 * mandatory and never disabled; timeouts and a line cap keep a hostile or
 * wedged peer from stalling a request indefinitely.
 */
final class TlsMailSocket implements MailSocket
{
    public const MAX_LINE = 8192;

    /** @var resource */
    private $stream;

    private function __construct($stream, private int $readTimeoutSeconds)
    {
        $this->stream = $stream;
        stream_set_timeout($stream, $readTimeoutSeconds);
        stream_set_blocking($stream, true);
    }

    /**
     * @param string $encryption 'ssl' (connect already encrypted) or
     *        'starttls' (connect plain; caller must issue startTls() before
     *        authenticating). Plaintext authentication is never allowed —
     *        the session layer enforces it; this object only opens bytes.
     */
    public static function open(string $host, int $port, string $encryption, int $timeoutSeconds = 20): self
    {
        if ($timeoutSeconds < 1 || $timeoutSeconds > 60) {
            throw new MailTransportException(MailTransportException::REFUSED, 'Connection timeout must be 1–60 seconds.');
        }
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
        ]]);
        $scheme = $encryption === 'ssl' ? 'tls' : 'tcp';
        $target = "{$scheme}://{$host}:{$port}";
        set_error_handler(static function (): bool { return true; });
        try {
            $stream = stream_socket_client($target, $errno, $errstr, $timeoutSeconds, STREAM_CLIENT_CONNECT, $context);
        } finally {
            restore_error_handler();
        }
        if ($stream === false) {
            throw new MailTransportException(MailTransportException::CONNECT, 'Cannot reach the mail server.');
        }
        return new self($stream, $timeoutSeconds);
    }

    public function readLine(int $maxBytes = self::MAX_LINE): string
    {
        $line = '';
        while (strlen($line) < $maxBytes) {
            $chunk = fgets($this->stream, $maxBytes + 1);
            if ($chunk === false) {
                $this->failRead();
            }
            $line .= $chunk;
            if (str_ends_with($line, "\n")) {
                break;
            }
        }
        if (strlen($line) > $maxBytes) {
            throw new MailTransportException(MailTransportException::PROTOCOL, 'Server response exceeded the line limit.');
        }
        if (!str_ends_with($line, "\n")) {
            throw new MailTransportException(MailTransportException::PROTOCOL, 'Truncated server line.');
        }
        return rtrim($line, "\r\n");
    }

    public function readBytes(int $count): string
    {
        if ($count < 0 || $count > 16 * 1024 * 1024) {
            throw new MailTransportException(MailTransportException::REFUSED, 'Literal size is out of bounds.');
        }
        $data = '';
        while (strlen($data) < $count) {
            $chunk = fread($this->stream, $count - strlen($data));
            if ($chunk === false || $chunk === '') {
                $this->failRead();
            }
            $data .= $chunk;
        }
        return $data;
    }

    public function write(string $data): void
    {
        if (fwrite($this->stream, $data) === false) {
            throw new MailTransportException(MailTransportException::CLOSED, 'Cannot write to the mail server.');
        }
    }

    public function startTls(): void
    {
        set_error_handler(static function (): bool { return true; });
        try {
            $ok = stream_socket_enable_crypto(
                $this->stream,
                true,
                STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
            );
        } finally {
            restore_error_handler();
        }
        if ($ok !== true) {
            throw new MailTransportException(MailTransportException::TLS, 'TLS negotiation failed or the certificate could not be verified.');
        }
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    public function isOpen(): bool
    {
        return is_resource($this->stream) && !feof($this->stream);
    }

    private function failRead(): never
    {
        $meta = stream_get_meta_data($this->stream);
        if ($meta['timed_out'] ?? false) {
            throw new MailTransportException(MailTransportException::TIMEOUT, 'The mail server stopped responding.');
        }
        throw new MailTransportException(MailTransportException::CLOSED, 'The mail server closed the connection.');
    }
}
