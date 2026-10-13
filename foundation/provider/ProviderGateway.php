<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * The entry point callers use. Enforces the HC-07 guarantees so
 * individual call sites don't have to:
 *
 *  - unsupported/unverified operations never reach the transport;
 *  - an idempotency key replays the recorded result instead of
 *    re-executing;
 *  - retryable failures (429/5xx, connection errors) are retried within
 *    a bounded policy and then recorded, so a later retry replays the
 *    failure record rather than silently re-running.
 */
final class ProviderGateway
{
    public function __construct(
        private readonly ProviderAdapter $adapter,
        private readonly HttpTransport $transport,
        private readonly OperationLedger $ledger,
        private readonly RetryPolicy $retry = new RetryPolicy(),
    ) {
    }

    public function checkConnection(ProviderCredential $credential): ConnectionCheck
    {
        try {
            return $this->adapter->probe($this->transport, $credential);
        } catch (TransportException $e) {
            return ConnectionCheck::unreachable($e->getMessage());
        }
    }

    public function perform(ProviderCredential $credential, ProviderOperation $operation): OperationResult
    {
        if (!$this->adapter->supports($operation->name)) {
            return OperationResult::unsupported($operation->name);
        }

        $recorded = $this->ledger->findByKey($operation->idempotencyKey);
        if ($recorded !== null) {
            return $recorded->asReplay();
        }

        $attempts = 0;
        $result = null;
        while ($attempts < $this->retry->maxAttempts) {
            $attempts++;
            try {
                $result = $this->adapter->execute($this->transport, $credential, $operation);
            } catch (TransportException $e) {
                $result = OperationResult::retryable($operation->name, $e->getMessage());
            }
            if ($result->status !== OperationResult::RETRYABLE) {
                break;
            }
            if ($attempts < $this->retry->maxAttempts && $this->retry->backoffSeconds > 0) {
                usleep((int) ($this->retry->backoffSeconds * 1_000_000));
            }
        }

        $result = $result->withAttempts($attempts);
        $this->ledger->record($operation->idempotencyKey, $result);
        return $result;
    }
}
