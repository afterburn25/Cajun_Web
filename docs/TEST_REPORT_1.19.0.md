# CajunWebPro 1.19.0 — exact-main acceptance

Source/tag: `22a2969692ab5bcc7f35158dcaa5355c623dc821` / `v1.19.0`. [Merged PR #72](https://github.com/afterburn25/Cajun_Web/pull/72). [Exact-main application acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38504271899). Packages use this clean tested commit and match Windows/Linux bytes. Draft/public download verification is recorded separately.

## Delivered scope

Administrators with current global Read permission and an exact mailbox Read assignment can view recent email notices. Successful explicit inbox checks atomically replace the newest-20 observation window. Exact message identities survive refresh while present; removed messages, changed mailbox epochs and changed saved settings cannot silently reuse an old notice. Failed or incomplete reads preserve the prior window. Opening and dismissal are local to each administrator; server read flags remain unchanged.

The admin and public headers show an authorized email bell backed by private local metadata. Anonymous responses expose no count or mailbox identity. Polling preserves idle expiry, clears stale display on page lifecycle changes, and performs no mailbox synchronization. Client-support headers suppress the bell. The notice page labels the last check and bounded count honestly; subjects, senders and bodies are not stored in notice records. Open message requires an explicit POST with CSRF, opaque handle and independent full identity, plus current authority before credentials and display.

Three additive tables retain observations and local state. Owners prepare them with their current password; fresh installation creates empty ready storage. Strict result audit and storage changes commit together. Existing maintained receiving dependencies and limits remain unchanged. No customer-side Composer or background server is required.

## Verified results

- 1153 native checks, including 79 notice checks; 3084 estimator comparisons; 1087 HTTP/Chromium; 205 filesystem/recovery; 779 Apache; 20 builder guards. Every named result passes.
- Each PHP 8.2/8.4 notice runner passes 79 checks covering current permissions, account generations, local state, actual reader hooks with injected transport, strict audit rollback and separate-process races.
- Each PHP 8.2/8.4 receiving runner passes 1227 parser/session/actual TLS/no-DOM checks, plus fresh reproduction of all 40 locked dependency packages. Diagnostic transport counts per runner: `{"policy": 104, "resolver": 13, "tls": 313}`.
- Packaged HTTP/Chromium exercises explicit synthetic TLS inbox/message flows, two staff members, changed/revoked/expired grants, stale exact-message forms, CSRF, anonymous/client/support isolation, keyboard navigation and count lifecycle. Actual 320px mail-only and owner two-bell screens are inspected. Delayed initial responses are accepted without ordinary-pageshow cancellation.
- Twenty-one supported starting packages: 1.0.8 and 1.1.0 through 1.18.0, including 1.11.1/1.11.2. Actual immutable 1.18.0 installation/update/recovery/reapplication retains earlier history, original private mail key, nonempty notice windows/items and per-staff dismissal. Changing saved account settings hides the old generation without deleting retained records.
- Homepage desktop/mobile pixels match verified 1.18.0. Package members, directory inventories, checksums, internal-path denial and Windows/Linux equality pass. Windows source reads now use exact Git blobs, including long dependency paths.

## Owner procedure and remaining work

Choose FULL-PROJECT for development, COMPLETE-UPLOAD for fresh installation, or UPDATE for a supported existing site. Back up website, database, private key and storage together. UPDATE stages cwp-update-1.19.0; use its authenticated owner procedure. File recovery preserves additive database records and is not a database downgrade.

On upgrades, open Email accounts, prepare inbox/message reading if needed, and select Prepare email notices with the owner's current password. Assign the account's Read access, then explicitly load recent inbox headers. The bell opens Recent email notices; Open message reads the exact observation and Dismiss for me changes only that administrator's count. Even owners need mailbox assignments.

This is a last-checked newest-20 view, not full unread synchronization. Sender/subject previews, automatic checks, provider read-flag writes, attachments, compose/send, OAuth and other folders remain future work. Real mailbox compatibility and target-host deployment remain unverified. No real customer mail, live deployment, provider write, charge or SSH session occurred.

All application counts: `{"activity-native": 64, "apache": 779, "appointments-native": 83, "attempts-native": 69, "build-guards": 20, "catalog-native": 49, "delivery-archive": 55, "delivery-native": 57, "estimator-adapter": 3084, "http": 1087, "ledger-native": 69, "mail-accounts-native": 68, "mail-connections-native": 55, "mail-inbox-native": 57, "mail-message-native": 69, "mail-notices-native": 79, "mail-templates-native": 59, "native": 65, "notifications-native": 62, "payments-native": 62, "reviews-native": 65, "staff-native": 31, "structure": 205, "support-native": 35}`.

All16 public assets were freshly downloaded and matched prepared bytes; checksums, archive members and tag passed. Prior1.18.0 fingerprints remain unchanged. [Release](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.19.0).
