<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIInventory;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** An observed account provider identifier, never a product, price or server entitlement. */
final readonly class InventoryCloudProvider
{
    /** Conservative local acceptance bound, not a claim about the provider's limit. */
    public const MAX_IDENTIFIER_BYTES = 128;

    private function __construct(public string $identifier) {}

    public static function fromValue(mixed $value): self
    {
        // Preserve exact identity and case. Never trim, coerce, normalize or use a
        // returned identifier as a host/path. Unsupported identifiers fail closed.
        if (!is_string($value) || strlen($value) > self::MAX_IDENTIFIER_BYTES
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.-]*\z/D', $value) !== 1) {
            throw new \InvalidArgumentException('Cloud provider identifier is unsupported.');
        }
        return new self($value);
    }
}
