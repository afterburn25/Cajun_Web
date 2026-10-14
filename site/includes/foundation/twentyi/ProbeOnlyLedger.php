<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyI;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{LedgerClaim,OperationLedger,OperationResult};

/** Refusal boundary, not a durable operation ledger. The probe never calls it. */
final class ProbeOnlyLedger implements OperationLedger
{
    public function claim(string $idempotencyKey, string $fingerprint, int $now): LedgerClaim
    {
        throw new \LogicException('Provider operations are unavailable in the connection-check adapter.');
    }

    public function record(string $idempotencyKey, string $fingerprint, OperationResult $result, int $now): void
    {
        throw new \LogicException('Provider operations are unavailable in the connection-check adapter.');
    }
}
