# CajunWebPro 1.14.0 — exact-main acceptance

Source/tag: `f55879339e38769b4ec5fa81780a3d650b63da25` / `v1.14.0`. [Merged PR #64](https://github.com/afterburn25/Cajun_Web/pull/64). [Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38453775600). All packages use the same clean tested commit. Windows and Linux artifact bytes agree; fresh public download verification is recorded after publication.

## Delivered scope

Staff with current website permission can track human website reviews from existing inquiries, explicitly adopt older requests, write findings with evidence labels/source/UTC time, retain report history, mark a saved draft and record delivery already performed outside CajunWebPro. Delivery requires the exact saved revision, contact consent, valid time/reference and current password; it sends nothing. Editing a drafted report returns to review; delivered and closed report contents are immutable. Inquiry sales status remains separate.

Public intake deliberately accepts bare website hostnames, normalizes to HTTPS, keeps the raw 500-byte bound and rejects controls/userinfo/fragments. Existing CSRF/consent/honeypot/rate/idempotency rules remain. New inquiry and structured review capture commit together when storage is enabled. No automatic website fetch, generated metrics or report sending is implemented.

## Results

- 810 native checks, including 65 review schema/lifecycle/security checks; 3084 estimator comparisons/boundaries; 890 HTTP/Chromium; 115 filesystem/recovery; 433 Apache; 19 package-builder checks. Named results, workflow logs and actual screenshots are included in TEST-EVIDENCE.
- Native tests cover additive repeated setup, rejecting unknown populated records and incompatible uniqueness/deletion relationships, owner setup, fresh website permission/session/support boundaries, explicit legacy adoption, input normalization and rejection, evidence/source/time validation, strict report/stage/delivery gates, stale and concurrent edits/delivery/deletion, audit rollback, atomic intake/replay, immutable history and targeted permanent deletion.
- HTTP and Chromium cover owner setup and CSRF, explicit adoption, scoped staff access, preserved invalid/stale values, escaped findings, forged-delivery denial, password-confirmed external delivery, deliberate bare-host intake and fragment rejection, draft add/remove controls and mobile fit. Actual desktop/mobile review screens are inspected before publication.
- The immutable 1.13.0 package is installed and seeded under Apache, updated through the owner workflow, then recovered/reapplied. Earlier templates, email profiles/assignments and the original private key survive, alongside new review/report/history rows. The original key still opens saved email credentials. All earlier supported update paths remain in the suite.
- Public homepage desktop/mobile pixels match verified 1.13.0. Administrator/client screenshots retain the layout with dynamic fixture dates/references and intentional version changes. All package bytes agree between clean Windows and Linux builds; per-file hashes and ZIP integrity pass.

## Installation, updates and limits

FULL-PROJECT is sanitized development source. COMPLETE-UPLOAD extracts organized production contents directly for a fresh install. UPDATE creates cwp-update-1.14.0 staging for installed 1.0.8 or 1.1.0 through 1.13.0 sites. Back up website files, database and private storage including mail-master.key before update. Read START-HERE and MIGRATION-INSTRUCTIONS; do not fresh-install over an existing site or manually copy payload files.

Older sites enable Administrators if needed, then review storage from a Website review inquiry. Setup creates validated InnoDB side tables without guessing old inquiry fields. Fresh installation prepares empty tables. File recovery retains added database records; it does not undo schema. Explicit permanent inquiry deletion also removes linked reports/history through validated foreign keys, and its staff audit is transactional.

External delivery is a staff assertion, not a provider confirmation. Real automated audit/fetching, report email/client delivery, IMAP/SMTP inbox/compose, payments/provider provisioning/SSH and target-host deployment remain outside this increment. No live customer or provider operation occurred. Apache fixtures do not certify a particular production host; file interruption tests cover durable boundaries, not arbitrary hardware failure.

Suite counts: `{"activity-native": 64, "apache": 433, "attempts-native": 69, "build-guards": 19, "catalog-native": 49, "delivery-archive": 55, "delivery-native": 57, "estimator-adapter": 3084, "http": 890, "ledger-native": 69, "mail-accounts-native": 68, "mail-templates-native": 59, "native": 65, "notifications-native": 62, "payments-native": 62, "reviews-native": 65, "staff-native": 31, "structure": 115, "support-native": 35}`.

## Published verification

All 16 [release assets](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.14.0) were freshly downloaded after publication and match prepared bytes. Checksums, archive members/paths/directories and source/tag agree. Prior v1.13.0 asset IDs, sizes, digests and modification timestamps remain unchanged.

| Package | Members | SHA-256 |
| --- | ---: | --- |
| FULL-PROJECT | 470 | `33cdfd40a7bf1eb9ebe06550742e9910bcbf6437eae6662cb38fe24374578f03` |
| COMPLETE-UPLOAD | 256 | `051d33fe6c2e9bb524b2b065c0fa72ada2d00979a1526e0d2d1bcb8e6b559f62` |
| UPDATE | 243 | `9be990acee90c4a34d00a3700c9b00b99be02a85457e2af950b8988fc7f77d62` |
