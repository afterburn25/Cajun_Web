# Approved roadmap and acceptance criteria

## Latest verified publication — 1.12.0

[v1.12.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.12.0) is published and freshly downloaded/verified at source/tag `c526e960db13246c217f2b9d6f421c17b8d48261` through [PR #58](https://github.com/afterburn25/Cajun_Web/pull/58), preserving corrected Devin #56 through `f3522f6`. Bounded A-07 now provides an administrator template library: personal/shared drafts, explicit permission, create/edit/duplicate/activate/archive, escaped sample previews, immutable history and stale-write protection. Additive owner setup preserves existing records; fresh setup prepares an empty library. [User/technical guide](ADMIN_EMAIL_TEMPLATES.md).

[Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38441087438): 677 native checks (including 59 template checks), 3,084 estimator comparisons, 834 HTTP/Chromium, 87 filesystem/recovery, 327 Apache and 17 builder checks. All 16 public assets, archive members/directories/checksums and immutable tag verified; Windows/Linux bytes agree. Homepage desktop/mobile pixels match 1.11.2; actual template screens inspected. [Test report](TEST_REPORT_1.12.0.md); [handoff](DEVIN_HANDOFF_1.12.0.md).

The organized directory and three-package contract remain permanent. Updates recognize 1.0.8 and 1.1.0 through 1.11.2; staging is cwp-update-1.12.0, recovery is target-specific and the original structure-1.11.0/mutex.php lock is shared. File recovery preserves added template database records. Prior releases remain immutable. No live deployment or external payment/provider/mail/SSH activation. Templates do not send mail or read client data.

Next executable work: apply/review the identified Devin #59 corrections (grant start times, UIDVALIDITY identities, duplicate messages and trust/evidence documentation), then implement the bounded A-06 account/configuration/grant adapter. Codex has claimed application/schema/UI/build ownership; Devin retains canonical corrections. Real transport, mailbox acceptance, compose/send and A-08 email bells remain incomplete. #31 portability corrections pass 86 no-extension checks; #36 URL-input contract needs accurate final caveats before L-02 integration. Corrected #32 scheduling passes 71 local component checks but its adapter/concurrency acceptance is still pending. Keep other foundations isolated until reviewed; the full roadmap is not complete.

## Previous verified publication — 1.11.2 (superseded checkpoint)

[v1.11.2](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.11.2) is published and freshly downloaded/verified at source/tag `4fd0d5efdc9d94f1f5b29d76be857d944f5f8c36` through [PR #55](https://github.com/afterburn25/Cajun_Web/pull/55), preserving Devin PR #35 ancestry through `efc967a`. The live public estimator now uses canonical exact arithmetic while preserving existing form, validation, prices, results and saved-lead behavior. [Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38433670555): 3,084 estimator comparisons/boundaries, 618 native, 802 HTTP/Chromium, 73 filesystem/recovery, 279 Apache and 17 builder checks. All 16 published assets, archive members/directories/checksums and immutable tag verified; Windows/Linux package bytes agree. [Test report](TEST_REPORT_1.11.2.md); [handoff](DEVIN_HANDOFF_1.11.2.md).

The organized directory and three-package contract remain permanent. Updates recognize 1.0.8 and 1.1.0 through 1.11.1; current staging is cwp-update-1.11.2, recovery is per target version and the original structure-1.11.0/mutex.php lock is shared. Private data/scaffolds and prior journals remain preserved. Desktop/mobile homepage screenshots are pixel-identical to verified 1.11.1. Previous releases remain immutable. No live deployment, schema change or external payment/provider/mail/SSH activation.

Next executable work: A-07 staff email-template authoring, storage and preview after correcting/re-reviewing Devin #56; Codex owns its application integration. #31 review-request portability and #36 contract corrections remain assigned; corrected #32 scheduling passes 71 local component checks but needs a consistent adapter and database concurrency acceptance. Other foundation branches remain preserved and unaccepted until reviewed. The full roadmap, including multi-account webmail, real merchant checkout and 20i/VPS/SSH, remains incomplete.

## Previous verified publication — 1.11.1 (superseded checkpoint)

[v1.11.1](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.11.1) is published and freshly downloaded/verified at source/tag `cefcc41bcd5297715f2a49d6526900ebe8ad4eb4` through [PR #50](https://github.com/afterburn25/Cajun_Web/pull/50), preserving Devin PR #34 ancestry. Delivers the bounded W-02 motion/preferences fix, canonical installer build/brand-path corrections and tested organized 1.11.0 owner-update/recovery path. [Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38429347222): 618 native, 800 HTTP/Chromium, 59 filesystem/recovery, 241 Apache and 17 package-builder checks; all 16 published assets, ZIP members/directories/checksums and immutable tag verified. Windows/Linux package bytes agree. [Test report](TEST_REPORT_1.11.1.md); [handoff](DEVIN_HANDOFF_1.11.1.md).

The organized directory and three-package contract remain permanent. Updates recognize 1.0.8 and 1.1.0 through 1.11.0; current staging is cwp-update-1.11.1, recovery is per target version and the original structure-1.11.0/mutex.php lock is shared. Existing private data/scaffolds and older recovery records are preserved; fresh organized sites gain no legacy entries. Previous releases remain immutable. No live deployment or external payment/provider/mail/SSH activation.

Next executable work: integrate the reviewed L-01 estimator adapter contract from PR #35 with exact existing behavior; review corrected L-02/L-03 components in #31/#32 before their application adapters. Keep other Devin foundations isolated and review sequentially. The roadmap continues; this bounded release does not complete W-02 or the full client/billing/mail/provider platform.

## Dependency-aware roadmap status — owner instruction (2026-10-13)

**External verification is not a stop-work order.** All workers must follow [dependency and verification gates](DEPENDENCY_GATES.md), separating *implementation progress* from *provider access/production gate* and *released/deployed*. Implement and test independent work; defer only unsupported real provider/merchant/SSH operations, not entire workstreams.

| Item | Gate label (replaces generic "Blocked" in trackers) | Continue now | Cannot activate until |
|---|---|---|---|
| HC-07 20i API | Awaiting API verification | Provider adapter, admin settings, connection/fixture tests, inventory mapping | Actual account authorization, capability checks and staging test |
| HC-09 Domains | Development pending — API verification | Search UI, TLD/pricing models, domain lifecycle and synthetic tests | Domain entitlement, wholesale funding and safe end-to-end provider test |
| HC-10 VPS | Development pending — API verification | VPS catalog/UX, order/lifecycle adapter and synthetic tests | Verified VPS API/prices and provision/cancel acceptance |
| A-10 VPS SSH | Architecture/security validation pending | Secure access model, audited UX and isolated transport tests | Authenticated vetted gateway/provider handoff and real security acceptance |

This table updates **dependency labels only**; none of these four roadmap features is hereby claimed complete, released or live. No real charges, domain changes, hosting/VPS writes or SSH connections without their operation-specific owner-approved gates. Agents must choose another executable scoped task rather than idling when a gate cannot pass.

## Current integration checkpoint — 2026-10-13

W-01: candidate 1.11.0 integrates existing portfolio browsing, real category URLs, no-JavaScript filtering, exact published-only detail lookup and accessible enhanced history/result count. Full case-study galleries/results editing and broader interaction/media acceptance remain partial. L-01: reviewed standalone estimator merged through PR #29; live quantity-price adapter is deferred. [Scope and tests](PORTFOLIO_BROWSING.md); publication pending.

**Current release — 2026-10-13:** [v1.10.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.10.0) is published and downloaded-back verified at `4649a913d40166b9874f9550e57b4186edfc43ab` through [PR #26](https://github.com/afterburn25/Cajun_Web/pull/26). Bounded P-07: separate client/staff activity centers, explicit per-person read state, exact-source links, paginated history and private unread bells, including authenticated staff on public pages. Passive polls preserve idle expiry; activity failures do not affect business writes. 603 native / 630 HTTP/Chromium checks pass; all eight assets/tag verified. [Activity guide](WORKSPACE_ACTIVITY.md). Full P-07 and A-06–A-08 webmail/templates/email bells remain incomplete, along with actual merchant/card checkout, broader 20i and VPS SSH. No live deployment/customer/provider operation. Devin PR #7 preserved; [handoff](DEVIN_HANDOFF_1.10.0.md). Next: claim a bounded independent roadmap increment or perform actual merchant acceptance before production checkout.

**Previous 1.9.0 checkpoint (superseded) — 2026-10-13:** [v1.9.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.9.0) is published and downloaded-back verified at `080f14aea8fe5c35e04e4eba62976609aef7cd67` through [PR #24](https://github.com/afterburn25/Cajun_Web/pull/24). Bounded P-06: prepared sanitized ZIP uploads, owner-reviewed publication, immutable version history/manifests, client-owned authenticated resumable downloads and audited support access. 539 native / 569 HTTP/Chromium checks pass; all eight assets/tag verified. [Delivery guide](CLIENT_DELIVERIES.md). Full P-06 remains partial: automatic builds, database handover, larger jobs, full restore/retention workflows and notifications remain pending. Real card checkout/merchant acceptance and broader 20i/mail/SSH remain pending. No live deployment/customer/provider operation. Devin PR #7 preserved; [handoff](DEVIN_HANDOFF_1.9.0.md). Next: continue independent portal/admin roadmap work or actual merchant acceptance before production payment enablement; agree a bounded contract first.

**Previous 1.8.0 checkpoint (superseded) — 2026-10-13:** [v1.8.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.8.0) is published and downloaded-back verified at `c36a36e80c40485c9e3e7e6cdd4884ced25db961` through [PR #22](https://github.com/afterburn25/Cajun_Web/pull/22). Bounded M2 / HC-06 / HC-11: verified external full/partial USD receipts, balances, retained owner corrections, legacy paid import, client-owned statements and printable receipts. New requests use remaining balances; sandbox observations never settle real invoices. 427 native / 503 HTTP/Chromium checks pass; all eight assets/tag verified. [Ledger guide](INVOICE_LEDGER.md). Card checkout, actual merchant acceptance, refunds/credits/tax/subscriptions and broader 20i/mail/ZIP/SSH remain pending. No live deployment/customer/provider operation. Devin PR #7 preserved; [handoff](DEVIN_HANDOFF_1.8.0.md). Next: actual sandbox acceptance and real gateway/client design checkout on top of this ledger before production enablement.

**Previous 1.7.0 checkpoint (superseded) — 2026-10-13:** [v1.7.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.7.0) is published and downloaded-back verified at `1fe0670142755f86aee235fabe1847e5d118ee52` through [PR #20](https://github.com/afterburn25/Cajun_Web/pull/20). Bounded M2 signed sandbox notification inbox: disabled-by-default HTTPS receipt, exact-body HMAC, durable deduplication and explicit owner reporting verification. Inbox/attempt/audit updates are atomic; no real invoice is paid. 358 native / 451 HTTP/Chromium checks pass; all eight assets/tag verified. Actual merchant delivery/key compatibility is unverified; signatures/reporting are synthetic. Production/client checkout, automated processing, receipts/refunds and recurring billing remain incomplete. [Notification guide](SANDBOX_NOTIFICATIONS.md). No deployment/customer/provider operation. Devin PR #7 preserved; [handoff](DEVIN_HANDOFF_1.7.0.md). Next: actual sandbox acceptance, separate real-money ledger and client/design checkout before production enablement; full 20i/mail/ZIP/SSH remain pending.

**Previous 1.6.0 checkpoint (superseded) — 2026-10-13:** [v1.6.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.6.0) is published and downloaded-back verified at `e5d4c17456faff0522429ab3f58093a06a523bd5` through [PR #18](https://github.com/afterburn25/Cajun_Web/pull/18). Bounded M2: owner-only Accept.js sandbox trials, durable single-attempt requests, safe reporting/reconciliation and shared manual/catalog/request locking. Sandbox states never pay real invoices. Production/client checkout, webhooks, receipts/refunds and recurring billing remain disabled or incomplete. [Sandbox guide](SANDBOX_PAYMENTS.md). Exact-main: 296 native and 421 HTTP/Chromium checks; eight assets/tag verified. Gateway and tokenization tests are synthetic, not real merchant acceptance. No live deployment/customer/provider operation. Devin PR #7 is preserved. Next: actual sandbox acceptance using securely configured credentials, verified signed events and client/design checkout with a real-money ledger before production enablement. Full 20i/mail/ZIP/SSH remain pending. [Devin handoff](DEVIN_HANDOFF_1.6.0.md).

**Previous 1.5.0 checkpoint (superseded) — 2026-10-13:** [v1.5.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.5.0) is published and downloaded-back verified at `3e95bdf81ca0126d99a69c41f2ff6eb0c08da1e1` via [PR #16](https://github.com/afterburn25/Cajun_Web/pull/16). Bounded M2 preparation: owner-only protected separate Authorize.net credentials, sandbox-authentication diagnostics, reviewed invoice requests with expiry/revocation/replacement and strict audit. Card collection, production connections and transaction processing remain disabled. [Payment guide](PAYMENT_PREPARATION.md). Exact-main acceptance: 227 native and 401 HTTP/Chromium checks; all eight assets/tag verified. Provider results use synthetic fixtures, not real merchant authentication. Earlier releases and Devin PR #7 remain unchanged. No live deployment/customer/provider operation. Next: tokenized sandbox payment attempts, verified events and reconciliation before actual design-only checkout; full M2/20i/mail/ZIP/SSH remain pending. [Devin handoff](DEVIN_HANDOFF_1.5.0.md).

**Previous 1.4.0 checkpoint (superseded) — 2026-10-13:** [v1.4.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.4.0) is published and downloaded-back verified at `0cdea7fbd17e887d3f4bc0574a411b157431b7df` through [PR #14](https://github.com/afterburn25/Cajun_Web/pull/14). Bounded M1 catalog/billing groundwork: approved service SKUs and exact USD prices, private costs, public configured cards, price revisions, reviewed immutable snapshots on existing invoices, duplicate prevention and audited manual paid/void transitions. [Catalog guide](SERVICE_CATALOG.md). Exact-main acceptance: 165 native and 345 HTTP/Chromium checks, all eight retained assets/tag verified. Earlier releases and Devin PR #7 remain unchanged. No live deployment or payment/provider operation. Next bounded work: M2 Authorize.net sandbox settings and payment-request foundation, followed by verified design-only checkout. Broader catalog/order/tax/subscription/20i/mail/ZIP/SSH work remains incomplete. [Devin handoff](DEVIN_HANDOFF_1.4.0.md).

**Previous 1.3.0 checkpoint (superseded) — 2026-10-13:** [v1.3.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.3.0) is published and downloaded-back verified at `7856cccf230a1646d02a4a92ad4bfb1c280efa19` through [PR #12](https://github.com/afterburn25/Cajun_Web/pull/12). A-09 is delivered for the current client project portal: explicit client/staff permissions, separate expiring support identity, persistent banner/return, attributed strict audit and sensitive-action denials. [Client support guide](CLIENT_SUPPORT.md). Exact-main acceptance: 50 original + 31 staff + 35 support native and 289 HTTP/Chromium checks; eight retained assets and immutable tag verified. Earlier releases and Devin PR #7 remain unchanged. No live deployment/provider operations. Next bounded platform work: M1 product/price and billing groundwork before M2 Authorize.net checkout; multi-account mail, 20i, website ZIP delivery and A-10 VPS SSH remain roadmap work. [Devin handoff](DEVIN_HANDOFF_1.3.0.md).

**Previous 1.2.0 checkpoint (superseded) — 2026-10-13:** [CajunWebPro v1.2.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.2.0) is published and downloaded-back verified at `f85627e05b171e4ee8508231e2ed6ee7b9374274` through [PR #10](https://github.com/afterburn25/Cajun_Web/pull/10). Core A-05 administrator accounts are integrated; [administrator guide](ADMIN_ACCOUNTS.md). Exact-main acceptance passed 50 original + 31 staff native and 216 HTTP/Chromium checks; all eight release assets, extracted bytes and tag target match the tested build. v1.1.0 remains immutable. No live deployment or provider operations. The owner resumed the roadmap after 1.1.0, superseding that task's older stop instructions. Devin PR #7 remains at `55d6067`; [updated handoff](DEVIN_HANDOFF_1.2.0.md) preserves its ownership. Next bounded platform work: A-09 audited client-support sessions using the existing staff/client identities; payments, 20i and mail remain tracked. Publication records are documentation-only and do not alter release bytes.

**Released checkpoint:** [v1.1.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.1.0) at `3703783e979abab38e8d72e9092c51dc835833b3` is published and downloaded back. The existing website/client-workspace subset passed exact-main acceptance. F-01 source recovery, F-03 branding integration and F-04 first verified release are delivered for this version. F-02 production/shared-host/SMTP acceptance remains unverified. Public request capture, partial W-01 and client workspace/manual invoice/database-upgrade subsets are delivered as mapped in [release acceptance](RELEASE_1.1.0.md); broader capabilities below are not silently completed. That 1.1.0 release-task stop was superseded by the owner's new roadmap-continuation request.

Updated: 2026-10-13. The existing website/client-workspace subset is implemented in `site/` and released through PR #8. The [feature-to-test matrix](RELEASE_1.1.0.md) covers F-01/03/04, public C/L work, partial W-01, client P-01/02/05, manual invoices, A-01/02 and the database-upgrade subset of A-04. Manual invoices are not P-03 card processing; private documents are not P-06 ZIP handover; database migration is not a complete updater. Nothing is marked deployed. The new owner request resumed the roadmap; current progress is recorded above.

The owner explicitly wants to build our own WHMCS from scratch: a custom CajunWebPro hosting/billing/client-management platform. The [platform blueprint](CUSTOM_PLATFORM_BLUEPRINT.md) maps that direction to this roadmap, including web-design payments, additional admins, webmail/templates and private mail notifications. Preserve current 1.1.0 source and branding when implementing these modules; no WHMCS installation is required.

## Foundation and release recovery

Historical independent A-05 checkpoint: [staff invitation persistence](STAFF_INVITATIONS.md) and opaque session-token compatibility are implemented independently; native component tests are provided for expiry, revocation, rollback and concurrent one-use activation. Account lifecycle, permissions UI and application integration remain pending; this does not complete A-05 or publish a version.

2026-10-13 owner clarification: independent modules/contracts/tests may proceed while the existing worker finishes 1.1.0. The [access foundation](ACCESS_FOUNDATION.md) is the first A-05/A-06 component, with isolated local tests; it is not application-integrated or deployed. Current-source inspection remains required for routes, login, schema, installer and package integration. See [agent ownership](AGENT_COORDINATION.md).

### F-01 — Import the exact application baseline

Recover and verify the owner's current 1.1.0 PHP distribution and original assets before importing source. The inspected 1.0.8 distribution is historical reference only. Preserve current filenames and functionality, document repository-to-upload layout, exclude secrets/private data, and make repeatable flat packages with manifests. Verify version constants and the installer's embedded hashes after every shipping-file change. Acceptance: verified current-build provenance, no missing includes/assets, no wrapper folders, no mixed-build files, no stale direct-download installer, and a source/commit/artifact record.

### F-02 — Real staging acceptance

Reconcile the user's reported 1.0.5 running screen and root-owned storage against the actual deployed entry point before diagnosing the cause. Exercise the one-page installer with native MySQL/MariaDB, GD, SMTP, HTTPS, subdirectory hosting, shared-host worker permissions, retries, cleanup and installed-site guards. Record measured results separately from historical reports or mocked tests. Preserve existing data; no forced reinstall.

### F-03 — Exact favicon and brand preservation

Use `icon(1).png`, not a generated replacement. Produce appropriate square transparent icon variants and ICO/PNG references without stretching/cropping the fleur-de-lis. Keep the original horizontal wordmark unchanged. Verify public/admin/installer icon references, browser rendering, subfolder paths, and updated installer integrity hashes. Record sizes and source fingerprint. App-icon/PWA installation is not implied merely by shipping icon files.

### F-04 — GitHub testing, incremental releases and agent coordination

Owner-approved: automated GitHub tests, small versioned releases, retained complete ZIP copies, continuously updated agent documentation, and separate Codex/Devin branches integrated as work finishes. [Release workflow](RELEASE_WORKFLOW.md) defines CI/version/package gates; [release register](RELEASES.md) tracks actual artifacts; [agent coordination](AGENT_COORDINATION.md) assigns ownership and integration rules. Documentation CI is implemented in this change. Application CI and versioned deployment packages are now delivered for v1.1.0; subsequent increments must repeat the applicable gates. Do not treat a green documentation check as application acceptance.

## Public experience and lead generation

### W-01 — Interactive portfolio and case studies

Real desktop/mobile captures, accessible before/after slider for actual redesigns, optional short user-initiated walkthroughs, filterable project categories, expanded challenge/solution/deliverables/results, and live links where approved. Admin controls for capture/media placement and clear concept/client labels. Acceptance: keyboard/touch usability, no false performance/result claims, no broken media, genuine responsive captures rather than a shrunken desktop screenshot.

### W-02 — Signature homepage motion

Preserve approved navy/gold composition while adding restrained metallic logo highlights, layered device depth, polished service/portfolio responses, and section reveals. Acceptance: immediate readable content, reduced-motion and no-JavaScript behavior, touch-safe controls, no flying pixels/autoplay audio/forced loading, and measured load/layout/interactivity before claiming performance improvements. Partially delivered on `devin/homepage-motion`: a working motion pause (`motion-off` now actually disables CSS motion and force-reveals pending sections — previously a no-op) plus a restrained metallic logo sheen; layered device depth and measured load/layout evidence remain.

### L-01 — Single-page project estimator

Current integration: verified release 1.11.2 adopts the canonical estimator through the reviewed quantity adapter without changing the established public form/results. Actual 1.11.1 parity, boundary and full application/upgrade acceptance pass; release assets are downloaded and verified. Broader L-01 completion is not claimed.

One form for service/project type, approximate pages/products, functionality, content/branding/migration/maintenance options, timing, and contact information. Optional fields can appear in place; no wizard. Admin-editable price rules and ranges, an explanation of assumptions, and a consultation result for uncertain/custom scopes. Acceptance: server-side recomputation, validation, spam controls, saved lead/brief before notification, input preservation on failure, no invented starting prices, and 'estimate, not final quote' labeling. The [estimator adapter contract](ESTIMATOR_ADAPTER.md) is integrated in verified 1.11.2.

### L-02 — Website-review requests

Capture existing website, business goals, and contact details in the lead inbox. Start with human-reviewed findings and a saved review/report before sending. Automated performance data is optional later and must label sources, observation date, lab versus real-user evidence, and absent data. Acceptance: valid request persistence, admin workflow, appropriate consent and delivery tracking. Any later server-side URL fetching requires SSRF protections, limits, and no access to private/internal addresses.

### C-01 — Why custom / Wix and Squarespace comparisons

Build an engaging homepage teaser plus detailed, separately scoped comparison content and migration explanations. Compare service model, design process, customization, integrations, responsibilities, content portability, operating costs, and support only with current primary-source evidence. Separate provider/plan facts from CajunWebPro's actual offered deliverables. Include who each route may suit and switching considerations. Acceptance: source/date/plan registry, no copied unsupported mockup claims, no guarantees of rankings/speed/conversion or universal superiority, and a clear consultation CTA.

### C-02 — Packages, FAQ, local/industry information

Admin-managed real package ranges and inclusions, service detail, process, ownership/handover, revisions, timeline responsibilities, hosting/maintenance, practical FAQ, legitimate local service information, and industry examples. Acceptance: no fake prices, locations, customer logos, endorsements, or results; meaningful content rather than duplicate search pages; accurate metadata and structured data only where facts support it.

### L-03 — Consultation scheduling

Start with an honest appointment-request workflow or integrate the selected calendar provider after configuration. Acceptance: timezone handling, real availability/conflict protection if promising instant booking, confirmations, cancellation/rescheduling, spam controls, and no appointment described as booked before persistence/provider confirmation. Provider selection remains a configuration decision, not an existing connection.

## Client operations

### P-01 — Private client portal

Admin-controlled invitation/access, client-isolated projects, milestone status, design previews, comments, revision requests, files, messages, and handover materials. Acceptance: authorization on every project/file/action; one client cannot access another client's records; protected uploads; audit events; session/reset controls; actual milestone updates rather than fabricated progress percentages.

The portal is the single customer workspace for design and all supported 20i-backed services. [Client portal roadmap](CLIENT_PORTAL_ROADMAP.md) specifies native hosting controls, explicit service ownership, project monitoring, update notifications and versioned website ZIP delivery. Secure StackCP access is a labeled bridge for unimplemented controls, not completion of the requested native integration.

### P-02 — Versioned approvals and proposals

Freeze the exact design/proposal version under review; capture authorized approval or change requests against that version. Proposals contain approved deliverables, exclusions, revisions, schedule assumptions and payment milestones. Acceptance: immutable approved snapshots/audit history, clear superseded versions, ownership checks, deliberate confirmation, and no implied legal e-signature compliance without separate review.

### P-03 — Web-design payment forms, deposits, invoices and maintenance payments

Provide dedicated CajunWebPro-styled payment forms for web-design services through the selected Authorize.net integration. Support full payment for an admin-published fixed-price service, a fixed-amount or percentage deposit against an approved quote, milestone invoices, final balance, and separately consented recurring maintenance. A customer must be able to pay for design work without buying hosting, a domain or VPS, and without a configured 20i account.

Admin creates a payment request from the customer/project/proposal/invoice with service description, approved amount, currency, due date and applicable terms; preview it and generate a secure shareable payment link. The client can also select Pay from their own portal invoice. Show invoice/reference, service scope, amount due now, prior payments and remaining project balance where applicable. Lock pricing to server-side invoice/quote records; an estimator result alone cannot authorize a charge. Paid, canceled, expired or superseded requests must not accept another payment. Links must not expose other invoices, files or customer records.

Use the same tokenized Authorize.net payment service, transaction records, verified notifications, receipts and reconciliation as hosting checkout; no separate gateway or raw card storage. Reflect confirmed payments and refunds in both invoice and project balance while keeping project completion/approval independent. See HC-11 for detailed workflow and acceptance. Acceptance includes standalone design-only sandbox payments, duplicate/timeout recovery, tampered amounts, link access/expiry/revocation, deposits/milestones/final balance, correct receipts, separate test/live configuration, and no 'paid' status from a success-page visit alone. Status: approved requirement, pending implementation/current-source inspection. Real charges/subscriptions are not authorized by documentation work.

### P-04 — Maintenance/service plans

Owner-defined inclusions, exclusions, limits, support expectations and request workflow. Acceptance: agreed prices and terms, explicit recurring-payment consent where applicable, genuine service status, and no invented 'unlimited support' promise.

### P-05 — Web-design project progress and delivery workspace

Client-visible stages, milestones, dates, blockers, required customer actions and a dated update timeline; protected previews, content uploads, versioned design approvals/revisions and project invoices. Staff controls what is published, and design-only clients need no hosting subscription. No invented progress percentages. Workflow and acceptance: [Project workspace](CLIENT_PORTAL_ROADMAP.md#p-05--web-design-project-workspace-and-progress).

### P-06 — Downloadable website ZIPs and handover

Latest and retained prior website releases with version, notes, date, size, checksum and scope. Admin publishes a verified client-specific ZIP plus necessary authorized database/content exports and instructions; omit secrets, agency records and other clients. Private permission-checked downloads, safe packaging jobs, retention, integrity and isolated restore tests are required. Keep deliverable releases distinct from expiring provider backups. Full requirements: [Website downloads](CLIENT_PORTAL_ROADMAP.md#p-06--versioned-website-zip-delivery-and-client-downloads).

### P-07 — Client notification center and real monitoring

Portal bell/timeline and configured email updates for project work, approval requests, new downloads, support, billing and service events. Link to the correct authorized object; record read/unread, preferences and delivery retries. Show actual project progress and verified hosting health/usage/check timestamps; mark stale/absent metrics honestly. Client notifications never include internal admin email or staff-only notes. Full acceptance: [Updates and monitoring](CLIENT_PORTAL_ROADMAP.md#p-07--client-updates-notifications-and-service-monitoring).

## Administration and resilience

### A-01 — Visual lead pipeline

Extend existing inquiries with stages such as New, Consultation, Proposal Sent, Won, and Not Proceeding; configurable where appropriate. Link briefs, notes, quotes, follow-up dates, source, reviews and client projects. Acceptance: authorized edits, validated transitions, useful search/filtering/export, audit events, and reliable saved state. Dragging cards must not be the only accessible control.

### A-02 — Content revision history

Save meaningful versions, compare changes, restore as draft, and retain approval context separately. Acceptance: permissions, no silent overwrite of later edits, explicit restore action, and recorded author/time.

### A-03 — Backup and restore

Protected export of required database/media/configuration with clear scope, integrity metadata, secrets protection, storage limits and retention. Restore must show impact, validate compatibility, require deliberate authorization, and support recovery. Acceptance: a successful restore drill in an isolated environment; an archive download alone is not a tested backup system.

### A-04 — Controlled update manager

Identify installed and available versions, verify trusted package integrity/compatibility, preserve private data/configuration, back up affected material, apply ordered migrations, report failures, and offer a tested recovery path. Acceptance: path-traversal/symlink/partial-write protections, concurrent-run prevention, migration tracking, exact artifact manifest, and no reintroduction of install keys or multi-step setup. Signing/trust provisioning must be designed before automatic updates are enabled.

### A-05 — Additional admin accounts and permissions

Core account lifecycle is released in 1.2.0 ([PR #10](https://github.com/afterburn25/Cajun_Web/pull/10)); [administrator guide](ADMIN_ACCOUNTS.md) maps routes/schema, current grants and acceptance. Owner creation/invitation, recovery, role edits, revocation and last-owner concurrency have exact-main native/browser evidence. Mailbox assignments/MFA remain deferred; full A-05/A-06 is not declared complete or deployed.

Owner-controlled creation/invitation, role and mailbox grants, reset and deactivation for additional administrators. Preserve the owner account, deny privilege escalation, revoke disabled sessions and protect the last active owner. Keep staff identities separate from client accounts. Acceptance and permission tests: [Admin mail roadmap](ADMIN_MAIL_ROADMAP.md#a-05--additional-admin-accounts-and-access-foundation).

### A-06 — Multi-account admin webmail and setup

Candidate 1.13.0 implements bounded profile/password/assignment preparation and the administrative UI. [Guide and pending acceptance](ADMIN_EMAIL_ACCOUNTS.md). No real connection, inbox or sending is included; full A-06 remains incomplete.

Add Email with incoming/outgoing account configuration, separate connection tests, mailbox access grants, account switching/combined inbox, folders, read/unread, search, compose/reply/forward, drafts and attachments. Preserve server-side mailbox permissions, encrypted credentials, safe message rendering and correct send/sync failure handling. Full workflows and native two-account tests: [Admin mail roadmap](ADMIN_MAIL_ROADMAP.md#a-06--multiple-email-accounts-and-webmail).

### A-07 — Reusable email templates

Current integration: verified 1.12.0 provides the private administrator library, retained revisions, explicit grants and escaped sample preview. [Scope and acceptance](ADMIN_EMAIL_TEMPLATES.md). Mailbox compose insertion and sending remain separate work; full A-07 is not complete.

Create/edit/duplicate/preview/archive templates with subject/body, safe allowlisted placeholders, shared/personal visibility and compose insertion. Preview resolved values and edit the draft before explicit sending. Keep signatures/account selection clear and transactional templates controlled. Acceptance: [Admin mail roadmap](ADMIN_MAIL_ROADMAP.md#a-07--reusable-sending-templates).

### A-08 — Admin-only unread-mail notifications

Show an unread-mail bell in admin and the public-site header for authenticated admins only. List unread messages from authorized accounts; clicking a notification opens the exact message in admin webmail. Synchronize counts/read state, show stale/error status, and exclude all private notification data from anonymous/client responses and public caches. Acceptance: [Admin mail roadmap](ADMIN_MAIL_ROADMAP.md#a-08--unread-mail-bell-in-admin-and-on-the-public-site).

### A-09 — One-click client portal support access

Owner/authorized staff can open any permitted client's portal from the customer record or support ticket without the client password. Maintain separate staff/customer identities, a visible support-session banner, Return to admin, expiry/revocation and action-attributed audit. Respect client data scope and independent permissions for sensitive actions; ordinary customer sessions cannot impersonate. Full acceptance: [Portal support access](CLIENT_PORTAL_ROADMAP.md#a-09--one-click-client-portal-access-for-support).

### A-10 — One-click VPS SSH for support

An Open SSH action launches a real interactive session for the selected authorized VPS once its support connection is configured. Verify product-specific authentication/privileges, staff terminal permissions, protected keys, verified host identity, short-lived scoped launch, audit and disconnect. Provider terminal handoff or a separate secure gateway must be proven; shared-host installation must not acquire a hidden daemon/root dependency. Full acceptance: [VPS support SSH](CLIENT_PORTAL_ROADMAP.md#a-10--vps-ssh-access-from-the-support-workspace).

## Implementation order and completion rule

Import and validate the baseline first. Land exact branding/favicon and public experience/content plus lead workflows next, then client operations and resilience in tested increments. Access control and backup/recovery checks must precede storing real client documents or enabling payments/automatic updates.

Add A-05 staff access in M1. A-06/A-07/A-08 form the M5/HC-12 admin-mail milestone after identity and mail-host/private-storage prerequisites; they do not depend on VPS availability. [Admin mail roadmap](ADMIN_MAIL_ROADMAP.md) provides setup, workflows and acceptance tests.

Establish client-notification permissions/data in M1, then connect billing/hosting/design/support events during M2–M5. P-05/P-06 complete design progress and ZIP handover in M4. Client hosting controls must be tracked against actual 20i capabilities during M3–M5; M6 includes cross-client/download/restore and cache-isolation gates.

Establish A-09 support-session identity/permissions alongside M1 and connect it to the client portal; deliver A-10 when real VPS authentication and terminal transport are verified in M5. One-click convenience does not substitute for initial server access setup.

Move an item through `approved` → `in progress` → `implemented` → `verified` → `deployed` only with evidence for each transition. Record branch/commit, routes, schema/config changes, tests, limitations, and deployment status. Update this roadmap and the session handoff with every meaningful change.

## H — Hosting commerce, white-label resale, custom billing and unified clients (approved 2026-10-12)

**Full requirements and phase gates:** [Custom hosting and billing roadmap](HOSTING_AND_BILLING_ROADMAP.md). This expands P-01/P-03/P-04 and adds the owner's selected payment/provider direction; existing web-design, branding, installer and safety requirements still apply. Do not treat a future launch as implemented.

- **HC-01/02:** Real hosting/domain/VPS/web-design catalog and prices editable in admin; branded unified client portal and strict tenant isolation.
- **HC-03/04/05:** Cart and first-party checkout; Authorize.net settings with API Login ID, Transaction Key, Signature Key, Public Client Key (separate sandbox/live), custom theme-matched Accept.js card form and cryptographically verified webhooks; no raw card storage.
- **HC-06:** Own invoice, recurring billing, deposits, refunds, dunning and payment-profile/token management; no WHMCS/HostShop dependency.
- **HC-07/08:** 20i Reseller API configuration, package types and automated shared hosting fulfillment/reconciliation/suspension safeguards.
- **HC-09/10:** Real domain catalog/register/transfer/renew; separately priced VPS/managed cloud products, only after API/cost and operational validation.
- **HC-11/12:** Web design projects/proposals/maintenance alongside hosting; staff admin, service desk, support tickets, queue recovery, financial reports.
- **HC-13/14:** Modular shared-host PHP/MySQL source, one-page/keyless installer compatibility, upgrade migrations, cron/reliability, PCI/privacy/compliance and backup/restore release gates.

**Implementation order:** M0 baseline import/API feasibility → M1 plans + clients → M2 Authorize.net custom checkout + billing → M3 20i shared hosting → M4 domains + web-design workflows → M5 VPS/support → M6 hardened staging/live approval. Prices are owner-administered; past illustrative rates are not approved retail or verified wholesale.

## 2026-10-13 integration checkpoint

L-01 standalone estimator PR #27 is being integrated with arithmetic/result validation fixes; application adaptation remains pending. W-01 PR #7 now includes a real portfolio adapter and will be integrated next. Neither full roadmap item is complete. [Contract](PUBLIC_COMPONENT_INTEGRATION.md).
