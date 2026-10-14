<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIInventory;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\TransportRequest;
use InvalidArgumentException;

/**
 * Closed set of semantically read-only 20i request descriptors.
 *
 * Every descriptor is a GET on the fixed provider host with a bounded
 * deadline and no request body. HTTP GET alone is not treated as proof
 * of a safe read: the register only admits operations whose documented
 * purpose is retrieval. Credential headers are never set here — the
 * caller's credential layer attaches authorization to the returned
 * request, so this component never touches a secret.
 */
final class InventoryRead
{
    public const HOST = 'api.20i.com';
    public const TIMEOUT_SECONDS = 15.0;

    public const PACKAGES_LIST = 'packages.list';
    public const PACKAGE_DETAIL = 'packages.detail';
    public const PACKAGE_TYPES = 'package.types';
    public const ACCOUNT_BALANCE = 'account.balance';

    /** Reseller self alias documented by 20i for the resellerId slot. */
    private const RESELLER_SELF = '*';

    /**
     * Build the descriptor for a closed read operation. Allocated IDs
     * are positive integers; the reseller slot also accepts the
     * documented '*' self alias. Anything else is refused before a
     * request object exists.
     */
    public static function request(string $operation, int|string $allocatedId = 0): TransportRequest
    {
        $path = match ($operation) {
            self::PACKAGES_LIST => '/package',
            self::PACKAGE_DETAIL => '/package/'.self::allocatedId($allocatedId),
            self::PACKAGE_TYPES => '/reseller/'.self::resellerId($allocatedId).'/packageTypes',
            self::ACCOUNT_BALANCE => '/reseller/'.self::resellerId($allocatedId).'/accountBalance',
            default => throw new InvalidArgumentException('Not a registered 20i inventory read: '.$operation),
        };
        return new TransportRequest('GET', 'https://'.self::HOST.$path, ['Accept' => 'application/json'], null, self::TIMEOUT_SECONDS);
    }

    /** @return string[] Registered read operation names. */
    public static function operations(): array
    {
        return [self::PACKAGES_LIST, self::PACKAGE_DETAIL, self::PACKAGE_TYPES, self::ACCOUNT_BALANCE];
    }

    private static function allocatedId(int|string $id): int
    {
        if (is_string($id)) {
            if (!preg_match('/\A[0-9]{1,18}\z/', $id)) {
                throw new InvalidArgumentException('Package identifiers are allocated numeric IDs.');
            }
            $id = (int) $id;
        }
        if ($id <= 0) {
            throw new InvalidArgumentException('Package detail reads require an allocated package ID.');
        }
        return $id;
    }

    private static function resellerId(int|string $id): int|string
    {
        if ($id === self::RESELLER_SELF) return $id;
        return self::allocatedId($id);
    }
}
