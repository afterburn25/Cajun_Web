<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIInventory;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Observed packageTypes identity and label only; not a price, platform or entitlement. */
final readonly class InventoryPackageType
{
    private function __construct(public string $id, public string $label) {}

    /** The original bounded schema read observed an integer ID and string label. */
    public static function fromItem(mixed $item): self
    {
        if (!$item instanceof \stdClass || !isset($item->id) || !is_int($item->id)) {
            throw new \InvalidArgumentException('Hosting catalog type identity is unsupported.');
        }
        return self::values((string)$item->id, $item->label ?? null);
    }

    /** Canonical detached storage form; never coerce a saved numeric string from wire JSON. */
    public static function fromStored(mixed $item): self
    {
        if (!is_array($item) || array_keys($item) !== ['id','label'] || !is_string($item['id'])) {
            throw new \InvalidArgumentException('Saved hosting catalog type is unsupported.');
        }
        return self::values($item['id'], $item['label']);
    }

    private static function values(string $id, mixed $label): self
    {
        if (preg_match('/\A[1-9][0-9]{0,17}\z/D', $id) !== 1 || !is_string($label)
            || trim($label) === '' || strlen($label) > 120 || preg_match('//u', $label) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $label)) {
            throw new \InvalidArgumentException('Hosting catalog type identity or label is unsupported.');
        }
        return new self($id, $label);
    }
}
