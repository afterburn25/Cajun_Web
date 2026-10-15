<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyI\Operations;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\OperationResult;
use CajunWebPro\Foundation\Provider\TransportException;
use CajunWebPro\Foundation\Provider\TransportResponse;

/**
 * Maps one provider write attempt to a durable OperationResult.
 *
 * Write classification is deliberately conservative: once the request
 * may have reached the provider, any ambiguous outcome is UNCERTAIN —
 * never a clean failure and never silently retryable, because the
 * remote side effect may have landed. Only provably rejected (4xx) or
 * provably unsent (connect/TLS/unavailable) attempts classify failed.
 *
 * Response bodies are read solely to enrich a successful result with a
 * documented identifier field; a 2xx status is authoritative even when
 * the body is unreadable, because the write already happened.
 */
final class TwentyIResultClassifier
{
    private const MAX_RESPONSE_BYTES = 65536;

    /**
     * @param string $operation  The ProviderOperation name, for the result record.
     * @param ?string $idField   Documented top-level body field that carries the
     *                           assigned resource id, if the reference names one.
     *                           None of the current descriptors document one, so
     *                           callers pass null and externalId stays null.
     */
    public static function fromResponse(string $operation, TransportResponse $response, ?string $idField = null): OperationResult
    {
        // 200/201/204 are the only terminal success statuses the reference
        // semantics support. 202 means the write is merely queued — it may
        // never be carried out — and any other 2xx has undocumented meaning
        // for a write, so both stay uncertain until verified.
        if (in_array($response->status, [200, 201, 204], true)) {
            return OperationResult::succeeded($operation, 'Provider accepted the request.', self::externalId($response->body, $idField));
        }
        if ($response->status === 401 || $response->status === 403) {
            return OperationResult::failed($operation, 'Provider rejected the credential.', 'authentication');
        }
        if ($response->status >= 400 && $response->status < 500 && $response->status !== 429) {
            return OperationResult::failed($operation, 'Provider rejected the request.', 'http_' . $response->status);
        }
        // 3xx, 429 and 5xx: the request was received; its effect is unknown.
        return OperationResult::uncertain($operation, 'http_' . $response->status);
    }

    public static function fromTransportFailure(string $operation, TransportException $e): OperationResult
    {
        if (in_array($e->kind, [TransportException::KIND_CONNECT, TransportException::KIND_TLS, TransportException::KIND_UNAVAILABLE], true)) {
            // Provably pre-send: the write could not have reached the provider.
            return OperationResult::failed($operation, 'Provider endpoint unreachable before send.', $e->kind);
        }
        // timeout / protocol / unknown: may have landed; reconcile first.
        return OperationResult::uncertain($operation, $e->kind);
    }

    private static function externalId(string $body, ?string $idField): ?string
    {
        if ($idField === null || strlen($body) > self::MAX_RESPONSE_BYTES) {
            return null;
        }
        $decoded = json_decode($body, true, 16);
        if (!is_array($decoded)) {
            return null; // status is authoritative; unreadable bodies never fail a landed write
        }
        $value = $decoded[$idField] ?? null;
        return is_int($value) && $value > 0 ? (string) $value : null;
    }
}
