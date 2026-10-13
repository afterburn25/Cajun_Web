# Architecture notes

Checkpoint: 2026-10-12. This is an inventory of the supplied 1.0.8 distribution and a guide for extensions. **The distribution is not yet imported into this Git tree.**

## Deployment model

Independent implementation added 2026-10-13: [access foundation](ACCESS_FOUNDATION.md) under `foundation/access/` with tests under `tests/access/`, and [estimator foundation](ESTIMATOR_FOUNDATION.md) under `foundation/estimator/` with tests under `tests/estimator/`. They are pure PHP decision modules with no framework, database, request handler or installer dependency. They are excluded from deployment until mapped into the verified current source; no current schema or authentication structure is assumed. Historical source inventory below remains historical.

Existing application: PHP with PDO MySQL/MariaDB, normal browser requests, local CSS/JavaScript/images, and persistent private file storage. PHP source targets 8.2 or newer. Required extensions are checked by the installer. Shared-host deployment must not require a Node service, command-line build, root access, or a long-running worker.

The repo's documentation tree is not the website document root. Decide and document the source directory at import. Build the deployment ZIP from the deployable files only, without a source-directory/version wrapper. Preserve the existing app/assets grouping and root entry-point behavior.

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
