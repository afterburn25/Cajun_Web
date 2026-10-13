<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Hosting;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * HC-08 — a grant of control-panel (StackCP-style) access to one hosting
 * account. The grant names the account, the grantee (client or staff
 * reference), an access level, the issuer, optional bounded expiry and
 * revocation. Panel access is always an explicit grant — never implied by
 * a relationship that happens to exist.
 *
 * Levels: `manage` (full panel) or `read` (status/usage view only).
 *
 * @internal Roadmap HC-08; additively inserted.
 */
final class StackCpGrant
{
    public const LEVEL_MANAGE = 'manage';
    public const LEVEL_READ = 'read';

    private ?int $revokedAt = null;

    public function __construct(
        public readonly string $id,
        public readonly string $accountId,
        public readonly string $granteeRef,
        public readonly string $level,
        public readonly string $issuedBy,
        public readonly int $issuedAt,
        public readonly ?int $expiresAt = null,
    ) {
        foreach (['id' => $id, 'accountId' => $accountId, 'granteeRef' => $granteeRef, 'issuedBy' => $issuedBy] as $f => $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException("Grant {$f} must not be empty.");
            }
        }
        if (!in_array($level, [self::LEVEL_MANAGE, self::LEVEL_READ], true)) {
            throw new InvalidArgumentException("Unknown panel access level '{$level}'.");
        }
        if ($expiresAt !== null && $expiresAt <= $issuedAt) {
            throw new InvalidArgumentException('Grant expiry must be after issuance.');
        }
    }

    public function active(int $now): bool
    {
        if ($this->revokedAt !== null) {
            return false;
        }
        return $this->expiresAt === null || $now < $this->expiresAt;
    }

    /** Whether the grant currently authorizes full panel management. */
    public function canManage(int $now): bool
    {
        return $this->active($now) && $this->level === self::LEVEL_MANAGE;
    }

    public function revoke(int $at): void
    {
        if ($this->revokedAt !== null) {
            throw new InvalidArgumentException('Grant is already revoked.');
        }
        $this->revokedAt = $at;
    }

    public function revokedAt(): ?int
    {
        return $this->revokedAt;
    }
}
