<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Hosting;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * HC-08 — the recorded outcome of one real provider operation.
 *
 * Produced by the adapter **after** attempting the operation. Three honest
 * outcomes: `applied` (provider confirmed, receipt required), `failed`
 * (named error + whether a retry is safe), and `timeout` (the provider did
 * not answer — outcome genuinely unknown, so the adapter must reconcile
 * before retrying rather than risk a duplicate package). Timeout is
 * deliberately distinct from failure: retrying a timed-out create is the
 * classic duplicate-provisioning bug.
 *
 * @internal Roadmap HC-08; additively inserted.
 */
final readonly class OperationResult
{
    public const APPLIED = 'applied';
    public const FAILED = 'failed';
    public const TIMEOUT = 'timeout';

    private function __construct(
        public string $operation,
        public string $outcome,
        public int $at,
        public ?string $receipt,
        public ?string $error,
        public bool $retryable,
    ) {
    }

    /** Provider confirmed; `receipt` is the provider's operation reference. */
    public static function applied(string $operation, string $receipt, int $at): self
    {
        if (trim($receipt) === '') {
            throw new InvalidArgumentException('An applied operation needs the provider receipt.');
        }
        return new self(self::check($operation), self::APPLIED, $at, $receipt, null, false);
    }

    public static function failed(string $operation, string $error, bool $retryable, int $at): self
    {
        if (trim($error) === '') {
            throw new InvalidArgumentException('A failed operation must name the error.');
        }
        return new self(self::check($operation), self::FAILED, $at, null, $error, $retryable);
    }

    /** No response — outcome unknown; reconcile before any retry. */
    public static function timeout(string $operation, int $at): self
    {
        return new self(self::check($operation), self::TIMEOUT, $at, null, null, false);
    }

    public function ok(): bool
    {
        return $this->outcome === self::APPLIED;
    }

    private static function check(string $operation): string
    {
        if (trim($operation) === '') {
            throw new InvalidArgumentException('An operation result needs a name.');
        }
        return $operation;
    }
}
