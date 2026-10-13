<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Hosting;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

require_once __DIR__ . '/HostingPackage.php';
require_once __DIR__ . '/OperationResult.php';

/**
 * HC-08 — lifecycle state machine for one shared-hosting account.
 *
 * pending → provisioning → active ⇄ suspending/suspended ⇄ resuming.
 * pending/provisioning → failed → retry() → pending (never auto-retries).
 * active/suspended → cancellation_requested → deprovisioning → deprovisioned.
 * pending → cancelled (pre-provisioning withdrawal only).
 *
 * Protections:
 *  - Suspension for payment lapse is refused before `graceUntil` and
 *    while a billing dispute is open — access and backups are preserved
 *    during disputes per policy.
 *  - Deprovisioning is a deliberate two-actor action: `requestCancellation`
 *    records one actor; `approveDeprovision` requires a *different* actor
 *    AND an explicit `retentionConfirmed` flag — a single account, or an
 *    approval without confirmed data retention, cannot destroy hosting.
 *  - Every transition records an attributed history entry; provider
 *    operation results are stored verbatim (including honest timeouts).
 *
 * @internal Roadmap HC-08; additively inserted.
 */
final class HostingAccount
{
    public const PENDING = 'pending';
    public const PROVISIONING = 'provisioning';
    public const ACTIVE = 'active';
    public const SUSPENDING = 'suspending';
    public const SUSPENDED = 'suspended';
    public const RESUMING = 'resuming';
    public const FAILED = 'failed';
    public const CANCELLATION_REQUESTED = 'cancellation_requested';
    public const DEPROVISIONING = 'deprovisioning';
    public const DEPROVISIONED = 'deprovisioned';
    public const CANCELLED = 'cancelled';

    private const TERMINAL = [self::DEPROVISIONED, self::CANCELLED];

    private string $status = self::PENDING;
    private ?string $providerRef = null;
    private ?string $primaryDomain = null;
    private ?string $cancellationRequester = null;
    private bool $disputeOpen = false;
    private ?int $graceUntil = null;
    /** @var list<array{status:string,at:int,actor:string,note:string}> */
    private array $history = [];
    /** @var list<OperationResult> */
    private array $operations = [];

    public function __construct(
        public readonly string $id,
        public readonly HostingPackage $package,
        public readonly string $clientRef,
        int $createdAt,
    ) {
        if (trim($id) === '' || trim($clientRef) === '') {
            throw new InvalidArgumentException('A hosting account needs an id and a client reference.');
        }
        $this->history[] = ['status' => self::PENDING, 'at' => $createdAt, 'actor' => '', 'note' => 'created'];
    }

    public function status(): string
    {
        return $this->status;
    }

    public function providerRef(): ?string
    {
        return $this->providerRef;
    }

    public function primaryDomain(): ?string
    {
        return $this->primaryDomain;
    }

    public function terminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    public function history(): array
    {
        return $this->history;
    }

    public function operations(): array
    {
        return $this->operations;
    }

    public function disputeOpen(): bool
    {
        return $this->disputeOpen;
    }

    // ── Provisioning ────────────────────────────────────────────

    /** Move pending → provisioning once cleared payment and checks exist. */
    public function beginProvisioning(string $actor, int $at): void
    {
        $this->requireStatus(self::PENDING);
        $this->move(self::PROVISIONING, $actor, $at, 'provisioning started');
    }

    /** Record the provider's result for the in-flight provisioning call. */
    public function provisionResult(OperationResult $result, string $actor, int $at): void
    {
        $this->requireStatus(self::PROVISIONING);
        $this->operations[] = $result;
        if ($result->ok()) {
            $this->providerRef = $result->receipt;
            $this->move(self::ACTIVE, $actor, $at, 'provisioned: ' . $result->receipt);
        } elseif ($result->outcome === OperationResult::TIMEOUT) {
            $this->move(self::FAILED, $actor, $at, 'provider timeout — reconcile before any retry');
        } else {
            $this->move(self::FAILED, $actor, $at, 'provisioning failed: ' . $result->error);
        }
    }

    /** A failed provisioning attempt returns to pending for an explicit retry. */
    public function retry(string $actor, int $at): void
    {
        $this->requireStatus(self::FAILED);
        $this->move(self::PENDING, $actor, $at, 'queued for retry after failure');
    }

