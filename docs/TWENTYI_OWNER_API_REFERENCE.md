# Owner-supplied 20i API reference: implementation handoff

Current ownership and priority: [Codex complete20i takeover](TWENTYI_CODEX_TAKEOVER.md). Earlier allocations/progress summaries below are historical; the complete requirements remain in force.

## Status and source provenance

This is a source-derived investigation and implementation handoff, not a shipped integration, provider test, release, deployment, or worker dispatch. The owner supplied the API documentation to accelerate the existing CajunWebPro work. Do not restart the project or replace its custom PHP UI with StackCP, WHMCS, or Easy Builder.

Source: `Pasted text(20261014-030432).txt`, headed **20i.com Reseller API**. Original upload: 195,457 bytes, 3,116 lines, SHA-256 `f7c738e746c2aebb9e1d047a555f3874a0766255795ed80edeebe8e599b9ec4d`. The filename is a source label, not proof of the documentation's publication/version date. No authoritative source URL, API version, complete schema, or response fixture is established by the pasted text alone.

An offline inventory found 327 PHP `getWithFields`/`postWithFields` example calls, representing 324 distinct literal method/path pairs. These are **example pairs**, not 324 independently validated endpoints. Wildcard and placeholder paths have not been normalized together. All General-key assignments inspected contain `API_KEY`; no owner credential is copied here. Line numbers below refer to the exact original upload. This document is a selected contract map and caveat register, not a complete verbatim copy of the upload.

