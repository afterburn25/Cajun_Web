<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Vps;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * Lifecycle state machine for one VPS. Transitions are guarded and
 * append an immutable history entry. Destructive teardown is a
 * deliberate two-actor action: `requestDecommission()` records one
 * actor's request and `approveDecommission()` requires a *different*
 * actor — a single account, compromised or mistaken, cannot destroy a
 * server alone. Cancellation is only possible for orders that never
 * provisioned.
 *
 * Flow: pending → provisioning → active ⇄ suspended.
 *       pending/provisioning → failed → retry → pending.
 *       active/suspended → decommission_requested (actor A)
 *         → decommissioning (actor B ≠ A) → terminated (terminal).
 */
final class VpsServer
{
    public const PENDING = 'pending';
    public const PROVISIONING = 'provisioning';
    public const ACTIVE = 'active';
    public const SUSPENDING = 'suspending';
    public const SUSPENDED = 'suspended';
    public const RESUMING = 'resuming';
    public const DECOMMISSION_REQUESTED = 'decommission_requested';
    public const DECOMMISSIONING = 'decommissioning';
    public const TERMINATED = 'terminated';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    private const TERMINAL = [self::TERMINATED, self::CANCELLED];

    private string $status = self::PENDING;
    private ?string $providerRef = null;
    private ?string $decommissionRequester = null;
    private string $decommissionReturnTo = self::ACTIVE;
    /** @var array<int,array{status:string,at:string,actor:string,note:string}> */
    private array $history = [];

    public function __construct(
        public readonly string $id,
        public readonly VpsOffering $offering,
        public readonly string $clientRef,
    ) {
        if (trim($id) === '' || trim($clientRef) === '') {
            throw new InvalidArgumentException('A VPS order needs an id and a client reference.');
        }
        $this->history[] = ['status' => self::PENDING, 'at' => gmdate('c'), 'actor' => '', 'note' => 'created'];
    }

    public function status(): string
    {
        return $this->status;
    }

    public function providerRef(): ?string
    {
        return $this->providerRef;
    }

    /** @return array<int,array{status:string,at:string,actor:string,note:string}> */
    public function history(): array
    {
        return $this->history;
    }

    public function terminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    /** Provider reference recorded when provisioning begins. */
    public function beginProvisioning(string $providerRef): self
    {
        $this->require(self::PENDING, 'beginProvisioning');
        if (trim($providerRef) === '') {
            throw new InvalidArgumentException('Provisioning needs the provider reference for reconciliation.');
        }
        $this->providerRef = $providerRef;
        return $this->go(self::PROVISIONING, '', 'sent to provider');
    }

    /** Completes both provisioning→active and resuming→active. */
    public function markActive(string $note = 'provider confirmed running'): self
    {
        if (!in_array($this->status, [self::PROVISIONING, self::RESUMING], true)) {
            throw new LogicException('Cannot activate a server in state ' . $this->status . '.');
        }
        return $this->go(self::ACTIVE, '', $note);
    }

    /**
     * A provisioning attempt that did not reach a confirmed state.
     * Only provisioning failures land here — in-flight suspend/resume/
     * teardown failures roll back to their prior stable state instead.
     */
    public function fail(string $reason): self
    {
        if (!in_array($this->status, [self::PENDING, self::PROVISIONING], true)) {
            throw new LogicException('Cannot fail a server in state ' . $this->status . '.');
        }
        return $this->go(self::FAILED, '', $reason === '' ? 'provider failure' : $reason);
    }

    /** A failed provisioning returns to pending for a fresh attempt. */
    public function retry(): self
    {
        $this->require(self::FAILED, 'retry');
        $this->providerRef = null;
        return $this->go(self::PENDING, '', 'queued for retry');
    }

    // --- suspend / resume ---------------------------------------------------

    public function suspend(): self
    {
        $this->require(self::ACTIVE, 'suspend');
        return $this->go(self::SUSPENDING, '', 'suspension sent to provider');
    }

