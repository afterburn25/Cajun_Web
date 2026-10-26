<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\SshAccess;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One immutable audit record for the access trail: who, what target,
 * which decision/action, and when — per A-10's requirement to retain
 * the real staff identity for every launch, denial, open and close.
 * Detail strings carry reasons, never secrets, tokens or terminal data.
 */
final readonly class AuditEvent
{
    public function __construct(
        public string $at,
        public string $staffId,
        public string $clientId,
        public string $serviceId,
        public string $action,
        public string $detail,
    ) {
        foreach (['at', 'staffId', 'clientId', 'serviceId', 'action'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('An audit event needs ' . $field . '.');
            }
        }
    }
}
