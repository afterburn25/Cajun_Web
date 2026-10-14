<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Provider\LedgerClaim;
use CajunWebPro\Foundation\Provider\OperationLedger;
use CajunWebPro\Foundation\Provider\OperationResult;

/**
 * File-backed OperationLedger proving the claim contract across real
 * OS processes. flock(LOCK_EX) serializes the whole read-modify-write —
 * the same atomicity a MySQL adapter gets from a unique key + INSERT.
 * JSON storage is test scaffolding, not a production recommendation.
 */
final class FileLedger implements OperationLedger
{
    public function __construct(private string $path)
    {
    }

    public function claim(string $idempotencyKey, string $fingerprint, int $now): LedgerClaim
    {
        return $this->mutate(function (array &$data) use ($idempotencyKey, $fingerprint, $now): LedgerClaim {
            if (isset($data['bound'][$idempotencyKey]) && $data['bound'][$idempotencyKey] !== $fingerprint) {
                return LedgerClaim::conflict();
            }
            if (isset($data['results'][$idempotencyKey])) {
                $result = unserialize($data['results'][$idempotencyKey]);
                return $result instanceof OperationResult
                    ? LedgerClaim::replay($result)
                    : LedgerClaim::uncertain();
            }
            if (isset($data['reservations'][$idempotencyKey])) {
                return $now - $data['reservations'][$idempotencyKey] >= OperationLedger::RESERVATION_TTL
                    ? LedgerClaim::uncertain()
                    : LedgerClaim::inProgress();
            }
            $data['bound'][$idempotencyKey] = $fingerprint;
            $data['reservations'][$idempotencyKey] = $now;
            return LedgerClaim::claimed();
        });
    }

    public function record(string $idempotencyKey, string $fingerprint, OperationResult $result, int $now): void
    {
        $this->mutate(function (array &$data) use ($idempotencyKey, $fingerprint, $result): LedgerClaim {
            if (isset($data['results'][$idempotencyKey])) {
                return LedgerClaim::claimed(); // first terminal result wins
            }
            if (isset($data['bound'][$idempotencyKey]) && $data['bound'][$idempotencyKey] !== $fingerprint) {
                throw new LogicException('Cannot record under a reservation owned by a different request.');
            }
            unset($data['reservations'][$idempotencyKey]);
            $data['bound'][$idempotencyKey] = $fingerprint;
            $data['results'][$idempotencyKey] = serialize($result);
            return LedgerClaim::claimed();
        });
    }

    /**
     * @param callable(array<string,mixed>&):LedgerClaim $op May mutate
     *        $data; runs inside the exclusive flock.
     */
    private function mutate(callable $op): LedgerClaim
    {
        $handle = fopen($this->path, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new RuntimeException('Fixture ledger storage unavailable.');
        }
        try {
            $raw = stream_get_contents($handle);
            $data = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
            $data += ['bound' => [], 'results' => [], 'reservations' => []];
            $claim = $op($data);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data));
            fflush($handle);
            return $claim;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
