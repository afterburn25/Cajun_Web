<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIInventory;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use stdClass;

/**
 * Bounded projection of GET /cloud_server from the official Cloud Hosting
 * API guide. Its printed PHP example does not establish JSON scalar types:
 * numeric facts accept integers or decimal strings, never floats/coercion.
 * Missing optional values remain unknown. Names/specifications are observed
 * metadata, not proof of ownership, entitlement, active service or pricing.
 * Unverified cPanel/boolean/count fields and all unknown values are dropped.
 */
final readonly class InventoryCloudServer
{
    public const OPERATION = 'cloudservers.list';
    public const MAX_PACKAGE_IDS = 4096;
    private const MAX_ID = 999999999999999999;

    private function __construct(
        public int $id,
        public ?string $name,
        public ?string $location,
        public ?string $productSpec,
        public ?array $configuration,
        public ?array $packageIds,
        public int $unmappedCount,
    ) {}

    public static function fromItem(mixed $item): self
    {
        if (!$item instanceof stdClass) throw new InvalidArgumentException('Cloud server entry is not an object.');
        $id = self::number($item->id ?? null, 1, self::MAX_ID);
        $name = self::optionalText($item, 'name', 253);
        $location = self::optionalText($item, 'location', 120);
        $productSpec = self::optionalText($item, 'productSpec', 120);
        $configuration = null;
        $unmapped = count(array_diff(array_keys(get_object_vars($item)), ['id','name','location','productSpec','configuration','packageIds']));
        if (isset($item->configuration)) {
            if (!$item->configuration instanceof stdClass) throw new InvalidArgumentException('Cloud configuration is not an object.');
            $configuration = [];
            foreach (['CpuCores'=>1,'RamMb'=>1,'OsDiskSizeGb'=>0,'DataDiskSizeGb'=>0,'VpsOsId'=>1] as $field=>$min) {
                $value = $item->configuration->$field ?? null;
                $configuration[$field] = $value === null ? null : self::number($value, $min, self::MAX_ID);
            }
            foreach (['Provider','Zone','Country','spec'] as $field) $configuration[$field] = self::optionalText($item->configuration, $field, 120);
            $unmapped += count(array_diff(array_keys(get_object_vars($item->configuration)), array_keys($configuration)));
        }
        $packageIds = null;
        if (isset($item->packageIds)) {
            if (!is_array($item->packageIds) || !array_is_list($item->packageIds) || count($item->packageIds) > self::MAX_PACKAGE_IDS) {
                throw new InvalidArgumentException('Cloud package identities exceed the supported list shape.');
            }
            $packageIds = [];
            $seen = [];
            foreach ($item->packageIds as $value) {
                $packageId = self::number($value, 1, self::MAX_ID);
                if (isset($seen[$packageId])) throw new InvalidArgumentException('Cloud package identities contain a duplicate.');
                $seen[$packageId] = true;
                $packageIds[] = $packageId;
            }
        }
        return new self($id, $name, $location, $productSpec, $configuration, $packageIds, $unmapped);
    }

    private static function number(mixed $value, int $min, int $max): int
    {
        if (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,17})\z/', $value)) $value = (int)$value;
        if (!is_int($value) || $value < $min || $value > $max) throw new InvalidArgumentException('Cloud numeric fact is outside its supported range.');
        return $value;
    }

    private static function optionalText(stdClass $item, string $field, int $limit): ?string
    {
        $value = $item->$field ?? null;
        if ($value === null) return null;
        if (!is_string($value) || $value === '' || strlen($value) > $limit || preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new InvalidArgumentException('Cloud text fact is outside its supported shape.');
        }
        return $value;
    }
}
