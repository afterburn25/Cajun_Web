<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * Durable record of provider operations by idempotency key. The site
 * adapter implements this over the application's database (a unique key
 * column makes record() atomic); tests use an in-memory map. The ledger
 * is what turns "retry" into "return the recorded outcome" instead of
 * "run the provider call again".
 */
interface OperationLedger
{
    /**
     * The recorded result for this idempotency key, or null when the
     * operation has never been recorded.
     */
    public function findByKey(string $idempotencyKey): ?OperationResult;

    /**
     * Durably record the result under the key. Implementations must be
     * idempotent: recording the same key twice keeps the first result.
     */
    public function record(string $idempotencyKey, OperationResult $result): void;
}
