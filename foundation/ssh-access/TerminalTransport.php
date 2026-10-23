<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\SshAccess;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * The transport boundary the vetted gateway/adapter implements — the
 * only place an actual SSH connection can exist. The component ships
 * with no implementation: until the architecture/security gate passes
 * there is no supported transport, and the broker returns an honest
 * "unavailable" denial rather than simulating a terminal.
 */
interface TerminalTransport
{
    /**
     * Open a real session to an approved target. Throws
     * TransportException on any failure — the broker audits the denial.
     */
    public function open(ServiceTarget $target, TerminalSession $session): void;
}

final class TransportException extends \RuntimeException
{
}
