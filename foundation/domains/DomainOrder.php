<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Domains;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * Lifecycle state machine for one domain order. Transitions are guarded
 * and append an immutable history entry, so a caller can never jump an
 * order to `active` without a submission, drop a registered domain
 * silently, or resurrect a terminal order.
 *
 * Flow: pending → submitted → active → (renew | expire | transfer out).
 * pending/submitted → failed → retry → pending.
 * pending/failed/expired → cancelled. transferring → transferred or
 * back to active when the transfer aborts.
 */
final class DomainOrder
{
    public const PENDING = 'pending';
    public const SUBMITTED = 'submitted';
    public const ACTIVE = 'active';
    public const TRANSFERRING = 'transferring';
    public const EXPIRED = 'expired';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const TRANSFERRED = 'transferred';

    private const TERMINAL = [self::CANCELLED, self::TRANSFERRED];

    private string $status = self::PENDING;
    private bool $consentRecorded = false;
    private ?string $providerRef = null;
    private ?string $expiresAt = null;
    /** @var array<int,array{status:string,at:string,note:string}> */
    private array $history = [];

    public function __construct(
        public readonly string $id,
        public readonly DomainName $domain,
        public readonly RegistrantContact $registrant,
        public readonly string $operation,
        public readonly int $years,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('A domain order needs an identifier.');
        }
        if (!in_array($operation, [TldOffering::REGISTER, TldOffering::TRANSFER], true)) {
            throw new InvalidArgumentException('Orders are created for register or transfer; renewals act on an active order.');
        }
        if ($years < 1 || $years > 10) {
            throw new InvalidArgumentException('Order years must be within 1–10.');
        }
        $this->history[] = ['status' => self::PENDING, 'at' => gmdate('c'), 'note' => 'created'];
    }

    public function status(): string
    {
        return $this->status;
    }

    public function providerRef(): ?string
    {
        return $this->providerRef;
    }

    public function expiresAt(): ?string
    {
        return $this->expiresAt;
    }

    /** @return array<int,array{status:string,at:string,note:string}> */
    public function history(): array
    {
        return $this->history;
    }

    public function terminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    /** Record that the registrant consented to the submission (required before submit). */
    public function recordConsent(): self
    {
        $this->consentRecorded = true;
        return $this;
    }

    public function submit(string $providerRef): self
    {
        $this->require(self::PENDING, 'submit');
        if (trim($providerRef) === '') {
            throw new InvalidArgumentException('Submitting needs the provider reference for reconciliation.');
        }
        if (!$this->consentRecorded) {
            throw new LogicException('Registrant consent must be recorded before submitting to a provider.');
        }
        $this->providerRef = $providerRef;
        return $this->go(self::SUBMITTED, 'sent to provider');
    }

    public function markActive(string $expiresAt): self
    {
        $this->require(self::SUBMITTED, 'markActive');
        if (strtotime($expiresAt) === false) {
            throw new InvalidArgumentException('Expiry needs a parseable date.');
        }
        $this->expiresAt = gmdate('c', strtotime($expiresAt));
        return $this->go(self::ACTIVE, 'provider confirmed');
    }

    public function fail(string $reason): self
    {
        if (!in_array($this->status, [self::PENDING, self::SUBMITTED], true)) {
            throw new LogicException('Only pending/submitted orders can fail.');
        }
        return $this->go(self::FAILED, $reason === '' ? 'provider failure' : $reason);
    }

    /** A failed order returns to pending for a fresh submission; the provider ref is cleared. */
    public function retry(): self
    {
        $this->require(self::FAILED, 'retry');
        $this->providerRef = null;
        $this->consentRecorded = false;
        return $this->go(self::PENDING, 'queued for retry');
    }

    /** Extend an active registration. The new expiry must move forward. */
    public function renew(string $newExpiresAt): self
    {
        $this->require(self::ACTIVE, 'renew');
        if (strtotime($newExpiresAt) === false || ($this->expiresAt !== null && strtotime($newExpiresAt) <= strtotime($this->expiresAt))) {
            throw new InvalidArgumentException('Renewal must extend the current expiry.');
        }
        $this->expiresAt = gmdate('c', strtotime($newExpiresAt));
        $this->history[] = ['status' => self::ACTIVE, 'at' => gmdate('c'), 'note' => 'renewed'];
        return $this;
    }

    public function expire(): self
    {
        $this->require(self::ACTIVE, 'expire');
        return $this->go(self::EXPIRED, 'registration lapsed');
    }

    public function beginTransferOut(): self
    {
        $this->require(self::ACTIVE, 'beginTransferOut');
        return $this->go(self::TRANSFERRING, 'outbound transfer started');
    }

    public function completeTransferOut(): self
    {
        $this->require(self::TRANSFERRING, 'completeTransferOut');
        return $this->go(self::TRANSFERRED, 'domain transferred away');
    }

    /** An outbound transfer that did not complete returns the domain to active. */
    public function abortTransferOut(string $note = 'transfer aborted'): self
    {
        $this->require(self::TRANSFERRING, 'abortTransferOut');
        return $this->go(self::ACTIVE, $note);
    }

    /**
     * Cancel an order that never reached (or no longer holds) a
     * registration. An active or transferring domain must go through
     * deprovisioning/transfer — never a silent cancel.
     */
    public function cancel(string $note = 'cancelled'): self
    {
        if (!in_array($this->status, [self::PENDING, self::FAILED, self::EXPIRED], true)) {
            throw new LogicException('Only pending, failed or expired orders can be cancelled.');
        }
        return $this->go(self::CANCELLED, $note);
    }

    private function require(string $expected, string $action): void
    {
        if ($this->status !== $expected) {
            throw new LogicException('Cannot ' . $action . ' an order in state ' . $this->status . '.');
        }
    }

    private function go(string $status, string $note): self
    {
        $this->status = $status;
        $this->history[] = ['status' => $status, 'at' => gmdate('c'), 'note' => $note];
        return $this;
    }
}
