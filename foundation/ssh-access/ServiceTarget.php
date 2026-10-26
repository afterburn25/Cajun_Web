<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\SshAccess;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * An approved SSH target bound to a registered client service. The
 * adapter constructs these only from the service registry — arbitrary
 * host/port input from a request is never a target (A-10: prevent
 * cross-client and internal-network connections).
 */
final readonly class ServiceTarget
{
    public function __construct(
        public string $serviceId,
        public string $host,
        public int $port,
        public string $osAccount,
        public string $serviceType,
    ) {
        if (trim($serviceId) === '') {
            throw new InvalidArgumentException('A target needs a registered service id.');
        }
        if (!filter_var($host, FILTER_VALIDATE_IP) && !preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $host)) {
            throw new InvalidArgumentException('Target host must be an IP address or DNS name.');
        }
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Target port out of range.');
        }
        if (trim($osAccount) === '' || strlen($osAccount) > 64 || preg_match('/\s/', $osAccount)) {
            throw new InvalidArgumentException('OS account must be a short non-space identifier.');
        }
        if (trim($serviceType) === '') {
            throw new InvalidArgumentException('A target needs its provider service type (e.g. unmanaged-vps).');
        }
    }
}
