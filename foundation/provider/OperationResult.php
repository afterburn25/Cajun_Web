<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * The recorded outcome of a provider operation. Results are durable:
 * once recorded by the ledger, replaying the same idempotency key
 * returns the recorded result instead of re-executing. `replayed` marks
 * a result returned from the ledger rather than a fresh execution, so
 * callers can distinguish a duplicate submission.
 *
 * Messages are a bounded classification channel: short printable UTF-8
 * only. Raw transport errors, provider URLs, response bodies and
 * credential material must never be placed here — they would persist
 * into durable ledger rows and surface to staff.
 */
final readonly class OperationResult
{
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const RETRYABLE = 'retryable';
    public const UNSUPPORTED = 'unsupported';
    public const UNCERTAIN = 'uncertain';
    public const IN_PROGRESS = 'in_progress';
    public const CONFLICT = 'conflict';

    private const MAX_MESSAGE = 200;

    private function __construct(
        public string $status,
        public string $operation,
        public string $message,
        public ?string $providerCode,
        public ?string $externalId,
        public int $attempts,
        public bool $replayed,
    ) {
        if (strlen($message) > self::MAX_MESSAGE
            || preg_match('//u', $message) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $message)
        ) {
            throw new InvalidArgumentException('Result messages are bounded printable classifications (<=200 bytes, no control characters).');
        }
        if ($providerCode !== null && (strlen($providerCode) > 64 || preg_match('/[\x00-\x1f\x7f]/', $providerCode))) {
            throw new InvalidArgumentException('Provider codes are short printable machine labels.');
        }
    }

    public static function succeeded(string $operation, string $message = '', ?string $externalId = null, ?string $providerCode = null): self
    {
        return new self(self::SUCCEEDED, $operation, $message, $providerCode, $externalId, 1, false);
    }

    public static function failed(string $operation, string $message, ?string $providerCode = null): self
    {
        return new self(self::FAILED, $operation, $message, $providerCode, null, 1, false);
    }

    public static function retryable(string $operation, string $message, ?string $providerCode = null): self
    {
        return new self(self::RETRYABLE, $operation, $message, $providerCode, null, 1, false);
    }

    /**
     * The remote side effect may or may not have happened (crash,
     * timeout after write, or a failed durable record). Terminal for
     * automatic purposes: never retried, never silently re-executed.
     * Callers must reconcile against the provider before retrying.
     */
    public static function uncertain(string $operation, ?string $providerCode = null): self
    {
        return new self(self::UNCERTAIN, $operation, 'The remote outcome is unknown; reconcile with the provider before retrying.', $providerCode, null, 1, false);
    }

    /** Another execution currently holds the reservation for this key. */
    public static function inProgress(string $operation): self
    {
        return new self(self::IN_PROGRESS, $operation, 'An identical request is already running; poll or retry shortly.', null, null, 0, false);
    }

    /** The idempotency key is already bound to a different request. */
    public static function conflict(string $operation): self
    {
        return new self(self::CONFLICT, $operation, 'This idempotency key was already used for a different request.', null, null, 0, false);
    }

    public static function unsupported(string $operation): self
    {
        return new self(self::UNSUPPORTED, $operation, 'Operation ' . $operation . ' is not supported or not verified for this provider account.', null, null, 0, false);
    }

    public function withAttempts(int $attempts): self
    {
        return new self($this->status, $this->operation, $this->message, $this->providerCode, $this->externalId, $attempts, $this->replayed);
    }

    public function asReplay(): self
    {
        return new self($this->status, $this->operation, $this->message, $this->providerCode, $this->externalId, $this->attempts, true);
    }

    /** Whether the operation ended in a durable terminal state. */
    public function isFinal(): bool
    {
        return in_array($this->status, [self::SUCCEEDED, self::FAILED, self::UNSUPPORTED, self::UNCERTAIN, self::CONFLICT], true);
    }

    /** Whether reconciliation with the provider is required before retrying. */
    public function needsReconciliation(): bool
    {
        return $this->status === self::UNCERTAIN;
    }
}
