<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyI\Operations;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{OperationResult,TransportException,TransportResponse};

/** Operation-specific acknowledgement; an HTTP status never proves business completion. */
final class TwentyIResultClassifier
{
    private const MAX_RESPONSE_BYTES = 65536;

    public static function fromResponse(string $operation, TransportResponse $response): OperationResult
    {
        if ($response->status === 401 || $response->status === 403) {
            return OperationResult::failed($operation, 'Provider rejected the credential.', 'authentication');
        }
        // A timeout/conflict may describe an already-running or existing effect.
        if ($response->status >= 400 && $response->status < 500 && !in_array($response->status, [408,409,425,429], true)) {
            return OperationResult::failed($operation, 'Provider rejected the request.', 'http_'.$response->status);
        }
        if (!in_array($response->status, [200,201,202], true)) {
            return OperationResult::uncertain($operation, 'http_'.$response->status);
        }
        if (strlen($response->body) > self::MAX_RESPONSE_BYTES) {
            return OperationResult::uncertain($operation, 'response_limit');
        }
        try { $body = json_decode($response->body, false, 16, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return OperationResult::uncertain($operation, 'response_shape'); }

        // Official provision-hosting-package-api: {"result": allocated package ID}.
        // Retain that ID for reconciliation, never substitute a domain-name match.
        // Mixed/error envelopes cannot silently ignore unvalidated siblings.
        if ($operation === 'packages.create' && $body instanceof \stdClass
            && array_keys(get_object_vars($body)) === ['result']) {
            $id = $body->result;
            if (is_int($id) && $id > 0 && strlen((string)$id) <= 18) {
                return OperationResult::acceptedPending($operation, (string)$id);
            }
        }
        // False/null, arbitrary true, unknown IDs and per-resource maps have no
        // established completion contract here. Keep ambiguity durable; never
        // echo raw content or retry an unknown side effect automatically.
        return OperationResult::uncertain($operation, 'response_unverified');
    }

    public static function fromTransportFailure(string $operation, TransportException $e): OperationResult
    {
        if (in_array($e->kind, [TransportException::KIND_CONNECT,TransportException::KIND_TLS,TransportException::KIND_UNAVAILABLE], true)) {
            return OperationResult::failed($operation, 'Provider endpoint unreachable before send.', $e->kind);
        }
        return OperationResult::uncertain($operation, $e->kind);
    }
}
