<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Finite local codes only; no upstream exception chain or server text. */
final class ReceivingFailure extends \RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct(in_array($reason, ['connection','tls','protocol','limit','timeout','authentication','mechanism','credential','upgrade','access_changed','mailbox_changed','folder','flags'], true) ? $reason : 'protocol');
    }
}
