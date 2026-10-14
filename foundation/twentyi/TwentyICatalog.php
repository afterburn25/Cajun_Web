<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyI;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\CapabilityCatalog;
use CajunWebPro\Foundation\Provider\CatalogEntry;

/**
 * The documented 20i reseller API surface as typed provenance
 * metadata. Every entry cites its source:
 *
 * - 'owner-upload L<lines>': the owner-supplied reference
 *   `Pasted text(20261014-030432).txt` as curated in
 *   docs/TWENTYI_OWNER_API_REFERENCE.md (SHA-256
 *   f7c738e746c2aebb9e1d047a555f3874a0766255795ed80edeebe8e599b9ec4d).
 *   Line numbers refer to that upload, via the curated reference.
 * - 'docs.20i.com ...': a public support article observed on
 *   2026-10-14.
 * - 'issue #65': the owner-authorized authenticated GET /package
 *   diagnostic — the only live observation on record.
 *
 * Only literal method/path pairs quoted by the curated reference are
 * registered; prose-only mentions stay out rather than be guessed.
 * Nothing here enables execution: evidence DOCUMENTED/AUTHENTICATED
 * projects to ProviderCapability::UNVERIFIED, and HTTP method alone
 * does not mark an operation read-only — the owner reference documents
 * GET-labelled mutations, so risk is the semantic classification.
 */
final class TwentyICatalog
{
    private function __construct()
    {
    }

