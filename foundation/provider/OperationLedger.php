<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * Durable, atomic store for provider-operation reservations and results.
 *
 * The ledger is what makes at-most-once execution real: a durable
 * reservation row must exist BEFORE any remote call is made. A durable
 * implementation (the site's MySQL adapter) must back claim() with a
 * unique key constraint + INSERT ... in one atomic step — a
 * SELECT-then-INSERT pair reintroduces the execution race.
 *
 * Reservations that outlive RESERVATION_TTL without a terminal record
 * are treated as crashed executions: claim() reports UNCERTAIN rather
 * than letting a second caller re-execute a write that may have landed.
 */
interface OperationLedger
{
    /**
     * Seconds a reservation may live without a terminal record before a
     * later claim classifies it as a crashed/uncertain execution. Longer
     * than the maximum transport timeout (120s is the request ceiling).
     */
    public const RESERVATION_TTL = 120;

    /**
     * Atomically claim the key for this request fingerprint.
     * $fingerprint binds the key to one canonical
     * provider/account/operation/params request; a key already bound to
     * a different fingerprint must return LedgerClaim::conflict().
     *
     * $now is the caller's epoch timestamp so implementations stay
     * deterministic and testable.
     */
    public function claim(string $idempotencyKey, string $fingerprint, int $now): LedgerClaim;

    /**
     * Atomically record the terminal result for a claimed key. The first
     * result for a key wins — recording must never overwrite an existing
     * terminal result. Recording under a reservation bound to a different
     * fingerprint is a programmer error and must throw.
     */
    public function record(string $idempotencyKey, string $fingerprint, OperationResult $result, int $now): void;
}
