<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * The recorded outcome of a provider operation. Results are durable:
 * once recorded by the ledger, replaying the same idempotency key
 * returns the recorded result instead of re-executing. `replayed` marks
 * a result returned from the ledger rather than a fresh execution, so
 * callers can distinguish a duplicate submission.
 */
final readonly class OperationResult
{
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const RETRYABLE = 'retryable';
    public const UNSUPPORTED = 'unsupported';

    private function __construct(
        public string $status,
        public string $operation,
        public string $message,
        public ?string $providerCode,
        public ?string $externalId,
        public int $attempts,
        public bool $replayed,
    ) {
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

    /** Whether the operation ended in a durable non-pending state. */
    public function isFinal(): bool
    {
        return in_array($this->status, [self::SUCCEEDED, self::FAILED, self::UNSUPPORTED], true);
    }
}
