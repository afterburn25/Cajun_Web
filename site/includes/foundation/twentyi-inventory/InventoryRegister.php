<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIInventory;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * The read-only inventory capability register. Every entry documents
 * exactly what the sources establish: the endpoint exists in 20i's
 * documentation and (for packages.list only) an authenticated read
 * succeeded under issue #65. No entry claims production-verified
 * behavior; the register deliberately excludes every documented
 * mutating, credential-returning or access-changing endpoint.
 */
final class InventoryRegister
{
    /** @return InventoryCapability[] Closed read set, keyed by operation. */
    public static function all(): array
    {
        return [
            InventoryRead::PACKAGES_LIST => new InventoryCapability(
                InventoryRead::PACKAGES_LIST, 'GET', '/package',
                InventoryCapability::RISK_READ, InventoryCapability::EVIDENCE_AUTHENTICATED_READ, true,
                'List of hosting packages; shape shown by docs.20i.com/api/retrieve-packages-api and probed once under issue #65.'
            ),
            InventoryRead::PACKAGE_DETAIL => new InventoryCapability(
                InventoryRead::PACKAGE_DETAIL, 'GET', '/package/{id}',
                InventoryCapability::RISK_SENSITIVE_READ, InventoryCapability::EVIDENCE_DOCUMENTED, false,
                'Owner reference describes a full representation including DNS/email sections; no public schema, so nothing is projected.'
            ),
            InventoryRead::PACKAGE_TYPES => new InventoryCapability(
                InventoryRead::PACKAGE_TYPES, 'GET', '/reseller/{resellerId}/packageTypes',
                InventoryCapability::RISK_READ, InventoryCapability::EVIDENCE_DOCUMENTED, false,
                'Available package types/configurations; the owner reference defers the full response schema elsewhere.'
            ),
            InventoryRead::ACCOUNT_BALANCE => new InventoryCapability(
                InventoryRead::ACCOUNT_BALANCE, 'GET', '/reseller/{resellerId}/accountBalance',
                InventoryCapability::RISK_READ, InventoryCapability::EVIDENCE_DOCUMENTED, false,
                'Account balance read; currency and response fields are not specified by the supplied sources.'
            ),
        ];
    }

    /** The only operation whose response shape has evidence enough to project. */
    public static function projectable(string $operation): bool
    {
        return $operation === InventoryRead::PACKAGES_LIST;
    }

    public static function get(string $operation): InventoryCapability
    {
        return self::all()[$operation] ?? throw new InvalidArgumentException('Unknown inventory read: '.$operation);
    }
}
