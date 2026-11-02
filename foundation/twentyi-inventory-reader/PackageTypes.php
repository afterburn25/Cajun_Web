<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIInventoryReader;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\TransportResponse;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryPackageType,InventorySnapshot};
require_once __DIR__.'/../twentyi-inventory/InventoryPackageType.php';

/** Reseller-self packageTypes GET: observed id/label projection, with unknown account coverage. */
final class PackageTypes
{
    public const MAX_BODY_BYTES = 1048576;
    private const MAX_NODES = 50000;

    public static function project(TransportResponse $response, int $observedAt, string $scope): InventorySnapshot
    {
        $reason = match (true) {
            in_array($response->status, [401,403], true) => 'authentication',
            $response->status === 429 => 'rate_limited',
            $response->status >= 500 => 'remote_error',
            $response->status !== 200 => 'unexpected_status',
            strlen($response->body) > self::MAX_BODY_BYTES => 'response_limit',
            default => null,
        };
        if ($reason !== null) return InventorySnapshot::unavailable($reason, $observedAt, $scope);
        try {
            $items = json_decode($response->body, false, 16, JSON_THROW_ON_ERROR);
            // A normal decoder erases duplicate keys; reject ambiguity before projecting.
            if (self::duplicateKeys($response->body)) throw new \JsonException();
            $nodes = 0;
            self::boundedValues($items, $nodes);
        } catch (\JsonException|\InvalidArgumentException) {
            return InventorySnapshot::unavailable('malformed_body', $observedAt, $scope);
        }
        if (!is_array($items)) return InventorySnapshot::unavailable('unexpected_shape', $observedAt, $scope);
        if (count($items) > InventorySnapshot::MAX_ITEMS) return InventorySnapshot::unavailable('response_limit', $observedAt, $scope);
        $records = []; $ids = [];
        try {
            foreach ($items as $item) {
                $record = InventoryPackageType::fromItem($item);
                if (isset($ids['id:'.$record->id])) throw new \InvalidArgumentException();
                $ids['id:'.$record->id] = true;
                $records[] = $record;
            }
        } catch (\InvalidArgumentException) {
            // Never allow import from a partly valid catalog or silently deduplicate IDs.
            return InventorySnapshot::unavailable('unexpected_shape', $observedAt, $scope);
        }
        usort($records, static fn(InventoryPackageType $a, InventoryPackageType $b): int => strcmp($a->id, $b->id));
        return new InventorySnapshot(InventorySnapshot::COMPLETE, $records, $observedAt, 0, [], '', $scope);
    }

    /** JSON is already valid and depth/bytes bounded. Count decoded work and refuse nonfinite numbers. */
    private static function boundedValues(mixed $value, int &$nodes): void
    {
        if (++$nodes > self::MAX_NODES || (is_float($value) && !is_finite($value))) throw new \InvalidArgumentException();
        if (is_array($value) || $value instanceof \stdClass) {
            foreach ($value as $child) self::boundedValues($child, $nodes);
        }
    }

    /** Scan validated JSON strings at their object depth; unknown keys are never retained in the result. */
    private static function duplicateKeys(string $json): bool
    {
        $keys = []; $depth = 0; $length = strlen($json);
        for ($i = 0; $i < $length; $i++) {
            $c = $json[$i];
            if ($c === '{' || $c === '[') { $keys[++$depth] = []; continue; }
            if ($c === '}' || $c === ']') { unset($keys[$depth--]); continue; }
            if ($c !== '"') continue;
            $start = $i++;
            while ($i < $length) {
                if ($json[$i] === '\\') { $i += 2; continue; }
                if ($json[$i] === '"') break;
                $i++;
            }
            $next = $i + 1;
            while ($next < $length && str_contains(" \r\n\t", $json[$next])) $next++;
            if ($next < $length && $json[$next] === ':') {
                $key = 'key:'.json_decode(substr($json, $start, $i - $start + 1), false, 2, JSON_THROW_ON_ERROR);
                if (isset($keys[$depth][$key])) return true;
                $keys[$depth][$key] = true;
            }
        }
        return false;
    }
}
