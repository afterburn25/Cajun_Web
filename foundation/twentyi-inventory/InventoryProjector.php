<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIInventory;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\TransportException;
use CajunWebPro\Foundation\Provider\TransportResponse;
use JsonException;

/**
 * Projects a `GET /package` response into a bounded InventorySnapshot.
 *
 * The projector never invents schema: only the fields shown in primary
 * documentation are retained, and a transport failure, error status or
 * malformed body is an UNAVAILABLE observation — never an empty list.
 * Duplicate package IDs keep the first entry and mark the snapshot
 * PARTIAL, because a duplicated identity means the response cannot be
 * trusted as-is.
 */
final class InventoryProjector
{
    public const MAX_BODY_BYTES = 1048576;
    public const MAX_PACKAGES = 4096;
    public const MAX_SKIP_REASONS = 3;
    private const JSON_DEPTH = 16;

    /** Map a transport failure to an unavailable observation — the finite kind, never raw text. */
    public static function unavailableFromTransport(TransportException $failure, int $observedAt): InventorySnapshot
    {
        return InventorySnapshot::unavailable('transport_'.$failure->kind, $observedAt);
    }

    public static function packagesList(TransportResponse $response, int $observedAt): InventorySnapshot
    {
        if ($response->status === 401 || $response->status === 403) {
            return InventorySnapshot::unavailable('authentication', $observedAt);
        }
        if ($response->status === 429) return InventorySnapshot::unavailable('rate_limited', $observedAt);
        if ($response->status >= 500) return InventorySnapshot::unavailable('remote_error', $observedAt);
        if ($response->status !== 200) return InventorySnapshot::unavailable('unexpected_status', $observedAt);
        if (strlen($response->body) > self::MAX_BODY_BYTES) {
            return InventorySnapshot::unavailable('response_limit', $observedAt);
        }
        try {
            $decoded = json_decode($response->body, true, self::JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return InventorySnapshot::unavailable('malformed_body', $observedAt);
        }
        if (!is_array($decoded) || !array_is_list($decoded)) {
            return InventorySnapshot::unavailable('unexpected_shape', $observedAt);
        }
        if (count($decoded) > self::MAX_PACKAGES) {
            return InventorySnapshot::unavailable('response_limit', $observedAt);
        }
        $packages = [];
        $skipped = 0;
        $reasons = [];
        foreach ($decoded as $item) {
            try {
                $package = InventoryPackage::fromItem($item);
            } catch (\InvalidArgumentException $e) {
                self::skip($skipped, $reasons, $e->getMessage());
                continue;
            }
            if (isset($packages[$package->id])) {
                self::skip($skipped, $reasons, 'Duplicate package id in response.');
                continue;
            }
            $packages[$package->id] = $package;
        }
        return new InventorySnapshot(
            $skipped > 0 ? InventorySnapshot::PARTIAL : InventorySnapshot::COMPLETE,
            array_values($packages), $observedAt, $skipped, $reasons,
        );
    }

    /** @param string[] $reasons */
    private static function skip(int &$skipped, array &$reasons, string $reason): void
    {
        $skipped++;
        if (count($reasons) < self::MAX_SKIP_REASONS && !in_array($reason, $reasons, true)) {
            $reasons[] = $reason;
        }
    }
}