Prior evidence: [issue #65](https://github.com/afterburn25/Cajun_Web/issues/65) preserves the old PHP module review and successful owner-authorized `GET /package` diagnostic. That success verifies only that operation. This documentation supplies substantially broader endpoint discovery, notably VPS/cloud operations, but does not establish account entitlements or authorization for further calls.

## Current work and ownership

Starting main for this documentation branch: `22a2969692ab5bcc7f35158dcaa5355c623dc821`. Read current [AGENTS](../AGENTS.md), [session handoff](../SESSION_HANDOFF.md), [coordination](AGENT_COORDINATION.md), [dependency gates](DEPENDENCY_GATES.md), [hosting/billing roadmap](HOSTING_AND_BILLING_ROADMAP.md), and [client portal roadmap](CLIENT_PORTAL_ROADMAP.md) before implementation.

At inspection, the shared handoff identifies active mail-notices candidate 1.19.0 / PR #72 and latest verified release 1.18.0. Preserve that lane. Provider [PR #43](https://github.com/afterburn25/Cajun_Web/pull/43), `devin/provider-foundation`, is open and not merged into main; inspected head `6c378f5249d05c9e7734214c124fd1e3b1d09e6a`. Its description reports 163 synthetic checks, not live provider acceptance. [PR #71](https://github.com/afterburn25/Cajun_Web/pull/71) merged request-key validation into Devin's branch, not main. Refresh these states before acting; this handoff does not independently rerun or certify those tests.

Codex now owns the complete integration under the takeover; the former split is historical. Reuse reviewed work rather than building a competing gateway or operation ledger. No live/API/secret-bearing workflow is added or triggered by this documentation change.

## Source conventions

The introduction (L1-L29) describes allocated resource IDs and the reseller `*` alias for self. It prefers allocated IDs over alternative domain-name references. It permits form or JSON bodies at appropriate POST endpoints. Examples instantiate `TwentyI\\API\\Services` with a General API key. Preserve exact endpoint case: `addManagedVps`, `addVPS`, `renewManagedVPS`, and `renewVPS` are not interchangeable spellings.

Do not infer transport safety from HTTP method alone. Several examples use GET for described state changes, while some POST endpoints describe lookups. Separate **documentation evidence**, **semantic operation/risk**, **implemented transport**, **account verification**, **release**, and **deployment** in the capability register.

## 1. Account and settings / hosting packages — HC-07 and HC-08

| Supplied sample | Source lines | Source-described purpose and important detail |
|---|---|---|
| GET `/reseller/{resellerId}/accountBalance` | 30-37 | Account balance data; exact currency/response fields are not specified here. |
| GET `/package` | 530-536 | Summary information for hosting packages. Prior diagnostic covers this read only. |
| GET `/package/{packageId}` | 564-570 | Full package representation including DNS/email/limits/web information; filter returned fields before storage or client exposure. |
| GET `/reseller/{resellerId}/packageTypes` | 648-654 | Available types/configurations. Example introduction also uses `/reseller/*/packageTypes`. Full response schema is referred elsewhere, not supplied. |
| GET `/package/{packageId}/bundleType/limits` | 641-647 | Type limits; unlimited values may be the string `INF`, not a number. |
| POST `/reseller/{resellerId}/addWeb` | 571-587 | Create hosting; fields shown: type, domain_name, extra_domain_names, label, documentRoots, stackUser, packageTemplate. Returns assigned ID. Linking stackUser can trigger a configured welcome email. |
| POST `/reseller/{resellerId}/updatePackage` | 718-750 | Mixed update/delete interface with billing, delete-id, id, labels, limit, limit-set and packageBundleTypes. Do not expose its unrestricted payload to clients. |
| POST `/package/{packageId}/userStatus` | 321-332 | Service activation/deactivation, not merely a status lookup. |
| POST `/reseller/{resellerId}/deleteWeb` | 632-640 | Package deletion. Destructive operation, never discovery. |
| GET `/package/{packageId}/web/usage` | 966-972 | Current-month usage; autoscale values may come from allowances. Do not invent uptime from usage. |
| GET `/package/{packageId}/web/bandwidthStats` | 333-339 | Hit/byte statistics over named periods. |
| GET `/reseller/{resellerId}/serviceChangeData` | 50-56 | Service-change requests, IDs and statuses for reconciliation. |

The source also describes package-type creation/update, subdomains, document roots, cron tasks, pending clone/split/install information, and platform-specific web settings. Package and web-limit reads can have different preconditions. A failed or incomplete read is not an authoritative empty inventory.

## 2. StackCP users and explicit service ownership

| Supplied sample | Source lines | Source-described purpose |
|---|---|---|
| GET `/reseller/{resellerId}/susers` | 1098-1102 | Users, contact information, grants and FTP locks. Sensitive account-wide data; not a client feed. |
| GET `/reseller/{resellerId}/stackUser/{stackUserId}/grants` | 1059-1065 | Resource tuples such as package:123. |
| GET `/package/{packageId}/stackUserList` | 1027-1033 | StackCP users with package access. |
| POST `/reseller/{resellerId}/susers` | 1103-1175 | Create/update users and grants; includes password/delete fields and optional new-user email. |

Use provider IDs and explicit owner-reviewed local client/service links. A returned grant is evidence to review, not permission to automatically attach another person's service by matching email or domain. Separate local client identity, staff support identity and upstream account permissions. Grant changes and user creation are writes with potential access/email effects.

## 3. Domain names and DNS — HC-09

| Supplied sample | Source lines | Source-described contract |
|---|---|---|
| GET `/domain-search/{prefix_or_name}` | 1245-1251 | Newline-delimited JSON stream, not one ordinary JSON document. Suggestions may precede final checked results; order varies. Limits may block access for up to 24 hours. TLD selection is not supported by this described search; filter unsold TLDs locally. |
| GET `/domain-period` | 1231-1237 | Supported TLD registration periods. |
| GET `/domainPremiumType` | 1238-1244 | Premium pricing group codes, not a complete retail price table. |
| GET `/domain` | 1259-1265 | Heading says list domains, but prose says summary record with purpose-dependent fields. Response shape needs clarification. |
| POST `/reseller/{resellerId}/addDomain` | 57-123 | Registration charges account balance; registrant/contact, privacy and registry-specific inputs. Source rejects non-ASCII/IDN registration. caRegistryAgreement requires actual registrant acceptance, not a default true. |
| POST `/reseller/{resellerId}/transferDomain` | 1185-1230 | Charges transfer-related costs; accepted purchase does NOT establish completed transfer. Fields include name, years, contact, emulateYears, otherContacts, authcode, limits, nameservers, privacyService and stackUser. |
| POST `/reseller/{resellerId}/renewDomain` | 239-249 | Balance-funded renewal with name, years and renewPrivacy. |
| GET `/package/{packageId}/domain/{domainId}/servicePrice` | 442-448 | Premium per-year renewal price only; null for ordinary TLD pricing. Source explicitly says GBP without reseller markup. Do not label this value as USD or as a registration quote. |
| GET/POST `/package/{packageId}/domain/{domainId}/dns` | 1368-1415 | DNS retrieval/mutation; update supports conflictPolicy, insertPolicy, new and delete. Record conflicts and authoritative-zone requirements matter. |
| GET/POST `/package/{packageId}/domain/{domainId}/nameservers` | 1337-1352 | Read current nameservers; update with added ns and removed old-ns. Avoid overlap and stale old values. |
| GET `/package/{packageId}/domain/{domainId}/pendingTransferStatus` | 473-479 | Cached registrar status, not necessarily live; null has multiple documented meanings. |

A proper bounded NDJSON adapter needs finite line/aggregate sizes, a deadline, explicit partial/error states, no result ordering assumptions, and no premature success from a suggestion. These are engineering requirements derived from the supplied streaming contract, not executed tests. Mark synthetic response fixtures as synthetic; obtain authorized redacted fixtures before asserting a real response schema.

## 4. Native hosting controls / mailboxes / security

The supplied sections document configuration operations useful for CajunWebPro's own service panel:

- Email/mailboxes (L1451-L1630): mailbox list/configuration, forwarders, autoresponders, spam lists, DKIM/DMARC, quotas/statistics, and POST `/package/{packageId}/email/{domain}` for email-object changes. The selected upload summary shows an empty mutation payload. The official [mailbox provisioning guide](https://docs.20i.com/api/provision-mailbox-api), checked2026-10-15, supplies the exact mailbox-create request and response; that creation boundary is now implemented with synthetic checks. Other mail-object field/response schemas still require precise evidence. See [mailbox implementation](TWENTYI_MAILBOX_CONTROLS.md). The webmail endpoint at L1622-L1630 generates a provider SSO URL; it is not an API to read or send messages in our native multi-account webmail.
- SSL/TLS (L1631-L1690): freeSSL, installed certificates, forceSSL and externalSSL. External-certificate input includes a private key and is not ordinary telemetry.
- WordPress (L1691-L1874): administrators/users, plugin/theme listing and changes, settings, checksum/database checks, staging and version updates. These are package-platform dependent and do not imply a site-builder pivot.
- CDN (L1875-L1995): features, caches, security headers and measured statistics. Cache purges and replacement of all headers are mutations.
- Security/access (L1996-L2217): country/IP rules, FTP accounts, malware reports/scans, protected directories, SSH allowed IPs and public keys.
- Databases/PHP (L2346-L2498): MySQL/MSSQL lists and changes, available/active PHP versions and allowed configuration. The PHP-config update example omits the actual payload schema.

Credentials and private material can appear even in GET responses: DKIM signature read (L1459-L1465), primary FTP credentials (L2054-L2060), and VNC details (L2910-L2916). Never bulk-probe, log or cache these with ordinary service metrics. Apply separate credential/access permissions and redact by an explicit output allowlist.

## 5. Managed VPS / cloud servers / VPS — HC-10, separate A-10 gate

| Supplied sample | Source lines | Source-described contract |
|---|---|---|
| GET `/managed_vps` and `/managed_vps/{managedVpsId}` | 2603-2623 | Managed-server summary/detail; detail states SuperPassword/VncPassword are scrubbed. This does not guarantee that every VPS endpoint is credential-free. |
| GET `/vps` and `/vps/{vpsId}` | 2818-2824, 2934-2940 | VPS list/detail. |
| GET `/reseller/{resellerId}/cloudProviders` | 2666-2672 | Account-available cloud-provider identifiers. |
| POST `/reseller/{resellerId}/cloudProviders` | 2673-2680 | Lookup provider specs/zones; example input provider. POST here is described as a lookup, not an order. |
| GET `/reseller/{resellerId}/cloudServerProductData` | 2711-2717 | Cloud product specifications; does not supply every price or purchase-term schema. |
| POST `/reseller/{resellerId}/addManagedVps` | 140-158 | Balance-charging managed VPS purchase; configuration, options.os, periodMonths, type, forUser. |
| POST `/reseller/{resellerId}/addVPS` | 192-211 | Balance-charging VPS purchase with configuration, forUser, options.os, periodMonths, type, optional cpanel. |
| POST `/reseller/{resellerId}/addCloudServer` | 2681-2700 | Balance-charging cloud purchase; configuration spec/zone/bandwidth/disk/mvpsName/optimisation/resellerCloudBranded, periodMonths, type, forUser. |
| POST `/reseller/{resellerId}/renewVPS` and `/renewManagedVPS` | 250-259, 298-307 | Balance-charging renewals. No generic no-charge renewal assumption. |
| POST `/vps/{vpsId}/reboot` | 2855-2862 | Hard reboot with service interruption. |
| POST `/vps/{vpsId}/changePassword` | 2795-2803 | Linux root-password change also reboots the server. |
| POST `/vps/{vpsId}/rebuild` | 2863-2875 | Irreversible OS-disk wipe; separate disks unaffected according to source. OS/licensing restrictions apply. |
| GET `/vps/{vpsId}/stop` and `/start` in supplied examples | 2896-2909 | Described as power changes. Treat as mutations regardless of GET sample; verify actual method/contract before execution. |
| GET `/vps/{vpsId}/vnc`, POST `/lockVnc`, POST `/unlockVnc` | 2910-2933 | Console details include password; ACL operations change access. VNC is not SSH. |

VPS ordering/control paths are now documented in this source. The earlier statement that the old module ZIP did not include dedicated VPS implementation remains historically true; it must not become a claim that no VPS API documentation exists. This extract still does not provide a complete interactive SSH session/gateway contract, every lifecycle/cancellation schema, or verified account product access. Keep A-10 independently tracked, not incorrectly completed by VNC or package SSH-key endpoints.

## 6. Timeline backups — provider controls, not complete A-03/P-06

L2988-L3116 document listing storage, web/database/mailbox snapshot timestamps, queued jobs, on-demand takeSnapshot and restoreSnapshot operations. Examples include:

- GET `/package/{packageId}/web/timelineBackup` and `/timelineStorages`.
- GET `/package/{packageId}/web/timelineBackup/web/jobs` and corresponding database/mailbox jobs.
- POST `/package/{packageId}/web/timelineBackup/web/takeSnapshot` and corresponding database/mailbox paths.
- POST `/package/{packageId}/web/timelineBackup/web/restoreSnapshot` with action, RestoreAsOf, RestorePath, target.
- POST `/package/{packageId}/web/timelineBackup/mailbox/{mailboxId}/restoreSnapshot` with timestamp, tempMailboxRestore.

Queued is not completed. Poll the relevant job under a bounded authorized workflow and retain uncertainty. Provider backup access does not itself implement a verified portable client ZIP, full application/private-key backup, or a successful restore drill. Returned recently deleted sites must not bypass current local ownership and retention permissions.

## Example defects, omissions and behavior requiring explicit review

Do not silently correct the source and label guesses verified:

1. **GET examples for changes:** clearPendingInstall (L340-L346), bulk deletion (L625-L631), allowance update (L1075-L1081), SSL approval email (L1631-L1638), WordPress blueprint toggle (L1706-L1711), bulk cache purge (L1875-L1882), CDN header replacement (L1989-L1995), bulk country/IP rules (L2028-L2034, L2124-L2130), VPS stop/start (L2896-L2909). Do not put them in a general GET-only diagnostic allowlist.
2. **Read and write ambiguity:** maintenanceMode Set example repeats a GET without a payload (L544-L556); DNSSEC Set repeats GET without enabled state (L1416-L1428). Actual write method/body unresolved. Also review the `canTransfer` boolean/lock description (L913-L927) before deciding polarity; do not normalize ambiguous terminology into an assumed registry status.
3. **Credential-changing read:** domain authCode (L899-L905) can generate/rotate an upstream transfer code. Exclude from inventory polling and protect the returned secret.
4. **Incomplete/generated snippets:** repeated prose, encoding artifacts, nested `{}` inside PHP-style arrays (for example cron L791-L817), and empty payload examples for email, backup-slot updates and PHP config are not executable contracts. Capture missing fields, types, errors and responses per operation.
5. **Billing side effects:** many orders/renewals explicitly charge the balance. Suffix `Pre` alone does not prove a no-charge quote: renewTlsCertificatePre (L288-L297) and renewMssqlPre (L2377-L2385) are described as charged renewals. Contrast the explicitly no-charge cloud timeline quote (L2718-L2726). Prevent automatic retries of potentially accepted purchases; reconcile uncertain outcomes first.
6. **Replacement semantics:** nameservers, CDN header sets, IP lists and user grants have operation-specific remove/replace behavior. Missing entries must never be assumed to mean no change.
7. **Ambiguous empty results:** documentRoots (L371-L377), CDN headers (L1939-L1945) and some other reads conflate absence with failure. Do not delete locally mapped resources based on an ambiguous empty response.
8. **Missing discovery contracts:** complete ordinary TLD wholesale pricing/currency, VPS product/OS catalogs alluded to by ordering examples, error schemas, pagination and rate-limit details are not fully supplied. Register these exact gaps instead of inventing them or blocking unrelated work.

## Proposed bounded implementation sequence

This is an engineering proposal within the existing roadmap, not new permission to perform remote operations or pause mail work.

1. Reconcile/review PR #43 against the documented semantic effects; retain its request binding, atomic claims, bounded redaction and uncertain-write protection. Add fixture tests that a GET-labelled mutation cannot execute as discovery and that a `Pre` suffix grants no special retry/quote semantics.
2. Implement protected 20i settings, encrypted credential persistence/rotation, explicit named connection checks, and account-scoped inventory/capability storage. Use the documented package/type/balance reads first. Prepare domain/VPS list adapters only to the extent their real schemas are known; keep unknown fields and privileges unverified. Reuse `TWENTYI_API_KEY` only through the existing approved secret mechanism; do not copy/export it into source, chat or release packages.
3. Add owner-reviewed provider-to-client service mapping and native read-only service summaries. Keep anonymous customers and other clients out of reseller-wide data. Display stale/unknown status honestly. Authorization and current configuration checks must precede credential use and display.
4. Build plan mappings, bounded domain-search parsing, job/reconciliation state machines and native DNS/mail/database/PHP/SSL/CDN screens against documented inputs and labeled fixtures. Do not use fake prices, fixture IDs or test successes as real account capability evidence.
5. Verify each additional external read through a specifically authorized allowlist. Purchases, renewals, DNS/access changes, welcome/reset mail, restoration, power changes and deletion require their own account/ownership/cost/acceptance and owner-authorization gates. New documentation does not authorize them.
6. Connect paid-order provisioning only after billing and operation-specific acceptance. Preserve design-only checkout independence from 20i. Implement provider queue/reconciliation without claiming exactly-once remote execution from a local ledger alone.

Keep organized `site/admin/`, `site/includes/`, business modules, categorized `images/`, protected storage/upload scaffolds, the one-page keyless installer and three-package distribution. No new root-level business-code dump and no customer Composer/SSH requirement.

Before calling a feature landed, add current native permissions/persistence/concurrency, bounded transport/parser, browser, upgrade/recovery and package tests; merge reviewed source, publish/verify the exact release, and update roadmap/status/handoff separately from target-host deployment. Do not bump roadmap completion merely because this reference was supplied.

## Session work and next handoff

Completed here: read the supplied reference; counted/hashes-checked the original bytes offline; inspected current agent/coordination/dependency guidance, issue #65 and provider PR #43/#71 metadata; created this selected mapping/caveat document on an isolated documentation branch. No application files, schema, release version, original release assets, live host, customer data or upstream account state were changed. No provider request or secret access occurred. Source conclusions are attributed to uploaded line ranges; no independent live API documentation/schema verification was performed in this intake.

Next: incorporate this evidence into the existing provider lane and its capability/verification register, without resetting other agents or conflating documentation with implementation. The repository issue/PR comments will point to this document. Publication of this handoff does not prove Codex or Devin has received or acted on it.
