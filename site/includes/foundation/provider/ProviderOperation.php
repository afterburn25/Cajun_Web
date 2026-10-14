<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One named provider operation with caller-supplied parameters and a
 * caller-generated idempotency key. The key is what the gateway and
 * ledger use to guarantee at-most-once execution across retries, page
 * reloads and queue replays — adapters must not generate their own.
 */
final readonly class ProviderOperation
{
    /**
     * @param array<string,mixed> $params
     */
    public function __construct(
        public string $name,
        public string $idempotencyKey,
        public array $params = [],
    ) {
        if (!preg_match('/^[a-z0-9]+(\.[a-z0-9]+)*$/', $name)) {
            throw new InvalidArgumentException('Operation names use dotted lowercase form, e.g. packages.list.');
        }
        $key = trim($idempotencyKey);
        if ($key === '' || strlen($key) > 190 || preg_match('/[\x00-\x1f\x7f]/', $key)) {
            throw new InvalidArgumentException('An operation needs a printable idempotency key of 1–190 characters.');
        }
    }
}
