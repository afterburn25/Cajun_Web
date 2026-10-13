# Session handoff — read before continuing

## Devin active branch — `devin/proposals-foundation` (P-02 version/approval model)

Branched from `main` `02fc784` (published 1.11.0 baseline). Isolated P-02 contract layer: `foundation/proposals/` (3 files — immutable content-hashed versions, version-pinned approve/request-changes decisions, guarded lifecycle with terminal approved state and actor-attributed history), `tests/proposals/run.php` (35 synthetic checks, all pass on PHP 8.4.25 locally), `docs/PROPOSALS_FOUNDATION.md`, `.github/workflows/proposals.yml`.

**Gate:** none for the contract layer; persistence/notification/UI remain Codex integration work. **Not integrated:** additive-only outside `site/`.

## Current directory and package contract — released 1.11.0

The owner directory correction supersedes all earlier flat-root/overwrite-update instructions below. See [Directory structure](docs/DIRECTORY_STRUCTURE.md), [migration and recovery](docs/STRUCTURE_MIGRATION.md), and [START-HERE](START-HERE.txt). Maintain thin root entries, organized controllers/views/business/database modules, categorized images and protected storage/upload scaffolds. FULL-PROJECT contains docs/tests/tools/brand/foundation/CI; COMPLETE-UPLOAD contains organized production files without a wrapper; UPDATE stages a verified owner-authenticated migration instead of blindly overwriting live files. Existing config/keys/private data and other agents' branches remain preserved.

