# Complete 20i requirements and evidence


Current evidence checkpoint: [438a3122 packaged acceptance checkpoint](https://github.com/afterburn25/Cajun_Web/actions/runs/38692573913) verifies actual populated upgrades, ownership/reapproval/isolation and operation screens (1,371 HTTP/Chromium assertions; desktop/mobile screenshots inspected). It excludes the extended recovery/Apache gates. The strict operation matrix at 2d2892c9 passes 784 named executions on both supported PHP/database versions; shipping bytes equal 438a3122. Independent review of 438a3122 found no major issues. A screenshot-discovered client-message correction now awaits full corrected-candidate acceptance. Historical pending statements below describe their original checkpoints; [current status](../PROJECT_STATUS.md) is authoritative. No 1.24 release or full-platform completion is claimed.

Status values: not started, implementing, branch-tested, integrated, released, live-verified, awaiting a named external input. A row describes its named scope only. Development, release and live acceptance are independent. This is the complete priority, not a declaration that an initial component finishes it.

| Capability | Required outcome | Status | Evidence / remaining work |
|---|---|---|---|
| Connection | Protected configuration, credential rotation, named diagnostics | released | v1.23 protected settings; additional account identity/entitlement work pending |
| Inventory | Package list, durable refresh, current permissions and honest stale/partial failures | implementing | Inherited #100/#122; takeover native upgrade/render evidence; integrated acceptance pending |
| Ownership | Explicit resource/client links, original account context, reapproval/unlink and isolation | implementing | Package/domain links inherited; moved-domain and data-isolation fixes in this checkpoint |
| Discovery | Account capabilities, package types, balance, limits/status/usage and refresh history | not started | Reuse existing descriptors; executable readers beyond packages.list still required |
| Resources | Registrar/domain, VPS and managed-cloud inventories and explicit ownership | implementing | Cloud list reader, durable scoped history and owner screen implemented on the separate development branch; pure cases pass, native/browser pending; cloud ownership and other inventories remain unfinished |
| Pricing | Owner retail prices separate from provider cost, units, currencies and eligibility | implementing | Existing catalog is reusable; provider mappings and verified quotes pending |
| Order journey | Selected plan → authorized order → verified payment or permitted manual fulfillment | not started | Use existing clients/catalog/invoices/ledger; no production synthetic payments |
| Provisioning | Persist commercial intent and request, provider IDs, queued/pending/active/failure/unknown | implementing | Existing hosting/gateway/ledger foundations; order-derived ownership and native workflow pending |
| Lifecycle | Plan changes, suspension/resumption, cancellation, retention and deletion safeguards | implementing | Reuse reviewed #87–#89; native controls and policy wiring pending |
| Execution | Single gateway/ledger, exact context, safe dispatch, semantic outcomes and no blind retries | implementing | Inherited #107/#109/#111/#115; pending IDs, request arrays and no-blind-retry corrections; label-only consent gate and missing-table recovery fixes under renewed acceptance |
| Reconciliation | Append operation-specific evidence, retain ambiguity and original attempts | implementing | Inherited #115 and hosting #89; name-only creation and unknown-coverage absence stay inconclusive; exact acknowledged-ID observation covered; full service recovery still pending |
| Jobs/recovery | Bounded cron, leases, backoff, visible errors, no replay after recovery | not started | Reuse jobs/locks and ledger; commercial automation and recovery acceptance pending |
| Hosting DNS | Typed DNS, nameservers, subdomains and document roots | not started | Verify documented record shapes and actual add/remove nameserver deltas |
| Hosting mail | Mailboxes, forwarders, autoresponders and quotas | not started | 20i service management only; unrelated admin inbox work paused |
| Hosting databases | Database and user creation/control | not started | Documented requests need exact identifiers, secret handling and fixtures |
| Runtime/security | PHP versions/config, SSL/HTTPS, FTP, SSH keys and access/security controls | not started | Native forms, permission checks and account capability states pending |
| Performance/CMS | CDN/cache and supported WordPress operations | not started | Native supported controls and operation-specific outcomes pending |
| Backups/logs | Usage/logs and provider snapshot/restore jobs | not started | Provider snapshots are distinct from client ZIP delivery; queued is not restored |
| Domain search | Bounded/throttled NDJSON suggestions versus checked availability | not started | Implement exact stream semantics, supported TLDs and periods |
| Domain purchase | Verified pricing, registration, transfer, contacts and registry-specific consent | not started | Full examples include contact structures; resolve individual registry gaps |
| Domain management | Renewal/auto-renew, expiry, privacy, locks, auth-code handling and reconciliation | implementing | Renew request inherited; registrar authority and full business journey pending |
| VPS/cloud catalog | Supported product/spec/zone/OS, price and entitlement choices | not started | Use full source examples; do not invent account offerings |
| VPS/cloud lifecycle | Order/provision/status/usage/renew/plan change/cancel | not started | Exact provider effects, IDs and commercial intent required |
| VPS controls | Power, rebuild, console, snapshot and recovery | not started | VNC is not SSH; destructive/downtime consent required |
| Support handoff | Scoped, expiring, audited provider support access | not started | Reuse local support identity; provider handoff and revoke/expiry pending |
| Interactive SSH | Verified destination/host identity, restricted credentials, connect/revoke/disconnect | not started | Implement provider facility or configured secure gateway; external gateway acceptance separately required |
| Automation consent | Persist policies and eligibility for routine renewals without per-run owner password | not started | Implement minimum billing/consent interface; no unapproved production charge |
| Packaged product | Native branded admin/client flows, desktop/mobile, root/subfolder, rewrite modes | implementing | New HTTP upgrade/isolation cases added; current package/browser/Apache run pending |
| Release | Independent review, normal merge, exact-main packages and downloaded assets | not started | First target inherited inventory/ownership/operations; v1.23 unchanged |
| Live acceptance | Per-operation account entitlement, real reads/writes, merchant, gateway and target host | awaiting a named external input | Previously authorized GET/package only; specific operation acceptance/configuration required when implementation is ready |

## Defects tracked separately

| Finding | Repair | Current evidence / remaining gate |
|---|---|---|
| v1/v2 domain readiness | Require v3 before queries; explicit preparation reachable | 38-check local native run; actual HTTP added, pending |
| Lost migration regressions | Genuine populated v1/v2, audit interruption, repeated preparation, exact history/isolation | Local native passes; PHP/database matrix pending |
| Unexpected column Extra | Exact auto_increment exception only on intended columns | Original MariaDB error1906 reproduced; regression now passes |
| Moved domain misrepresented | Compare approved pair/context; show both packages and require unlink/new approval | Native PHP render and client isolation pass; full browser pending |
| Domain-only client data | Exclude sibling hosting primary name and unrelated names | Native regression passes; HTTP regression added |
| Revocation/replay guessed counters | Capture immediately before each action, assert unchanged plus original semantics | Original failure evidence retained; repaired full suites pending |
| Request and outcome contract gaps | Review full reference, scalar/mixed updates, 2xx/false/null/async outcomes | Open implementation/review task; no whole-lane clearance |

See [takeover provenance](TWENTYI_CODEX_TAKEOVER.md), [full request](TWENTYI_CODEX_TAKEOVER_REQUEST.md) and [dependency gates](DEPENDENCY_GATES.md). Missing engineering is not classified as an external-owner gate.

### Combined inheritance and response semantics checkpoint

Inherited resources40ad600 and hosting1c92e6b are now retained in normal merges5b115f0/08b90c8; combined acceptance is pending. The earlier inventory fixes have544 verified named native executions at962cc3e. Current acknowledgement/replay semantics, corrected ID-array request boundaries, and name-match reconciliation repair pass278 local pure operation executions; current native matrices are pending. Package acknowledgements remain accepted_pending, never automatically active. Complex per-package request fields are only bounded at their outer documented shape; typed native forms and their inner semantics remain required. Original API-reference intake still needs an accessible complete source. This is implementation progress, not platform completion or release evidence.
