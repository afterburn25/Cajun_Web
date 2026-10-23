<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\SshAccess;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * One terminal session's lifecycle, with idle and absolute timeouts
 * enforced by the broker. States: active -> closed | expired |
 * terminated. Terminal states are final; actor identity and target are
 * fixed at creation and never change.
 */
final class TerminalSession
{
    public const ACTIVE = 'active';
    public const CLOSED = 'closed';
    public const EXPIRED = 'expired';
    public const TERMINATED = 'terminated';

    private const TERMINAL = [self::CLOSED, self::EXPIRED, self::TERMINATED];

    private string $state = self::ACTIVE;
    private int $lastActivityAt;
    private ?int $endedAt = null;

    /** @var array<int,array{at:int,event:string}> */
    private array $history = [];

    public function __construct(
        public readonly string $id,
        public readonly string $staffId,
        public readonly string $clientId,
        public readonly ServiceTarget $target,
        public readonly int $idleTimeoutSeconds,
        public readonly int $absoluteTimeoutSeconds,
        int $openedAt,
    ) {
        foreach (['id', 'staffId', 'clientId'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('A session needs ' . $field . '.');
            }
        }
        if ($idleTimeoutSeconds < 1 || $absoluteTimeoutSeconds < $idleTimeoutSeconds) {
            throw new InvalidArgumentException('Timeouts must be positive, absolute >= idle.');
        }
        if ($openedAt < 0) {
            throw new InvalidArgumentException('Session times must be non-negative.');
        }
        $this->lastActivityAt = $openedAt;
        $this->record($openedAt, 'opened');
    }

    /** Activity bumps the idle clock; never resurrects a dead session. */
    public function activity(int $at): self
    {
        $this->requireActive();
        $this->requireMonotonic($at);
        $this->lastActivityAt = $at;
        $this->record($at, 'activity');
        return $this;
    }

    /** Apply timeouts — the caller asks before each use. */
    public function refresh(int $now): string
    {
        if ($now < 0 || $now < $this->openedAt()) {
            throw new InvalidArgumentException('Refresh time must be non-negative and at/after session open.');
        }
        if ($this->state !== self::ACTIVE) {
            return $this->state;
        }
        if ($now >= $this->lastActivityAt + $this->idleTimeoutSeconds
            || $now >= $this->openedAt() + $this->absoluteTimeoutSeconds) {
            $this->settle(self::EXPIRED, $now);
        }
        return $this->state;
    }

    /** Operator Disconnect — graceful close. */
    public function close(int $at): self
    {
        $this->requireActive();
        return $this->settle(self::CLOSED, $at);
    }

    /** Forced end — revocation or a staff timeout/kill action. */
    public function terminate(int $at): self
    {
        $this->requireActive();
        return $this->settle(self::TERMINATED, $at);
    }

    public function openedAt(): int
    {
        return $this->history[0]['at'];
    }

    public function state(): string
    {
        return $this->state;
    }

    public function active(): bool
    {
        return $this->state === self::ACTIVE;
    }

    public function endedAt(): ?int
    {
        return $this->endedAt;
    }

    public function history(): array
    {
        return $this->history;
    }

    private function settle(string $state, int $at): self
    {
        $this->requireMonotonic($at);
        $this->state = $state;
        $this->endedAt = $at;
        $this->record($at, $state);
        return $this;
    }

    /** Lifecycle events must be non-negative and never reorder history. */
    private function requireMonotonic(int $at): void
    {
        if ($at < 0 || $at < $this->lastActivityAt) {
            throw new InvalidArgumentException('Session event times must be non-negative and monotonic.');
        }
    }

    private function requireActive(): void
    {
        if ($this->state !== self::ACTIVE) {
            throw new LogicException('Session is already ' . $this->state . '.');
        }
    }

    private function record(int $at, string $event): void
    {
        $this->history[] = ['at' => $at, 'event' => $event];
    }
}
