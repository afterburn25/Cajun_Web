# Changelog

## Unreleased — exact-byte installer inventory correction

Regenerate owner UI dependency hashes from the committed LF source bytes after CI rejected Windows-normalized development hashes. No application behavior change.

## Unreleased — owner 20i connection screen

Wire protected owner settings, explicit fixed connection checks, finite/history displays and fresh/additive setup. Add actual packaged HTTP/Chromium against isolated HTTPS. Combined application acceptance and release remain pending.

## Unreleased — audited explicit provider-check preparation

Add unwired current-owner fixed-check orchestration, finite retained outcomes, request limits and interrupted-result handling; reuse canonical provider mirrors and add native process fixtures. Application integration remains pending.

## Unreleased — owner provider storage preparation

Add unwired owner-only encrypted 20i profile and retained metadata history, exact additive migration, native fixtures/workflow and provider-key package exclusion. Native acceptance and application integration remain pending.

## Unreleased — isolated transport runner repair

Create writable output mountpoints before mounting the source read-only. This fixes a pre-test Docker startup failure; no application or transport behavior changes.

## Unreleased — isolated 20i HTTPS transport

- Fixed destination/pinning/TLS and proxy/redirect refusal, finite errors, bounded streamed headers/body and transfer deadlines, with 333 local actual synthetic HTTPS checks and request/DNS policy evidence. No admin application integration or real API operation; matrix/review pending.

## Unreleased — isolated fixed 20i connection probe

- Added a fixed read-only package-list authentication adapter, finite classifications and explicit refusal of all general provider operations, with 120 synthetic checks. No real transport or application integration; no release version reserved.

## Unreleased provider contract review

- Recorded the 163-check canonical review and reproduced JSON shape defect; narrowed first integration to protected settings and explicit read-only checks. Inventory needs separately verified response fields and ownership acceptance. Documentation only; no feature or release claimed.

## Unreleased — provider settings contract preparation

Claim an isolated owner20i settings/read-only check contract after the1.19 email increment. Documentation only; no API call, application feature, release or deployment.

## Unreleased — HC-07 provider foundation (Devin, isolated component)

