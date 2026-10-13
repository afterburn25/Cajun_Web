<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Webmail;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * A-06 — the recorded outcome of a real connection probe against one side
 * (imap or smtp) of one mailbox.
 *
 * This is a value the adapter produces **after actually attempting** the
 * connection — never a simulated or presumed state. A failure must carry
 * the error text; a success has none. `untested` is representable (the
 * profile exists but was never probed) so the UI can show "not yet checked"
 * instead of a fabricated "working".
 *
 * Honesty boundary: `success()` records an outcome the *caller* asserts —
 * the factory cannot itself prove a real connection happened. The contract
 * obligation (probe actually attempted) sits on the adapter side.
 *
 * @internal Roadmap A-06; additively inserted.
 */
final readonly class ConnectionResult
{
    public const TARGET_IMAP = 'imap';
    public const TARGET_SMTP = 'smtp';

    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_FAILED = 'failed';
    public const OUTCOME_UNTESTED = 'untested';

    private function __construct(
        public string $mailboxId,
        public string $target,
        public string $outcome,
        public ?int $checkedAt,
        public ?string $error,
    ) {
    }

    public static function success(string $mailboxId, string $target, int $checkedAt): self
    {
        return new self($mailboxId, self::checkTarget($target), self::OUTCOME_SUCCESS, $checkedAt, null);
    }

    public static function failure(string $mailboxId, string $target, int $checkedAt, string $error): self
    {
        if (trim($error) === '') {
            throw new InvalidArgumentException('A failed connection result must name the error.');
        }
        return new self($mailboxId, self::checkTarget($target), self::OUTCOME_FAILED, $checkedAt, $error);
    }

    public static function untested(string $mailboxId, string $target): self
    {
        return new self($mailboxId, self::checkTarget($target), self::OUTCOME_UNTESTED, null, null);
    }

    public function ok(): bool
    {
        return $this->outcome === self::OUTCOME_SUCCESS;
    }

    private static function checkTarget(string $target): string
    {
        if (!in_array($target, [self::TARGET_IMAP, self::TARGET_SMTP], true)) {
            throw new InvalidArgumentException("Unknown connection target '{$target}'.");
        }
        return $target;
    }
}
