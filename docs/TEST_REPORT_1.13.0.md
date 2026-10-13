# CajunWebPro 1.13.0 — exact-main acceptance

Source/tag: `b83cb9fb6856bca4f333fe953bee2e68f5074d51` / `v1.13.0`. [Merged PR #63](https://github.com/afterburn25/Cajun_Web/pull/63). [Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38446948573). All packages use the same clean tested commit. Windows and Linux artifact bytes agree; fresh public download verification is recorded after publication.

## Delivered scope

Owners can prepare multiple incoming IMAP/outgoing SMTP profiles, save protected passwords, disable/enable availability, clear passwords, assign read/send capabilities to individual administrators and inspect retained metadata/assignment history. Scoped staff listings show currently authorized account labels/addresses only. Even owners need an explicit mailbox assignment. Ordinary staff also need the matching global read/send permission. Templates and existing website SMTP settings remain separate.

This is account preparation, not a working inbox or sending service. Connection state remains untested. No network connection, message download, synchronization or sending occurs in this module.

## Results

- 745 native checks, including 68 email-account database/vault/security checks; 3084 estimator comparisons/boundaries; 862 HTTP/Chromium; 101 filesystem/recovery; 381 Apache; 19 package-builder checks. Named results, workflow logs and actual screenshots are included in TEST-EVIDENCE.
- Native account checks cover additive repeated migration, refusing incompatible keys/collations and populated unknown tables, current owner password, bounded metadata, TLS choices, separate encrypted passwords, preserving password whitespace, wrong-account binding, tampering, missing/wrong keys, unsafe key permissions and symlinks. Password/ciphertext data stays out of rendered metadata, history and audit details.
- Permission tests cover owner mailbox assignment, independent read/send capabilities, global permissions, foreign-account isolation, template-only staff, removed permissions, future/expired/revoked assignments, disabled users/profiles, stale sessions and client/support identities. Mutation/history/audit transactions roll back together; simultaneous writers yield one success and one stale rejection. Metadata history is paginated.
- HTTP and Chromium cover actual setup, CSRF and password errors, account creation, unreflected credentials, escaped labels, preserved invalid settings and stale revisions, private assigned listings, forged staff mutations, immediate disabling/revocation, explicit password clearing, internal HTTP denial and mobile fit. Actual desktop/mobile account screens are reviewed.
- The actual immutable 1.12.0 package is installed under Apache, seeded with existing business rows and a template/history, updated and recovered through the authenticated owner workflow. New protected account/assignment/history rows and the original external private key survive file recovery and reapplication; saved passwords can still be opened after recovery. Earlier template and business records remain preserved.
- All fifteen recognized starting packages remain covered: 1.0.8 and 1.1.0 through 1.12.0. Earlier flat/organized layout migration, retained routes, private files/scaffolds, shared update locking, retry/interruption and opcode cache invalidation cases are retained. Rewriting is tested present and absent.
- Existing public forms/estimator/portfolio, staff/support permissions, client approvals/messages/invoices, sandbox-only payment workflows, private ZIP delivery and activity bells retain acceptance. Fresh installation remains one page and prepares empty account tables without credential keys until needed.
- Canonical webmail model corrections pass 64 isolated no-extension checks and independent PHP 8.2/8.4 CI: future-issued assignments, UIDVALIDITY identity, duplicate rejection and unambiguous tuple keys. The existing access policy remains authoritative. No transport acceptance is inferred from model tests.
- Installer dependency ordering now explicitly uses repository-relative POSIX strings so mixed-case class names produce identical Windows/Linux inventories. Source/inventory/ZIP members, secret/runtime rejection and actual package byte comparisons pass. The mail master key is rejected from shipping code directories.
- Desktop/mobile homepage screenshots are pixel-identical to 1.12.0; administration intentionally gains Email accounts and My email accounts. Workspace and new account screens are inspected; comparison details are in evidence.

## Use and limitations

FULL-PROJECT is sanitized development source. COMPLETE-UPLOAD contains the organized website for fresh setup. UPDATE creates cwp-update-1.13.0 staging for the existing-site authenticated owner workflow. Back up website files, database and all configured private storage first. Read START-HERE and MIGRATION-INSTRUCTIONS; never copy payload files manually or fresh-install over an existing site. Keep the database and private mail-master.key together for credential recovery.

Existing sites enable Administrators first if needed, then additive Email accounts storage. Fresh setup prepares empty tables. Password storage requires OpenSSL and verified private storage outside the served root with owner-only key permissions. A saved profile does not certify DNS, TLS, authentication or provider compatibility. Real IMAP/SMTP transport, safe message rendering, attachments, compose/template insertion, sending and email bells remain future work requiring separate acceptance. No live hosting deployment, customer mail, real merchant operation, 20i/VPS provisioning or SSH connection occurred. Apache fixtures do not certify a particular production host; interruption tests cover durable operation boundaries, not arbitrary hardware failure.

Suite counts: `{"activity-native": 64, "apache": 381, "attempts-native": 69, "build-guards": 19, "catalog-native": 49, "delivery-archive": 55, "delivery-native": 57, "estimator-adapter": 3084, "http": 862, "ledger-native": 69, "mail-accounts-native": 68, "mail-templates-native": 59, "native": 65, "notifications-native": 62, "payments-native": 62, "staff-native": 31, "structure": 101, "support-native": 35}`.

## Published verification

All 16 [release assets](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.13.0) were freshly downloaded after publication and match prepared bytes. Checksums, archive members/paths/directories and source/tag agree. Prior v1.12.0 asset IDs, sizes, digests and modification timestamps remain unchanged.

| Package | Members | SHA-256 |
| --- | ---: | --- |
| FULL-PROJECT | 441 | `ef1d59a7835b12be42884f78c6a2bb795a02ba81d3efb8428550390691b1e1f2` |
| COMPLETE-UPLOAD | 243 | `e2a24d324901ed1f1c43ecb3e3a72a8b6ffd2ff566b8418a2493c18700f1c6ee` |
| UPDATE | 230 | `6017b4bf1f00fd2271b980ba3541fc99772b2542cef01a025e5199ad7b3c4055` |
