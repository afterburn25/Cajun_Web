<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\SshAccess;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * The approved-target lookup. The site adapter implements this over the
 * registered client-service table; tests use a fixed map. Returning
 * null means "not an approved service" — the broker treats it as a
 * denial, never a pass-through to an arbitrary address.
 */
interface TargetRegistry
{
    public function lookup(string $serviceId): ?ServiceTarget;
}
