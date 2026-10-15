# Complete 20i requirements and evidence

Current1.25/#127 candidate includes cloud/server assignments and the real1.24 baseline with populated ownership/unknown-operation recovery fixtures. Candidate acceptance is pending; immutable publication remains1.24. Capability rows distinguish released package workflows from branch-tested cloud and unfinished controls.


Codex owns the complete 20i P0 integration. [CajunWebPro 1.24.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.24.0) is published and freshly downloaded/verified at immutable source `7190b582a02be8a1daa501db14b5899cfc1eb5d0`. All16 assets, checksums, ZIP members and the tag agree with accepted source; previous1.23 assets are unchanged. This is a GitHub release, not live-site deployment. Full20i is unfinished; unrelated roadmap work remains paused. See [verified release report](TEST_REPORT_1.24.0.md). Older checkpoints below are historical; current capability rows are authoritative.

Status values: not started, implementing, branch-tested, integrated, released, live-verified, awaiting a named external input. A row describes its named scope only. Development, release and live acceptance are independent. This is the complete priority, not a declaration that an initial component finishes it.

| Capability | Required outcome | Status | Evidence / remaining work |
|---|---|---|---|
| Connection | Protected configuration, credential rotation, named diagnostics | released | v1.23 protected settings; additional account identity/entitlement work pending |
| Inventory | Package list, durable refresh, current permissions and honest stale/partial failures | released | 1.24 package list, durable refresh and recovery; [acceptance38711850732](https://github.com/afterburn25/Cajun_Web/actions/runs/38711850732). Additional resource readers remain separate. |
| Ownership | Explicit resource/client links, original account context, reapproval/unlink and isolation | released | 1.24 explicit package/domain links, original/current account binding, moved-domain reapproval, unlink and isolation; cloud ownership #127 remains branch-tested. |
| Discovery | Account capabilities, package types, balance, limits/status/usage and refresh history | not started | Reuse existing descriptors; executable readers beyond packages.list still required |
| Resources | Registrar/domain, VPS and managed-cloud inventories and explicit ownership | branch-tested | Cloud/VPS/managed inventory and explicit ownership #127/#128: native848 and c377 checkpoint7,585. Current release candidate and registrar inventory still pending. |
| Pricing | Owner retail prices separate from provider cost, units, currencies and eligibility | implementing | Service plans/consent #130 with reviewed #132 correction; recurring screens #133 under review. Provider mappings, quotes and complete order eligibility pending. |
| Order journey | Selected plan → authorized order → verified payment or permitted manual fulfillment | not started | Use existing clients/catalog/invoices/ledger; no production synthetic payments |
| Provisioning | Persist commercial intent and request, provider IDs, queued/pending/active/failure/unknown | implementing | Existing hosting/gateway/ledger foundations; order-derived ownership and native workflow pending |
| Lifecycle | Plan changes, suspension/resumption, cancellation, retention and deletion safeguards | implementing | Reuse reviewed #87–#89; native controls and policy wiring pending |
| Execution | Single gateway/ledger, exact context, safe dispatch, semantic outcomes and no blind retries | released | 1.24 typed package-label consent and single ledger with semantic pending/unknown results; commercial, destructive and other typed native operations remain unfinished. |
| Reconciliation | Append operation-specific evidence, retain ambiguity and original attempts | released | 1.24 append-only operation evidence and acknowledged-ID observation; uncertain name/absence evidence cannot authorize replay. Full service workflows remain pending. |
| Jobs/recovery | Bounded cron, leases, backoff, visible errors, no replay after recovery | not started | Reuse jobs/locks and ledger; commercial automation and recovery acceptance pending |
| Hosting DNS | Typed DNS, nameservers, subdomains and document roots | not started | Verify documented record shapes and actual add/remove nameserver deltas |
| Hosting mail | Mailboxes, forwarders, autoresponders and quotas | not started | 20i service management only; unrelated admin inbox work paused |
| Hosting databases | Database and user creation/control | not started | Documented requests need exact identifiers, secret handling and fixtures |
| Runtime/security | PHP versions/config, SSL/HTTPS, FTP, SSH keys and access/security controls | not started | Native forms, permission checks and account capability states pending |
| Performance/CMS | CDN/cache and supported WordPress operations | not started | Native supported controls and operation-specific outcomes pending |
| Backups/logs | Usage/logs and provider snapshot/restore jobs | not started | Provider snapshots are distinct from client ZIP delivery; queued is not restored |
| Domain search | Bounded/throttled NDJSON suggestions versus checked availability | implementing | Exact-name bounded parser and fixed transport: 77 local synthetic cases under default/no-ini. Native screens, durable throttling, sold-TLD filtering, periods/pricing and checkout remain pending; see [implementation note](TWENTYI_DOMAIN_SEARCH.md). |
| Domain purchase | Verified pricing, registration, transfer, contacts and registry-specific consent | not started | Full examples include contact structures; resolve individual registry gaps |
| Domain management | Renewal/auto-renew, expiry, privacy, locks, auth-code handling and reconciliation | implementing | Renew request inherited; registrar authority and full business journey pending |
| VPS/cloud catalog | Supported product/spec/zone/OS, price and entitlement choices | not started | Use full source examples; do not invent account offerings |
| VPS/cloud lifecycle | Order/provision/status/usage/renew/plan change/cancel | not started | Exact provider effects, IDs and commercial intent required |
| VPS controls | Power, rebuild, console, snapshot and recovery | not started | VNC is not SSH; destructive/downtime consent required |
| Support handoff | Scoped, expiring, audited provider support access | not started | Reuse local support identity; provider handoff and revoke/expiry pending |
| Interactive SSH | Verified destination/host identity, restricted credentials, connect/revoke/disconnect | not started | Implement provider facility or configured secure gateway; external gateway acceptance separately required |
| Automation consent | Persist policies and eligibility for routine renewals without per-run owner password | not started | Implement minimum billing/consent interface; no unapproved production charge |
| Packaged product | Native branded admin/client flows, desktop/mobile, root/subfolder, rewrite modes | released | 1.24 scoped inventory/ownership/label operations: actual desktop/mobile, root/subfolder, recovery and Apache modes. Other first-party workflows remain unfinished. |
| Release | Independent review, normal merge, exact-main packages and downloaded assets | released | Immutable1.24 source7190b582:9,134 full assertions,30 gates, reviewed candidates, normal merge, identical builds and16 draft/public assets verified. Continue next increments. |
| Live acceptance | Per-operation account entitlement, real reads/writes, merchant, gateway and target host | awaiting a named external input | Previously authorized GET/package only; specific operation acceptance/configuration required when implementation is ready |

## Defects tracked separately

The first six inherited defects below are repaired and released in1.24, with final main9134 and package/download proof above. Their original checkpoint notes are retained. Current open recurring defect: PR133 review4281512902 (lost mapping plus changed invoice reference), under correction before integration. Whole-platform response contracts and workflows remain unfinished.

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
