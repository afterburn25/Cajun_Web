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
 *
 * JSON decoding preserves object/list identity: `json_decode` without
 * the associative flag keeps objects as stdClass and arrays as lists,
 * so a `{}` or `{"0": ...}` body can never collapse into a plausible
 * empty or populated PHP array.
 *
 * `scope` is the caller-supplied provider account/operation identity
 * recorded on the observation — callers must bind it to the exact
 * account read so stored snapshots cannot be confused between
 * accounts. Collection coverage is always recorded UNKNOWN: no
 * pagination/completeness contract is documented, so a successful
 * response projection is not proof of account-wide inventory.
 */
final class InventoryProjector
{
    public const MAX_BODY_BYTES = 1048576;
    public const MAX_PACKAGES = 4096;
    public const MAX_RESOURCES = 8192;
    public const MAX_SKIP_REASONS = 3;
    private const JSON_DEPTH = 16;

    /** Map a transport failure to an unavailable observation — the finite kind, never raw text. */
    public static function unavailableFromTransport(TransportException $failure, int $observedAt, string $scope = ''): InventorySnapshot
    {
        return InventorySnapshot::unavailable('transport_'.$failure->kind, $observedAt, $scope);
    }

    public static function packagesList(TransportResponse $response, int $observedAt, string $scope = ''): InventorySnapshot
    {
        $decoded = self::listBody($response, $observedAt, $scope);
        if ($decoded instanceof InventorySnapshot) return $decoded;
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
            array_values($packages), $observedAt, $skipped, $reasons, '',
            $scope, InventorySnapshot::COVERAGE_UNKNOWN,
        );
    }

    /** Cloud metadata never implies active service, a retail offering or client ownership. */
    public static function cloudServersList(TransportResponse $response, int $observedAt, string $scope = ''): InventorySnapshot
    {
        $decoded = self::listBody($response, $observedAt, $scope, self::MAX_RESOURCES);
        if ($decoded instanceof InventorySnapshot) return $decoded;
        $servers = []; $skipped = 0; $reasons = [];
        foreach ($decoded as $item) {
            try { $server = InventoryCloudServer::fromItem($item); }
            catch (\InvalidArgumentException $e) { self::skip($skipped, $reasons, $e->getMessage()); continue; }
            if (isset($servers[$server->id])) { self::skip($skipped, $reasons, 'Duplicate cloud server id in response.'); continue; }
            $servers[$server->id] = $server;
        }
        return new InventorySnapshot($skipped > 0 ? InventorySnapshot::PARTIAL : InventorySnapshot::COMPLETE,
            array_values($servers), $observedAt, $skipped, $reasons, '', $scope, InventorySnapshot::COVERAGE_UNKNOWN);
    }

    /** 20i SDK registrar example documents only allocated id and name. */
    public static function domainsList(TransportResponse $response, int $observedAt, string $scope = ''): InventorySnapshot
    {
        require_once __DIR__.'/InventoryDomain.php';
        $decoded=self::listBody($response,$observedAt,$scope,self::MAX_RESOURCES);
        if ($decoded instanceof InventorySnapshot) return $decoded;
        $domains=[]; $names=[]; $skipped=0; $reasons=[];
        foreach($decoded as $item) {
            try { $domain=InventoryDomain::fromItem($item); }
            catch (\InvalidArgumentException $e) { self::skip($skipped,$reasons,$e->getMessage()); continue; }
            if(isset($domains[$domain->id])||isset($names[$domain->name])) { self::skip($skipped,$reasons,'Duplicate registrar identity in response.'); continue; }
            $domains[$domain->id]=$domain; $names[$domain->name]=true;
        }
        return new InventorySnapshot($skipped?InventorySnapshot::PARTIAL:InventorySnapshot::COMPLETE,array_values($domains),$observedAt,$skipped,$reasons,'',$scope,InventorySnapshot::COVERAGE_UNKNOWN);
    }

    /**
     * Identity-only projection for list endpoints whose response schema
     * is not documented (vps.list, managedvps.list, domains.list,
     * reseller.servicechanges). Each item retains only an allocated
     * numeric `id`; every other field is dropped and reported by name.
     * Items without an `id` are skipped rather than guessed at, so an
     * unexpectedly shaped response degrades to PARTIAL/UNAVAILABLE —
     * never a fabricated inventory.
     */
    public static function resourceList(TransportResponse $response, int $observedAt, string $operation, string $scope = ''): InventorySnapshot
    {
        $decoded = self::listBody($response, $observedAt, $scope, self::MAX_RESOURCES);
        if ($decoded instanceof InventorySnapshot) return $decoded;
        $resources = [];
        $skipped = 0;
        $reasons = [];
        foreach ($decoded as $item) {
            try {
                $resource = InventoryResource::fromItem($item, $operation);
            } catch (\InvalidArgumentException $e) {
                self::skip($skipped, $reasons, $e->getMessage());
                continue;
            }
            if (isset($resources[$resource->id])) {
                self::skip($skipped, $reasons, 'Duplicate resource id in response.');
                continue;
            }
            $resources[$resource->id] = $resource;
        }
        return new InventorySnapshot(
            $skipped > 0 ? InventorySnapshot::PARTIAL : InventorySnapshot::COMPLETE,
            array_values($resources), $observedAt, $skipped, $reasons, '',
            $scope, InventorySnapshot::COVERAGE_UNKNOWN,
        );
    }

    /**
     * Shared response envelope: status classification, byte bound, and
     * a strict JSON top-level list with object identity preserved.
     * Returns the decoded item list (stdClass items), or an UNAVAILABLE
     * snapshot naming the bounded failure kind.
     *
     * @return array<int,\stdClass>|InventorySnapshot
     */
    private static function listBody(TransportResponse $response, int $observedAt, string $scope, int $maxItems = self::MAX_PACKAGES): array|InventorySnapshot
    {
        if ($response->status === 401 || $response->status === 403) {
            return InventorySnapshot::unavailable('authentication', $observedAt, $scope);
        }
        if ($response->status === 429) return InventorySnapshot::unavailable('rate_limited', $observedAt, $scope);
        if ($response->status >= 500) return InventorySnapshot::unavailable('remote_error', $observedAt, $scope);
        if ($response->status !== 200) return InventorySnapshot::unavailable('unexpected_status', $observedAt, $scope);
        if (strlen($response->body) > self::MAX_BODY_BYTES) {
            return InventorySnapshot::unavailable('response_limit', $observedAt, $scope);
        }
        try {
            $decoded = json_decode($response->body, false, self::JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return InventorySnapshot::unavailable('malformed_body', $observedAt, $scope);
        }
        if (!is_array($decoded)) {
            return InventorySnapshot::unavailable('unexpected_shape', $observedAt, $scope);
        }
        if (count($decoded) > $maxItems) {
            return InventorySnapshot::unavailable('response_limit', $observedAt, $scope);
        }
        return $decoded;
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
