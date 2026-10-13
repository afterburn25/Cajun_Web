# Changelog

This log distinguishes repository work from application releases and deployment. Documentation-only work does not advance the application version.

## 2026-10-12 — Custom platform, admin webmail, staff access and notifications (requirements only)

- Added `docs/CUSTOM_PLATFORM_BLUEPRINT.md` for the owner's explicit instruction to build our own WHMCS-style platform, mapping owned business modules, shared identity/ledger, provider adapters and phased delivery to the existing roadmap.

- Added A-05 to A-08 and `docs/ADMIN_MAIL_ROADMAP.md` for additional admin accounts, scoped mailbox access, multiple email account setup and webmail, reusable sending templates, and unread-mail bells in admin and the admin-only public header.
- Specified account connection checks, inbox/compose/drafts/attachments, safe email rendering, template preview/variables, provider read-state sync, exact-message links and private endpoint/cache isolation.
- Integrated staff identity in M1 and the independent mail milestone within M5/HC-12, synchronized direction/architecture/decisions/status/handoff, and retained the design-payment roadmap and 1.1.0 source prerequisite.
- No admin or mailbox account created, no provider connection, no email sent, and no runtime feature or deployment performed.

## 2026-10-12 — Dedicated web-design payment forms (requirements only)

- Expanded P-03/HC-11 to define themed Authorize.net forms for full service payments, quote deposits, milestones, final balances and optional maintenance, plus admin-created payment links and portal invoices.
- Specified shared invoice/payment records, accurate project balances, secure request lifecycle, sandbox acceptance, refunds and duplicate/timeout recovery. Design payments operate independently of 20i or a hosting purchase.
- Assigned standalone forms to M2; kept richer project/hosting bundles in M4. Synchronized direction, architecture, decisions, status and handoff, and corrected current source-import prerequisites to owner-confirmed 1.1.0.
- No application source, working form, gateway setup, card charge or deployment changed. Current 1.1.0 source remains to be located and inspected.

## 2026-10-12 — Owner corrects latest version to 1.1.0 (local docs only)

- Recorded the owner's explicit confirmation that 1.1.0 is current; retained verified 1.0.8 only as historical reference.
- Updated status/handoff to locate and verify 1.1.0 before source import or implementation, superseding older 1.0.8 starting-point instructions.
- Rechecked GitHub; remote main remains documentation commit `43bebd0`. Requested the current build's location. No 1.1.0 tests, code changes, push or deployment claimed.

## 2026-10-12 — Current roadmap and available build verification (local docs only)

- Confirmed GitHub main at `43bebd019a6f548d7a059b2c8e259beed3efe561`; feature branches contain no newer source, and no release/tag/workflow run was available.
- Located both original/recovered 1.0.8 archives in local Downloads, verified their recorded SHA-256, and retained an unchanged archive and extracted working copy outside the repository.
- Fresh checks passed ZIP CRC, 33-file byte equality, 32 installer dependency hashes, version/layout constants, original logo hash, 14 PHP syntax checks and two JavaScript syntax checks.
- Updated status, handoff and session log. No source import, feature implementation, new release, host repair, provider action or deployment; remote GitHub content unchanged.

## 2026-10-12 — Hosting resale and custom billing roadmap (docs only)

- Approved first-party hosting/domain/VPS storefront integrated with CajunWebPro web-design services; created docs/HOSTING_AND_BILLING_ROADMAP.md (HC-01 through HC-14, M0–M6 phases and acceptance tests).
- Selected 20i reseller API for infrastructure and Authorize.net for payments (replacing the earlier generic/Stripe proposal), without WHMCS/HostShop requirement. Configurable admin key fields: API Login ID, Transaction Key, Signature Key and Public Client Key; custom themed Accept.js payment flow with PCI gate.
- Added unified client portal, products/plans, domains, VPS, invoices, recurring payments, refunds, provisioning jobs, admin, security and shared-host/installer constraints to roadmap, direction, architecture, status, decisions, handoff and session log.
- **Not shipped:** no application source import, live credentials, integration, checkout, plan prices, hosting account or deployable artifact. Existing download/installer/storage issues remain open.

## 2026-10-12 — Download recovery and release-claim correction

### Corrected

- Withdrawn the unsupported chat claim that a complete, tested v1.1.0 release and update package were ready. The advertised files were not recovered in the active runtime or file searches; GitHub had no published release.
- Documented that main and feature/growth-suite-1.1.0 still pointed to the documentation checkpoint at investigation time. A created branch is not implementation.
- Updated status and handoff with the delivery incident and explicit baseline-import/rebuild prerequisites.

### Verified and recovered

- Rechecked CRC and SHA-256 of the real 33-file v1.0.8 archive and its application/installer version constants.
- Rechecked the exact-favicon archive and decoded PNG/ICO variants; source hash matches the user attachment.
- Created fresh byte-identical recovery copies clearly named as the earlier 1.0.8 baseline and separate favicon assets, not v1.1.0.

### Not changed or claimed

No application source/asset import, feature implementation, host repair, database migration or deployment. No new workflow tests or live download-UI success claimed. Existing files and data on the user's host were untouched.

## 2026-10-12 — Repository documentation bootstrap

### Added

- Project source-of-truth README and `AGENTS.md` working agreement.
- Current status, unresolved host issues, and exact session handoff.
- Approved project direction, feature roadmap, architecture inventory, deployment rules, branding provenance, and decision record.
- Dated session log, session template, and pull-request documentation checklist.
- Inspected 1.0.8 distribution identity and original logo/favicon-source fingerprints.

### Verified in the bootstrap session

- Connected repository identity, default branch, write access, and initially empty tree.
- Supplied ZIP hash, ZIP integrity, flat 33-file listing, version headers, schema declarations, and logo byte identity.

### Not changed or claimed

- No application source/asset import into Git yet.
- No runtime behavior, production files, database, or provider configuration changed.
- No new portal, estimator, comparison page, favicon implementation, payment system, or update manager shipped.
- No historical test counts represented as current tests; no host repair or full installation claimed.

## Pre-repository baseline — supplied 1.0.8 distribution

`CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip` is the latest recoverable complete artifact identified in the conversation. It contains the PHP application and one-page installer source. It is not a Git release/tag created by the bootstrap, and its live deployment is unverified. See `docs/BASELINE_INVENTORY.md` and `PROJECT_STATUS.md`.
