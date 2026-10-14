<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIInventory;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use stdClass;

/**
 * One hosting package projected from a 20i `GET /package` list item.
 *
 * Only the fields a primary source shows are retained: id, created,
 * enabled, name, names, packageTypeName, stackUsers, typeRef and
 * packageLabels (docs.20i.com/api/retrieve-packages-api). Raw provider
 * payloads are never kept; unmapped keys are reported by safe name
 * plus a total count so schema drift is visible without storing
 * unknown values. productSpec is documented as null in every sample —
 * a non-null presence is noted but its shape remains unknown and its
 * content is dropped.
 *
 * The reported collection is preserved as-is: if the documented
 * primary `name` is absent from `names`, the item is rejected as
 * inconsistent rather than silently unioned into a different set of
 * domains.
 */
final readonly class InventoryPackage
{
    private const MAX_NAMES = 64;
    private const MAX_STACK_USERS = 64;
    private const MAX_LABELS = 64;
    private const MAX_UNMAPPED_KEYS = 16;
    private const MAX_NAME_BYTES = 253;
    private const MAX_TEXT_BYTES = 120;
    private const MAX_KEY_BYTES = 64;

    /**
     * @param string[] $names         Domain names exactly as reported by the provider.
     * @param string[] $stackUsers    Documented `stack-user:N` references — provider IDs, not client links.
     * @param string[] $packageLabels
     * @param string[] $unmappedKeys  Printable unmapped field names (values dropped).
     * @param int      $unmappedCount Total unmapped field count, including unprintable names.
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $names,
        public bool $enabled,
        public string $createdAt,
        public int $createdEpoch,
        public int $typeRef,
        public ?string $packageTypeName,
        public array $stackUsers,
        public array $packageLabels,
        public bool $hasProductSpec,
        public array $unmappedKeys,
        public int $unmappedCount,
    ) {}

    /**
     * Project one documented list item. The item must be a decoded JSON
     * object (stdClass) — a PHP array is programmer error, a list is
     * malformed input. Known fields with the wrong type fail the item;
     * unknown keys never fail and are reported by name only.
     */
    public static function fromItem(mixed $item): self
    {
        if (!$item instanceof stdClass) {
            throw new InvalidArgumentException('Package entry is not an object.');
        }
        $id = self::allocatedInt($item->id ?? null, 'id');
        $typeRef = self::allocatedInt($item->typeRef ?? null, 'typeRef');
        $enabled = $item->enabled ?? null;
        if (!is_bool($enabled)) throw new InvalidArgumentException('Package enabled flag is not boolean.');
        $name = self::text($item->name ?? null, 'name', self::MAX_NAME_BYTES);
        $names = self::textList($item->names ?? null, 'names', self::MAX_NAMES, self::MAX_NAME_BYTES);
        if (!in_array($name, $names, true)) {
            throw new InvalidArgumentException('Package name is absent from the reported names list.');
        }
        $packageTypeName = $item->packageTypeName ?? null;
        if ($packageTypeName !== null) $packageTypeName = self::text($packageTypeName, 'packageTypeName', self::MAX_TEXT_BYTES);
        $createdAt = $item->created ?? null;
        if (!is_string($createdAt) || ($epoch = self::createdEpoch($createdAt)) === null) {
            throw new InvalidArgumentException('Package created timestamp is not an ISO-8601 instant.');
        }
        $stackUsers = self::stackUserList($item->stackUsers ?? []);
        $labels = self::textList($item->packageLabels ?? [], 'packageLabels', self::MAX_LABELS, self::MAX_TEXT_BYTES);
        $hasProductSpec = ($item->productSpec ?? null) !== null;
        $known = ['id', 'created', 'enabled', 'name', 'names', 'packageTypeName', 'productSpec', 'stackUsers', 'typeRef', 'packageLabels'];
        $unmapped = [];
        $unmappedCount = 0;
        foreach (get_object_vars($item) as $key => $_) {
            if (in_array($key, $known, true)) continue;
            $unmappedCount++;
            $safe = self::safeKey($key);
            if ($safe !== null && !in_array($safe, $unmapped, true)) $unmapped[] = $safe;
        }
        sort($unmapped);
        return new self($id, $name, $names, $enabled, $createdAt, $epoch, $typeRef, $packageTypeName, $stackUsers, $labels, $hasProductSpec, array_slice($unmapped, 0, self::MAX_UNMAPPED_KEYS), $unmappedCount);
    }

    private static function allocatedInt(mixed $value, string $field): int
    {
        if (is_string($value) && preg_match('/\A[0-9]{1,18}\z/', $value)) $value = (int) $value;
        if (!is_int($value) || $value <= 0) throw new InvalidArgumentException('Package '.$field.' is not an allocated numeric ID.');
        return $value;
    }

    /**
     * Strict instant validation: real calendar date, real clock time,
     * documented offset forms. strtotime alone normalizes impossible
     * dates (2026-02-30 becomes March 2) and must not be trusted.
     */
    private static function createdEpoch(string $value): ?int
    {
        if (strlen($value) > 40
            || !preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})(?:\.[0-9]+)?(Z|[+-][0-9]{2}:[0-9]{2})\z/', $value, $m)) {
            return null;
        }
        [, $y, $mo, $d, $h, $mi, $s, $tz] = $m;
        if (!checkdate((int) $mo, (int) $d, (int) $y) || (int) $h > 23 || (int) $mi > 59 || (int) $s > 59) {
            return null;
        }
        if ($tz !== 'Z') {
            [$oh, $om] = array_map('intval', explode(':', substr($tz, 1)));
            if ($oh > 14 || ($oh === 14 && $om > 0) || $om > 59) return null;
        }
        $epoch = strtotime($value);
        return $epoch === false ? null : $epoch;
    }

    private static function text(mixed $value, string $field, int $maxBytes): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > $maxBytes || !preg_match('//u', $value) || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new InvalidArgumentException('Package '.$field.' is not bounded printable text.');
        }
        return $value;
    }

    /** @return string[] */
    private static function textList(mixed $value, string $field, int $maxCount, int $maxBytes): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > $maxCount) {
            throw new InvalidArgumentException('Package '.$field.' is not a bounded list.');
        }
        $out = [];
        foreach ($value as $entry) $out[] = self::text($entry, $field.'[]', $maxBytes);
        return self::dedupe($out);
    }

    /** @return string[] Documented stack-user references only — never raw user objects. */
    private static function stackUserList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > self::MAX_STACK_USERS) {
            throw new InvalidArgumentException('Package stackUsers is not a bounded list.');
        }
        $out = [];
        foreach ($value as $entry) {
            if (!is_string($entry) || !preg_match('/\Astack-user:[0-9]{1,18}\z/', $entry)) {
                throw new InvalidArgumentException('Package stackUsers entry is not a stack-user reference.');
            }
            $out[] = $entry;
        }
        return self::dedupe($out);
    }

    /**
     * An unmapped key worth reporting by name: printable UTF-8,
     * truncated at a character boundary — never a byte-split encoding
     * fragment. Unprintable keys still count toward unmappedCount.
     */
    private static function safeKey(string $key): ?string
    {
        if (preg_match('//u', $key) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $key)) {
            return null;
        }
        if (strlen($key) > self::MAX_KEY_BYTES) {
            $key = substr($key, 0, self::MAX_KEY_BYTES);
            while ($key !== '' && preg_match('//u', $key) !== 1) {
                $key = substr($key, 0, -1);
            }
        }
        return $key === '' ? null : $key;
    }

    /** @return string[] */
    private static function dedupe(array $values): array
    {
        return array_values(array_unique($values));
    }
}
