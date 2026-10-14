<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\MailTransport\EndpointPolicy;
use DirectoryTree\ImapEngine\Connection\Streams\StreamInterface;

/** A bounded read-only receiving stream with two fixed literal profiles. No proxy, CA override or DNS at connect time. */
final class ReceivingStream implements StreamInterface
{
    private $socket = null;
    private bool $tls = false;
    private bool $used = false;
    private float $deadline = 0;
    private int $received = 0;
    private int $sent = 0;
    private int $lines = 0;
    private int $writes = 0;

    public function __construct(private string $host, private int $port, private string $encryption, private string $address, private ReadProfile $profile=ReadProfile::Headers)
    {
        $this->host = EndpointPolicy::host($host);
        EndpointPolicy::port('imap', $port, $encryption);
        $this->address = EndpointPolicy::addresses([$address])[0];
    }

    public function open(string $transport, string $host, int $port, int $timeout, array $options = []): bool
    {
        if ($this->used || EndpointPolicy::host($host) !== $this->host || $port !== $this->port || $transport !== $this->encryption || $timeout !== 20 || $options !== []) throw new ReceivingFailure('protocol');
        $this->used = true;
        $this->deadline = hrtime(true) / 1e9 + 20.0;
        if (!extension_loaded('openssl')) throw new ReceivingFailure('tls');
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false,
            'peer_name' => $this->host, 'SNI_enabled' => true, 'disable_compression' => true,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
        ]]);
        $destination = 'tcp://'.(str_contains($this->address, ':') ? '['.$this->address.']' : $this->address).':'.$this->port;
        try { $this->socket = @stream_socket_client($destination, $errno, $error, 5, STREAM_CLIENT_CONNECT, $context); }
        catch (\Throwable) { throw new ReceivingFailure('connection'); }
        if (!is_resource($this->socket)) throw new ReceivingFailure('connection');
        try {
            $this->remaining();
            if ($this->encryption === 'ssl') $this->setSocketSetCrypto(true, null);
            return true;
        } catch (\Throwable $e) { $this->close(); throw $e; }
    }

    private function remaining(): void
    {
        if (!$this->opened()) throw new ReceivingFailure('connection');
        $remaining = min(5.0, $this->deadline - hrtime(true) / 1e9);
        if ($remaining <= 0) throw new ReceivingFailure('timeout');
        $seconds = (int)$remaining;
        if (!stream_set_timeout($this->socket, $seconds, max(1, (int)(($remaining - $seconds) * 1000000)))) throw new ReceivingFailure('connection');
    }

    private function received(string|false $data): string
    {
        $meta = $this->meta();
        $this->remaining();
        if ($meta['timed_out'] ?? false) throw new ReceivingFailure('timeout');
        if ($data === false || $data === '') throw new ReceivingFailure('connection');
        $this->received += strlen($data);
        if ($this->received > 393216) throw new ReceivingFailure('limit');
        return $data;
    }

    /** Literals are exact byte reads, including when TLS fragments the payload. */
    public function read(int $length): string|false
    {
        if ($length < 0 || $length > $this->profile->literalBytes()) throw new ReceivingFailure('limit');
        $data = '';
        while (strlen($data) < $length) {
            $this->remaining();
            try { $part = @fread($this->socket, $length - strlen($data)); }
            catch (\Throwable) { throw new ReceivingFailure('connection'); }
            $data .= $this->received($part);
        }
        return $data;
    }

    /** One CRLF-terminated physical protocol line; literal contents use read(). */
    public function fgets(): string|false
    {
        $this->remaining();
        if (++$this->lines > 256) throw new ReceivingFailure('limit');
        try { $line = @fgets($this->socket, 8195); }
        catch (\Throwable) { throw new ReceivingFailure('connection'); }
        $line = $this->received($line);
        if (strlen($line) > 8192) throw new ReceivingFailure('limit');
        if (!str_ends_with($line, "\r\n") || preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', substr($line, 0, -2))) throw new ReceivingFailure('protocol');
        return $line;
    }

    public function fwrite(#[\SensitiveParameter] string $data): int|false
    {
        $length = strlen($data); $this->sent += $length;
        if (++$this->writes > 16 || $length > 4096 || $this->sent > 8192) throw new ReceivingFailure('limit');
        if (!str_ends_with($data, "\r\n") || preg_match('/[\x00\r\n]/', substr($data, 0, -2))) throw new ReceivingFailure('protocol');
        $at = 0;
        while ($at < $length) {
            $this->remaining();
            try { $written = @fwrite($this->socket, substr($data, $at)); }
            catch (\Throwable) { throw new ReceivingFailure('connection'); }
            if ($written === false || $written === 0) throw new ReceivingFailure(($this->meta()['timed_out'] ?? false) ? 'timeout' : 'connection');
            $at += $written;
        }
        $this->remaining();
        return $length;
    }

    public function setSocketSetCrypto(bool $enabled, ?int $method): bool|int
    {
        $this->remaining();
        if (!$enabled || $method !== null || $this->tls || ($this->meta()['unread_bytes'] ?? 0) > 0) throw new ReceivingFailure('tls');
        try { $ok = @stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT); }
        catch (\Throwable) { throw new ReceivingFailure('tls'); }
        if ($ok !== true || !in_array($this->meta()['crypto']['protocol'] ?? '', ['TLSv1.2','TLSv1.3'], true)) throw new ReceivingFailure('tls');
        $this->tls = true;
        $this->remaining();
        return true;
    }

    public function meta(): array { return $this->opened() ? stream_get_meta_data($this->socket) : ['eof'=>true]; }
    public function opened(): bool { return is_resource($this->socket); }
    public function isTls(): bool { return $this->opened() && $this->tls; }
    public function assertWithinBudget(): void { $this->remaining(); }
    public function setTimeout(int $seconds): bool { throw new ReceivingFailure('protocol'); }
    public function close(): void { if ($this->opened()) fclose($this->socket); $this->socket = null; $this->tls = false; }
    public function __destruct() { $this->close(); }
}
