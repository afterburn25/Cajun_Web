# CajunWebPro 1.12.0 — exact-main acceptance

Source/tag: `c526e960db13246c217f2b9d6f421c17b8d48261` / `v1.12.0`. [Merged PR #58](https://github.com/afterburn25/Cajun_Web/pull/58). [Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38441087438). All three packages come from the same clean tested commit. Windows rebuild and Linux CI artifacts agree; fresh public download verification is recorded after publication.

## Delivered scope

The administrator email-template library now provides personal/shared drafts, create/edit/duplicate/activate/archive, escaped previews with manual sample values and paginated immutable version history. Explicit mail.templates.manage permission gives no mailbox or sending access. Personal templates remain private even from other owners; shared edits require their creator or an owner. Client and support identities are excluded. No client data is loaded for sample previews, and no messages are sent.

The canonical Devin model is reused after review corrections: single-pass substitutions keep merge values literal, malformed double-brace fields are rejected and values must be strings. The adapter adds bounded UTF-8 text, single-line subjects, current actor checks, strict audit/history transactions and stale-write rejection. Existing installations enable additive storage with owner reauthentication; fresh setup creates an empty library. No existing business table is replaced.

## Results

- 677 native database/security checks, including 59 new template checks; 3084 retained estimator comparisons/boundaries; 834 HTTP/Chromium checks; 87 filesystem/interruption/recovery checks; 327 Apache checks; 17 negative builder checks. Named results and actual screenshots are included in TEST-EVIDENCE.
- Native template tests cover incomplete/incompatible migration, refusing populated unrecognized state, owner password/role requirements, personal/shared access, current permissions/sessions, client/support exclusion, canonical lifecycle, exact retained snapshots, audit-failure rollback, literal samples, input limits, stale edits and simultaneous writers. Library/history pagination is checked with distinct retained versions.
- Real HTTP and Chromium exercise setup, create, duplicate, preview, invalid-draft preservation, stale form revisions, activate/archive, shared restrictions, personal confidentiality, escaped HTML, missing fields, permission removal and direct internal access denial. Desktop/mobile template screens are inspected; the mobile page fits the viewport and browser errors are checked.
- The actual published 1.11.2 COMPLETE package is installed under real Apache, seeded with retained business records, interrupted during update and resumed through the owner page. Additive template setup and saved history work after update. File recovery and reapplication preserve new template data and existing business rows. Rewriting is tested enabled and absent; opcode timestamp validation is disabled so cache invalidation is exercised.
- All fourteen supported starting packages retain acceptance: 1.0.8 and 1.1.0 through 1.11.2. Organized 1.11.0/1.11.1 and earlier old-layout migration cases retain private/configuration/scaffold bytes, shared locking, prior recovery journals, sessions and legacy POST/callback behavior. Fresh organized installs receive no legacy root endpoints.
- Public forms/estimator/portfolio, staff roles/support sessions, client messages/approvals/invoices, sandbox-only workflows, private resumable ZIP delivery and activity bells retain acceptance. Fresh setup remains one page with inline validation, exact branding, canonical version/inventory and setup cleanup.
- Desktop/mobile homepage screenshots are pixel-identical to verified 1.11.2. Administrator navigation intentionally gains Email templates; dimensions/differences are recorded alongside actual template screenshots. Original image bytes retain their acceptance checks.
- Clean-source deterministic packages, Windows/Linux byte agreement, installer generated-code consistency, syntax, unsafe-member/runtime/secret/root-module/scaffold rejection and independent PHP 8.2/8.4 component checks pass.

## Operation and limits

FULL-PROJECT includes sanitized site/docs/tests/tools/brand/foundation/CI source for development. COMPLETE-UPLOAD extracts organized production files directly into the intended website directory. UPDATE creates cwp-update-1.12.0 staging; use its authenticated owner page after a recoverable website/database/private-storage backup. Never copy the payload over the live site manually or run fresh setup on an existing installation. See START-HERE and MIGRATION-INSTRUCTIONS.

Full A-07 compose insertion/sending, A-06 account/inbox setup and A-08 email bells remain incomplete. No live host deployment, real mail delivery, merchant operation, 20i/VPS provisioning or SSH connection is claimed. Provider/gateway observations remain synthetic or disabled under the existing external gates. Apache acceptance does not certify a particular LiteSpeed/nginx/IIS host. Interruption acceptance tests durable operation boundaries, not arbitrary hardware failures. Full roadmap features beyond this bounded increment remain pending.

Suite counts: `{"activity-native": 64, "apache": 327, "attempts-native": 69, "build-guards": 17, "catalog-native": 49, "delivery-archive": 55, "delivery-native": 57, "estimator-adapter": 3084, "http": 834, "ledger-native": 69, "mail-templates-native": 59, "native": 65, "notifications-native": 62, "payments-native": 62, "staff-native": 31, "structure": 87, "support-native": 35}`.

## Published verification

All 16 [release assets](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.12.0) were freshly downloaded after publication and match prepared bytes. Checksums, archive members/paths/directories and source/tag agree. Prior v1.11.2 asset IDs, sizes, digests and modification timestamps remain unchanged.

| Package | Members | SHA-256 |
| --- | ---: | --- |
| FULL-PROJECT | 417 | `cb36b2484e28abaac331eb9cd195f411751da9e3864a78f47b7f23ccce6908d0` |
| COMPLETE-UPLOAD | 232 | `fd9dcb1bab34588588384c5eb99af5b06f67ec7a1ad1af7da86aa0532e6c453e` |
| UPDATE | 219 | `37ede535d020bb78284dd56f53540f9a9ead37058a5e4e866af41583fe57a4b9` |
