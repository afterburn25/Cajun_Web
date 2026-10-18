<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIDomainExpiry;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{TransportRequest, TransportResponse};

/** Pure descriptor/projection only: no credential resolution or transport dispatch. */
final class DomainExpiry
{
    public const OPERATION = 'domain.upstream_expiry_date';
    public const PREFIX = 'https://api.20i.com/domain/';
    public const MAX_BODY_BYTES = 4096;

    /** Name comes only from the exact validated registrar authority document. */
    public static function request(DomainExpiryBinding $binding): TransportRequest
    {
        return new TransportRequest(
            'GET',
            self::PREFIX . $binding->registrar->data['domain'] . '/upstreamExpiryDate',
            ['Accept' => 'application/json'],
            null,
            15.0,
        );
    }

    /** Consume one supplied response; discard body/headers and all error details. */
    public static function project(
        #[\SensitiveParameter] TransportResponse $response,
        DomainExpiryBinding $binding,
        int $at,
    ): DomainExpiryObservation {
        $fail = static fn(string $reason): DomainExpiryObservation =>
            new DomainExpiryObservation($binding, 'unavailable', null, $reason, $at);
        if ($response->status !== 200) {
            return $fail(match (true) {
                in_array($response->status, [401, 403], true) => 'authentication',
                $response->status === 429 => 'rate_limited',
                $response->status >= 500 => 'remote_error',
                default => 'unexpected_status',
            });
        }
        if (strlen($response->body) > self::MAX_BODY_BYTES) return $fail('response_limit');
        try {
            $timestamp = json_decode($response->body, false, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $fail('malformed_body');
        }
        if ($timestamp === null) return $fail('timestamp_null');
        if (!is_string($timestamp)) return $fail('timestamp_type');
        if (!DomainExpiryObservation::validTimestamp($timestamp)) return $fail('timestamp_unsupported');
        return new DomainExpiryObservation($binding, 'complete', $timestamp, '', $at);
    }
}
