<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIInventory;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One provider resource projected identity-only from a list endpoint
 * whose full response schema is not documented (/vps, /managed_vps,
 * /domain, /reseller/{id}/serviceChangeData).
 *
 * The only retained value is the allocated provider ID — the
 * reconciliation primitive "does the provider still list this
 * resource". No other field is trusted or stored: unmapped keys are
 * reported by name only so schema drift is visible without keeping
 * unknown values. A resource never links to a local client or service.
 */
final readonly class InventoryResource
{
    private const MAX_UNMAPPED_KEYS = 24;
    private const MAX_KEY_BYTES = 64;

    /**
     * Construction is factory-only: `fromItem` is the single validated
     * entry point, so every record carries only verified identity data.
     *
     * @param string[] $unmappedKeys Names of item fields not projected; values are dropped.
     */
    private function __construct(
        public int $id,
        public string $operation,
        public array $unmappedKeys,
    ) {
        if ($id <= 0 || !preg_match('/\A[a-z0-9]+(\.[a-z0-9]+)*\z/', $operation)) {
            throw new InvalidArgumentException('An inventory resource needs an allocated ID and its read operation.');
        }
    }

    /**
     * Project one list item retaining only `id`. The item must be a
     * decoded JSON object (stdClass); items without an allocated
     * numeric `id` fail — the documented convention is `id`, and this
     * component does not guess alternative identity keys.
     */
    public static function fromItem(mixed $item, string $operation): self
    {
        if (!$item instanceof \stdClass) {
            throw new InvalidArgumentException('Resource entry is not an object.');
        }
        $id = $item->id ?? null;
        if (is_string($id) && preg_match('/\A[0-9]{1,18}\z/', $id)) {
            $id = (int) $id;
        }
        if (!is_int($id) || $id <= 0) {
            throw new InvalidArgumentException('Resource id is not an allocated numeric ID.');
        }
        $unmapped = [];
        foreach (get_object_vars($item) as $key => $_) {
            if ($key !== 'id') {
                $safe = self::safeKey((string) $key);
                if ($safe !== null && !in_array($safe, $unmapped, true)) $unmapped[] = $safe;
            }
        }
        sort($unmapped);
        return new self($id, $operation, array_slice($unmapped, 0, self::MAX_UNMAPPED_KEYS));
    }

    /**
     * A reportable unmapped key: printable UTF-8, truncated at a
     * character boundary — never a byte-split encoding fragment.
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
}
