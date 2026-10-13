# CajunWebPro 1.16.0 — exact-main acceptance

Source/tag: `372ea28bf0d3081180c836e2c90aa14b7a90bdd3` / `v1.16.0`. [Merged PR #67](https://github.com/afterburn25/Cajun_Web/pull/67). [Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38466626819). All packages use the same clean tested commit. Windows and Linux artifact bytes agree; fresh public verification is recorded after publication.

## Delivered scope

Owners explicitly check saved incoming or outgoing server settings with their current password, exact saved revision and active matching mailbox assignment. Even owners need Read or Send assignment. Checks resolve public destinations, pin the numeric connection, verify original-hostname TLS, authenticate and disconnect without reading or sending messages. IMAP supports 993/TLS or 143/STARTTLS with AUTHENTICATE PLAIN; SMTP supports 465/TLS or 587/STARTTLS with AUTH PLAIN or LOGIN. OAuth and other ports remain unsupported.

Two additive tables separate auditable started checks from immutable terminal results. Intent and strict audit commit before DNS; DNS occurs outside the staff mutex; fresh authority/password/revision are checked afterward under that mutex. Credential decryption occurs only after this recheck. Every credential payload and the final result repeat authority checks. No InnoDB transaction spans network work. Revocation/account edits serialize with the bounded exchange. A failed final audit or interrupted request leaves an honest incomplete attempt. Old saved versions are labeled historical. Cooldown is 30 seconds per account/target and 20 starts per owner per hour.

## Results

- 948 native checks, including 55 connection checks; 3084 estimator comparisons/boundaries; 948 HTTP/Chromium; 143 filesystem/recovery; 541 Apache; 19 builder checks. Named evidence and actual screenshots accompany the release.
- Independent transport checks on **each** PHP 8.2 and 8.4 runner: 104 destination-policy, 13 DNS-resolver and 313 actual isolated TLS/protocol checks. These counts are separate from the application total. Ephemeral CA and synthetic credentials exercise both encryption modes, certificate/hostname failures, original SNI and production socket options, SMTP LOGIN, revocation between credential payloads, buffered STARTTLS injection, EOF, oversized/aggregate/flood/literal refusal and elapsed budgets. No real mailbox is contacted.
- Native acceptance covers additive schema refusal/idempotence, owner/current password/global role/session and per-account assignment boundaries, disabled/future/revoked identities, credentials, strict intent/final audit rollback, honest incomplete history, rate limits and separate processes competing for checks or editing/revoking while DNS waits. Database transport is explicitly injected; real socket evidence is the separate TLS suite.
- HTTP/Chromium acceptance covers separate controls, CSRF, wrong password and stale settings, revoked assignment, finite failures and blank passwords, historical labels, mobile fit and internal-path denial. Failed/interrupted display rows are explicitly synthetic and do not claim a provider login. Actual connection-check desktop/mobile screens were inspected before publication.
- Actual immutable 1.15.0 is installed through Apache, updated through the owner workflow, interrupted/recovered/reapplied with original private key, mail accounts/assignments, templates, reviews and nonempty appointment history retained. Added nonempty synthetic failed-check history survives file recovery/reapplication. Original key still decrypts saved credentials. All 18 supported starting packages remain covered.
- Homepage desktop/mobile pixels match verified 1.15.0. Retained admin/client workspaces are compared and inspected; dynamic fixture text, dates and version labels may differ. Clean Windows/Linux package bytes agree; archives, per-file hashes and manifests pass.

## Installation, updates and limits

FULL-PROJECT is sanitized development source. COMPLETE-UPLOAD extracts organized production files directly for fresh setup. UPDATE stages cwp-update-1.16.0 for installed 1.0.8 or 1.1.0 through 1.15.0 sites. Back up website, database and private storage, including mail-master.key, together. Read START-HERE and MIGRATION-INSTRUCTIONS; never fresh-install over an existing site or manually copy staged payloads. File recovery preserves additive database records and is not a database downgrade.

Fresh setup creates empty check storage. Upgraded owners select a saved account in Email accounts and use Prepare connection checks, then ensure they hold its matching Read/Send assignment before choosing a check. Save changes first: unsaved editor values are never tested. Results describe only the recorded time and version. No final result means interrupted/unrecorded; historical results do not certify changed settings.

Host outbound policy, OpenSSL/system trust store and DNS availability still require target-host acceptance; DNS uses the operating-system timeout and cannot be interrupted by the application budget. Transport after DNS has a 15-second overall budget, bounded reads/writes and verified TLS 1.2/1.3. Some public special-purpose IP ranges are deliberately rejected. No alternate-CA/private-destination switch is available. Authentication success does not establish sending permission, delivery, mailbox synchronization or ongoing availability. Website system-email settings are unchanged. Inbox/rendering/compose, OAuth and mail notification bells remain future work. No real mailbox/message, payment/provider/SSH activation or hosting deployment occurred.

Suite counts: `{"activity-native": 64, "apache": 541, "appointments-native": 83, "attempts-native": 69, "build-guards": 19, "catalog-native": 49, "delivery-archive": 55, "delivery-native": 57, "estimator-adapter": 3084, "http": 948, "ledger-native": 69, "mail-accounts-native": 68, "mail-connections-native": 55, "mail-templates-native": 59, "native": 65, "notifications-native": 62, "payments-native": 62, "reviews-native": 65, "staff-native": 31, "structure": 143, "support-native": 35}`.

Execution metadata: GitHub returned successful jobs with empty step arrays and intermittent API errors. Exact-source job conclusions, actual command execution logs (including final shipping-source diff check), and every named artifact result were independently verified; no missing step status was synthesized. The downloaded evidence preserves this limitation.

## Published verification

All 16 [release assets](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.16.0) were freshly downloaded after publication and match prepared bytes. Checksums, archive members/paths/directories and source/tag agree. Prior v1.15.0 asset IDs, sizes, digests and modification timestamps remain unchanged.

| Package | Members | SHA-256 |
| --- | ---: | --- |
| FULL-PROJECT | 524 | `e9abeca05ea535259c66a7de0ffd7a51d8323ed6e00209fe94370cbf7ab854af` |
| COMPLETE-UPLOAD | 280 | `dc9567005cb94bc65015d0980410d27cca1971db912662dfcd117b037267539f` |
| UPDATE | 267 | `87fefd02fae97bd3e70e60ae9a7cbcb3db2742367e219226b9b76ca7d95a0b9e` |
