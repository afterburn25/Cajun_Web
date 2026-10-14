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
            InventoryRead::VPS_LIST => new InventoryCapability(
                InventoryRead::VPS_LIST, 'GET', '/vps',
                InventoryCapability::RISK_READ, InventoryCapability::EVIDENCE_DOCUMENTED, false,
                'VPS list (owner-upload L2818-2824). Schema undocumented; identity-only projection retains ids.'
            ),
            InventoryRead::MANAGED_VPS_LIST => new InventoryCapability(
                InventoryRead::MANAGED_VPS_LIST, 'GET', '/managed_vps',
                InventoryCapability::RISK_READ, InventoryCapability::EVIDENCE_DOCUMENTED, false,
                'Managed-server summaries (owner-upload L2603-2623). Schema undocumented; identity-only projection retains ids.'
            ),
            InventoryRead::DOMAINS_LIST => new InventoryCapability(
                InventoryRead::DOMAINS_LIST, 'GET', '/domain',
                InventoryCapability::RISK_READ, InventoryCapability::EVIDENCE_DOCUMENTED, false,
                'Owner-upload L1259-1265: heading says list but prose describes a summary record — shape unresolved; identity-only projection may legitimately return zero ids.'
            ),
            InventoryRead::SERVICE_CHANGES => new InventoryCapability(
                InventoryRead::SERVICE_CHANGES, 'GET', '/reseller/{resellerId}/serviceChangeData',
                InventoryCapability::RISK_SENSITIVE_READ, InventoryCapability::EVIDENCE_DOCUMENTED, false,
                'Service-change requests, IDs and statuses for reconciliation (owner-upload L50-56). Schema undocumented; identity-only projection retains ids.'
            ),
        ];
    }

    /** The only operation whose response shape has evidence enough to project fully. */
    public static function projectable(string $operation): bool
    {
        return $operation === InventoryRead::PACKAGES_LIST;
    }

    /** Operations whose list responses support the identity-only projection. */
    public static function identityProjectable(string $operation): bool
    {
        return in_array($operation, [
            InventoryRead::PACKAGES_LIST, // falls back if the documented fields change
            InventoryRead::VPS_LIST, InventoryRead::MANAGED_VPS_LIST,
            InventoryRead::DOMAINS_LIST, InventoryRead::SERVICE_CHANGES,
        ], true);
    }

    public static function get(string $operation): InventoryCapability
    {
        return self::all()[$operation] ?? throw new InvalidArgumentException('Unknown inventory read: '.$operation);
    }
}
