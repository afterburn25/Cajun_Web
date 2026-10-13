<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailTransport;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Only local finite codes; never use server replies or credential-bearing exception chains. */
final class ProbeFailure extends \RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct(in_array($reason,['connection','tls','protocol','limit','timeout','authentication','mechanism','credential','upgrade','access_changed'],true)?$reason:'connection');
    }
}