**Published and downloaded-back verified:** [v1.11.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.11.0), exact source/tag `de70c5da2e8f7bd35fbb92b9b829d0ab3e880372`, [PR #38](https://github.com/afterburn25/Cajun_Web/pull/38). [Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38422422077) passes 618 native, 782 HTTP/Chromium, 41 filesystem/recovery, 203 Apache and 16 package-builder checks. All 16 published assets match the prepared bytes; all three package manifests, paths, contents, checksums and immutable tag target pass. [Test report](docs/TEST_REPORT_1.11.0.md); [Devin handoff](docs/DEVIN_HANDOFF_1.11.0.md). No live deployment. The priority directory correction is complete; the earlier pending/candidate instructions below are historical.

**Historical pre-release progress (superseded by the verified release above):** branch `codex/priority-directory-refactor` now contains organized routes/assets, focused bootstrap/security/database modules, split admin/portal/public/setup source, protected scaffolds, and a staged owner-only structural updater with protected rollback journal. The builder produces three distinct artifacts only from a clean commit. Existing native/HTTP tests now target canonical routes and perform real staged updates from each retained release. Expanded Apache, interruption/recovery and package guard suites now pass; next: verify final cleanup/docs, merge, test exact main and publish. Nothing deployed or published.

## Historical owner priority — fulfilled by verified 1.11.0 delivery

The owner requires an actual structural refactor and three separately verified downloads before any unrelated roadmap work resumes. This supersedes earlier constraints that kept substantial root PHP files or interpreted a no-wrapper ZIP as a flat application. Work on `codex/priority-directory-refactor` from main `b975915` preserves integrated portfolio/estimator work and the tested browser correction. Latest published version is **1.10.0**; the 1.11.0 portfolio-only release was stopped before draft/tag creation. The unused 1.11.0 candidate will be corrected before release. Do not publish the old-layout candidate.

Required meaningful areas: admin, portal, pages, includes, business app modules, API/webhooks/media, install, database, config, assets/css and assets/js, categorized images, protected storage/upload scaffolds. Root entry points must be thin. FULL-PROJECT includes docs/tests/tools/brand/foundation/CI; COMPLETE-UPLOAD extracts organized site contents directly; UPDATE includes tested compatibility/migration support. Keep private configured data locations, exact brand bytes, one-page keyless setup, all existing behavior and no-rewrite/root/subfolder support. [Refactor contract](docs/DIRECTORY_REFACTOR_CONTRACT.md). No live deployment is authorized.

Devin PRs #31/#32 are separate independent components, preserved and paused for integration while paths change. Supply the exact mapping and corrected baseline before further application adapters. Next action is structure implementation, migration/recovery and Apache/native/browser/package acceptance—not another roadmap feature.

## Owner instruction — keep development moving despite provider gates (2026-10-13)

The owner approved [dependency gates policy](docs/DEPENDENCY_GATES.md) for all Codex and Devin sessions. Replace the four vague "Blocked" items in roadmap/tracker interpretation with: **HC-07 Awaiting API verification; HC-09/HC-10 Development pending — API verification; A-10 Architecture/security validation pending**. Do not mark those features implemented as a result of relabeling. Build and test all independently feasible designs, admin inputs, PHP logic, data models, fixtures/adapters, idempotent jobs, permissions and UI; then continue another authorized roadmap item if an external test cannot yet pass.

**Next safe action:** review current main/open work and choose a bounded independently verifiable feature; record exact remaining account/endpoint/auth/transport acceptance for live operation and keep those actions disabled. Preserve other workers' branches, 1.10.0 immutable release, and 1.11.0 candidate acceptance gates. Don't ask owner to paste secrets or block unrelated work. **Docs-only policy update; no software deployed or provider call made.**

## Historical portfolio acceptance follow-up — superseded by 1.11.0 publication

Portfolio integration PR #30 is merged at `332af19333a62d3dfcc90eb9cf34871a7fac50ec`; original Devin PRs #7/#27 are merged with preserved history. Both candidate runs passed 618 native / 675 HTTP/Chromium checks and reviewed screenshots. Exact-main run 38408053057 passed native and actual 1.10.0 retention checks, then exposed a synthetic modified-click test navigation race. `codex/portfolio-acceptance` fixes the test observer and adds a real Ctrl-click/new-tab assertion; it changes no shipping bytes. Publication remains pending successful exact-main acceptance. Latest public release remains 1.10.0. New Devin L-02 PR #31 is separate unreviewed work, outside this release.

## Historical portfolio integration candidate — included in released 1.11.0

Codex is integrating Devin portfolio [PR #7](https://github.com/afterburn25/Cajun_Web/pull/7) at `016a4057a3a17b62b2edcf583252f52cb9ff06a2` on `codex/portfolio-browsing`, starting after estimator integration main `39de8ff50ea245928a1f6528f10057afcf55e5b7`. Original branch/history remain intact. **Candidate source 1.11.0; latest published release remains v1.10.0.** [Contract](docs/PUBLIC_COMPONENT_INTEGRATION.md) and [browsing guide](docs/PORTFOLIO_BROWSING.md).

Devin's existing-project adapter now drives exact published-only portfolio/category/detail lookups. Real category links work without JavaScript, enhanced filtering retains back/forward and ordinary modified clicks, and a live result count announces changes. Review fixes preserve numeric-string categories, distinguish the category `all` from All work, normalize card/filter values consistently, reject ambiguous published slugs and suppress identical-media before/after comparisons. Existing v1.10.0 activity, client/private delivery and billing features remain.

Local PHP 8.4.25 passes 55 portfolio and 65 estimator checks; complete builder verifies 80 flat shipping files / 55 PHP files, 79 UPDATE files, exact bundled components and installer inventory. Combined GitHub acceptance, screenshots, actual retained 1.10.0 update and release download verification are pending. No full W-01/L-01 completion or deployment claim.

Estimator [PR #29](https://github.com/afterburn25/Cajun_Web/pull/29) passed [combined application acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38405668799) and PHP 8.2/8.4 component checks before merge at `39de8ff`. Its core stays outside deployment; existing quantity pricing/custom exclusions need an adapter. Later Devin estimator refresh `65c2084` is preserved in this integration. Next: fix any combined failures, review desktop/mobile evidence, merge the exact checked candidate, run exact main and publish/verify immutable 1.11.0 assets. Mail, actual merchant acceptance/card checkout, broader 20i and VPS SSH remain pending.

## 2026-10-13 — Devin estimator integration candidate

Codex is integrating [Devin PR #27](https://github.com/afterburn25/Cajun_Web/pull/27) at `b69da9626448a2ddfdf30b425dc7c2d5e16d1582` on `codex/integrate-estimator`, from main `6f0120913c93eace492b6c5f333c754fea3bea31`. The ancestry-preserving merge keeps the standalone core outside deployment. Review adds integer-overflow consultation and strict explained-result invariants. Current shipping application/release remains **v1.10.0**; no new form or pricing behavior is claimed. Local PHP 8.4.25 passes 65 named estimator checks and syntax; documentation links pass. Combined GitHub verification is pending. Next: merge only after checks, then integrate portfolio PR #7's new real-route adapter at `c8821f8` against current source. [Integration contract](docs/PUBLIC_COMPONENT_INTEGRATION.md).

## 2026-10-13 — Verified workspace activity release

**Published and downloaded-back verified: [CajunWebPro v1.10.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.10.0).** Source/tag `4649a913d40166b9874f9550e57b4186edfc43ab`, [PR #26](https://github.com/afterburn25/Cajun_Web/pull/26). [Complete upload ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.10.0/CajunWebPro-1.10.0-COMPLETE-UPLOAD.zip) · [UPDATE ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.10.0/CajunWebPro-1.10.0-UPDATE.zip). Prior releases remain immutable; publication documentation does not alter release bytes.

Delivered bounded P-07: clients receive activity for studio messages, reviews, files, invoices, payment recordings/corrections and published website releases. Staff with client-workspace access receive client replies, uploads and review decisions in their own centers. Explicit read/unread status is personal; Mark visible changes only the reviewed page. Exact-source links, older-history pagination and unread bells recheck current identity/grants/ownership. Staff also get a workspace bell on public pages after a private check. Count responses contain no message bodies or source metadata. Support cannot access client activity.

Retained source records provide history; `activity_reads` stores only per-person status. There are no business-write notification hooks or copied bodies. Activity failures cannot undo messages/invoices/payments/releases. Visible active pages check at most once per minute without extending idle logins; failed established checks show unavailable status and later recover. Historical updates start unread. This is workspace activity, not webmail or email delivery. [Guide](docs/WORKSPACE_ACTIVITY.md).

[Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38402918742): **603 native checks (64 activity), 630 HTTP/Chromium checks**, PHP 8.3/MariaDB 10.11. Actual retained 1.9.0 UPDATE preserves business records, identities, encrypted credentials/master key, original settings/configuration/guards/media and published/draft website ZIPs/manifests. A retained client downloads the same original ZIP; prior balances and credentials remain usable. All older update/fresh paths pass. Desktop/320px screens and polling/failure/revocation behavior verified. Independent policy 251/invitation 46 checks pass on PHP 8.2/8.4. [Report](docs/TEST_REPORT_1.10.0.md), [scope](docs/RELEASE_1.10.0.md), [download verification](docs/RELEASE_VERIFICATION_1.10.0.json). All eight draft/public assets and immutable tag match exact-main CI/local rebuild.

Existing sites back up files/database/private storage, merge UPDATE and have an owner enable **Workspace activity** with the current password. Fresh installation includes an enabled empty table. COMPLETE contains 73 files / 48 PHP files; UPDATE contains 72, omitting only install.php. Never rerun fresh installation. No SMTP, background daemon or host CLI is needed.

**Full P-07 remains partial:** outbound email, preferences, stage timeline and hosting/renewal/ticket events remain pending. **A-06–A-08 multi-account webmail, mail templates and email bells are not implemented by this increment.** Real card checkout/actual merchant acceptance, broader billing/20i and VPS SSH remain pending. No live deployment, customer mail/payment or provider operation occurred. Synthetic gateway evidence is not actual merchant acceptance.

A new independent L-01 estimator component arrived during release: [PR #27](https://github.com/afterburn25/Cajun_Web/pull/27), `devin/lead-estimator` at `b69da9626448a2ddfdf30b425dc7c2d5e16d1582`. Its 40 isolated PHP checks pass on 8.2/8.4; it starts from old pre-1.1 main `e9549d4d0ea9bd4ff4c68dc6d4dd242d864af812` and does not implement the application form/storage. Preserve it alongside PR #7. Next integration task: inspect both components against current main, reconcile stale baseline/docs and agree actual application adapters before shipping; component tests are not integrated acceptance.

**Next:** preserve Devin portfolio [PR #7](https://github.com/afterburn25/Cajun_Web/pull/7) at `55d60670497d987286e64630e037e1ba2ec3a318`; [handoff](docs/DEVIN_HANDOFF_1.10.0.md). Refresh main/open work and claim the next bounded independent roadmap feature, or complete actual merchant acceptance before production card checkout. No background continuation is scheduled.

## Historical checkpoints — superseded where noted

## 2026-10-13 — Native regression fixes

First MariaDB acceptance found a real schema-idempotency failure: digit-bearing `sha256` was omitted by the column parser. Fixed both column-name patterns without weakening type/engine/unique-key checks. Local native tests then passed, and real unprivileged PHP HTTP tests completed fresh one-page installation and physical cleanup. Also fixed the existing lead editor's seven-status/six-label mismatch and corrected the test's portfolio route to the actual `work` endpoint. Tests now run extracted package bytes and cover incompatible schema preservation, cross-client/CSRF rejection and disguised executable uploads. Public service copy was polished and official comparison sources rechecked on 13 October. Complete browser acceptance and release publication remain pending. No customer host or database was changed.


## 2026-10-13 — Source recovery checkpoint

Recovered 41 actual website files plus native/HTTP tests and a build tool from the nine preserved source fragments. Original external assets were fetched only at the recorded public paths and checked against recorded SHA-256 values. Executable source is now under `site/` on `codex/finish-1.1.0`. This supersedes the older no-source-in-Git blocker on this branch, NOT the unverified runtime or release claims. No production files, database, mail or payments were changed. Review `docs/SOURCE_RECOVERY_2026-10-13.json`; run native integration and authorization tests next. No 1.1.0 release is declared by source recovery.


Checkpoint: 2026-10-12 — GitHub release/testing discipline and Codex/Devin coordination established; recover current 1.1.0 source next.

Checkpoint: 2026-10-13 — parallel permission-component implementation on codex/platform-foundation; 1.1.0 completion remains with its existing worker.
Repository: afterburn25/Cajun_Web · Authoritative branch: main.

## Resume here — GitHub workflow and two-agent split

Current continuation: source now exists under `site/` on the other worker's `codex/finish-1.1.0` branch, inspected at `4a8b4c4` (worker has since advanced to `90ba4f5`). It is still a draft. Do not overwrite that branch. This stream changes only independent foundations: opaque string authorization versions aligned to the draft, and the [staff invitation store](docs/STAFF_INVITATIONS.md). Source `718dbc4` in [PR #6](https://github.com/afterburn25/Cajun_Web/pull/6) passed 146 access and 46 native invitation checks on PHP 8.2/8.4 with MariaDB 11.4; evidence is linked in the store documentation. Verify final PR state and integrate, then review Devin's incoming [PR #7](https://github.com/afterburn25/Cajun_Web/pull/7) sequentially with combined checks. Next application work is the staff account lifecycle/adapter and UI after final-source reconciliation; no application schema, installer, live account or mail was changed here.

Latest owner instruction: start independent work while another worker finishes 1.1.0 and updates GitHub. Do not ask again for the build or overwrite `codex/finish-1.1.0`. Main at `9e30564` contains that worker's progress report but no deployable current source. Independent components/contracts/tests can proceed; application integration still needs the actual current source.

This branch adds `foundation/access/` and `tests/access/run.php`: immutable contexts, known global permissions, separate mailbox read/send grants, default denial and stale/inactive session decisions. Local PHP 8.4.25 passes 142 cases and four syntax checks. [PR #5](https://github.com/afterburn25/Cajun_Web/pull/5) contains source commit `5cf3f01`; [GitHub checks](https://github.com/afterburn25/Cajun_Web/actions/runs/38349906968) passed on PHP 8.2.34 and 8.4.26. Read `docs/ACCESS_FOUNDATION.md` for exact limitations and the adapter/lifecycle work needed after source publication. Inspect PR merge/head state before starting further work. No working administrators UI or real session revocation is claimed.

The owner requested a revised Devin prompt. Issue #2 is now updated so Devin can start isolated `foundation/portfolio/` contracts/filtering and `tests/portfolio/` with synthetic examples, then adapt them to the real portfolio after 1.1.0 lands. It must not edit our access module or invent a replacement application. The owner will paste the assignment; no Devin session was launched here. Keep the two implementation branches separate and integrate tested pieces with clear status.

Read `docs/RELEASE_WORKFLOW.md`, `docs/RELEASES.md` and `docs/AGENT_COORDINATION.md`. The owner wants incremental versions archived as complete GitHub Release ZIPs, automated GitHub tests, current documentation for other agents, and Codex/Devin work on separate branches integrated when ready. Documentation CI now exists; application tests/build/release automation await the actual 1.1.0 source. Do not publish a placeholder version or relabel 1.0.8.

[PR #1](https://github.com/afterburn25/Cajun_Web/pull/1) merged at `633f5a1e5c27f0ad626276f2bae75cbc2b88ea66`; documentation checks passed on the PR and [merged main](https://github.com/afterburn25/Cajun_Web/actions/runs/38339033073). Earlier sections below describe historical states. Both `codex/platform-foundation` and `devin/public-experience` now exist on GitHub at that checkpoint. Refresh them from the same verified 1.1.0 source baseline before implementation. Codex coordinates shared contracts, tests and integration; Devin owns the initial W-01 portfolio increment.

The owner selected a GitHub assignment to paste into Devin: [issue #2](https://github.com/afterburn25/Cajun_Web/issues/2). It is open and ready for that handoff; Devin was not dispatched by this chat. Next actions: recover/verify/import 1.1.0, establish exact file ownership and shared contracts, then begin separate implementation PRs. Keep ready work on `main`, preserve published versions, and report CI, package and deployment evidence separately. No provider or production operation is authorized by a documentation/release update alone.

## Latest client and support requirements

Read `docs/CLIENT_PORTAL_ROADMAP.md`. The owner wants every client-facing service in the portal, including native 20i controls, web-design progress/updates/approvals and downloadable website ZIPs. P-05/06/07 define design workspace, immutable private release downloads with needed client data/instructions, and client updates/monitoring separate from staff mail. A-09/10 define one-click owner/authorized-staff client portal support access and separately authorized real VPS SSH. Preserve actor attribution, safe client scope and a visible support-session exit; do not ask for client passwords.

Public official 20i documentation was checked; account-specific endpoints/entitlements and delegated-terminal/SSH transport are still discovery gates. Browser SSH may require a separately hosted secure gateway; do not quietly add a daemon/root requirement to the shared-host website. Existing source prerequisite remains current 1.1.0. No live provider/customer login, mail send, SSH session, archive generation or deployment occurred. Continue existing draft PR #1 and test each capability against its documented acceptance before marking implemented.

## Explicit owner direction — build our own WHMCS

Read `docs/CUSTOM_PLATFORM_BLUEPRINT.md`: own the platform's customer/staff identity, catalog, checkout, invoicing, subscriptions, service lifecycle, support, admin and portal, with direct 20i and Authorize.net adapters. Include standalone web-design payments and the admin mail suite. Existing HC/A/P requirements remain applicable; no WHMCS/HostShop application dependency or invented feature-completion claim. M0 current-source/staging work precedes M1 identity/catalog foundation. This is a scope/architecture update in the existing documentation PR, not a runtime build.

## Latest requirement — admin mail and additional staff accounts

The owner wants an Email section for multiple accounts with setup, send/receive and templates; additional administrator accounts; and an unread-email bell in admin plus public pages visible only to signed-in admins. Each notification opens the matching protected webmail message. Read `docs/ADMIN_MAIL_ROADMAP.md` and A-05 through A-08 for complete workflows and acceptance. Staff accounts/permissions are M1; webmail/templates/bells are the M5/HC-12 mail group, which can proceed independently of VPS after its own prerequisites.

Continuing `docs/web-design-payment-forms` and [draft PR #1](https://github.com/afterburn25/Cajun_Web/pull/1), with the existing design-payment and owner-confirmed 1.1.0 requirements retained. New work is documentation only. Recover and inspect 1.1.0, reconcile existing auth/mail capabilities, then implement secure admin/mailbox authorization before exposing mail or notifications. Do not ask for credentials in chat or send real mail as part of roadmap capture. Provider setup belongs in the eventual secured admin interface.

## Latest requirement — web-design payment forms

The owner wants payment forms for web-design services too. Updated P-03 and HC-11 with full service payments, fixed/percentage quote deposits, milestones, final balances, optional recurring maintenance, admin-created payment requests/secure links and portal payments. Use the same branded Authorize.net engine and ledger as hosting checkout, but design payments must work with 20i disabled and no infrastructure purchase. Deliver these forms in M2; deeper project/hosting bundles remain M4.

Current branch: `docs/web-design-payment-forms`, starting from local `072449c` over remote main `43bebd0`. This branch also preserves the earlier verification and owner version correction. This is documentation only; inspect the actual 1.1.0 build to identify existing implementation before coding. Use the P-03/HC-11 acceptance cases for sandbox payments, invoice links/access, server-side amounts, duplicate/timeout recovery, receipts/refunds and balances. No live payment or deployment is authorized by this documentation update.

## Current owner correction — takes precedence over older baseline instructions

The owner says the latest version is **1.1.0**. Obtain and inspect that exact build before source import or feature work. Only 1.0.8 has been located and verified in this chat; keep it as historical evidence, not the implementation starting point. GitHub currently has documentation only, so ask for the 1.1.0 local folder/download link or another repository/branch when its location is not available. That location question is pending with the owner.

This correction supersedes older instructions below to import 1.0.8 first. Preserve those earlier investigation results as history. No current 1.1.0 archive, version constants, test evidence or hosted version has yet been verified here.

## Latest owner request and result

The owner asked to check GitHub for the roadmap, understand the intended CajunWebPro product, and have the latest build available. Fresh clone/fetch confirmed main at `43bebd019a6f548d7a059b2c8e259beed3efe561`; both existing feature branches are behind main. There are no published releases/tags, Actions runs, open PRs or open issues, and the remote source tree is documentation only.

Recovered a local working copy of the exact 1.0.8 baseline from Downloads. Both original/recovered ZIPs match the recorded archive hash. ZIP CRC, 33-file extraction equality, all 32 installer dependency hashes, app/installer 1.0.8 constants, one-page layout, original logo hash, 14 PHP syntax checks and two JavaScript syntax checks passed. No application behavior or live deployment is established by these checks.

In this desktop chat workspace the retained archive is `work/CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip`, extracted source is `work/CajunWebPro-1.0.8-baseline/`, and detailed local evidence is `work/baseline-verification.json`. These are workspace-relative local files, outside this repository and not permanent download URLs. Verify their existence and hashes when resuming elsewhere. Local documentation branch: `docs/verify-latest-baseline-20261012`; this investigation was not pushed or deployed.

Next implementation step remains importing the verified baseline and original assets into Git after a secrets/private-data review, then resolving actual staging/installer/storage acceptance. The hosting roadmap and Authorize.net choice remain authoritative; none of HC-01 through HC-14 is claimed implemented by this investigation.

## Prior approved owner request

Create an actionable GitHub roadmap expanding CajunWebPro into a **web design AND hosting business**, with a fully custom first-party billing platform, client portal, plan store, checkout, 20i reseller provisioning for shared hosting/domains/VPS, and Authorize.net instead of Stripe. Admin must accept API Login ID, Transaction Key, Signature Key and Public Client Key. Payment forms should be styled to match the existing navy-and-gold design.

## What changed

- New docs/HOSTING_AND_BILLING_ROADMAP.md defines HC-01 to HC-14, scope, detailed workflows, catalog and admin controls, auth, billing, provider integrations, safety and launch gates, phased M0–M6 implementation, and verification matrix.
- docs/ROADMAP.md integrates the new section with existing F-/W-/L-/P-/A- items, rather than discarding the prior website/project workflows.
- docs/PROJECT_DIRECTION.md, docs/ARCHITECTURE.md, docs/DECISIONS.md, PROJECT_STATUS.md, CHANGELOG.md and docs/SESSION_LOG.md synchronize the accepted architecture, provider choice and status.
- Public provider documentation consulted for 20i package provisioning and Authorize.net Accept.js, webhooks and customer profiles; no account-specific API/merchant connection tested.

## Still not done

**No executable website source is in Git yet.** The actual 1.0.8 distribution/branding import, v1.1.0 missing-release recovery, installer/host permissions, payment gateway, client portal, domain registration, VPS, invoices and hosting fulfillment remain unimplemented and unverified. No checkout or service has been launched. No prices/credentials were invented or committed. The latest runnable deployment state on the actual hosting server is unknown.

## Exact next actions

1. Inspect current main/AGENTS.md/PROJECT_STATUS.md/docs/ROADMAP.md and docs/HOSTING_AND_BILLING_ROADMAP.md; do not use prior chat release claims as evidence.
2. Import exact verified 1.0.8 source ZIP and original logo/favicon into Git, excluding secrets/private data; keep flat deployment, one-page keyless installer and approved brand. Verify source-to-package consistency.
3. Investigate target-host runtime/installer/storage blockers and establish real staging acceptance before expanding platform.
4. Inspect My20i account-only API reference for actual product CRUD, hosting provisioning, domains, VPS and costs. Obtain API credential setup through secured admin ONLY when implementation ready. No request for keys in chat/repo.
5. Configure Authorize.net sandbox through secure admin, implement themed Accept.js with PCI scope review and hardened payment/webhook/recurring engine.
6. Deliver roadmap by M0–M6 gates with tests/commits/status/handoff per phase; no premature live card charging, registrations or destructive provider operations.
7. Produce tested downloadable releases only after actual source, migrations, hosting permissions, backups and release checks are proven.

## Prior evidence still valid

The verified baseline ZIP is CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip (33 files, SHA-256 13bcf66cf069896017a6168556eba5211ec5bf3b1bee2aa23d354b4e9be5b6cf). Favicon source hash, installer incident and missing v1.1.0 details remain in PROJECT_STATUS.md, docs/BASELINE_INVENTORY.md and docs/DELIVERY_INCIDENT_2026-10-12.md. Do not downgrade the hosted site to repair a missing chat download.

The latest session verified existing documentation and the local distribution only. Next implementation session should record source-import and runtime-test evidence and update every continuity file.

Release acceptance follow-up (parent `f007803`): HTTP/browser checks reached account revocation; console classification stopped on the intentionally invalid estimator response. The next commit scopes that expected resource message to the precise estimator URL, retains diagnostic JSON, adds original-contact and real-browser fresh-install coverage, scans PHP runtime diagnostics, and improves mobile consent wrapping. Full rerun required before publication.

Final matrix test correction (parent `7a0c0dd`): run 38356481572 rejected valid versioned favicon URLs because it compared the query suffix as part of the path. Parse the URL path and fetch the actual referenced icon for byte equality. Application source is unchanged; rerun the full suite.
