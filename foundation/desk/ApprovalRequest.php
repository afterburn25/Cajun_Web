<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Desk;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

require_once __DIR__ . '/TraceLink.php';

/**
 * HC-12 — role-based approval for irreversible operations (refunds,
 * deprovisioning, purges).
 *
 * `requested → approved | denied | expired`. Approval requires a
 * *different* actor holding the required role — the requester can never
 * approve their own destructive action. Approvals expire so a stale
 * decision can't authorize a later attempt. Only `approved` requests
 * release a `FulfillmentJob`'s approval gate.
 *
 * @internal Roadmap HC-12; additively inserted.
 */
final class ApprovalRequest
{
    public const REQUESTED = 'requested';
    public const APPROVED = 'approved';
    public const DENIED = 'denied';
    public const EXPIRED = 'expired';

    private string $status = self::REQUESTED;
    private ?string $decidedBy = null;
    private ?string $reason = null;

    public function __construct(
        public readonly string $id,
        public readonly string $operationKind,
        public readonly TraceLink $subject,
        public readonly string $requestedBy,
        public readonly int $requestedAt,
        public readonly int $expiresAt,
        public readonly string $approverRole = 'owner',
    ) {
        foreach (['id' => $id, 'operationKind' => $operationKind, 'requestedBy' => $requestedBy, 'approverRole' => $approverRole] as $f => $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException("Approval {$f} must not be empty.");
            }
        }
        if ($expiresAt <= $requestedAt) {
            throw new InvalidArgumentException('An approval must expire after it is requested.');
        }
    }

    public function status(int $now): string
    {
        if ($this->status === self::REQUESTED && $now >= $this->expiresAt) {
            return self::EXPIRED;
        }
        return $this->status;
    }

    public function approved(int $now): bool
    {
        return $this->status($now) === self::APPROVED;
    }

    public function decidedBy(): ?string
    {
        return $this->decidedBy;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    /**
     * Approve. The approver must differ from the requester and hold the
     * required role — enforced by the caller passing the actor's role.
     */
    public function approve(string $actor, string $actorRole, int $at): void
    {
        $this->decide($actor, $actorRole, $at, true);
    }

    public function deny(string $actor, string $actorRole, int $at, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A denial needs a recorded reason.');
        }
        $this->reason = $reason;
        $this->decide($actor, $actorRole, $at, false);
    }

    private function decide(string $actor, string $actorRole, int $at, bool $grant): void
    {
        if ($this->status($at) !== self::REQUESTED) {
            throw new LogicException('Only a live request can be decided.');
        }
        if ($actor === $this->requestedBy) {
            throw new LogicException('The requester cannot decide their own request.');
        }
        if ($actorRole !== $this->approverRole) {
            throw new LogicException("Approval requires role '{$this->approverRole}'.");
        }
        $this->decidedBy = $actor;
        $this->status = $grant ? self::APPROVED : self::DENIED;
    }
}
