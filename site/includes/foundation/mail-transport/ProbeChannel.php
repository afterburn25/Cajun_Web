<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailTransport;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Internal transport boundary. Implementations must bound total bytes, lines and elapsed time. */
interface ProbeChannel
{
    public function line(): string;
    public function write(#[\SensitiveParameter] string $data): void;
    public function startTls(): void;
    public function isTls(): bool;
    public function close(): void;
}
