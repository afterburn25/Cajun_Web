<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Notifications;

use InvalidArgumentException;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * P-07 — one outbound delivery attempt for a notification on one channel.
 *
 * State machine: pending → sent | retrying → sent | failed.
 * Retries are bounded (maxAttempts) with exponential backoff, and `failed`
 * is terminal and honest — an attempt that exhausted retries reports failed,
 * never silently reports sent. `sent` can only be recorded from a real
 * transport confirmation supplied by the caller.
 *
 * @internal Roadmap P-07; additively inserted.
 */
final class DeliveryAttempt
{
    public const STATE_PENDING = 'pending';
    public const STATE_RETRYING = 'retrying';
    public const STATE_SENT = 'sent';
    public const STATE_FAILED = 'failed';

    public const CHANNEL_PORTAL = 'portal';
    public const CHANNEL_EMAIL = 'email';

    private string $state = self::STATE_PENDING;
    private int $attempts = 0;
    private ?int $sentAt = null;
    private ?string $lastError = null;

    public function __construct(
        public readonly string $notificationId,
        public readonly string $channel,
        public readonly int $maxAttempts = 3,
        public readonly int $backoffSeconds = 60,
        ?int $firstAttemptAt = null,
        private ?int $nextAttemptAt = null,
    ) {
        if (!in_array($channel, [self::CHANNEL_PORTAL, self::CHANNEL_EMAIL], true)) {
            throw new InvalidArgumentException("Unknown channel '{$channel}'.");
        }
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be >= 1.');
        }
        if ($backoffSeconds < 1) {
            throw new InvalidArgumentException('backoffSeconds must be >= 1.');
        }
        $this->nextAttemptAt = $firstAttemptAt;
    }

    /**
     * Rehydrate a persisted attempt. Validates the stored state so a corrupt
     * or impossible record is refused before any transport call. Terminal
     * records cannot carry a scheduled retry; a retrying record must name
     * when it is next due.
     */
    public static function restore(
        string $notificationId,
        string $channel,
        int $maxAttempts,
        int $attempts,
        string $state,
        ?int $sentAt,
        ?string $lastError,
        ?int $nextAttemptAt,
        int $backoffSeconds = 60,
    ): self {
        $a = new self($notificationId, $channel, $maxAttempts, $backoffSeconds, $nextAttemptAt);
        if (!in_array($state, [self::STATE_PENDING, self::STATE_RETRYING, self::STATE_SENT, self::STATE_FAILED], true)) {
            throw new InvalidArgumentException("Unknown delivery state '{$state}'.");
        }
        if ($attempts < 0 || $attempts > $maxAttempts) {
            throw new InvalidArgumentException('Persisted attempt count is outside the configured bounds.');
        }
        if (($state === self::STATE_SENT || $state === self::STATE_FAILED) && $nextAttemptAt !== null) {
            throw new InvalidArgumentException('A terminal delivery cannot schedule another attempt.');
        }
        if ($state === self::STATE_RETRYING && $nextAttemptAt === null) {
            throw new InvalidArgumentException('A retrying delivery must carry its next attempt time.');
        }
        if ($state === self::STATE_SENT && $sentAt === null) {
            throw new InvalidArgumentException('A sent delivery must carry its sent time.');
        }
        $a->state = $state;
        $a->attempts = $attempts;
        $a->sentAt = $sentAt;
        $a->lastError = $lastError;
        return $a;
    }

    public function state(): string { return $this->state; }
    public function attempts(): int { return $this->attempts; }
    public function sentAt(): ?int { return $this->sentAt; }
    public function lastError(): ?string { return $this->lastError; }
    public function nextAttemptAt(): ?int { return $this->nextAttemptAt; }

    /** Whether the transport may attempt delivery at $now. */
    public function due(int $now): bool
    {
        if ($this->state !== self::STATE_PENDING && $this->state !== self::STATE_RETRYING) {
            return false;
        }
        return $this->nextAttemptAt === null || $now >= $this->nextAttemptAt;
    }

    /** Record a confirmed send. Only reachable from pending/retrying. */
    public function recordSent(int $at): void
    {
        if ($this->state === self::STATE_SENT || $this->state === self::STATE_FAILED) {
            throw new InvalidArgumentException("Delivery already reached terminal state {$this->state}.");
        }
        $this->attempts++;
        $this->state = self::STATE_SENT;
        $this->sentAt = $at;
        $this->nextAttemptAt = null;
    }

    /**
     * Record a transport failure. Retries continue until maxAttempts, then the
     * attempt fails terminally — the caller must surface that honestly rather
     * than retry forever or claim success.
     */
    public function recordFailure(int $at, string $error): void
    {
        if ($this->state === self::STATE_SENT || $this->state === self::STATE_FAILED) {
            throw new InvalidArgumentException("Delivery already reached terminal state {$this->state}.");
        }
        if (trim($error) === '') {
            throw new InvalidArgumentException('A failure record must name the error.');
        }
        $this->attempts++;
        $this->lastError = $error;
        if ($this->attempts >= $this->maxAttempts) {
            $this->state = self::STATE_FAILED;
            $this->nextAttemptAt = null;
        } else {
            $this->state = self::STATE_RETRYING;
            $this->nextAttemptAt = $at + $this->backoffSeconds * (2 ** ($this->attempts - 1));
        }
    }
}
