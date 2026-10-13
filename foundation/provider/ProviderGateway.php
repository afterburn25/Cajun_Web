<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use Throwable;

/**
 * Executes provider operations against an adapter with durable
 * at-most-once semantics.
 *
 * Flow per call:
 *   1. capability check — unverified/unsupported operations are refused
 *      before any claim or transport work;
 *   2. canonical fingerprint — sha256 over provider id, account
 *      identity, operation name and recursively-sorted params;
 *   3. atomic claim — the ledger durably reserves the key BEFORE any
 *      remote call. A second caller with the same key can never execute;
 *      it observes REPLAY / IN_PROGRESS / UNCERTAIN / CONFLICT instead;
 *   4. execution — idempotent operations retry inside the bounded
 *      policy; non-idempotent operations get exactly one attempt and any
 *      ambiguous outcome (transport fault or retryable response) is
 *      recorded as UNCERTAIN for reconciliation, never blindly retried;
 *   5. record — the terminal result is written under the claim. A failed
 *      record leaves the caller with UNCERTAIN, not a false verdict.
 *
 * Exactly-once remote execution cannot be guaranteed: a process that
 * crashes after the provider applies a write but before recording leaves
 * the reservation to expire, after which the key reports UNCERTAIN. That
 * is the honest answer — never silently re-execute a possibly-landed
 * write.
 */
final class ProviderGateway
{
    public function __construct(
        private ProviderAdapter $adapter,
        private HttpTransport $transport,
        private OperationLedger $ledger,
        private RetryPolicy $retry = new RetryPolicy(),
    ) {
    }

    /**
     * Verify credentials with the provider's cheapest read endpoint.
     * Transport failures classify to a finite unreachable kind — raw
     * exception text never reaches the result.
     */
    public function checkConnection(ProviderCredential $credential): ConnectionCheck
    {
        try {
            return $this->adapter->probe($this->transport, $credential);
        } catch (TransportException $e) {
            return ConnectionCheck::unreachable($e->kind);
        }
    }

    public function perform(ProviderCredential $credential, ProviderOperation $operation): OperationResult
    {
        if (!$this->adapter->supports($operation->name)) {
            return OperationResult::unsupported($operation->name);
        }

        $fingerprint = self::fingerprint(
            $this->adapter->id(),
            $credential->accountIdentity,
            $operation,
        );

        $claim = $this->ledger->claim($operation->idempotencyKey, $fingerprint, time());

        switch ($claim->state) {
            case LedgerClaim::REPLAY:
                return $claim->result->asReplay();
            case LedgerClaim::IN_PROGRESS:
                return OperationResult::inProgress($operation->name);
            case LedgerClaim::UNCERTAIN:
                return OperationResult::uncertain($operation->name);
            case LedgerClaim::CONFLICT:
                return OperationResult::conflict($operation->name);
        }

        return $this->executeClaimed($credential, $operation, $fingerprint);
    }

    private function executeClaimed(ProviderCredential $credential, ProviderOperation $operation, string $fingerprint): OperationResult
    {
        $idempotent = $this->isIdempotent($operation->name);
        $maxAttempts = $idempotent ? $this->retry->maxAttempts : 1;

        $result = OperationResult::uncertain($operation->name);
        $attempts = 0;
        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                $result = $this->adapter->execute($this->transport, $credential, $operation);
            } catch (TransportException $e) {
                $result = $idempotent
                    ? OperationResult::retryable($operation->name, 'transport ' . $e->kind)
                    : OperationResult::uncertain($operation->name);
            }
            if ($result->status !== OperationResult::RETRYABLE) {
                break;
            }
            if ($attempts < $maxAttempts && $this->retry->backoffSeconds > 0) {
                usleep((int) ($this->retry->backoffSeconds * 1_000_000));
            }
        }

        // A write whose verdict is ambiguous (429/5xx, transport fault
        // after the request may have been sent) is recorded as UNCERTAIN
        // — it may have landed, so it is never classified as a clean
        // failure nor retried.
        if (!$idempotent && $result->status === OperationResult::RETRYABLE) {
            $result = OperationResult::uncertain($operation->name, $result->providerCode);
        }
        $result = $result->withAttempts($attempts);

        try {
            $this->ledger->record($operation->idempotencyKey, $fingerprint, $result, time());
        } catch (Throwable) {
            // The remote call may have landed but the durable record
            // failed — the honest state is uncertain, not the result.
            return OperationResult::uncertain($operation->name)->withAttempts($attempts);
        }
        return $result;
    }

    private function isIdempotent(string $operation): bool
    {
        foreach ($this->adapter->capabilities() as $capability) {
            if ($capability->operation === $operation) {
                return $capability->idempotent;
            }
        }
        return false;
    }

    /**
     * Canonical binding of a key to one request. Arrays are sorted
     * recursively by key so logically-equal params hash identically;
     * anything not JSON-representable is rejected rather than hashed
     * ambiguously.
     */
    private static function fingerprint(string $providerId, string $accountIdentity, ProviderOperation $operation): string
    {
        $canonical = self::canonicalize($operation->params);
        return hash('sha256', implode("\n", [$providerId, $accountIdentity, $operation->name, $canonical]));
    }

    /**
     * @param array<string,mixed> $value
     */
    private static function canonicalize(array $value): string
    {
        $normalized = self::normalize($value);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
        if ($json === false) {
            throw new InvalidArgumentException('Operation parameters must be JSON-representable.');
        }
        return $json;
    }

    private static function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        // Sequential arrays keep order; associative arrays sort by key.
        if ($value !== [] && array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value);
        }
        return array_map(self::normalize(...), $value);
    }
}
