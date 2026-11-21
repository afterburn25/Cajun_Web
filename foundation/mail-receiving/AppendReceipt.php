<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Immutable, content-free proof that the server returned tagged OK. */
final readonly class AppendReceipt
{
    public bool $accepted;

    public function __construct(public bool $authorityCurrent)
    {
        $this->accepted = true;
    }
}