- Finite probe classification per the [owner 20i settings contract](https://github.com/afterburn25/Cajun_Web/blob/codex/provider-settings/docs/PROVIDER_SETTINGS_CONTRACT.md): `ConnectionCheck` gains an `unknown` state for post-send ambiguity (timeout/protocol/lost result/unaccepted 2xx shape) — never reported as a pass or a clean failure; `unreachable` is reserved for faults demonstrably before send. Check details are bounded printable classifications (≤200 bytes, no control characters), and `probe()`/`HttpTransport` docblocks now state the pinned-endpoint, strict-shape, no-redirect/proxy, DNS-validation and byte-cap invariants adapters and transports must honor. Codex review follow-up: the fixture probe decodes with JSON object/list identity preserved — objects (incl. `{}`/`{"0":…}`), scalars, null, malformed/trailing and over-depth bodies classify `unknown`, never `verified`; validation wording says bytes, not chars. All 171 checks pass locally on PHP 8.4 with/without php.ini. No shipping integration.

- Codex contribution from reviewed provider head 1e42402: reject failed JSON map-key encoding before operation claim, with nested malformed UTF-8, zero side-effect and valid Unicode/replay regressions. All 150 checks pass locally on PHP 8.4 with/without php.ini; matrix and review pending. No shipping integration.

- Added canonical `foundation/provider/` contract classes (adapter interface + capability matrix, lazy secret-resolving credentials, HTTPS-only transport boundary, operation/idempotency-key model, durable ledger interface, enforcing gateway, and external-resource reconciliation) plus `docs/PROVIDER_FOUNDATION.md`.
- Corrected the ledger/execution contract per the HC-07 correction pass: `OperationLedger::claim()` atomically reserves an idempotency key bound to a canonical provider/account/operation/params fingerprint before any remote call (new `LedgerClaim` states `claimed`/`replay`/`in_progress`/`uncertain`/`conflict`); `record()` finalizes under the claim. Non-idempotent operations never auto-retry — ambiguous outcomes (transport fault or 429/5xx after the request may have been sent) record `uncertain` for reconciliation. `TransportException` carries only a finite failure kind; `OperationResult` enforces bounded printable messages; `masked()` fully masks secrets of 8 characters or fewer; timeouts/backoff reject `NAN`/`±INF`.
- Added `tests/provider/run.php` (124 synthetic checks) plus `FileLedger.php`/`file_ledger_worker.php` (a real four-process `flock` claim race — beyond scripted interleaving) and the `provider.yml` GitHub workflow (PHP 8.2/8.4, no network).
- No `site/` changes, schema, persistence, admin UI or real provider calls; HC-07 external gate remains `Awaiting API verification`. Not yet integrated or released. Exactly-once remote execution is not claimed — crash-after-write resolves to `uncertain`, not silent re-execution.

## 1.11.1 — candidate, motion preferences and organized updates

## 1.11.2 — candidate, canonical live estimator integration

## 1.12.0 — candidate, administrator email-template library
## 1.13.0 — candidate, email account preparation
## 1.14.0 — candidate, human website review workflow
## 1.15.0 — candidate, consultation scheduling
## Unreleased — 1.16.0 candidate: explicit email connection checks
## 1.17.0 candidate — bounded recent inbox headers (unreleased)
## 1.18.0 candidate — exact-message reading (unreleased)

- Acceptance correction: actual Chromium diagnostics prove injected image attempts are blocked by CSP before network handling; the assertion now distinguishes blocked events from network requests and still requires both explicit CSP failures. Stable mobile viewport captures include plaintext and formatted views. Shipping behavior is unchanged; complete release acceptance remains pending.

- Align the actual TLS wrong-UID browser fixture with the established unavailable-message classification; malformed MIME remains a protocol failure and neither exposes partial content.
- Update the existing browser availability assertion for the new message-reading feature while preserving connection, attachment and sending limitations; all 69 new native message tests pass, corrected browser acceptance pending.
- Independent review of 21e00c0 reproduced the components and reviewed application access/rendering boundaries with no blocking finding. Candidate native database checks pass; complete browser/update/recovery/Apache evidence remains pending. Correct staged owner/recovery instructions to candidate 1.18.0.
- Add explicit assigned-staff message opening with current account/actor/revision and exact INBOX/UIDVALIDITY/UID checks, bounded MIME content and unchanged server read flags. Separate strict audit/results, request limits, owner-prepared upgrade storage and empty fresh-install storage retain no message contents.
- Decode raw MIME through private maintained parser state, preserve escaped plaintext and show minimal sanitized formatting in an opaque sandbox with restrictive CSP. No automatic remote resources, attachment content or sending. Missing DOM has an honest plaintext/unavailable-format fallback.
- Preserve all 36 existing dependency pins and add four maintained formatting dependencies. Correct PHP 8.2 full-document handling by stripping html/body wrappers while preserving sanitized children; all 1,227 components pass on PHP 8.2/8.4 at 740b4e2 with fresh byte reproduction.
- Add actual 1.17.0 update/recovery and original key/history preservation fixtures, alongside native/concurrency, packaged TLS/HTTP/Chromium and Apache checks. Application acceptance, independent review and release remain pending; latest published release remains 1.17.0.

## 1.19.0 — verified recent email notices

- Explicit successful inbox reads populate a newest-20 observation window with exact mailbox generation/UID identity; strict result/audit failures roll back notice changes. Failed reads retain the prior window.
- Current-authorized notice page, per-staff opened/dismissed state, independent handle/full-tuple POST opening, and private admin/public bells. Passive polling never fetches mail or renews idle login; client-support pages suppress staff email notices. Server read flags remain unchanged.
- Additive owner preparation and empty fresh storage; accurate last-check/failed/incomplete status without retaining sender, subject, body or credentials in notices. Existing read transport and message limits remain unchanged.
- Corrected ordinary-pageshow cancellation, actual support-header isolation, test clock/capture ordering, and Windows exact Git-blob packaging. Source documentation validation excludes generated evidence folders while still verifying all canonical dependencies.
- Published/download-verified `22a2969692ab5bcc7f35158dcaa5355c623dc821` after exact-main 38504271899: 1153 native, 3084 estimator, 1087 HTTP/Chromium, 205 recovery, 779 Apache and 20 builder checks. All16 assets/tag, archive members and Windows/Linux bytes verified; actual mobile/desktop screens reviewed.
- Twenty-one starting packages supported, with nonempty1.18.0 update/recovery/reapplication retaining notice state, earlier records and the original private key. Prior releases unchanged. Automatic/full synchronization, previews, other folders, attachments, sending/OAuth and server flag changes remain future work. No production operation.

## Unreleased — recent email notice storage preparation

- Corrected the native CI container UID/GID after both database stages passed but artifact upload failed on a root-owned private QA directory. Corrected saved-evidence acceptance is pending; application/test behavior is unchanged.
- Added a native migration fixture that removes full-tuple uniqueness while preserving the supporting foreign-key index; this tests application refusal rather than InnoDB index protection. Database acceptance remains pending.
- Added bounded current-window metadata, separate per-person opened/dismissed markers, exact current mailbox permission filtering, collision refusal and independent full-tuple resolution.
- Added explicit owner migration with column/key/relationship compatibility checks and strict audit; observation helpers require the successful terminal-result transaction. No schema/reader/HTTP integration or bell UI is wired yet.
- Local PHP syntax passes. Native database fixtures and PHP 8.2/8.4 workflow are added; database and full application acceptance remain pending. Published/download-verified 1.18.0 remains immutable; its publication documentation is merged into this preparation branch.


## 1.18.0 — verified individual email reading

- Assigned staff explicitly open an exact INBOX message under current account/actor/global and mailbox permissions, saved revision, UIDVALIDITY/UID and size checks. Verified TLS and BODY.PEEK preserve server read flags.
- Private maintained MIME parsing, escaped plaintext and minimal maintained sanitized formatting in an opaque CSP-restricted frame. Whole messages over 256 KiB (including encoded attachments) are refused; no partial display or content retention. Missing DOM preserves plaintext with an honest format limitation.
- Additive owner-prepared request storage, strict started/terminal audit and outcome history, independent account/staff limits and current authorization through transport/rendering. Fresh installs create empty storage.
- Fixed PHP 8.2 document-wrapper sanitization and acceptance assertions without editing upstream libraries or weakening application protections. Actual browser evidence distinguishes CSP-blocked request events from network work. Desktop/mobile plaintext/formatted views were inspected; independent review found no blocking application issue.
- Published/download-verified `91550d956c404585545dbd766c96f36f58ae0623` after exact-main 38492678532: 1074 native, 3084 estimator, 1032 HTTP/Chromium, 191 recovery, 701 Apache and 19 builder checks. Each PHP 8.2/8.4 receiving runner passes 1,227 checks and fresh dependency reproduction; all 16 assets/tag and Windows/Linux bytes verified.
- Twenty starting packages supported; actual 1.17.0 upgrade/recovery preserves prior records/private key and new message history. Prior assets unchanged. Attachments, sending, full sync, other folders/OAuth and email bells remain future work. No live mailbox, deployment, provider write, charge or SSH activation.

## 1.17.0 — verified recent inbox headers

- Preserve early browser evidence so failures can be investigated while the complete recovery matrix continues.
- Route inbox authorization through the current account-scoped permission and assignment checks; the generic staff gate has no mailbox identity.
- Correct updater path validation for exact maintained locale filenames containing @; preserve traversal, control-character and symlink rejection.
- Add explicit assigned-staff INBOX header loading over verified TLS, using maintained pinned IMAP/MIME dependencies bundled for PHP shared hosting. Preserve account/folder/UIDVALIDITY/UID identity and existing read flags; no body, attachment, compose/send or background polling.
- Enforce current session, global permission, mailbox assignment and saved revision before credentials and display. Limit refreshes to one per account per 15 seconds and 120 per staff hour. Commit started and terminal audits separately; retain only outcome/count metadata and personal request history, never header contents.
- Add owner-prepared additive request storage, fresh empty install schema, byte-verified protected runtime mirror, and actual 1.16.0 update/recovery coverage. Published and downloaded/verified from `fc3bd89c5e1c286f9dc4df12d50dd566a4d4ab86` after exact-main 38480568403; all 16 assets/tag pass. PHP 8.2/8.4 each pass 548 receiving checks. No real mailbox or deployment.

## 1.16.0 — verified explicit email connection checks

- Add separate owner-only checks of saved incoming/outgoing settings, current password and matching mailbox assignment. Verify public DNS destinations, pin the numeric connection, validate TLS and authenticate without reading or sending messages.
- Add auditable started/terminal history, saved-version labels, bounded response handling and per-account/owner repeat limits. Interrupted or unaudited final outcomes remain incomplete.
- Add additive upgrade setup, fresh empty schema, isolated TLS/DNS/database/HTTP acceptance and actual 1.15.0 update/recovery coverage. Published and downloaded/verified from `372ea28bf0d3081180c836e2c90aa14b7a90bdd3` after exact-main 38466626819; all 16 assets/tag pass. No real mailbox or deployment.

## 1.15.0 — verified consultation scheduling

- Preserve corrected Devin #32 scheduling ancestry and 71 isolated checks. Add one explicitly configured studio calendar, disabled by default, with timezone-aware offered slots, buffers, notice and a 30-day horizon.
- Capture pending public appointments atomically with inquiries; keep honest preference-only intake when scheduling is disabled. Use one locked database configuration row for every conflict-changing write; stale forms, current permissions, contact consent and strict audit protect staff decisions.
- Add explicit old-inquiry adoption, password-confirmed staff confirmation, cancellation/decline, rescheduling back to pending and retained revisions. Add public/admin forms and actual 1.14.0 update/recovery fixtures preserving earlier reviews/templates/email credentials and private key.
- Published/download-verified from `8749bc5a89e52f8f35a193cc49d306136576ec8e` after exact-main run 38460197599; all 16 assets/tag pass. Native/HTTP/Chromium/Apache/build acceptance passes. No provider calendar, outgoing confirmation/cancellation mail or live deployment.

## 1.14.0 — verified human website review workflow

- Preserve corrected Devin #31/#36 and adapt the canonical review lifecycle into the existing inquiry system. Accept bare website hosts deliberately; retain the 500-byte bound and reject fragments, userinfo and control characters. Capture new review identity atomically with accepted leads; older inquiry adoption is explicit.
- Add current website-staff authorization, owner-only additive setup, evidence-labeled findings, saved report revisions, stale/concurrent protection and strict audit. External delivery requires the exact saved draft, current consent, real entered time/reference and staff password; this records delivery outside the app and sends nothing.
- Add escaped desktop/mobile forms, draft finding add/remove controls, history and explicit inquiry deletion of dependent personal review content. Recognize actual 1.13.0 upgrade/recovery with earlier email key/assignment/template retention. Published/download-verified from `f55879339e38769b4ec5fa81780a3d650b63da25` after exact-main run 38453775600; all 16 assets/tag pass. Native/HTTP/Chromium/Apache/build acceptance passes; no live deployment occurred.

## 1.13.0 — verified email account preparation

- Preserve Devin #59 canonical profile/assignment/message models and isolated CI. Re-reviewed 64 no-extension component checks; future-issued grants, UIDVALIDITY, duplicate identities and tuple encoding are corrected. Combined application acceptance passes.
- Add owner-managed incoming/outgoing profiles, encrypted private credentials, immutable metadata/assignment history, strict audit and stale-write protection. Add separate staff read/send permissions and current per-mailbox assignments, including for owners.
- Add administrative settings and scoped assigned-account lists, additive owner setup, fresh schema preparation and actual 1.12.0 upgrade/key/data recovery fixtures. Published/download-verified from `b83cb9fb6856bca4f333fe953bee2e68f5074d51` after exact-main run 38446948573; all 16 assets/tag pass. No transport, mailbox contents, connection success, sending or deployment is implied.

## 1.12.0 — verified administrator email-template library

- Add explicit template permissions, personal/shared drafts, create/edit/duplicate/activate/archive and escaped sample previews. Reuse the corrected Devin #56 model with literal single-pass substitutions and strict field grammar.
- Retain immutable versions and strict actor audit with transaction rollback, row locking and stale-edit rejection. Add owner-authenticated, idempotent schema setup; fresh installs prepare an empty library automatically.
- Keep personal templates private even from other owners; shared modification requires creator/owner access. Exclude support/client identities. Preview never reads client records or sends mail.
- Recognize verified 1.11.2 update inventory, exercise actual latest upgrade/schema/file recovery and all prior supported baselines. Published/download-verified from `c526e960db13246c217f2b9d6f421c17b8d48261` after exact-main run 38441087438; all 16 assets/tag pass. Local and PHP 8.2/8.4 component checks and combined acceptance pass.
- Full webmail, compose insertion, real sending and transactional messages remain outside this bounded increment.

## Historical A-07 isolated component checkpoint — integrated in 1.12.0

- Added canonical `foundation/email-templates/` classes (`PlaceholderRegistry` allowlist with descriptions, `EmailTemplate` with structural placeholder allowlisting, draft→active→archived lifecycle, non-mutating edits, personal-duplicate, shared/personal visibility, transactional compose exclusion, and honest `preview()` that names unresolved placeholders) plus `docs/EMAIL_TEMPLATES_FOUNDATION.md`.
- Added `tests/email-templates/run.php` (50 synthetic checks; fixture templates only) and the `email-templates.yml` GitHub workflow (PHP 8.2/8.4, no network).
- No `site/` changes, compose UI, sending or persistence. Not yet integrated or released.

## 1.11.2 — verified canonical live estimator integration

- Preserve Devin's reviewed adapter contract and route existing calculator arithmetic through the canonical estimator, folding quantities with exact integer guards. Existing field validation, missing-price early returns, invalid-range errors, consultation exclusions/cap and displayed results remain compatible.
- Add direct-entry guards before mirroring the canonical estimator into protected includes; installer inventory verifies the new files.
- Compare actual application results with the immutable published 1.11.1 calculator and independent boundary examples. Retain server-recomputed HTTP results and saved-lead acceptance.
- Recognize verified 1.11.1 for staged owner updates; exercise both organized starting versions as well as retained old-layout upgrades and recovery records. No schema change or live external activation.
- Published/download-verified from `4fd0d5efdc9d94f1f5b29d76be857d944f5f8c36` after exact-main run 38433670555; all 16 assets/tag pass. Homepage pixels match 1.11.1. Prior releases remain immutable; no live deployment.

## 1.11.1 — motion preferences and verified organized updates

- Preserve and integrate Devin PR #34: manual pause also disables root smooth scrolling; restrained logo hover/focus sheen respects pause and reduced motion; remove unused hero-scene CSS without changing the layout or image bytes.
- Measure homepage lab layout shifts with a real buffered PerformanceObserver and session-window calculation; test saved pause/resume, device reduced motion and no-JavaScript readability. This is synthetic browser evidence, not a production performance guarantee.
- Recognize the verified 1.11.0 package for owner updates; retain known upgrade-only routes without adding them to fresh organized sites. Preserve private scaffolds, previous recovery records and all configuration/business data. Separate target-version journals share the original permanent update lock.
- Extend real Apache update/recovery acceptance with the actual retained 1.11.0 installer, database records, session continuity and opcode caching enabled without timestamp validation. Retain old-layout historical acceptance.
- Derive installer page/JavaScript/diagnostic build labels from its canonical build constant; restore organized installer logo/emblem paths. Browser setup now explicitly checks version and loaded brand images; the builder rejects stale literal image paths.
- Make package checksum ordering identical on Windows and Linux; use committed history and guide bytes in exact-source artifacts.
- Published/download-verified from `cefcc41bcd5297715f2a49d6526900ebe8ad4eb4` after exact-main run 38429347222. All 16 assets and tag pass. Prior releases remain immutable; no live deployment or new external payment/provider operations.

## 1.11.0 — organized application and verified three-package release

Published from `de70c5da2e8f7bd35fbb92b9b829d0ab3e880372` after exact-main acceptance; all 16 assets downloaded and verified. Original history and all earlier releases retained. No live deployment.

- Organized administration, portal, public templates, shared bootstrap/security, business services, database migrations, API/webhooks/media and setup source; retained existing visuals and exact image bytes.
- Canonical root/subfolder URLs, shared session identity, guarded internal paths and private upload staging within the configured store.
- Staged owner update with recognized-source verification, backup confirmation, serialized journaled replacement, known-hash retirement, direct legacy callbacks and recovery.
- Three clean-commit artifacts: sanitized FULL-PROJECT, organized COMPLETE-UPLOAD and independently exercised UPDATE; per-artifact inventories/checksums and START-HERE.
- Expanded application, historical upgrade, Apache and filesystem recovery acceptance. Publication depends on completed checks and exact-main verification.

## Historical priority request — fulfilled by 1.11.0

Owner override stops the portfolio-only 1.11.0 publication. Preserve integrated source and implement organized modules/assets, protected storage/upload scaffolding, bounded owner migration/recovery, and FULL-PROJECT/COMPLETE-UPLOAD/UPDATE artifacts. Latest published version remains 1.10.0; no corrected release claimed yet.

## 2026-10-13 — Dependency-aware roadmap and agent instructions (docs only)

- Added a mandatory owner-approved policy for Codex/Devin: unavailable third-party API/merchant/SSH verification blocks only the specific real integration or production operation, **not** safe independent development or unrelated features.
- Clarified HC-07 (Awaiting API verification), HC-09/HC-10 (Development pending — API verification), and A-10 (Architecture/security validation pending), tracking those gates separately from implementation/release/deployment status.
- Linked the rules from agent instructions, roadmap, provider/client roadmaps, coordination, status, handoff and decisions. Explicit production restrictions remain.
- No PHP/runtime feature, integration, live transaction, deployment or app version bump; previously released v1.10.0 stays unchanged.

## Historical 1.11.0 portfolio candidate — included in the corrected release

- Stabilize modified-click acceptance by observing the application decision before suppressing synthetic navigation; add an actual Ctrl-click/new-tab check. Shipping bytes are unchanged by this test-only correction.

- Preserve and integrate Devin's W-01 component and real-project adapter. Public category URLs, no-JavaScript navigation, enhanced history and result counts use published records with truthful labels.
- Correct numeric-string categories, the real category `all`, duplicate public slugs, normalized card values and modified-click handling. Identical media no longer presents a before/after comparison.
- Regenerate the 80-file complete / 79-file update package and versioned keyless installer. No schema changes. Retained 1.10.0 and full application acceptance are required before publication.
- Reviewed estimator integration remains outside deployment, with 65 exact-integer/result checks; existing live estimator is preserved.

## Unreleased — review-request foundation

- Add independent L-02 review-request contract (`foundation/review-requests/`): normalized website addresses with SSRF-readiness flags, contact consent, legal stage transitions, human-reviewed findings with labeled lab/field/absent evidence, and delivery guarded by a saved report plus consent. Not integrated; no schema, form, mail, or URL fetching.

## Unreleased — reviewed estimator foundation

- Integrate Devin PR #27 with preserved ancestry; retain the standalone component outside shipping files. Add exact-integer overflow handling and reject inconsistent direct result objects. Application version remains 1.10.0; adapter and UI acceptance remain pending.

## 1.10.0 — workspace activity

- Personal client and staff activity centers with explicit read/unread and bounded mark-visible actions.
- Unread bells, exact source links and paginated history with current ownership/role checks.
- Generic public mount point; private cookie-authorized count, no-store and active-page polling without extending idle sessions.
- Additive read-state schema, existing-source projections, failure recovery and retained 1.9.0 update coverage.
- Published/download-verified at `4649a913d40166b9874f9550e57b4186edfc43ab`; 603 native / 630 HTTP/Chromium checks pass. Full P-07, email dispatch and A-06–A-08 webmail/templates/mail bells remain incomplete.

## 1.9.0 — client website downloads

- Private staff uploads, immutable manifests and owner-reviewed publication of versioned client ZIPs.
- Client/project history, notes, package scope, SHA-256 checksums and authenticated resumable downloads; strict ownership and support attribution.
- Bounded archive inspection without extraction/execution; reject unsafe paths, links, credentials, encrypted/nested archives and databases. Outside-web-root guarded storage with project/site quotas.
- Additive setup and retained 1.8.0 upgrade coverage preserve the existing portal, payment ledger and credentials.
- Published/download-verified at `080f14aea8fe5c35e04e4eba62976609aef7cd67`; 539 native / 569 HTTP/Chromium checks pass. Automated packaging, database transfer, larger jobs and full P-06 remain incomplete.

## 1.8.0 — invoice payments and client receipts

- Record verified external USD payments in full or in parts; show total paid and remaining invoice balances. Replay, stale review, duplicate reference and overpayment guards share the existing financial lock.
- Owners correct mistakes with an audited full reversal while retaining original entries. No money is sent or refunded.
- Clients view owned statements and printable receipts; staff-only references remain private, and support receipt access is blocked and audited.
- Additive migration imports legacy paid balances without fabricating receipts. Partial payments/corrections retire earlier requests; new requests use the remaining amount.
- Published/download-verified at `c36a36e80c40485c9e3e7e6cdd4884ced25db961`; 427 native / 503 HTTP/Chromium checks pass. Card checkout, actual merchant acceptance and recurring/refund processing remain pending.

## 1.7.0 — sandbox notification inbox

- Owner-configured sandbox callback with raw-body HMAC verification, account/webhook binding, bounded payloads and replay protection. Durable minimal receipt and strict audit precede HTTP 200.
- Owner verification matches an existing attempt and checks current sandbox reporting; observation, processing state and audit commit together. No real invoices are paid.
- Explicit additive upgrade preserves earlier attempts, requests, encrypted credentials and external keys. Browser setup/inbox follows existing styling.
- Published/download-verified at `1fe0670142755f86aee235fabe1847e5d118ee52`; exact-main 358 native / 451 HTTP/Chromium checks pass. Actual merchant delivery, scheduled processing, production and client checkout remain pending.

## 2026-10-13 — Sandbox trial release published and verified

Continued roadmap from main `dd29436fa40c2a0f800bc02569b5fd5d1fb75c09` / 1.5.0 on `codex/sandbox-attempts`. Added owner-only tokenized sandbox UI, fixed sandbox reporting/charge adapter, additive attempt/events, exact amount/reference/account checks, idempotency and concurrency safeguards, unknown-result recovery, shared manual/catalog/request locks, strict audit and immutable 1.5.0 upgrade acceptance. PR #18 merged as `e5d4c17456faff0522429ab3f58093a06a523bd5`; exact-main run https://github.com/afterburn25/Cajun_Web/actions/runs/38380935411 passes 296 native / 421 HTTP/Chromium checks. Independent policy/invitation/documentation checks pass. Reviewed desktop/320px screenshots.

First candidate 4158d37 failed installer inventory due to Windows line endings; normalized LF and regenerated. f36e44a passed native tests but HTTP expected the established duplicate-payment message; restored the message without removing safeguards. 3ebc8a8 passed complete acceptance; final wording clarifies owner sandbox versus disabled client checkout. Final merged source and packages were checked independently. All eight draft and published release assets, extracted hashes, version labels and tag were downloaded-back verified. [Report](docs/TEST_REPORT_1.6.0.md), [verification](docs/RELEASE_VERIFICATION_1.6.0.json). Earlier releases untouched. No live deployment, provider operation or customer mail/data. Real merchant acceptance remains unverified; tests use synthetic gateway/Accept.js boundaries. Next: signed events, actual sandbox acceptance, distinct client/real-money checkout. Preserve Devin PR #7. Publication records are docs-only.

## v1.5.0 — 2026-10-13

**Current release — 2026-10-13:** [v1.5.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.5.0) is published and downloaded-back verified at `3e95bdf81ca0126d99a69c41f2ff6eb0c08da1e1` via [PR #16](https://github.com/afterburn25/Cajun_Web/pull/16). Bounded M2 preparation: owner-only protected separate Authorize.net credentials, sandbox-authentication diagnostics, reviewed invoice requests with expiry/revocation/replacement and strict audit. Card collection, production connections and transaction processing remain disabled. [Payment guide](docs/PAYMENT_PREPARATION.md). Exact-main acceptance: 227 native and 401 HTTP/Chromium checks; all eight assets/tag verified. Provider results use synthetic fixtures, not real merchant authentication. Earlier releases and Devin PR #7 remain unchanged. No live deployment/customer/provider operation. Next: tokenized sandbox payment attempts, verified events and reconciliation before actual design-only checkout; full M2/20i/mail/ZIP/SSH remain pending. [Devin handoff](docs/DEVIN_HANDOFF_1.5.0.md).

## v1.4.0 — 2026-10-13

M1 / partial HC-01 and HC-06: managed service prices, public configured cards, preserved invoice snapshots and audited manual payment/void workflow. Published at `0cdea7fbd17e887d3f4bc0574a411b157431b7df` via PR #14 after 165 native and 345 HTTP/Chromium checks. Eight retained assets/tag downloaded-back verified. No live deployment or gateway operation; [scope](docs/RELEASE_1.4.0.md).

## v1.3.0 — 2026-10-13

- Explicit owner-confirmed client policy and separate staff support view/write/download permissions.
- Browser-bound expiring contexts, persistent staff/client banner and return, stale-tab isolation and separate form tokens.
- Attributed messages/uploads/downloads and strict audit rollback; blocked customer decisions/security/payment actions.
- Additive support schema, preserved 1.2.0 update state, native and HTTP/Chromium acceptance.
- Published and downloaded-back verified at `7856cccf230a1646d02a4a92ad4bfb1c280efa19` through PR #12; 116 native and 289 HTTP/Chromium checks, eight retained assets. No host deployment. SSH/provider access remains separate.

## v1.2.0 — 2026-10-13

Owner-controlled creation, selected website/client-workspace permissions, enable/disable and private 24-hour setup/recovery links are integrated with existing users. Current-password confirmation, per-request authorization, reset/setup/session revocation and concurrent last-owner protection are enforced. Fresh installs initialize the owner; existing installations explicitly select an owner and add two tables without changing legacy credentials. Original and actual released-1.1.0 package upgrades are tested. Canonical foundation components and the embedded installer core are verified by the builder. [User/developer guide](docs/ADMIN_ACCOUNTS.md), [PR #10](https://github.com/afterburn25/Cajun_Web/pull/10). Published and downloaded-back verified at `f85627e05b171e4ee8508231e2ed6ee7b9374274`: eight retained assets; 81 native and 216 HTTP/Chromium checks plus independent suites. See [release register](docs/RELEASES.md). No target-host deployment.

## v1.1.0 — 2026-10-13

Published [v1.1.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.1.0) at `3703783e979abab38e8d72e9092c51dc835833b3` after PR #8. Delivered website/client-workspace scope and release fixes are listed in [the matrix](docs/RELEASE_1.1.0.md). Exact-main tests passed: 50 native, 157 HTTP/browser, all independent component/documentation checks. Seven retained assets were downloaded back and verified. Complete/update ZIPs support fresh setup and the tested populated 1.0.8 upgrade; no live deployment. This later documentation-only checkpoint records publication, remaining roadmap and the exact-release Devin handoff. Older pending statements below are historical.

## 2026-10-13 — Native regression fixes

First MariaDB acceptance found a real schema-idempotency failure: digit-bearing `sha256` was omitted by the column parser. Fixed both column-name patterns without weakening type/engine/unique-key checks. Local native tests then passed, and real unprivileged PHP HTTP tests completed fresh one-page installation and physical cleanup. Also fixed the existing lead editor's seven-status/six-label mismatch and corrected the test's portfolio route to the actual `work` endpoint. Tests now run extracted package bytes and cover incompatible schema preservation, cross-client/CSRF rejection and disguised executable uploads. Public service copy was polished and official comparison sources rechecked on 13 October. Complete browser acceptance and release publication remain pending. No customer host or database was changed.

## 2026-10-13 — Source recovery checkpoint

Recovered 41 actual website files plus native/HTTP tests and a build tool from the nine preserved source fragments. Original external assets were fetched only at the recorded public paths and checked against recorded SHA-256 values. Executable source is now under `site/` on `codex/finish-1.1.0`. This supersedes the older no-source-in-Git blocker on this branch, NOT the unverified runtime or release claims. No production files, database, mail or payments were changed. Review `docs/SOURCE_RECOVERY_2026-10-13.json`; run native integration and authorization tests next. No 1.1.0 release is declared by source recovery.

This log distinguishes repository work from application releases and deployment. Documentation-only work does not advance the application version.

## 2026-10-13 — 1.1.0 release acceptance in progress

- Reconciled recovered application source with main while preserving foundation components and Devin's separate work.
- Renamed the browser runner to avoid shadowing Python's standard HTTP library; the first combined run passed native database tests but failed on that import.
- Aligned candidate labels at 1.1.0, enforced canonical line endings, and made the build compare shipping bytes with its recorded commit.
- Client changes revoke outstanding invitations and sessions; activation rechecks expiry after locking/password hashing. Uploads reject executable extensions/code; form feedback respects reduced motion.
- Expanded release acceptance and publication remain in progress.
- Fixed real JavaScript submissions where a named action field hid the form endpoint; both growth requests and the original inquiry form now use their explicit destination attribute.
- Added the exact historical 1.0.8 test fixture and actual UPDATE-package/admin-upgrade checks, broader saved-state/browser/installer tests, local synthetic mail capture and the feature-to-test matrix. Preserved the original favicon source with its verified hash.

## 2026-10-13 — Staff invitations and source-compatible session tokens

- Inspected the recovered draft's real login/schema and corrected access contexts from integer revisions to opaque authorization strings with strict comparison.
- Added independent PDO staff invitation persistence: hashed single-use links, bounded expiry, replacement/revocation, transactional activation/consumption and rollback.
- Added a native MariaDB test workflow covering concurrency and failures using synthetic accounts; kept application migration, delivery, UI and release integration separate.
- Verified 146 access and 46 native invitation checks on each of PHP 8.2.34/8.4.26 with MariaDB 11.4.13; recorded branch and PR evidence in the invitation contract and continuity documents.

## 2026-10-13 — Independent staff and mailbox permission component

- Added standalone PHP 8.2+ immutable access contexts and permission policy: deny unknown/inactive/stale identities, separate staff management/permission grants, require exact mailbox read/send memberships, and reject conflicting duplicate grants.
- Added 142 local decision checks and a GitHub PHP 8.2/8.4 syntax/unit-test workflow. No Composer/runtime service requirement.
- Verified the source and PR checks on GitHub: 142 decisions and four syntax checks pass on PHP 8.2.34/8.4.26. Evidence is linked from the access foundation and status/handoff; the isolated component is not current-application acceptance.
- Documented the trusted adapter boundary and pending lifecycle/database/browser integration; updated ownership and Devin's independent portfolio scope while another worker finishes 1.1.0.
- No version bump, current-source replacement, admin UI, real account/mail operation, release ZIP or deployment. This component remains outside application packaging.

## 2026-10-12 — GitHub checks, release retention and Codex/Devin coordination

- Integrated roadmap/workflow PR #1 into main, verified passing PR and merged-main documentation jobs, created both work branches and published Devin assignment issue #2. Current status/handoff links the evidence and states that Devin has not been dispatched.
- Added a read-only GitHub Actions workflow and standard-library documentation checker for continuity files and local Markdown links/headings. Application CI remains pending current-source recovery.
- Defined incremental versioning, retained complete GitHub Release ZIPs, manifests/checksums/test reports, exact-artifact verification and a truthful release register.
- Added separate Codex/Devin work assignments, branch ownership, a ready-to-use Devin handoff and sequential PR integration with combined checks.
- Updated agent rules, PR checklist, roadmap F-04, status/handoff and release documentation. No application version bump, deployment or public application release is included.

## 2026-10-12 — Full client portal, website delivery and support access (requirements only)

- Added client-portal capability coverage for verified 20i services and native controls, explicit existing-service grants, project monitoring/updates/approvals and a separate client notification center.
- Added P-05/06/07: design workspace, authorized versioned website ZIP/data downloads with integrity/restore/retention criteria, and measured service observations without fabricated metrics.
- Added A-09/10: one-click staff support sessions into client portals and separately authorized VPS SSH with real verified transport, secure session scope and staff audit identity.
- Reviewed official public 20i documentation and recorded sources/account-specific limitations. Synchronized roadmaps, blueprint, direction, architecture, decisions, status and handoff.
- No current-source implementation, archive release, client login, SSH connection, provider/account change or deployment performed.

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

- 1.1.0 acceptance follow-up: mobile consent text wraps as one paragraph; expanded browser installer/contact checks and retained runtime diagnostics.

- Candidate 91ef30d: 50 native and 143 HTTP/browser checks passed; final browser-admin, sitemap and favicon-reference assertions added before merge.

Final matrix test correction (parent `7a0c0dd`): run 38356481572 rejected valid versioned favicon URLs because it compared the query suffix as part of the path. Parse the URL path and fetch the actual referenced icon for byte equality. Application source is unchanged; rerun the full suite.


## Historical component contract — review-request adapter contract (L-02 dependency)

- Add `docs/REVIEW_REQUESTS_ADAPTER.md`: the contract for adopting the review-request foundation (PR #31) in the live `website-review` lead path — `WebsiteAddress` intake normalization, consent-aware `ReviewContact`, stage persistence via an additive `review_stages` table, `review_reports` as the delivery gate, and staff transition controls through `ReviewInbox`. No shipping code or schema changes; records two decisions for integration review.