    public function markSuspended(): self
    {
        $this->require(self::SUSPENDING, 'markSuspended');
        return $this->go(self::SUSPENDED, '', 'provider confirmed suspended');
    }

    /** A suspension that did not complete leaves the server active. */
    public function suspendFailed(string $note = 'suspension failed'): self
    {
        $this->require(self::SUSPENDING, 'suspendFailed');
        return $this->go(self::ACTIVE, '', $note);
    }

    public function resume(): self
    {
        $this->require(self::SUSPENDED, 'resume');
        return $this->go(self::RESUMING, '', 'resume sent to provider');
    }

    /** A resume that did not complete leaves the server suspended. */
    public function resumeFailed(string $note = 'resume failed'): self
    {
        $this->require(self::RESUMING, 'resumeFailed');
        return $this->go(self::SUSPENDED, '', $note);
    }

    // --- decommission (two-actor teardown) -----------------------------------

    /** Step 1: one actor requests teardown. */
    public function requestDecommission(string $actor): self
    {
        if (!in_array($this->status, [self::ACTIVE, self::SUSPENDED], true)) {
            throw new LogicException('Decommission can only be requested on an active or suspended server.');
        }
        if (trim($actor) === '') {
            throw new InvalidArgumentException('A decommission request needs an actor.');
        }
        $this->decommissionRequester = $actor;
        $this->decommissionReturnTo = $this->status;
        return $this->go(self::DECOMMISSION_REQUESTED, $actor, 'teardown requested');
    }

    /** Step 2: a different actor approves — teardown begins. */
    public function approveDecommission(string $actor): self
    {
        $this->require(self::DECOMMISSION_REQUESTED, 'approveDecommission');
        if (trim($actor) === '') {
            throw new InvalidArgumentException('A decommission approval needs an actor.');
        }
        if ($actor === $this->decommissionRequester) {
            throw new LogicException('Teardown approval requires a different actor than the requester.');
        }
        return $this->go(self::DECOMMISSIONING, $actor, 'teardown approved and sent to provider');
    }

    /** The requester (or anyone) backs out before approval — server returns to its prior state. */
    public function cancelDecommission(string $actor, string $note = 'teardown request withdrawn'): self
    {
        $this->require(self::DECOMMISSION_REQUESTED, 'cancelDecommission');
        $back = $this->decommissionReturnTo;
        $this->decommissionRequester = null;
        return $this->go($back, $actor, $note);
    }

    /**
     * A teardown that did not complete rolls back to the stable state
     * the server was in and clears the request — destruction must
     * restart the full two-actor flow rather than silently retrying.
     */
    public function decommissionFailed(string $note = 'teardown failed'): self
    {
        $this->require(self::DECOMMISSIONING, 'decommissionFailed');
        $back = $this->decommissionReturnTo;
        $this->decommissionRequester = null;
        return $this->go($back, '', $note);
    }

    public function markTerminated(): self
    {
        $this->require(self::DECOMMISSIONING, 'markTerminated');
        return $this->go(self::TERMINATED, '', 'provider confirmed destroyed');
    }

    /**
     * Cancel only before provisioning (or after a failed attempt). An
     * active/suspended server must go through decommission — never a
     * silent cancel.
     */
    public function cancel(string $actor, string $note = 'cancelled'): self
    {
        if (!in_array($this->status, [self::PENDING, self::FAILED], true)) {
            throw new LogicException('Only pending or failed orders can be cancelled.');
        }
        return $this->go(self::CANCELLED, $actor, $note);
    }

    // -----------------------------------------------------------------------

    private function require(string $expected, string $action): void
    {
        if ($this->status !== $expected) {
            throw new LogicException('Cannot ' . $action . ' a server in state ' . $this->status . '.');
        }
    }

    private function go(string $status, string $actor, string $note): self
    {
        $this->status = $status;
        $this->history[] = ['status' => $status, 'at' => gmdate('c'), 'actor' => $actor, 'note' => $note];
        return $this;
    }
}
