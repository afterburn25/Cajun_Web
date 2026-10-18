<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIInventory;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Registrar list identity only. Hosting names, contacts and billing are not authority. */
final readonly class InventoryDomain
{
    private function __construct(public int $id, public string $name, public int $unmappedCount) {}

    public static function fromItem(mixed $item): self
    {
        if (!$item instanceof \stdClass) throw new \InvalidArgumentException('Registrar entry is not an object.');
        $id=$item->id??null;
        if (is_string($id) && preg_match('/\A[1-9][0-9]{0,17}\z/',$id)) $id=(int)$id;
        if (!is_int($id)||$id<1||$id>999999999999999999) throw new \InvalidArgumentException('Registrar id is not an allocated numeric ID.');
        $name=$item->name??null;
        if (!is_string($name)||strlen($name)>253||$name!==strtolower($name)
            ||!preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+\z/',$name)
            ||filter_var($name,FILTER_VALIDATE_IP)!==false) throw new \InvalidArgumentException('Registrar name is not a canonical ASCII domain.');
        // Only count unknown fields: their keys can themselves contain private values.
        return new self($id,$name,max(0,count(get_object_vars($item))-2));
    }
}
