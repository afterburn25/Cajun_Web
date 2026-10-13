<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Webmail;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * A-06 — a staff member's authorization to use one mailbox.
 *
 * Mailbox access is an explicit grant, never implied by being staff or by
 * owning the account row — A-05's mailbox-assignment requirement maps here.
 * The grant records the issuer and supports revocation; expiry is optional
 * but bounded when present.
 *
 * @internal Roadmap A-06; additively inserted.
 */
final class MailboxGrant
{
    private ?int $revokedAt = null;

    public function __construct(
        public readonly string $id,
        public readonly string $mailboxId,
        public readonly string $staffId,
        public readonly string $issuedBy,
        public readonly int $issuedAt,
        public readonly ?int $expiresAt = null,
    ) {
        foreach (['id' => $id, 'mailboxId' => $mailboxId, 'staffId' => $staffId, 'issuedBy' => $issuedBy] as $f => $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException("Grant {$f} must not be empty.");
            }
        }
        if ($expiresAt !== null && $expiresAt <= $issuedAt) {
            throw new InvalidArgumentException('Grant expiry must be after issuance.');
        }
    }

    /** Whether the grant currently authorizes mailbox use. */
    public function active(int $now): bool
    {
        if ($this->revokedAt !== null) {
            return false;
        }
        return $this->expiresAt === null || $now < $this->expiresAt;
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
