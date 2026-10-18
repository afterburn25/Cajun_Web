<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIInventory;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/InventoryCloudProvider.php';

/**
 * Pure projection of the documented cloudProviders identifier array. The caller
 * owns HTTP classification, JSON object/list preservation and account binding.
 * No transport, credential, persistence or generic provider dispatch exists here.
 */
final class CloudProviderList
{
    /** Local capacity bound; no provider maximum or exhaustive coverage inferred. */
    public const MAX_ITEMS = 256;

    /** @return InventoryCloudProvider[] Detached records in observed order. */
    public static function fromValues(mixed $values): array
    {
        if (!is_array($values) || !array_is_list($values) || count($values) > self::MAX_ITEMS) {
            throw new \InvalidArgumentException('Cloud provider list is unsupported.');
        }
        $records = []; $seen = [];
        foreach ($values as $value) {
            $record = InventoryCloudProvider::fromValue($value);
            // Prefix keys so PHP's numeric-string array-key coercion cannot
            // silently conflate identities. Duplicates refuse the whole list.
            $identity = 'provider:'.$record->identifier;
            if (isset($seen[$identity])) {
                throw new \InvalidArgumentException('Cloud provider list repeats an identifier.');
            }
            $seen[$identity] = true;
            $records[] = $record;
        }
        return $records;
    }
}
