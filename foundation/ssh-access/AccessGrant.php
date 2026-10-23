<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\SshAccess;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * A client's explicit authorization for one staff member to open a
 * terminal on one service. Grants are scoped, expiring and revocable —
 * a revoked or expired grant can never produce a session, and the grant
 * records who issued it for audit attribution. Portal-login permission
 * alone never grants terminal access; the grant is a separate record.
 */
final class AccessGrant
{
    public const SCOPE_TERMINAL = 'terminal';

    private ?string $revokedAt = null;

    /**
     * @param string[] $scopes
     * @param string $expiresAt ISO-8601 expiry
     */
    public function __construct(
        public readonly string $id,
        public readonly string $staffId,
        public readonly string $clientId,
        public readonly string $serviceId,
        public readonly array $scopes,
        public readonly string $issuedBy,
        public readonly string $expiresAt,
        public readonly bool $clientAuthorized,
    ) {
        foreach (['id', 'staffId', 'clientId', 'serviceId', 'issuedBy'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('A grant needs ' . $field . '.');
            }
        }
        if ($scopes === []) {
            throw new InvalidArgumentException('A grant needs at least one scope.');
        }
        if (strtotime($expiresAt) === false) {
            throw new InvalidArgumentException('Grant expiry must be a parseable date.');
        }
        if (!$clientAuthorized) {
            throw new InvalidArgumentException('A grant requires recorded client authorization.');
        }
    }

    public function usableAt(int $now): bool
    {
        return $this->revokedAt === null && strtotime($this->expiresAt) > $now;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function revoke(string $at): self
    {
        if ($this->revokedAt !== null) {
            throw new LogicException('Grant is already revoked.');
        }
        $this->revokedAt = $at;
        return $this;
    }

    public function revoked(): bool
    {
        return $this->revokedAt !== null;
    }
}