    public static function catalog(): CapabilityCatalog
    {
        return new CapabilityCatalog('twenty-i',
            // -- Hosting packages and account (reference section 1) --
            new CatalogEntry('packages.balance', 'GET', '/reseller/{resellerId}/accountBalance',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L30-37', 'Account balance data; currency/response fields unspecified.'),
            new CatalogEntry('packages.list', 'GET', '/package',
                CatalogEntry::EVIDENCE_AUTHENTICATED, CatalogEntry::RISK_READ,
                'issue #65; owner-upload L530-536; docs.20i.com retrieve-packages-api 2026-10-14',
                'Package summary array. One authorized live response observed; general schema/entitlements unverified.'),
            new CatalogEntry('packages.get', 'GET', '/package/{packageId}',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L564-570', 'Full package representation incl. DNS/email/limits; allowlist fields before storage or display.'),
            new CatalogEntry('packages.types', 'GET', '/reseller/{resellerId}/packageTypes',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L648-654', 'Available package types/configurations; full response schema not supplied.'),
            new CatalogEntry('packages.bundleTypeLimits', 'GET', '/package/{packageId}/bundleType/limits',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L641-647', "Type limits; unlimited may be the string 'INF', not a number."),
            new CatalogEntry('packages.usage', 'GET', '/package/{packageId}/web/usage',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L966-972', 'Current-month usage; autoscale values may come from allowances.'),
            new CatalogEntry('packages.bandwidthStats', 'GET', '/package/{packageId}/web/bandwidthStats',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L333-339', 'Hit/byte statistics over named periods.'),
            new CatalogEntry('reseller.serviceChangeData', 'GET', '/reseller/{resellerId}/serviceChangeData',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L50-56', 'Service-change requests, IDs and statuses for reconciliation.'),
            new CatalogEntry('hosting.create', 'POST', '/reseller/{resellerId}/addWeb',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L571-587', 'Creates hosting; may trigger a configured welcome email via stackUser.'),
            new CatalogEntry('hosting.update', 'POST', '/reseller/{resellerId}/updatePackage',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L718-750', 'Mixed update/delete interface incl. billing and delete-id fields.'),
            new CatalogEntry('packages.userStatus', 'POST', '/package/{packageId}/userStatus',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L321-332', 'Service activation/deactivation — a mutation, not a status lookup.'),
            new CatalogEntry('hosting.delete', 'POST', '/reseller/{resellerId}/deleteWeb',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_DESTRUCTIVE,
                'owner-upload L632-640', 'Package deletion; never usable as discovery.'),

            // -- StackCP users (reference section 2) --
            new CatalogEntry('stackusers.list', 'GET', '/reseller/{resellerId}/susers',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L1098-1102', 'Users, contacts, grants, FTP locks — account-wide sensitive data.'),
            new CatalogEntry('stackusers.grants', 'GET', '/reseller/{resellerId}/stackUser/{stackUserId}/grants',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L1059-1065', 'Resource grant tuples such as package:123; evidence to review, not auto-link authority.'),
            new CatalogEntry('packages.stackUserList', 'GET', '/package/{packageId}/stackUserList',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L1027-1033', 'StackCP users with access to the package.'),
            new CatalogEntry('stackusers.save', 'POST', '/reseller/{resellerId}/susers',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L1103-1175', 'Create/update users and grants incl. password/delete fields and new-user email.'),

            // -- Domains and DNS (reference section 3) --
            new CatalogEntry('domains.search', 'GET', '/domain-search/{prefix_or_name}',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L1245-1251', 'NDJSON stream; suggestions precede final results; access may be blocked for up to 24h.'),
            new CatalogEntry('domains.periods', 'GET', '/domain-period',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L1231-1237', 'Supported TLD registration periods.'),
            new CatalogEntry('domains.premiumTypes', 'GET', '/domainPremiumType',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L1238-1244', 'Premium pricing group codes; not a complete retail price table.'),
            new CatalogEntry('domains.list', 'GET', '/domain',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L1259-1265', 'Heading says list but prose describes a summary record; response shape unresolved.'),
            new CatalogEntry('domains.servicePrice', 'GET', '/package/{packageId}/domain/{domainId}/servicePrice',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L442-448', 'Premium renewal price only; GBP without markup; never label as USD.'),
            new CatalogEntry('domains.pendingTransferStatus', 'GET', '/package/{packageId}/domain/{domainId}/pendingTransferStatus',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L473-479', 'Cached registrar status; null has multiple documented meanings.'),
            new CatalogEntry('domains.dns.get', 'GET', '/package/{packageId}/domain/{domainId}/dns',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L1368-1415', 'DNS record retrieval.'),
            new CatalogEntry('domains.dns.set', 'POST', '/package/{packageId}/domain/{domainId}/dns',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L1368-1415', 'DNS mutation with conflictPolicy/insertPolicy; record conflicts matter.'),
            new CatalogEntry('domains.nameservers.get', 'GET', '/package/{packageId}/domain/{domainId}/nameservers',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L1337-1352', 'Current nameservers.'),
            new CatalogEntry('domains.nameservers.set', 'POST', '/package/{packageId}/domain/{domainId}/nameservers',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L1337-1352', 'Replace semantics: add new ns and remove old; stale values matter.'),
            new CatalogEntry('domains.register', 'POST', '/reseller/{resellerId}/addDomain',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L57-123', 'Charges account balance; registry-specific inputs; no IDN.'),
            new CatalogEntry('domains.transfer', 'POST', '/reseller/{resellerId}/transferDomain',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L1185-1230', 'Charges transfer costs; accepted purchase is not a completed transfer.'),
            new CatalogEntry('domains.renew', 'POST', '/reseller/{resellerId}/renewDomain',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L239-249', 'Balance-funded renewal.'),

            // -- Mailbox / platform (reference section 4, literal paths only) --
            new CatalogEntry('email.change', 'POST', '/package/{packageId}/email/{domain}',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L1451-1630', 'Email-object changes; shown mutation payload is empty — field schema missing.'),

            // -- Managed VPS / cloud / VPS (reference section 5) --
            new CatalogEntry('managedVps.list', 'GET', '/managed_vps',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L2603-2623', 'Managed-server summaries.'),
            new CatalogEntry('managedVps.get', 'GET', '/managed_vps/{managedVpsId}',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L2603-2623', 'Detail states SuperPassword/VncPassword are scrubbed; not a guarantee for other endpoints.'),
            new CatalogEntry('vps.list', 'GET', '/vps',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L2818-2824', 'VPS list.'),
            new CatalogEntry('vps.get', 'GET', '/vps/{vpsId}',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L2934-2940', 'VPS detail.'),
            new CatalogEntry('cloudProviders.list', 'GET', '/reseller/{resellerId}/cloudProviders',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L2666-2672', 'Account-available cloud-provider identifiers.'),
            new CatalogEntry('cloudProviders.specs', 'POST', '/reseller/{resellerId}/cloudProviders',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L2673-2680', 'POST used as a lookup for provider specs/zones — semantically a read.'),
            new CatalogEntry('cloudServer.productData', 'GET', '/reseller/{resellerId}/cloudServerProductData',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L2711-2717', 'Cloud product specifications; not every price/term schema.'),
            new CatalogEntry('managedVps.create', 'POST', '/reseller/{resellerId}/addManagedVps',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L140-158', 'Balance-charging managed VPS purchase.'),
            new CatalogEntry('vps.create', 'POST', '/reseller/{resellerId}/addVPS',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L192-211', 'Balance-charging VPS purchase.'),
            new CatalogEntry('cloudServer.create', 'POST', '/reseller/{resellerId}/addCloudServer',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L2681-2700', 'Balance-charging cloud purchase.'),
            new CatalogEntry('vps.renew', 'POST', '/reseller/{resellerId}/renewVPS',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L250-259', 'Balance-charging renewal.'),
            new CatalogEntry('managedVps.renew', 'POST', '/reseller/{resellerId}/renewManagedVPS',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L298-307', 'Balance-charging renewal; exact path case matters.'),
            new CatalogEntry('vps.reboot', 'POST', '/vps/{vpsId}/reboot',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L2855-2862', 'Hard reboot with service interruption.'),
            new CatalogEntry('vps.changePassword', 'POST', '/vps/{vpsId}/changePassword',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L2795-2803', 'Root password change; also reboots the server.'),
            new CatalogEntry('vps.rebuild', 'POST', '/vps/{vpsId}/rebuild',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_DESTRUCTIVE,
                'owner-upload L2863-2875', 'Irreversible OS-disk wipe per source.'),
            new CatalogEntry('vps.stop', 'GET', '/vps/{vpsId}/stop',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L2896-2909', 'GET-labelled power change; verify actual method before execution.'),
            new CatalogEntry('vps.start', 'GET', '/vps/{vpsId}/start',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L2896-2909', 'GET-labelled power change; verify actual method before execution.'),
            new CatalogEntry('vps.vnc', 'GET', '/vps/{vpsId}/vnc',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_CREDENTIAL,
                'owner-upload L2910-2916', 'Console details include a password; not ordinary telemetry.'),
            // -- Timeline backups (reference section 6, literal paths only) --
            new CatalogEntry('backup.web.list', 'GET', '/package/{packageId}/web/timelineBackup',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L2988-3116', 'Web snapshot timestamps.'),
            new CatalogEntry('backup.storages', 'GET', '/package/{packageId}/web/timelineStorages',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L2988-3116', 'Backup storage listing.'),
            new CatalogEntry('backup.web.jobs', 'GET', '/package/{packageId}/web/timelineBackup/web/jobs',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ,
                'owner-upload L2988-3116', 'Queued != completed; poll under a bounded authorized workflow.'),
            new CatalogEntry('backup.web.take', 'POST', '/package/{packageId}/web/timelineBackup/web/takeSnapshot',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L2988-3116', 'On-demand web snapshot.'),
            new CatalogEntry('backup.web.restore', 'POST', '/package/{packageId}/web/timelineBackup/web/restoreSnapshot',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_DESTRUCTIVE,
                'owner-upload L2988-3116', 'Restore overwrites data; action/RestoreAsOf/RestorePath/target fields.'),
            new CatalogEntry('backup.mailbox.restore', 'POST', '/package/{packageId}/web/timelineBackup/mailbox/{mailboxId}/restoreSnapshot',
                CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE,
                'owner-upload L2988-3116', 'Mailbox restore with timestamp/tempMailboxRestore.'),
        );
    }

    /**
     * Documented operations the curated reference describes without
     * quoting a literal method/path pair — registered as gaps rather
     * than guessed. Each must be resolved against primary 20i
     * documentation before any descriptor can exist.
     *
     * @return string[]
     */
    public static function knownGaps(): array
    {
        return [
            'Domain authCode read (owner-upload L899-905): generates/rotates the upstream transfer code; literal path not quoted.',
            'DNSSEC set (owner-upload L1416-1428): example repeats GET without an enabled state; write method/body unresolved.',
            'VNC lock/unlock (owner-upload L2917-2933): reference abbreviates POST paths as /lockVnc and /unlockVnc.',
            'Mailbox list/configuration reads (owner-upload L1451-1630): described in prose; no literal read path quoted.',
            'Maintenance-mode set (owner-upload L544-556): GET example without payload; write contract unresolved.',
        ];
    }
}
