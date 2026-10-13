# Architecture notes

## Current directory and package contract — 1.11.0 candidate

The owner directory correction supersedes all earlier flat-root/overwrite-update instructions below. See [Directory structure](DIRECTORY_STRUCTURE.md), [migration and recovery](STRUCTURE_MIGRATION.md), and [START-HERE](../START-HERE.txt). Maintain thin root entries, organized controllers/views/business/database modules, categorized images and protected storage/upload scaffolds. FULL-PROJECT contains docs/tests/tools/brand/foundation/CI; COMPLETE-UPLOAD contains organized production files without a wrapper; UPDATE stages a verified owner-authenticated migration instead of blindly overwriting live files. Existing config/keys/private data and other agents' branches remain preserved.

Candidate `9e8dedf` in [PR #38](https://github.com/afterburn25/Cajun_Web/pull/38) passes 618 native, 782 HTTP/Chromium, 41 filesystem/recovery, 203 Apache and 16 package-builder checks. [Acceptance record](TEST_REPORT_1.11.0.md); [Devin handoff](DEVIN_HANDOFF_1.11.0.md). Final cleanup/docs acceptance, merge, exact-main tests and immutable publication remain mandatory. Latest published release remains 1.10.0. No live deployment.

## Owner priority override — directory structure and packaging, 2026-10-13

The owner requires an actual structural refactor and three separately verified downloads before any unrelated roadmap work resumes. This supersedes earlier constraints that kept substantial root PHP files or interpreted a no-wrapper ZIP as a flat application. Work on `codex/priority-directory-refactor` from main `b975915` preserves integrated portfolio/estimator work and the tested browser correction. Latest published version is **1.10.0**; the 1.11.0 portfolio-only release was stopped before draft/tag creation. The unused 1.11.0 candidate will be corrected before release. Do not publish the old-layout candidate.

Required meaningful areas: admin, portal, pages, includes, business app modules, API/webhooks/media, install, database, config, assets/css and assets/js, categorized images, protected storage/upload scaffolds. Root entry points must be thin. FULL-PROJECT includes docs/tests/tools/brand/foundation/CI; COMPLETE-UPLOAD extracts organized site contents directly; UPDATE includes tested compatibility/migration support. Keep private configured data locations, exact brand bytes, one-page keyless setup, all existing behavior and no-rewrite/root/subfolder support. [Refactor contract](DIRECTORY_REFACTOR_CONTRACT.md). No live deployment is authorized.

Devin PRs #31/#32 are separate independent components, preserved and paused for integration while paths change. Supply the exact mapping and corrected baseline before further application adapters. Next action is structure implementation, migration/recovery and Apache/native/browser/package acceptance—not another roadmap feature.

## Portfolio adapter — 1.11.0 candidate

`site/app/portfolio.php` maps current published `projects` rows to the immutable portfolio component; `tools/build.py` bundles canonical `foundation/portfolio` classes verbatim. Existing project rows remain the rendering source; normalized category values match filters and card data. Public routes use the catalog's exact category/slug rules; draft/archived rows never enter public markup. No new schema or alternative CMS. Browser enhancement preserves real navigation links and announces result counts. [Guide](PORTFOLIO_BROWSING.md).

## 2026-10-13 — Verified 1.10.0 workspace activity increment

Separate client/staff activity centers and private workspace bells are delivered in [v1.10.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.10.0) / `4649a913d40166b9874f9550e57b4186edfc43ab`. Additive per-person read state projects retained sources without changing business writes. Exact links, bounded mark-visible actions, history, current authorization and passive idle expiry pass 603 native / 630 HTTP/Chromium checks including actual 1.9.0 UPDATE. Full P-07 remains partial; SMTP dispatch/preferences/stage/hosting/ticket/renewal events and A-06–A-08 multi-account webmail/templates/email bells remain pending. [Guide](WORKSPACE_ACTIVITY.md) · [report](TEST_REPORT_1.10.0.md).

## 2026-10-13 — Verified 1.9.0 client ZIP delivery increment

Prepared, reviewed and versioned client ZIP handover is delivered in [v1.9.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.9.0) / `080f14aea8fe5c35e04e4eba62976609aef7cd67`. Guarded private storage, immutable manifests, owner publication, client history and authenticated resumable downloads preserve previous records through actual 1.8.0 UPDATE acceptance. 539 native / 569 HTTP/Chromium checks pass. Full P-06 remains partial: generated builds, database handover, larger jobs, dynamic restore and notifications remain pending. Actual merchant acceptance/card checkout and broader 20i/mail/SSH remain pending. [Guide](CLIENT_DELIVERIES.md) · [report](TEST_REPORT_1.9.0.md).

## 2026-10-13 — Verified 1.8.0 invoice payment increment

External USD receipts, partial/full balances, retained owner corrections, legacy balance import and client-owned statements/printable receipts are delivered in [v1.8.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.8.0) / `c36a36e80c40485c9e3e7e6cdd4884ced25db961`. Existing 1.7.0 invoices, attempts/notifications, credentials/keys and earlier data survive UPDATE. New requests/trials use remaining balances; sandbox evidence never becomes revenue. 427 native / 503 HTTP/Chromium checks pass. Actual merchant acceptance, production/client checkout, refunds/tax/credits/subscriptions remain pending. [Guide](INVOICE_LEDGER.md) · [report](TEST_REPORT_1.8.0.md).

## 2026-10-13 — Verified 1.7.0 sandbox notification increment

Signed sandbox receipt, durable minimal inbox and explicit owner-triggered current reporting are delivered in [v1.7.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.7.0) / `1fe0670142755f86aee235fabe1847e5d118ee52`. Receiver defaults off; schema is additive, invoice settlement remains disabled and no runtime daemon is needed. Original 1.6.0 UPDATE preserves attempts/events and all earlier records/keys. 358 native / 451 HTTP/Chromium checks pass; signatures/reporting remain synthetic. Actual merchant acceptance, production/client checkout and automatic processing are pending. [Guide](SANDBOX_NOTIFICATIONS.md) · [report](TEST_REPORT_1.7.0.md).

## 2026-10-13 — Verified 1.6.0 sandbox increment

Owner-only tokenized sandbox trials, durable intent/events, transaction-detail reconciliation and shared manual/catalog/request locking are delivered in [v1.6.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.6.0) / `e5d4c17456faff0522429ab3f58093a06a523bd5`. Sandbox never pays real invoices. Production/client checkout, webhooks, receipts/refunds and recurring billing remain pending. Original 1.5.0 UPDATE preserves credentials/key, requests and existing records. 296 native / 421 HTTP/Chromium checks pass; gateway and browser tokenization fixtures remain synthetic. Back up and enable Sandbox trials after UPDATE. [Guide](SANDBOX_PAYMENTS.md) · [report](TEST_REPORT_1.6.0.md).


Released 1.5.0 adds [payment preparation](PAYMENT_PREPARATION.md): two additive tables, owner-only external-key credential protection and reviewed existing-invoice requests. Charges and production connections stay disabled. 227 native / 401 HTTP-browser checks passed; [report](TEST_REPORT_1.5.0.md). Complete/update: 58/57 files. After backup and UPDATE merge, owners enable Payment preparation; private gateway storage must be outside the document root. Existing manual billing remains unchanged.


Released 1.4.0 adds `catalog.php`, `catalog-admin.php` and shared `catalog-view.php`. Products append revision history and use explicit catalog permission; public reads select only safe fields. Existing studio invoices receive immutable catalog snapshots with unique request keys and serialized issuance. Product saves and issue/paid/void transitions use strict transactional audit. [Catalog guide](SERVICE_CATALOG.md).


A-09 released 1.3.0: `app/support.php` holds additive support policy/session/audit adapters; `app/support-admin.php` provides owner setup and client entry. The existing portal receives an explicit browser-bound context without customer-login impersonation. Every request revalidates staff/client versions and client policy; writes revalidate under the shared staff mutex and InnoDB transaction. Per-context CSRF prevents URL stripping or retargeting. Strict action audit precedes commit/download bytes. [Operational guide](CLIENT_SUPPORT.md).


The website/client application shipped in v1.1.0. Released 1.2.0 integrates administrator access using canonical foundation components bundled verbatim into `site/app/foundation`; see [administrator architecture](ADMIN_ACCOUNTS.md). See [release register](RELEASES.md) and [test report](TEST_REPORT_1.1.0.md).

Updated 2026-10-13: recovered 1.1.0 source is in `site/`. Public growth modules, `workspace.php`, `studio-admin.php` and `portal.php` extend the existing application. The seven additive workspace tables and actual routes/controls are mapped in [release acceptance](RELEASE_1.1.0.md). The older source inventory below remains useful baseline history.

## Deployment model

Follow-on A-05 component: [Staff invitations](STAFF_INVITATIONS.md) uses PDO MySQL/MariaDB and an explicitly migrated InnoDB table, with transactional one-use redemption and an application-supplied account activation callback. The 1.2.0 staff adapter now supplies that callback, lifecycle serialization and explicit migration; the canonical component is bundled into the deployable tree. Read-only inspection of recovered draft source on `codex/finish-1.1.0` revealed opaque string `auth_version` values; the foundation now matches those without changing the draft's schema/login code.

Independent implementation added 2026-10-13: [access foundation](ACCESS_FOUNDATION.md) under `foundation/access/`, with synthetic unit tests under `tests/access/`. It is a pure PHP permission-decision module with no framework, database, request handler or installer dependency. The 1.2.0 adapter maps it to existing `users` and additive `staff_profiles`; the original standalone design remains testable. Historical source inventory below remains historical.

Existing application: PHP with PDO MySQL/MariaDB, normal browser requests, local CSS/JavaScript/images, and persistent private file storage. PHP source targets 8.2 or newer. Required extensions are checked by the installer. Shared-host deployment must not require a Node service, command-line build, root access, or a long-running worker.

The repo's documentation tree is not the website document root. `site/` is the source directory; `brand/`, `tests/`, `tools/` and the canonical `foundation/` directory are not deployed directly; verified copies of the access/invitation classes are shipping dependencies under `site/app/foundation/`. Build the deployment ZIP from the deployable files only, without a source-directory/version wrapper. Preserve the existing app/assets grouping and root entry-point behavior.

## Inspected source map

| Distribution path | Responsibility indicated by source/package |
|---|---|
| `index.php` | Public page routing/rendering |
| `app/home.php`, `app/view.php` | Homepage and common public presentation |
| `admin.php`, `app/admin-view.php` | Administration workflows and views |
| `api.php` | Public form/API handling |
| `app/core.php` | Common configuration/session/database/security/filesystem helpers; version constant |
| `app/schema.php` | Initial table definitions and seed/default content |
| `app/mail.php` | Mail delivery helpers |
| `app/staff.php`, `app/staff-admin.php` | Account lifecycle/authorization, explicit upgrade and administrator/setup forms |
| `app/foundation/` | Verbatim bundled access/invitation components; canonical sources stay in root foundation |
| `media.php` | Media-serving entry point |
| `robots.php`, `sitemap.php` | Search-crawler endpoints |
| `install.php` | Embedded-bootstrap, one-page setup and package inventory |
| `.htaccess`, `app/.htaccess`, `app/index.php` | Existing server/application access protections |
| `assets/` | Logo, emblem, existing favicon, site/admin CSS/JS and decorative/project artwork |

These responsibilities do not substitute for a full code review. Native database and service behavior were not exercised in the documentation session.

## Initial schema observed

Ten named definitions in `app/schema.php`: settings, users, projects, services, testimonials, leads, media, rate_limits, resets, audit. Definitions use a validated table prefix and InnoDB/utf8mb4 declarations.

Existing lead records include a request/reference, contact/project requirements, state/notes, and notification status. Existing public project records include publication/demo/featured state and case-study fields. Do not confuse these public portfolio projects with a complete multi-client project-management/approval/invoicing model.

## Runtime state to preserve

Configuration and installation guards are created at installation. Private media and other persistent data are not release payload. Protected `config.php`, installed markers, storage, media and DB data must survive application updates. Do not publish account-specific proxy configuration, credentials, private diagnostic paths, or operational backups in Git.

The installer contains an exact dependent-file inventory. Altering CSS, JavaScript, PHP, logo/icon files, or the set of deployable files requires regenerating that inventory; otherwise the correct application change may be rejected as a mixed upload.

## Extension design constraints

- Add reviewed schema migrations for new capabilities; do not use fresh setup to upgrade an installed site or overwrite existing tables.
- Distinguish portfolio records, leads, client organizations/accounts, client projects, proposal versions, approvals, files, messages and financial records as appropriate after source review.
- Enforce authorization server-side for each resource; hiding a link is not client isolation.
- Keep estimator calculations and business validation server-side, regardless of live UI previews.
- Use hosted payments and verified notifications; never store raw card data.
- Keep customer uploads outside public reach and validate type, size, authorization and retrieval paths.
- Provide accessible non-drag/non-animation controls and preserve root/subfolder deployment.
- Background integrations must fit real hosting capabilities; do not silently depend on a daemon or a cron job that has not been configured.

Architecture choices for these extensions remain to be implemented and tested. Update this file with actual routes, classes/modules, schema migrations, provider selections and data flows when they land.


## Planned hosting/business-commerce extension (approved 2026-10-12; not yet implemented)

Owner direction: implement our own platform for the agreed WHMCS-style functions, with first-party application/data/workflows and direct provider adapters. See [Custom platform blueprint](CUSTOM_PLATFORM_BLUEPRINT.md). Preserve current source and avoid duplicate customer identities, financial ledgers or disconnected admin tools when integrating modules.

- Custom PHP storefront and /clients identity/portal with service-family modules, organized under app/ (commerce, billing, payments/authorize-net, providers/twentyi, domains, vps, clients, support, jobs, admin) after baseline is imported. Preserve current entry points and the one-page keyless installer.
- 20i authenticated reseller API adapter with explicit feature discovery, package-type mapping and external IDs. Public docs support hosting package operations; actual domain/VPS API endpoints and capabilities must be checked within account documentation before coding or promising full automation.
- Authorize.net hosted tokenization via Accept.js in a themed first-party checkout, with token-driven PHP charges, customer/payment profiles, signed webhooks, transaction idempotency, reconciliation and separate sandbox/live configuration. Four admin inputs: API Login ID, Transaction Key, Signature Key, Public Client Key. Secret keys encrypted outside public reach. PCI SAQ A-EP implications require review for own HTML card form; Accept Hosted is a fallback.
- Persist customer/orders/line items/plan price snapshots/invoices/payments/subscriptions/provider fulfillment status/jobs and audit trails independently; split custom web-design/manual milestones from server provisioning.
- Web-design payment requests share the Authorize.net adapter, invoice/payment ledger and client identity. Model request-to-project/proposal-version/invoice relationships, fixed/percentage deposits, milestone/final-balance obligations, protected expiring links, request supersession and payment-attempt concurrency. Resolve prices and invoice access on the server. Confirmed design payments update financial balances without triggering 20i or marking project work approved/completed; this flow must operate when 20i is disabled. Exact schema/routes remain pending current 1.1.0 source inspection.
- PHP web requests plus scheduled PHP CLI cron for reliable recurring billing, reconciliation and queue progression. Browser installer remains one-page; scheduled-job configuration is a go-live requirement, not a new installer wizard.
- Precise architecture, migrations, data flow, endpoints and test results are not yet available. See docs/HOSTING_AND_BILLING_ROADMAP.md for acceptance requirements.

## Planned admin accounts and webmail (approved 2026-10-12; unimplemented here)

See [Admin mail roadmap](ADMIN_MAIL_ROADMAP.md). Extend current 1.1.0 staff authentication after source inspection with owner-controlled administrator lifecycle, roles/permissions and mailbox grants. Reuse secure sessions, reset flows and audit infrastructure; preserve owner access and separate customers from staff.

Plan separate modules under app/ for mailbox connections/credential protection, provider adapters, sync, message rendering, compose/drafts, templates and private notifications. Use secure IMAP for receive/read-state and SMTP for submit where supported. Verify provider authentication and hosting capabilities; bundle needed PHP dependencies for browser/shared-host installation. No mandatory persistent worker, Node server or installer wizard.

Model mailbox connections, admin-mailbox grants, folder/message identities and validity, sync checkpoints, drafts/send attempts, attachment references, templates/versions and staff audit events as needed. Remote mail remains authoritative; cache only necessary private data with explicit retention. Preserve uncertain send outcomes and provider Sent behavior rather than blindly resending. Keep transactional notification SMTP and support-ticket records distinct from mailbox webmail.

Expose unread summaries and exact-message links only through authorized, non-cacheable admin endpoints. An authenticated admin's public-header bell consumes that private data; shared public HTML/CDNs must contain no mailbox details. Enforce authorization again for message body, attachment and send endpoints. Isolate/sanitize email HTML, block active content and remote tracking by default, protect credentials and private attachments, and verify safe refresh/scheduled-sync behavior on the real host. Schema, routes and libraries remain design choices pending source review and native tests.

## Unified client delivery and support access (planned 2026-10-12)

[Client portal roadmap](CLIENT_PORTAL_ROADMAP.md) expands the native customer surface and its account-specific capability register. Persist explicit customer-to-provider-user/service grants and verify them on every provider operation. Keep service/financial state separate from design milestone, approval and published-deliverable state. Model client-visible project events and notification recipients/delivery independently from staff-only notes and internal mail.

Store immutable deliverable versions with project/source identity, approved scope, manifest/hash, storage reference, publication and download audit. A bounded packaging job creates a client-scoped archive outside the public root; publication requires integrity, secret/tenant exclusion and required data/restore verification. Release files/database exports cannot include the shared agency platform's data or credentials. Provider snapshots and published website handovers have separate retention and semantics.

Support sessions preserve an authenticated admin actor plus a single customer context with their own token, expiry and permissions; never replace the staff actor with the customer in audit. Separate per-tab/context routing avoids accidental cross-customer writes. Sensitive customer commitments and financial/authentication actions require their explicit authorized workflows. A-09 views do not grant A-10 terminal privileges.

VPS terminal launch validates staff role and the mapped server/service, then issues a one-use scoped short-lived transport token. Verify a provider terminal handoff if available, or use an independently hosted authenticated SSH gateway with protected per-target credentials, host-key validation, allowlisted targets, timeout and revocation. Keep any persistent terminal transport outside the normal PHP shared-host installation. This transport design and product-specific SSH privileges remain feasibility gates, not verified provider capabilities or deployed infrastructure.

## Reviewed estimator boundary — 2026-10-13

[Standalone estimator](ESTIMATOR_FOUNDATION.md) remains outside `site/`. The current `growth_estimate` still owns configured quantity pricing, custom/integration consultation, amount limits and lead recomputation. [Integration contract](PUBLIC_COMPONENT_INTEGRATION.md) records the adapter gap; no competing schema is introduced.
