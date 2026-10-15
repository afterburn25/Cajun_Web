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
                InventoryCapability::RISK_READ, InventoryCapability::EVIDENCE_DOCUMENTED, true,
                '20i-authored SDK 1.0.6 registrar example: list of id/name objects; only those fields projected. Coverage, registration status and entitlements remain unknown.'
            ),
            InventoryRead::SERVICE_CHANGES => new InventoryCapability(
                InventoryRead::SERVICE_CHANGES, 'GET', '/reseller/{resellerId}/serviceChangeData',
                InventoryCapability::RISK_SENSITIVE_READ, InventoryCapability::EVIDENCE_DOCUMENTED, false,
                'Service-change requests, IDs and statuses for reconciliation (owner-upload L50-56). Schema undocumented; identity-only projection retains ids.'
            ),
            InventoryRead::CLOUD_SERVERS_LIST => new InventoryCapability(
                InventoryRead::CLOUD_SERVERS_LIST, 'GET', '/cloud_server',
                InventoryCapability::RISK_READ, InventoryCapability::EVIDENCE_DOCUMENTED, true,
                'Public Cloud Hosting API guide shows a list with allocated ID, name, location, configuration and package IDs. Bounded metadata projection; ambiguous flags/count are omitted. No authenticated cloud read is claimed.'
            ),
        ];
    }

    /**
     * Documented endpoints deliberately refused from the inventory surface.
     * Naming them in code keeps the safety boundary enumerable: a future
     * extension cannot mistake "documented GET" for "safe inventory read".
     *
     * @return list<array{operation:string,method:string,path:string,reason:string}>
     */
    public static function exclusions(): array
    {
        return [
            ['operation' => 'vps.vnc', 'method' => 'GET', 'path' => '/vps/{vpsId}/vnc',
                'reason' => 'Returns VNC connection credentials — credential-bearing read, never inventory.'],
            ['operation' => 'vps.stop', 'method' => 'GET', 'path' => '/vps/{vpsId}/stop',
                'reason' => 'GET-labelled power mutation — semantically a write, refused as a read.'],
            ['operation' => 'vps.start', 'method' => 'GET', 'path' => '/vps/{vpsId}/start',
                'reason' => 'GET-labelled power mutation — semantically a write, refused as a read.'],
            ['operation' => 'stackusers.grants', 'method' => 'GET', 'path' => '/reseller/{resellerId}/stackUser/{stackUserId}/grants',
                'reason' => 'Access-grant detail is access-control information, outside inventory scope.'],
            ['operation' => 'packages.stackUserList', 'method' => 'GET', 'path' => '/package/{packageId}/stackUserList',
                'reason' => 'Enumerates stack-user access identities on a package — sensitive identity read.'],
            ['operation' => 'domains.pendingTransferStatus', 'method' => 'GET', 'path' => '/package/{packageId}/domain/{domainId}/pendingTransferStatus',
                'reason' => 'Transfer status may carry authorization detail; schema unverified, excluded.'],
        ];
    }

    /** Operations with implemented allowlist projections of documented metadata. */
    public static function projectable(string $operation): bool
    {
        return in_array($operation, [InventoryRead::PACKAGES_LIST, InventoryRead::CLOUD_SERVERS_LIST, InventoryRead::DOMAINS_LIST], true);
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
