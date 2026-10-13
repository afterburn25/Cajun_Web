<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Hosting;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

require_once __DIR__ . '/HostingPackage.php';
require_once __DIR__ . '/OperationResult.php';

/**
 * HC-08 — a queued upgrade, downgrade or renewal for one hosting account.
 *
 * Changes are queued first and applied later through an honest recorded
 * result — a plan never silently retargets. Downgrades carry the current
 * usage snapshot so `approve()` can refuse a move that would not fit
 * (e.g. 40 GB in use against a 20 GB target), instead of applying and
 * breaking the account. A queued change can be cancelled by its own actor
 * while still pending.
 *
 * @internal Roadmap HC-08; additively inserted.
 */
final class PlanChange
{
    public const UPGRADE = 'upgrade';
    public const DOWNGRADE = 'downgrade';
    public const RENEWAL = 'renewal';

    public const QUEUED = 'queued';
    public const APPLIED = 'applied';
    public const REFUSED = 'refused';
    public const CANCELLED = 'cancelled';

    private string $status = self::QUEUED;
    private ?string $reason = null;

    public function __construct(
        public readonly string $id,
        public readonly string $accountId,
        public readonly string $kind,
        public readonly HostingPackage $target,
        public readonly string $requestedBy,
        public readonly int $requestedAt,
        public readonly int $effectiveAt,
    ) {
        foreach (['id' => $id, 'accountId' => $accountId, 'requestedBy' => $requestedBy] as $f => $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException("Plan change {$f} must not be empty.");
            }
        }
        if (!in_array($kind, [self::UPGRADE, self::DOWNGRADE, self::RENEWAL], true)) {
            throw new InvalidArgumentException("Unknown plan change kind '{$kind}'.");
        }
        if ($kind === self::RENEWAL && $effectiveAt <= $requestedAt) {
            throw new InvalidArgumentException('A renewal must take effect after it is requested.');
        }
        if ($kind === self::DOWNGRADE && $target->sku === '') {
            throw new InvalidArgumentException('A downgrade needs a target package.');
        }
    }

    public function status(): string
    {
        return $this->status;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    /** Whether the target package can hold the current usage. */
    public function targetFits(array $usage): bool
    {
        return $this->target->fits($usage);
    }

    /**
     * Approve and record the provider result. For downgrades the caller
     * must pass current usage; a change that cannot fit is refused rather
     * than applied.
     */
    public function approve(array $currentUsage, OperationResult $result, int $at): void
    {
        if ($this->status !== self::QUEUED) {
            throw new InvalidArgumentException('Only a queued change can be decided.');
        }
        if ($this->kind === self::DOWNGRADE && $result->ok() && !$this->targetFits($currentUsage)) {
            $this->status = self::REFUSED;
            $this->reason = 'Current usage exceeds the target package limits.';
            return;
        }
        $this->status = $result->ok() ? self::APPLIED : self::REFUSED;
        $this->reason = $result->error;
    }

    public function cancel(string $actor, int $at): void
    {
        if ($this->status !== self::QUEUED) {
            throw new InvalidArgumentException('Only a queued change can be cancelled.');
        }
        if ($actor !== $this->requestedBy) {
            throw new InvalidArgumentException('Only the requester can cancel a queued change.');
        }
        $this->status = self::CANCELLED;
    }
}
