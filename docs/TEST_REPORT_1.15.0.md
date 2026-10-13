# CajunWebPro 1.15.0 — exact-main acceptance

Source/tag: `8749bc5a89e52f8f35a193cc49d306136576ec8e` / `v1.15.0`. [Merged PR #66](https://github.com/afterburn25/Cajun_Web/pull/66). [Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38460197599). All packages use the same clean tested commit. Windows and Linux artifact bytes agree; fresh public verification is recorded after publication.

## Delivered scope

One explicitly configured studio consultation calendar: owner-selected IANA timezone, weekday hours, duration, buffer, minimum notice and 30-day offered horizon. Scheduling starts disabled with no offered hours. Visitors request available slots; inquiries and pending appointments commit together. Staff with current website permission can adopt older consultation inquiries, confirm the exact saved future request with password and current consent, decline/cancel, or reschedule back to pending. A decision updates the studio calendar only; it sends no confirmation/cancellation message or provider invitation. Contact prospects separately.

Four additive InnoDB tables retain configuration, appointments and revisions. Every conflict-changing write takes the same database configuration lock, then uses current locking reads under REPEATABLE READ. Stale form versions, fresh staff authorization, strict audit and transactional rollback protect decisions. Existing explicit permanent inquiry deletion cascades linked personal appointment records using the same lock order. Earlier inquiry text and sales status remain separate. Disabled scheduling preserves preference-only intake, with existing CSRF, consent, honeypot, form timing, rate limits and replay controls.

## Results

- 893 native checks, including 83 scheduling checks; 3084 estimator comparisons/boundaries; 923 HTTP/Chromium; 129 filesystem/recovery; 487 Apache; 19 package-builder checks. Named results, workflow logs and actual screenshots are included in TEST-EVIDENCE.
- Native acceptance covers setup/idempotence/schema refusal, existing-record preservation, role/session/support/consent boundaries, policy validation, exact revisions, held slots and buffers, terminal states, confirmation/rescheduling, audit rollback and atomic intake/replay. Held-lock separate PHP processes test competing public bookings, confirmation, deletion/rescheduling and configuration changes. The initial snapshot-read concurrency failure is corrected and its regression retained.
- HTTP/Chromium acceptance exercises disabled preference fallback, explicit owner configuration, real public requests and replay, scoped staff access, escaped contact text, wrong passwords, stale values, confirmation/cancellation/rescheduling and desktop/mobile fit. Actual consultation public/admin screenshots are inspected before publication.
- The immutable 1.14.0 package is installed and seeded under Apache, updated through the owner workflow, recovered and reapplied. Earlier review reports/history, email profiles/assignments/private key and templates survive, alongside added appointment/configuration history. The original private key still opens saved email credentials. All 17 earlier supported starting packages remain covered.
- Public homepage desktop/mobile pixels match verified 1.14.0. Retained workspaces are inspected, with an intentional Consultations navigation entry and dynamic fixture dates/references/version labels. All package bytes agree between clean Windows and Linux builds; per-file hashes and ZIP integrity pass.

## Installation, updates and limits

FULL-PROJECT is sanitized development source. COMPLETE-UPLOAD extracts organized production contents directly for a fresh installation. UPDATE stages cwp-update-1.15.0 for installed 1.0.8 or 1.1.0 through 1.14.0 sites. Back up website, database and private storage including mail-master.key together. Read START-HERE and MIGRATION-INSTRUCTIONS; never fresh-install over an existing site or manually copy payload files.

Older owners enable Administrators if needed, then prepare scheduling through Consultations. Choose hours before enabling public slots. Fresh setup prepares disabled scheduling. Older inquiry dates remain free text until explicit staff adoption. File recovery retains added database records; it does not downgrade the database. Minimum notice applies to new requests and rescheduling, not staff confirmation of an already held future slot. Past appointments cannot be confirmed/rescheduled; terminal records cannot reopen.

This increment has one studio calendar and manual staff decisions. Provider-calendar integration, outbound decision messages, public self-service cancellation/rescheduling links, multi-calendar assignments and target-host acceptance remain separate work. Real webmail, merchant/provider operations and SSH remain incomplete. No live customer/provider operation or hosting deployment occurred. Apache fixtures do not certify a particular production host; interruption tests cover durable boundaries, not arbitrary hardware failure.

Suite counts: `{"activity-native": 64, "apache": 487, "appointments-native": 83, "attempts-native": 69, "build-guards": 19, "catalog-native": 49, "delivery-archive": 55, "delivery-native": 57, "estimator-adapter": 3084, "http": 923, "ledger-native": 69, "mail-accounts-native": 68, "mail-templates-native": 59, "native": 65, "notifications-native": 62, "payments-native": 62, "reviews-native": 65, "staff-native": 31, "structure": 129, "support-native": 35}`.

## Published verification

All 16 [release assets](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.15.0) were freshly downloaded after publication and match prepared bytes. Checksums, archive members/paths/directories and source/tag agree. Prior v1.14.0 asset IDs, sizes, digests and modification timestamps remain unchanged.

| Package | Members | SHA-256 |
| --- | ---: | --- |
| FULL-PROJECT | 497 | `4e93c549daca3a7cfedd42fde0325f1cb96873a5fc3e9a02e83e5550c73433da` |
| COMPLETE-UPLOAD | 270 | `c85379eee996a7d9dcbc012a0595dd7b6195889e6bde17ff90a855b15b6ccde5` |
| UPDATE | 257 | `d58f91e719a94a1f4bad514f05dc4c226bb94a649b129c01fc75da53ff17e42b` |