    /** Link the primary domain once registration confirms. */
    public function linkDomain(string $domain, string $actor, int $at): void
    {
        $this->requireStatus(self::ACTIVE);
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/i', $domain)) {
            throw new InvalidArgumentException("Invalid primary domain '{$domain}'.");
        }
        $this->primaryDomain = strtolower($domain);
        $this->move(self::ACTIVE, $actor, $at, 'primary domain: ' . $this->primaryDomain);
    }

    // ── Suspension ──────────────────────────────────────────────

    /** Record a billing grace deadline; suspension cannot precede it. */
    public function setGraceUntil(int $until, string $actor, int $at): void
    {
        $this->requireStatus(self::ACTIVE);
        if ($until <= $at) {
            throw new InvalidArgumentException('A grace deadline must be in the future.');
        }
        $this->graceUntil = $until;
        $this->move(self::ACTIVE, $actor, $at, 'billing grace until ' . gmdate('c', $until));
    }

    public function openDispute(string $actor, int $at): void
    {
        $this->requireStatus(self::ACTIVE);
        $this->disputeOpen = true;
        $this->move(self::ACTIVE, $actor, $at, 'billing dispute opened — access preserved');
    }

    public function closeDispute(string $actor, int $at): void
    {
        $this->disputeOpen = false;
        $this->move($this->status, $actor, $at, 'billing dispute closed');
    }

    /**
     * Suspend an active account. Refused while a dispute is open or before
     * the recorded grace deadline.
     */
    public function suspend(string $actor, int $at, string $reason): void
    {
        $this->requireStatus(self::ACTIVE);
        if ($this->disputeOpen) {
            throw new LogicException('Cannot suspend while a billing dispute is open.');
        }
        if ($this->graceUntil !== null && $at < $this->graceUntil) {
            throw new LogicException('Cannot suspend before the grace deadline.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Suspension needs a recorded reason.');
        }
        $this->move(self::SUSPENDED, $actor, $at, 'suspended: ' . $reason);
    }

    public function resume(OperationResult $result, string $actor, int $at): void
    {
        $this->requireStatus(self::SUSPENDED);
        $this->operations[] = $result;
        if ($result->ok()) {
            $this->move(self::ACTIVE, $actor, $at, 'resumed: ' . $result->receipt);
        } elseif ($result->outcome === OperationResult::TIMEOUT) {
            $this->move(self::SUSPENDED, $actor, $at, 'resume timed out — reconcile before retry');
        } else {
            $this->move(self::SUSPENDED, $actor, $at, 'resume failed: ' . $result->error);
        }
    }

    // ── Cancellation and deprovisioning ─────────────────────────

    /** Withdraw a never-provisioned order. */
    public function cancelOrder(string $actor, int $at): void
    {
        if (!in_array($this->status, [self::PENDING, self::FAILED], true)) {
            throw new LogicException('Only a never-provisioned order can be cancelled.');
        }
        $this->move(self::CANCELLED, $actor, $at, 'order cancelled before provisioning');
    }

    /** Step one of two: request cancellation of a provisioned account. */
    public function requestCancellation(string $actor, int $at): void
    {
        if (!in_array($this->status, [self::ACTIVE, self::SUSPENDED], true)) {
            throw new LogicException('Cancellation can only be requested from active or suspended.');
        }
        $this->cancellationRequester = $actor;
        $this->move(self::CANCELLATION_REQUESTED, $actor, $at, 'cancellation requested — second approval required');
    }

    /**
     * Step two of two: approve deprovisioning. Requires a *different*
     * actor and confirmed data retention.
     */
    public function approveDeprovision(string $actor, bool $retentionConfirmed, int $at): void
    {
        $this->requireStatus(self::CANCELLATION_REQUESTED);
        if ($actor === $this->cancellationRequester) {
            throw new LogicException('Deprovisioning needs a second approver, not the requester.');
        }
        if (!$retentionConfirmed) {
            throw new LogicException('Deprovisioning requires confirmed data retention.');
        }
        $this->move(self::DEPROVISIONING, $actor, $at, 'deprovisioning approved with retention confirmed');
    }

    /** Record the provider's result for deprovisioning. */
    public function deprovisionResult(OperationResult $result, string $actor, int $at): void
    {
        $this->requireStatus(self::DEPROVISIONING);
        $this->operations[] = $result;
        if ($result->ok()) {
            $this->move(self::DEPROVISIONED, $actor, $at, 'deprovisioned: ' . $result->receipt);
        } else {
            $this->move(self::DEPROVISIONING, $actor, $at, 'deprovision attempt did not confirm — stays deprovisioning');
        }
    }

    // ── Internals ───────────────────────────────────────────────

    private function requireStatus(string $status): void
    {
        if ($this->status !== $status) {
            throw new LogicException("Hosting account is {$this->status}, not {$status}.");
        }
    }

    private function move(string $status, string $actor, int $at, string $note): void
    {
        $this->status = $status;
        $this->history[] = ['status' => $status, 'at' => $at, 'actor' => $actor, 'note' => $note];
    }
}
