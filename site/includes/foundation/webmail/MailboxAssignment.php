<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Webmail;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * A-06 — a temporal record assigning one mailbox to one staff member.
 *
 * This is the *lifecycle* half of mailbox authorization: who was granted
 * access, by whom, when it took effect, and when it ends or was revoked.
 * It is NOT the per-request authorization authority — the canonical
 * `CajunWebPro\Foundation\Access\MailboxGrant` (a server-loaded
 * capability with `canRead`/`canSend`/`enabled`) and `PermissionPolicy`
 * decide each request. The bridge: an assignment that `active($now)`
 * is true for may produce read/send capabilities; an inactive one
 * produces none. This record can never widen a denied request.
 *
 * Mailbox access is assigned, never implied by being staff or by owning
 * the account row. Times must be non-negative; an assignment is active
 * only at or after `issuedAt` — future issuance is representable but
 * authorizes nothing yet.
 *
 * @internal Roadmap A-06; additively inserted.
 */
final class MailboxAssignment
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
                throw new InvalidArgumentException("Assignment {$f} must not be empty.");
            }
        }
        if ($issuedAt < 0) {
            throw new InvalidArgumentException('Assignment times must be non-negative.');
        }
        if ($expiresAt !== null && $expiresAt <= $issuedAt) {
            throw new InvalidArgumentException('Assignment expiry must be after issuance.');
        }
    }

    /**
     * Whether the assignment currently authorizes mailbox use. Active
     * only when issued (now >= issuedAt), unexpired and unrevoked.
     */
    public function active(int $now): bool
    {
        if ($now < 0) {
            throw new InvalidArgumentException('Now must be non-negative.');
        }
        if ($this->revokedAt !== null || $now < $this->issuedAt) {
            return false;
        }
        return $this->expiresAt === null || $now < $this->expiresAt;
    }

    public function revoke(int $at): void
    {
        if ($at < $this->issuedAt) {
            throw new InvalidArgumentException('Cannot revoke before issuance.');
        }
        if ($this->revokedAt !== null) {
            throw new InvalidArgumentException('Assignment is already revoked.');
        }
        $this->revokedAt = $at;
    }

    public function revokedAt(): ?int
    {
        return $this->revokedAt;
    }
}
