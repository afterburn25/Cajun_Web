# Session log

## 2026-10-13 — Devin estimator adapter contract (L-01 dependency)

**Request:** Continue the roadmap while Codex sequences v1.11.0 and the queued Devin PRs. PUBLIC_COMPONENT_INTEGRATION.md names an explicit adapter contract as the prerequisite before the live estimator adopts `foundation/estimator`.

**Starting commit:** `b975915` (main after Codex PR #33). New branch `devin/estimator-adapter`. Owned paths: `docs/ESTIMATOR_ADAPTER.md` plus continuity docs. No `site/` or foundation changes.

**Work:** Documented the adapter contract: `EstimateRequest` maps `project_type` → `serviceType`; quantity-priced live keys (`page` × pages−5, `product` × products) fold into adapter-built `PriceRule` low/high ranges with a pre-multiplication overflow guard; every live consultation condition (disabled pricing, `custom` type, `integration` option, missing/null key, summed high over 99999999) is mapped to either an adapter short-circuit or a component path; result kinds map back to the existing `mode`/`low`/`high`/`label` payload. Recorded a byte-for-byte fixture-equivalence test plan as required acceptance, and an open question on quantity-in-component vs adapter folding for Codex.

**Verification:** Documentation only — `tools/check_docs.py` link check run before commit. No code executed, no estimator behavior changed.

**Next:** Codex review; implementation PR follows only after the contract is accepted. PRs #31/#32/#34 remain queued and mergeable; PR #34 received an installer-hash fix at `ac91f0e` (CRLF-hashed site.css → LF).

## 2026-10-13 — Sandbox trial release published and verified

Continued roadmap from main `dd29436fa40c2a0f800bc02569b5fd5d1fb75c09` / 1.5.0 on `codex/sandbox-attempts`. Added owner-only tokenized sandbox UI, fixed sandbox reporting/charge adapter, additive attempt/events, exact amount/reference/account checks, idempotency and concurrency safeguards, unknown-result recovery, shared manual/catalog/request locks, strict audit and immutable 1.5.0 upgrade acceptance. PR #18 merged as `e5d4c17456faff0522429ab3f58093a06a523bd5`; exact-main run https://github.com/afterburn25/Cajun_Web/actions/runs/38380935411 passes 296 native / 421 HTTP/Chromium checks. Independent policy/invitation/documentation checks pass. Reviewed desktop/320px screenshots.

First candidate 4158d37 failed installer inventory due to Windows line endings; normalized LF and regenerated. f36e44a passed native tests but HTTP expected the established duplicate-payment message; restored the message without removing safeguards. 3ebc8a8 passed complete acceptance; final wording clarifies owner sandbox versus disabled client checkout. Final merged source and packages were checked independently. All eight draft and published release assets, extracted hashes, version labels and tag were downloaded-back verified. [Report](TEST_REPORT_1.6.0.md), [verification](RELEASE_VERIFICATION_1.6.0.json). Earlier releases untouched. No live deployment, provider operation or customer mail/data. Real merchant acceptance remains unverified; tests use synthetic gateway/Accept.js boundaries. Next: signed events, actual sandbox acceptance, distinct client/real-money checkout. Preserve Devin PR #7. Publication records are docs-only.

## 2026-10-13 — Administrator application integration candidate

Request: continue the roadmap after verified v1.1.0. Parent: `d6de19c` on `codex/admin-accounts`. Added the staff adapter, owner-controlled account forms and setup flow; wired per-request account state, explicit website/client permissions, password/reset/session revocation and serialized last-owner protection into the existing identities. Added compatible staff tables and an explicit owner-selection migration; fresh installs initialize the owner. Canonical access/invitation components now bundle verbatim into the application; the builder also verifies the installer's embedded core. Candidate source version is 1.2.0; no new release or host deployment yet. Devin's portfolio branch is untouched.

Local checks: 176 isolated permission-policy assertions on PHP 8.4.25; builder syntax/inventory/ZIP checks for 50 shipping files and 27 PHP files; new native test scripts parse; `git diff --check` passes. Native database and HTTP/browser evidence is pending GitHub CI, including invitation consumption, upgrade preservation and concurrent owner changes. Remaining: browser account acceptance, actual v1.1.0 package upgrade, review/fixes and verified release gates. Commit: `Integrate owner-managed administrator accounts for acceptance`.

Append dated entries. Keep this chronological. Use `SESSION_TEMPLATE.md`; link detailed test evidence instead of pasting excessive logs or secrets. The surrounding Git commit records the exact resulting revision.

## 2026-10-12 — Establish repository continuity

**Request:** The owner created `Cajun_Web` and wants documentation for everything done and project direction, so future sessions resume accurately.

**Starting state:** Connected GitHub lookup resolved `afterburn25/Cajun_Web`; default branch `main`; write access available. Root Contents response reported an empty repository. README initialization created commit `4ea74bf8b83ef7474a7f0f06b7464c56b0990ed3`. The following documentation commit uses that commit as its parent.

**Work:** Added working agreement, project status, handoff, changelog, direction, all approved roadmap items, source-map notes, installation/release requirements, brand provenance, baseline inventory, decisions, session template and PR documentation checklist. Captured the exact one-page/no-key requirement and original favicon/logo selection. Kept unresolved host problems and unimplemented additions explicit.

**Current-session verification:** Read the historical 1.0.8 report and inspected the actual ZIP with Python. SHA-256 matched `13bcf66cf069896017a6168556eba5211ec5bf3b1bee2aa23d354b4e9be5b6cf`; ZIP integrity returned no bad member; 33 deployable files were listed without a wrapper. Source headers declare app/installer 1.0.8 and one-page layout. Read the ten schema declarations. Verified the standalone 1080×302 RGBA logo matches packaged logo bytes and recorded the 248×301 RGBA attached favicon source fingerprint.

**Not done:** No runtime source or binary assets imported into Git. No new features or favicon outputs implemented. No source/security audit, native MySQL/GD test, email delivery, production HTTPS flow, ownership repair, or deployment. Historical test suites were not re-run.

**Remaining blockers:** B-001 source import; B-002 actual host entry point/version; B-003 real worker filesystem permissions; B-004 native acceptance; B-005 approved expansion.

**Next action:** Import the fingerprinted complete PHP baseline and original assets into Git, document the source-to-package mapping, then implement/test the exact favicon request and work through the roadmap without regressing the installer or design.

## 2026-10-12 — Investigate failed v1.1.0 downloads

**Request:** Owner pasted 'Failed to download file. Please try again later.' after the assistant advertised v1.1.0 release/update ZIPs and previews.

**Starting commit:** `089d2d9e7b62416bc10f6a53f9cba8879f644550` on main. Connected branch inspection also found feature/growth-suite-1.1.0 at that same commit. GitHub releases returned an empty list. Read AGENTS, current status, handoff, changelog and this log before changing documentation.

**Investigation:** Recursive runtime file search found no v1.1.0/checkpoint files. Exact advertised artifact-name searches in conversation/Library did not recover them. A recent Library listing found the exact-favicon archive and earlier baseline; it was a partial Library listing, not an exhaustive enumeration of external mounts. No native browser download test was run.

**Recovery and actual verification:** Python ZIP CRC checks passed for the 1.0.8 baseline and exact-favicon archive. Baseline is 1,185,879 bytes, hash `13bcf66cf069896017a6168556eba5211ec5bf3b1bee2aa23d354b4e9be5b6cf`, with 33 files and 1.0.8 application/installer constants. Favicon ZIP is 247,403 bytes, hash `ea6a158f7b5362ce199c4f17e09c47922e71ae3150f958a76b40492d29b089ff`; its PNG and ICO variants decoded and the recorded source matches icon(4).png. Copied both to fresh recovery filenames and verified byte equality plus archive CRC again. No older archive was relabeled as v1.1.0.

**Documentation:** Added `docs/DELIVERY_INCIDENT_2026-10-12.md`; updated PROJECT_STATUS, SESSION_HANDOFF, CHANGELOG and this log. Explicitly withdrew unsupported release/completion/test claims from the prior response. Approved feature scope and product direction remain unchanged.

**Limits / no external implementation:** No website source import or feature build in this recovery session, no staging acceptance, no hosting modification, and no customer-data changes. No complete v1.1.0 package or supporting test evidence was recovered. A favicon asset archive does not establish its live integration.

**Next action:** Commit the recoverable complete baseline and originals into the source tree before rebuilding requested additions in tested, committed increments. Verify real artifacts and attach only existing files before declaring another downloadable release. Keep the current website intact rather than downgrade/reinstall it to address a chat download failure.

## 2026-10-12 — Add custom hosting commerce and Authorize.net roadmap

**Request:** Owner wants a GitHub roadmap for a full-fledged custom CajunWebPro web design + hosting company: product plans, direct 20i shared hosting/domain/VPS resale, native client portal and custom Authorize.net checkout. Admin must configure API Login ID, Transaction Key, Signature Key and Public Client Key.

**Starting branch/head:** main at 74d1892ca96c3e5080346a3886b3440afd7dcab6. Existing README, AGENTS, status, roadmap, project direction, architecture and prior handoff were reviewed. Git tree was documentation only, with source import unresolved.

**Changes:** Added docs/HOSTING_AND_BILLING_ROADMAP.md; updated ROADMAP, PROJECT_DIRECTION, ARCHITECTURE, DECISIONS, PROJECT_STATUS, SESSION_HANDOFF, CHANGELOG and this session log. Scope HC-01 through HC-14; milestone gates M0–M6; real custom PHP/MySQL billing system; separate sandbox/live key configuration; branded Accept.js form; 20i provisioning, domain/VPS catalog, unified clients for design + hosting, admin pricing, service recovery and security acceptance.

**Verification:** Read current connected GitHub files, source-only inventory/status, and public 20i/Authorize.net technical documentation. Checked 20i documented hosting package API and Authorize.net Accept.js tokenization/webhook signature requirements. No PHP execution, native DB, integration calls, PCI certification, charge, test domain registration, hosting API provisioning or live host/browser test was performed.

**Known blockers:** Source baseline not yet imported; last target host installer/storage state and old v1.1.0 release claims unresolved. 20i account-only API capabilities, wholesale VPS/domain costs, and Authorize.net merchant/sandbox account not supplied; PCI compliance and release controls not yet confirmed. Avoid all pricing or deployment claims.

**Next:** Import the fingerprinted 1.0.8 baseline into Git, inspect live deployment and API capabilities, implement and test M0→M6 incrementally. Preserve existing logo/favicon/site and one-page keyless installer, customer data and modular structure. This documentation commit makes no release claim.

## 2026-10-12 — Verify current roadmap and available build

**Request:** Check the owner's Cajun_Web GitHub roadmap and intended product, and ensure the latest available build is present locally.

**Starting commit:** Fresh clone of main at `43bebd019a6f548d7a059b2c8e259beed3efe561`, reconfirmed by a final fetch. Read AGENTS, README, status, handoff, direction, full roadmap, hosting/billing roadmap, baseline inventory, brand provenance, installation requirements, delivery incident, changelog and prior session log.

**Remote findings:** Main's latest commit records custom hosting resale, Authorize.net billing and unified clients. `feature/growth-suite-1.1.0` is two commits behind main; `feature/public-growth-rc1` is one commit behind; neither is ahead. GitHub returns no releases, tags, Actions runs, open PRs or open issues. The remote tree contains documentation only.

**Local actions and checks:** Scoped filename search across Downloads, Desktop and Documents found the original and recovered 1.0.8 ZIPs, with no matching newer CajunWebPro archive. Both copies are 1,185,879 bytes and SHA-256 `13bcf66cf069896017a6168556eba5211ec5bf3b1bee2aa23d354b4e9be5b6cf`. Retained an unchanged archive and extracted source outside the repository. ZIP CRC, 33-file extraction byte equality, all 32 embedded installer dependency hashes, original logo hash, consistent 1.0.8 app/installer constants and one-page layout passed. Syntax checks passed for 14 PHP files using PHP 8.4.25 and two JavaScript files using Node. Local detailed evidence is in the parent chat workspace's `work/baseline-verification.json`.

**Tracked changes:** PROJECT_STATUS.md, SESSION_HANDOFF.md, CHANGELOG.md and this log, on local branch `docs/verify-latest-baseline-20261012`. Commit message: `docs: verify current roadmap and recover local 1.0.8 baseline`; parent is the starting commit above. No push or remote mutation.

**Limits and remaining blockers:** These checks establish artifact identity and syntax, not working installation or full application correctness. No source import, new feature build, favicon integration, database test, SMTP test, hosted browser flow, payment/provisioning test or live deployment. B-001 through B-006 remain open, and the running host version remains unknown. Missing v1.1.0 release claims remain withdrawn. The selected Authorize.net direction supersedes the earlier Stripe proposal.

**Next concrete action:** Import the verified baseline and original branding after a secrets/private-data review, retain source-to-package provenance, then complete native staging and real installer/storage checks before extending the approved M0–M6 roadmap.

## 2026-10-12 — Owner confirms current version is 1.1.0

**Request:** Owner corrected the latest version to 1.1.0.

**Starting commit:** Local `1eab202` on `docs/verify-latest-baseline-20261012`; remote main remains `43bebd019a6f548d7a059b2c8e259beed3efe561` after another fetch.

**Actions and evidence:** Rechecked GitHub history, branches, releases and workflow runs. No newer application commit or published release appeared. Repeated scoped local filename searches without locating a matching 1.1.0 build. Asked the owner for its local folder, download link or repository/branch. Updated PROJECT_STATUS, SESSION_HANDOFF, CHANGELOG and this log to accept 1.1.0 as the owner-confirmed latest version while keeping artifact verification distinct.

**Limits:** No 1.1.0 source/package was inspected, no runtime tests ran, and no source, host, provider or remote GitHub changes occurred. The earlier 1.0.8 verification remains valid for that historical artifact only.

**Next concrete action:** Locate and verify the owner's 1.1.0 build, then reconcile/import the current source and reassess roadmap status. Do not start new feature work or downgrade the host using 1.0.8. Local documentation commit message: `docs: record owner-confirmed 1.1.0 current version`; parent is `1eab202`.

## 2026-10-12 — Specify web-design service payment forms

**Request:** The owner also wants payment forms for web-design services.

**Starting state:** Local commit `072449c7596c8c3af0e2b7b28149b2508203c152`; clean worktree; refreshed origin/main remains `43bebd019a6f548d7a059b2c8e259beed3efe561`. Created branch `docs/web-design-payment-forms`. The repository contains documentation only, and the owner's current 1.1.0 source/package is still awaiting location/verification.

**Changes:** Expanded P-03 and HC-11 with standalone branded Authorize.net forms, full service payments, fixed/percentage quote deposits, milestones, final balance, approved extras and opt-in maintenance. Defined admin payment-request configuration, secure links, portal entry, invoice/project ownership and balances, receipts/refunds, immutable terms and duplicate/timeout recovery. Moved payment-form delivery into M2, independent of 20i/hosting purchases; richer project/bundle integration remains M4. Updated README and current import prerequisites to 1.1.0, retaining 1.0.8 as historical evidence. Synchronized PROJECT_STATUS, SESSION_HANDOFF, CHANGELOG, PROJECT_DIRECTION, ARCHITECTURE, DECISIONS and this log.

**Verification:** `git diff --check` passed; all 21 relative documentation links resolved. These are documentation checks only. No current application source was available, so no PHP/database/browser/payment integration tests were run. New sandbox acceptance scenarios are requirements, not passing test results. No external gateway research or configuration occurred in this scope clarification.

**Blockers / next action:** Recover and verify the owner's 1.1.0 source, reconcile implemented capabilities against P-03/HC-11, then implement/test standalone service payments against Authorize.net sandbox. No actual form, charge, 20i operation, host change or deployment is claimed. Commit message: `docs: specify standalone web-design payment forms`; parent is the starting local commit above. Prepare this documentation branch for GitHub review with the current-version correction included.

## 2026-10-12 — Specify custom platform, admin webmail and team access

**Request:** Add admin email send/receive from multiple accounts with setup, reusable sending templates, additional administrator accounts, and unread-mail bells in admin and on the public site for admins only, linking to individual messages. During this work the owner additionally clarified: build our own WHMCS from scratch.

**Starting state:** `29a550621bcce454eee378e050d89777037382dc` on `docs/web-design-payment-forms`, clean and tracking origin. Refreshed GitHub and confirmed [PR #1](https://github.com/afterburn25/Cajun_Web/pull/1) is open and draft on that head. Current 1.1.0 application source remains unavailable for inspection.

**Changes:** Added `docs/ADMIN_MAIL_ROADMAP.md` and A-05 to A-08. Specified admin lifecycle/roles and mailbox grants; secure multi-account setup, send/receive and connection/error handling; templates with safe placeholders and previews; permission-filtered unread bells, message links, synchronization and public-cache isolation. Added `docs/CUSTOM_PLATFORM_BLUEPRINT.md` to explicitly map the owner's custom WHMCS-style direction to first-party modules, shared customer/financial data and direct provider integrations. Integrated HC-12 and milestones, and synchronized README, direction, architecture, decisions, status, handoff, changelog and this session log. Retained all prior web-design payment requirements and the current-version correction.

**Verification:** `git diff --check` passed; all 40 relative documentation links resolved, including four links to specific headings. Native provider, multi-user, browser/cache, template-rendering and notification scenarios are future acceptance criteria, not executed tests. No 1.1.0 source, runtime implementation, administrator creation, mailbox connection, customer message or deployment occurred.

**Next action / blockers:** Recover and verify 1.1.0, review existing authentication/mail/business structure, then build the custom platform foundation and admin/mailbox authorization before webmail/templates/bells; verify with isolated test accounts. Mailbox/provider/hosting capability checks remain pending and credentials must be configured securely in admin. Continue the existing documentation PR with commit message `docs: define custom platform and admin mail requirements`; parent is the starting commit above.

## 2026-10-12 — Complete client workspace, ZIP delivery and support access

**Request:** Identify gaps for full 20i/client-portal use; place every customer service and web-design project in one portal, with progress monitoring, updates and website ZIP downloads. During this work the owner also requested one-click access to any permitted client portal for support, including VPS SSH.

**Starting state:** Clean `docs/web-design-payment-forms` at `b4d96f9ca2acd9bf0241fbd5429c8b30a74df26f`; fetched GitHub and confirmed draft PR #1 is open on the same head. The current 1.1.0 source remains unavailable.

**Changes:** Added `docs/CLIENT_PORTAL_ROADMAP.md` with native 20i capability/entitlement mapping, explicit customer/service grants, P-05 project workspace, P-06 immutable private website ZIP/data handover, P-07 client updates/monitoring, A-09 audited one-click support portal sessions and A-10 authorized VPS SSH with a real transport feasibility gate. Distinguished admin mail/customer notifications, published deliverables/provider backups and portal impersonation/SSH privileges. Updated HC-02/07/08/11/12, milestones, roadmap, admin-mail scope, blueprint, README, direction, architecture, decisions, status, handoff and changelog.

**Research and verification:** Reviewed public official 20i API, StackCP grants/features, webmail, backup and SSH guides on 2026-10-12. Exact source links and product scopes are in the new roadmap. `git diff --check` passed; all 54 relative documentation links resolved, including nine heading links. No logged-in API, account entitlement, endpoint/SSO/browser-terminal, native database, live client/provider, SSH or archive/restore test ran. Documentation checks are the only local validation in this change.

**Next action / blockers:** Recover and inspect 1.1.0, inspect the reseller's actual API capabilities and grants, then implement the scoped platform/client foundation and verify individual services. Establish secure VPS support authentication and a provider terminal handoff or independently hosted gateway before claiming one-click SSH. No client impersonation, SSH connection, secret collection, payment/mail send, provider write, downloadable release or deployment occurred. Continue draft PR #1 with commit message `docs: complete client portal delivery and support access scope`; parent is the starting commit above.

## 2026-10-12 — Establish GitHub delivery workflow and Codex/Devin split

**Request:** Use GitHub for testing, incremental versioned releases and retained copies; keep documentation current for other agents. During the work the owner requested splitting tasks with Devin on separate branches and integrating as each finishes.

**Starting state:** `fccce34ed2238749766bf0879b87a26288ffe9d7` on clean `docs/web-design-payment-forms`; refreshed main remains `43bebd019a6f548d7a059b2c8e259beed3efe561`. PR #1 is open/draft and mergeable, with no existing checks/reviews. No published releases or application source. Read current instructions, direction, release constraints and roadmap.

**Changes:** Added scoped read-only GitHub documentation CI and a standard-library local-link/continuity checker; release/version/retention workflow and truthful release register; Codex/Devin work allocation, handoff and branch/integration rules. Updated AGENTS, README, PR template, installation guidance, roadmap F-04, decisions D-034/035, status, handoff and changelog. Current source prerequisite remains 1.1.0. No direct Devin tool is available; asked the owner whether to use a prepared GitHub handoff or an existing session link.

**Verification:** Local `python tools/check_docs.py` passed: 12 required documents, 23 Markdown files, 77 relative links including 10 heading links. Deliberate isolated fixtures passed five checks: valid links succeed; missing files, missing headings, outside-repository links and empty required documents fail. `git diff --check` passed. The fixture harness lives outside the repository in the local work directory. GitHub run links will be recorded after execution. Official GitHub workflow/releases/source-archive documentation was reviewed. These are documentation checks, not PHP/database/browser/provider/payment/mail/SSH/deployment acceptance; application checks remain pending source recovery.

**Next action / limitations:** Push this branch, verify the GitHub job, integrate the accumulated roadmap/workflow into main and prepare separate work branches. The owner selected a GitHub assignment to paste into Devin; create and link it without claiming Devin is running. No application release, version bump, provider operation, customer communication or host change is included. Then recover/import 1.1.0 and start the bounded implementation streams. Commit message: `ci: establish release workflow and Codex Devin coordination`; parent is the starting commit above.

## 2026-10-12 — Verify GitHub checks and publish Devin assignment

**Request / continuation:** Finish the authorized GitHub workflow and prepare the GitHub assignment the owner chose to paste into Devin.

**Starting commit:** Merged main `633f5a1e5c27f0ad626276f2bae75cbc2b88ea66`, following PR #1 with implementation commit `2f4da54534958c2dc9e21e72f264e3fde432434e`. Checkpoint documentation uses branch `docs/github-coordination-checkpoint` and commit message `docs: record passing CI and Devin handoff`.

**External actions and evidence:** Pushed the workflow/roadmap, marked PR #1 ready, verified [its GitHub check](https://github.com/afterburn25/Cajun_Web/actions/runs/38338949061) passed and merged it without bypassing protections. Verified [merged-main check](https://github.com/afterburn25/Cajun_Web/actions/runs/38339033073) also passed. Created [Devin assignment #2](https://github.com/afterburn25/Cajun_Web/issues/2) and atomically created `codex/platform-foundation` and `devin/public-experience` on GitHub from that main checkpoint. Updated status, handoff, coordination document and changelog with verified results and links.

**Tests and limitations:** GitHub's documentation job ran successfully on Ubuntu; local checker passed 12 required files, 23 Markdown documents, 77 local links/10 headings, plus five isolated success/failure cases and whitespace checks. These are documentation checks only. No application build, version release, source recovery, runtime/provider operation or deployment occurred. Devin has not been dispatched or confirmed running. Existing application source/package availability remains unchanged.

**Next action:** Owner gives issue #2 to Devin; recover and verify 1.1.0, refresh both branches to its imported source, agree precise file ownership/contracts and begin separately tested implementation PRs. No additional product-scope decision beyond D-034/035.

## 2026-10-13 — Source recovery checkpoint

Recovered 41 actual website files plus native/HTTP tests and a build tool from the nine preserved source fragments. Original external assets were fetched only at the recorded public paths and checked against recorded SHA-256 values. Executable source is now under `site/` on `codex/finish-1.1.0`. This supersedes the older no-source-in-Git blocker on this branch, NOT the unverified runtime or release claims. No production files, database, mail or payments were changed. Review `docs/SOURCE_RECOVERY_2026-10-13.json`; run native integration and authorization tests next. No 1.1.0 release is declared by source recovery.

Starting commit: 367fab9747eb2d525fb9756cb7ae75052361375c. Recovery verified payload hashes, individual file hashes, safe paths and absence of common secret formats. The first import attempt rejected the origins record format before writing files; corrected to its actual path-to-fingerprint map. No application code was executed by the recovery job.

## 2026-10-13 — Native regression fixes

First MariaDB acceptance found a real schema-idempotency failure: digit-bearing `sha256` was omitted by the column parser. Fixed both column-name patterns without weakening type/engine/unique-key checks. Local native tests then passed, and real unprivileged PHP HTTP tests completed fresh one-page installation and physical cleanup. Also fixed the existing lead editor's seven-status/six-label mismatch and corrected the test's portfolio route to the actual `work` endpoint. Tests now run extracted package bytes and cover incompatible schema preservation, cross-client/CSRF rejection and disguised executable uploads. Public service copy was polished and official comparison sources rechecked on 13 October. Complete browser acceptance and release publication remain pending. No customer host or database was changed.

## 2026-10-13 — Start independent permissions foundation during 1.1.0 completion

**Request:** Start this stream now; the owner has another worker finishing 1.1.0 and will update GitHub when done. During implementation the owner requested a Devin prompt to start its stream too.

**Starting state:** Refreshed main at `9e30564ee83d8e859c0d420ae42d6bbd805a6a57` with the other worker's integration-progress report. Read `codex/finish-1.1.0` tree/workflows: preparation exists there but no application source is tracked yet. Fast-forwarded our existing `codex/platform-foundation` branch from `633f5a1` to current main. Did not modify the 1.1.0 worker or Devin branches.

**Work:** Implemented standalone PHP access contexts, exact enabled mailbox memberships, known capabilities, default-deny decisions and stale/inactive identity handling in `foundation/access/`. Added synthetic policy tests and a PHP 8.2/8.4 GitHub workflow, without framework/database/runtime-service dependencies. Wrote the trusted adapter and later integration requirements. Updated agent ownership, D-036, roadmap/component status, architecture, release scope and current handoff. Revised Devin's assignment to start an independent portfolio filtering contract/tests before existing-site integration.

**Tests executed locally:** PHP 8.4.25 passed 142 permission cases; all four PHP files passed syntax checks. Documentation-link and whitespace checks are run before commit; exact counts appear in the local/CI output. GitHub execution has not yet been claimed at this checkpoint. No real login/session invalidation, native database, invitation, mail, browser, current-app/installer or target-host test occurred.

**Limits / next action:** Push and verify GitHub checks, then record the remote evidence. The owner can paste the revised assignment into Devin; no Devin session is started by this chat. When 1.1.0 source is published, inspect its actual identity/schema and integrate the component with additive migrations, lifecycle/last-owner controls and browser/native acceptance. Standalone code is not included in a deployable package or a new version. No live data, provider call, customer message, release or host change. Commit message: `feat: add isolated staff and mailbox permission foundation`; parent is the refreshed main commit above.

**Same-session remote verification:** Source commit `5cf3f01975dec7365d57415eecd061dda43a4823` was pushed on the Codex branch and opened as [PR #5](https://github.com/afterburn25/Cajun_Web/pull/5). [Branch run](https://github.com/afterburn25/Cajun_Web/actions/runs/38349906968) and [PR merge-ref run](https://github.com/afterburn25/Cajun_Web/actions/runs/38349921814) passed; branch logs confirm 142 policy cases and four syntax checks on PHP 8.2.34 and 8.4.26. The documentation check also passed: 12 required documents, 25 Markdown files, 93 relative links including 10 headings. Issue #2 was updated to the independent portfolio assignment requested by the owner. No source changed during the evidence follow-up; commit message `docs: record access policy CI and updated Devin assignment`, parent `5cf3f01`. Verify final PR checks before integrating the isolated component into main; integration with the actual application remains pending 1.1.0 publication.

## 2026-10-13 — Continue A-05 with staff invitations and native persistence tests

**Request:** Continue the roadmap on the Codex stream, preserving separate 1.1.0 and Devin work.

**Starting commit:** `e9549d4d0ea9bd4ff4c68dc6d4dd242d864af812` from main, fast-forwarded into `codex/platform-foundation`. No open PRs/releases appeared initially. Inspected the other worker's recovered source at `4a8b4c4` read-only: staff login/schema use random string auth_version, and existing staff resets and client invites are separate flows. No edit to that branch or source tree occurred. A read-only workflow-list request timed out; it is not evidence of that worker's test result.

**Changes:** Corrected access-context version inputs to opaque strings and added strict comparison regressions. Added PDO staff invitation persistence, digest-only tokens, bounded expiry, resend/revoke, transactional activation/consumption and explicit InnoDB DDL. Added native test fixtures including two-process concurrency, callback rollback and expiry cases; GitHub PHP 8.2/8.4 with MariaDB 11.4. Updated architecture, access/invitation contracts, ownership, roadmap, decisions D-037, status, handoff and changelog.

**Local verification:** 146 access checks pass on PHP 8.4.25; changed/new PHP files pass syntax checks. Documentation links and whitespace are checked before commit. No local native database test is claimed: no isolated local MariaDB executable/container runtime was found. GitHub native test execution and exact results must be recorded after the workflow runs. Reviewed primary PHP, MariaDB and OWASP references linked in the invitation contract.

**Scope / next:** Push and verify both native and isolated CI, then integrate the tested component. Final application account adapter, staff lifecycle/last-owner transaction controls, invitation UI/email, schema/installer/package integration and deployment remain pending final-source reconciliation. No real account, secret, message, provider or host operation occurred. Commit message: `feat: add transactional staff invitations and opaque session tokens`; parent is the starting commit above.

**Same-session native verification:** Source `718dbc4867ff7fe8cf214d3ce10410a9a41570a0`, [PR #6](https://github.com/afterburn25/Cajun_Web/pull/6), passed [branch native CI](https://github.com/afterburn25/Cajun_Web/actions/runs/38351494316) and [PR native CI](https://github.com/afterburn25/Cajun_Web/actions/runs/38351532791). Read logs confirm 146 access and 46 native invitation checks on each of PHP 8.2.34 and 8.4.26 against MariaDB 11.4.13, including exactly one successful concurrent redemption and callback rollback. Access-only and documentation workflows also passed. Local syntax passed eight files; documentation validation passed 12 required documents, 26 Markdown files and 108 local links including 10 headings before this evidence update. Evidence follow-up commit: `docs: record native staff invitation CI`, parent `718dbc4`. Incoming Devin PR #7 is ready for review; 1.1.0 worker advanced to `90ba4f5` with no ready PR at this checkpoint. Next: final documentation checks, merge #6, then review and integrate Devin independently; no application release acceptance is implied.

## 2026-10-13 — Owner priority override: finish and publish 1.1.0

The owner paused later roadmap work and authorized finishing, testing, merging and publishing the existing **1.1.0 website/client workspace** before further features. Completion branch: `codex/release-1.1.0`, started from `90ba4f5e0c824e422de8cb2232f5d3ee92b7bec6` on `codex/finish-1.1.0`, reconciled with main `b28a67cdbf20c81842cf73735ee4e6024030262f`. Actual application source is in `site/`; do not follow historical source-recovery blockers below. The original worker branch remains untouched.

Staff/access and invitation components from PRs #5/#6 are preserved outside the deployment package. Devin's PR #7 at `55d60670497d987286e64630e037e1ba2ec3a318` remains on his branch; the tentative local integration was aborted cleanly when priority changed. No portfolio changes were lost. Review follow-up after release: numeric category strings must remain strings, and duplicate published slugs need unambiguous detail behavior. Do not develop later roadmap work during this release task.

Next: run native MariaDB and real HTTP/Chromium acceptance on the reconciled candidate, extend missing release coverage, fix failures, publish only the exact tested main/package, verify the downloaded release, then post the authorized issue #2 handoff and stop. Issue #4 tracks completion. No release is declared at this checkpoint. No live hosting, customer data/mail, charges or provider operations are authorized.

**Work/evidence at this checkpoint:** fetched branches, tags, PRs, issues #2/#4 and releases; read both source/main records and the build/acceptance tools. No tag or release exists. Earlier full application run failed on an older SHA; corrected application head has no complete run. Reconciled documentation histories without replacing either stream; no shipping changes in this merge. Commit: `merge: preserve main foundations in 1.1.0 release completion`; parents are the exact source/main commits above. Validation and later release evidence will follow.

**Acceptance correction:** PR #8 combined run 38352334014 passed build and native database steps, then failed importing requests because tests/http.py shadowed the standard library. Renamed it, aligned candidate labels, fixed build UTF-8/line-ending and recorded-commit verification, revoked invites on client changes, rechecked activation expiry and tightened uploads/reduced-motion behavior. Local syntax/build and documentation checks pass; expanded native/browser acceptance remains required. Parent df57b40; commit message: fix: prepare release candidate and repair browser acceptance entrypoint. No publication or deployment.

**Expanded acceptance and browser defect:** `01e7706` added the exact 1.0.8 historical test fixture, actual update ZIP/admin migration, captured synthetic mail, saved admin/client workflows, installer failures and narrow/mobile/root browser cases. Run 38353670112 passed the native and expanded HTTP portions, then exposed a real browser bug: named action controls shadowed form.action, producing POST /existing/[object RadioNodeList]. Both growth and original inquiry scripts now use the explicit action attribute. Added real Chromium review/consultation submission and account-revocation coverage. Parent `01e7706`; commit `fix: submit public browser forms to their configured endpoints`. No CSP weakening, live mail, deployment or publication occurred; rerun the entire suite after refreshed installer hashes.

**Release documentation and browser continuation:** parent 7f8a6af. Actual browser forms now submit and persist; remaining run stopped on a test locator expecting Sign in without the displayed arrow. Corrected the locator and added browser protected-download/logout verification. Updated source layout, installation versus 1.0.8 upgrade, current roadmap/ownership/release status and official competitor reference review. No published release yet; run final combined acceptance before merging. Commit: docs: reconcile release instructions and browser acceptance.

### 2026-10-13 — Final browser acceptance coverage

Parent `f007803`; request remains finish and publish 1.1.0. Native and package-upgrade tests passed; run 38354497138 stopped at browser console classification after account revocation. Added scoped expected-422 handling with retained diagnostics, original inquiry regression, actual browser installer with saved-state and cleanup checks, and PHP log scanning. Wrapped consent prose for mobile layout and refreshed package hashes. Corrected obsolete documentation-job summary. No external mail or live operations; next action is complete candidate CI and visual review.

### 2026-10-13 — Complete candidate evidence and final browser matrix

Parent `91ef30d`; [branch run](https://github.com/afterburn25/Cajun_Web/actions/runs/38355321670) passed 50 native and 143 HTTP/browser checks, no PHP warnings, no unexpected browser errors or failed requests. The only console error was the asserted estimator 422 with exact URL and retained diagnostics. Visually reviewed implementation captures. Added explicit browser content/pricing/pipeline/client/media controls, mobile navigation, favicon heads and sitemap behavior; reduced-motion admin capture prevents mid-transition screenshots. No further shipping-source changes. Next: full rerun, merge and exact-main release acceptance.

Final matrix test correction (parent `7a0c0dd`): run 38356481572 rejected valid versioned favicon URLs because it compared the query suffix as part of the path. Parse the URL path and fetch the actual referenced icon for byte equality. Application source is unchanged; rerun the full suite.

### 2026-10-13 — Publish and download-verify 1.1.0; exact Devin handoff

Parent for this documentation-only change: `3703783e979abab38e8d72e9092c51dc835833b3`, application merged in PR #8 from `2486f27`. User priority override required completion/publication before later roadmap work. Exact-main [application run](https://github.com/afterburn25/Cajun_Web/actions/runs/38357271480) executed all steps and passed 50 native plus 157 HTTP/Chromium checks. Access/invitation/documentation checks passed on the same SHA. Clean local build matched CI ZIP/manifest bytes; implementation screenshots inspected. Draft assets were downloaded and verified, then [v1.1.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.1.0) published at `2026-10-13T06:39:50Z`. Fresh public-release download verified all seven assets, ZIP CRC/members/extracted hashes/version and tag target; machine-readable verification retained.

Updated README/status/handoff/changelog/roadmap/coordination/release/architecture/branding/install/test records and added complete Devin setup/contract/ownership instructions. Reread issue #2 and PR #7 (head `55d6067`); preserved that branch and acknowledged the separate estimator intent without claiming it was pushed or running. Next administrative steps: merge this documentation after checks, post the authorized issue #2 handoff, close #4 with evidence, then stop. No shipping source, released tag/assets, live hosting, customer data/mail, charges or provider resources changed in this follow-up.

### 2026-10-13 — Resume A-05 integration

**Roadmap resumed by the owner, 2026-10-13:** the new request "Continue With Roadmap" supersedes the completed release-task stop. Starting main: `ffdcef36bc71a10a456987808c70d4abd61afa9d`; released v1.1.0 stays immutable. Codex claims A-05 application integration on `codex/admin-accounts`: additional staff invitations/accounts, explicit website/client permissions, revocation and last-owner protection using existing identities and foundation components. Own staff adapter/UI, necessary auth/schema/installer/build integration and native/browser tests. Devin PR #7 remains at `55d6067` with its portfolio paths untouched. No live hosting/customer mail/payments/provider actions. Next: implement and verify the bounded admin-account increment, then integrate/release only after checks.

Refreshed refs/open PRs/issue #2. Worktree clean; main/release unchanged; no new Devin push. Inspected current authentication, installer, schema, invitation and access contracts. Implementation and tests pending; no new feature claimed complete.

## 2026-10-13 — Administrator browser and package-upgrade acceptance

Parent `9980930`; PR #10. Added synthetic real HTTP/Chromium cases for migration, setup links, account creation, selected-access landing/navigation, forged requests, deactivation/recovery and narrow layouts. Download the immutable 1.1.0 complete ZIP in CI and verify its published SHA before exercising the current update ZIP against populated original schema, credentials, projects, settings and retained private bytes. Fixed a 1.1.0 filename in installer-failure tests and wrapped long setup links. The first candidate run passed original and staff native database checks but browser acceptance stopped at that stale test filename. Local Python/PHP test syntax, refreshed shipping inventory and diff checks pass; new browser coverage remains pending. Next: fix any acceptance failures, inspect captures and finalize documentation before release.

## 2026-10-13 — Candidate application acceptance and final review

Parent `53cf246`; PR #10. Branch application run 38361215151 and PR run 38361219960 passed 50 original native, 31 staff native and 212 HTTP/Chromium cases, including the actual published 1.1.0 upgrade. Component and documentation checks passed. Reviewed desktop/320px administrator screenshots; forms fit without horizontal overflow. Final review explicitly rejects empty-password pending accounts at login (in addition to dummy-hash timing behavior), and adds authorized staff mutations, removed-grant revocation and own-account-only landing cases. Builder manifest now names the staff migration. Updated current status/handoff, administrator guide, migration/build architecture, decisions, roadmap, component history and fixture provenance. No Devin-path edits, publication, live deployment or provider operations. Next: exact-head checks, checked merge, exact-main package verification, retained release assets and publication records.

## 2026-10-13 — Verified 1.2.0 publication and handoff

Request: continue the roadmap. Application began from main `ffdcef3`, integrated through PR #10 at `f85627e05b171e4ee8508231e2ed6ee7b9374274` after checked branch/PR heads. Exact-main run 38362411560 passed 50 original native + 31 staff native + 216 HTTP/Chromium checks; independent access (176 each PHP 8.2.34/8.4.26), invitation (46 each, MariaDB 11.4.13) and docs checks passed. Full app used PHP 8.3.6/MariaDB 10.11.19. All eight versioned assets were built from exact source, compared byte-for-byte to CI, downloaded/verified as a draft, published at 2026-10-13T07:28:07Z, then freshly downloaded and verified again including extraction/hashes/version/tag. Desktop/mobile administrator captures reviewed. No known failing acceptance; live host/provider/SMTP remains unverified. No customer data/mail, charges, provisioning or production deployment.

This documentation-only publication commit has parent `f85627e05b171e4ee8508231e2ed6ee7b9374274` and message `Record verified 1.2.0 release and current roadmap handoff`. It updates current status, roadmap/core A-05 scope, release register/report/matrix/verification, installation notes and the Devin assignment. Prior release/tag/assets remain unchanged; Devin PR #7 remains intact at 55d6067. Next concrete backend increment is A-09 audited client-support sessions using current identities; A-10 SSH and providers remain later. Preserve the parallel portfolio assignment and refresh main before more changes.

## 2026-10-13 — Begin A-09 client support integration

Request: Continue With Roadmap. Inspected clean current main c09a15c, published 1.2.0, required continuity/requirements and actual staff/portal routes. Devin PR #7 remains 55d6067. Claim codex/client-support for the bounded A-09 backend/UI and necessary shared integration; preserve customer login identity, explicitly authorize support viewing/writes/downloads, prevent cross-client tab switching, forbid client approvals/security/payment actions, and audit staff identity. No runtime changes or tests yet; this is the ownership/continuity checkpoint. Next: implementation, native/real-browser acceptance and checked incremental release. No live deployment or provider activity.

## 2026-10-13 — A-09 implementation and acceptance expansion

Request: Continue With Roadmap. Starting main c09a15c; branch claim d1e7391 and implementation 0ea3e5b, PR #12. Changed support service/UI, existing portal/staff/client-record integrations, installer/schema/version and permission tests. Added CLIENT_SUPPORT guide, actual published 1.2.0 upgrade fixture and support HTTP/browser/failure-injection cases. GitHub application run 38366228989 passed 50 original + 31 staff + 35 support native checks, but Windows CRLF changed the installer inventory after Git normalization; complete browser suite did not run. Corrected canonical LF and made the build pipeline fail on builder errors. Local PHP syntax/build and 221 policy checks pass. Current commit (parent 0ea3e5b, message “Test support isolation and retained-package upgrade”) includes these corrections/docs; HTTP/browser evidence is pending, not claimed passed. No live hosting/customer operations and no Devin files changed. Next: run full candidate acceptance, resolve failures, review screenshots and release only from checked exact main.

## 2026-10-13 — A-09 candidate acceptance and final presentation

Parent c42d85f, commit message “Finalize support guidance and compact activity history”. PR #12 application run 38366999645 passed 50 original + 31 staff + 35 support native checks and 289 actual HTTP/Chromium checks. Actual published 1.2.0 update preserved config/guards/private media/settings/passwords/roles/pending invitations/client projects; previous upgrade and normal workflows still pass. Injected audit failures rolled back messages/uploads and withheld downloads. Browser narrow/desktop screenshots reviewed. Changed only account guidance and a scroll-bounded support audit panel after that run; refreshed installer hashes. Publication remains pending final candidate and exact-main checks; next checked merge and retained release verification. No outstanding observed application failure. No host/provider/customer change.

## 2026-10-13 — Verified 1.3.0 publication and Devin handoff

Request: Continue With Roadmap. Started main c09a15c, implemented on codex/client-support and merged checked PR #12 at `7856cccf230a1646d02a4a92ad4bfb1c280efa19`. Exact-main https://github.com/afterburn25/Cajun_Web/actions/runs/38367798995 passed 50 original + 31 staff + 35 support native and 289 actual HTTP/Chromium checks. Independent policy/invitation/docs gates passed. Earlier candidate LF/CRLF inventory failure was resolved before merge. Native/HTTP injected audit failure, identity/scope/CSRF/expiry/revocation checks and retained 1.2.0 package migration passed. Final desktop/narrow implementation screenshots reviewed. All eight release assets matched CI/local rebuild and passed draft and public fresh-download/extraction/version/tag verification. Published `2026-10-13T08:17:54Z`. No known failing acceptance; live hosting/provider/SMTP acceptance remains unverified. No customer operations or live deployment. Prior releases are immutable.

Documentation-only publication commit parent `7856cccf230a1646d02a4a92ad4bfb1c280efa19`, message “Record verified 1.3.0 release and roadmap handoff”, updates current docs, register/report/matrix/download evidence, installation and Devin assignment. PR #7 remains untouched at 55d6067; issue #2 will carry the authorized handoff after publication records integrate. Next bounded work: M1 catalog/price/billing groundwork, then M2 sandbox checkout; full mail, 20i, handover and SSH remain tracked. No background monitor or continuation is scheduled.

## 2026-10-13 — M1 catalog claim

Request: Continue With Roadmap. Starting main 9b836e8 clean and equal to origin/main; latest release v1.3.0 at 7856ccc; Devin PR #7 unchanged 55d6067. Read project direction, current status/handoff, coordination and HC-01/03/06/11. Existing estimator is indicative; existing manual invoices/client identities are retained. Claim codex/service-catalog and catalog adapter/UI, pricing/public cards, immutable invoice snapshots and required integration/tests. No implementation test yet; no new release/deployment. Next implement bounded service catalog and native/HTTP/package acceptance. Parent 9b836e8, commit message “Claim M1 service catalog and invoice groundwork”.

## 2026-10-13 — Catalog implementation checkpoint

Parent 1f1f445, commit message: Implement managed service catalog and reviewed invoice snapshots. Added catalog schema/service/admin/shared views; integrated current Packages page and project/portal invoices, explicit catalog permission and strict paid/void handling. Installer candidate 1.4.0 has 55 complete / 54 update files, 32 PHP files. Local PHP/JS/package syntax/inventory checks and 236 policy assertions pass. Native catalog tests include simultaneous duplicate issuance, audit rollback and price/privacy boundaries; native and HTTP/browser execution pending GitHub. Service guide documents supported scope and deferred gateway/tax/provider behavior. No live operations. Next run native integration and add actual 1.3.0 upgrade/browser acceptance before checked release.

## 2026-10-13 — Catalog upgrade and browser acceptance

Parent 484b55b; commit message “Test catalog privacy and retained 1.3.0 invoice upgrade”. First GitHub run 38370183513 passed 49 catalog + 116 earlier native and 289 existing HTTP/Chromium checks. Added actual checksum-pinned 1.3.0 UPDATE fixture preserving staff invitations/roles, support policy/audit, manual invoices, clients/projects and original files; HTTP catalog/private-field/stale-price/role/audit/idempotency/receipt/void cases and real Chromium create/publish/review/issue/client-view screenshots. Tightened maximum publishable unit+setup total and quote currency/period checks. Local syntax/build/docs checks pass; expanded HTTP/browser run pending, no claim of complete catalog release. No live customer/provider changes. Next complete acceptance, visual review, exact-main release gate and retained downloads.

## 2026-10-13 — Verified 1.4.0 publication and handoff

Request: Continue With Roadmap. Started main 9b836e8, claimed 1f1f445, implementation 484b55b, expanded acceptance a0bad55. Checked PR #14 merged at `0cdea7fbd17e887d3f4bc0574a411b157431b7df`. Exact-main https://github.com/afterburn25/Cajun_Web/actions/runs/38371380103 passed 165 native (50 original/31 staff/35 support/49 catalog) and 345 HTTP/Chromium checks; independent permission/invitation/docs gates passed. Actual published 1.3.0 update preserved original records/credentials/files; original upgrades/fresh installation and prior workflows passed. Reviewed actual catalog/public/invoice desktop/narrow screenshots. All eight release assets matched exact-main CI/local rebuild, passed fresh draft and published download/extraction/version/tag verification; published `2026-10-13T08:49:55Z`. No known failing acceptance; live hosting, tax/gateway/provider behavior remains unverified. No live deployment or customer/provider operation.

Documentation-only publication commit parent `0cdea7fbd17e887d3f4bc0574a411b157431b7df`, message “Record verified 1.4.0 catalog release and handoff”, updates status/roadmap/register/report/matrix/evidence/guides/Devin assignment. Earlier releases immutable; Devin PR #7 untouched at 55d6067. Post authorized issue #2 handoff after records merge. Next bounded work: M2 Authorize.net sandbox settings/payment-request foundation before verified design-only checkout; full platform milestones remain tracked. No scheduled background continuation.

## 2026-10-13 — M2 payment foundation in progress

Owner requested continued roadmap implementation. Starting main `f659adfc58d3176b233d629e157fd685599bc824`; latest verified release remains 1.4.0 at `0cdea7fbd17e887d3f4bc0574a411b157431b7df`. Codex owns `codex/payment-foundation`: protected separate Authorize.net credentials, sandbox authentication diagnostics, and invoice-bound prepared payment requests with expiry/revocation and explicit no-charge status. Shared permissions, schema, installer, tests and documentation belong to this increment. Devin PR #7 remains untouched at `55d60670497d987286e64630e037e1ba2ec3a318`. Card collection, transaction processing, verified webhooks, refunds and production activation remain disabled and pending separate acceptance. No merchant credentials or live operations are needed for isolated implementation tests; provider success will not be claimed from fixtures. Next: implement and test against native MariaDB and actual browser flows, then release only after complete exact-artifact acceptance.

## 2026-10-13 — Candidate 1.5.0 payment foundation

Implemented on `codex/payment-foundation`, parent `e0f8968` after main `f659adf`: owner-only protected separate Authorize.net settings, sandbox-authentication-only adapter and reviewed invoice-bound prepared requests. Existing invoices, catalog snapshots, permissions and client identities are retained. Card collection/production processing remain disabled. [Guide](PAYMENT_PREPARATION.md). Local PHP syntax, deterministic 58/57-file build and 251 permission checks pass. Native MariaDB/actual HTTP/browser and retained 1.4.0 upgrade tests are added; CI is pending and no provider success is claimed. No real credentials, live host, customer mail or provider operation. Next: run/fix full application acceptance, review screenshots, merge only when green and verify an exact-main release. Devin PR #7 is unchanged.

## 2026-10-13 — Payment candidate installer mismatch

Candidate `92d2dee` run 38374489193 passed native tests but actual Chromium fresh setup caught old 1.4.0 browser response guards/labels paired with the 1.5.0 backend. Correct all installer labels/handshake versions and add a build-time guard. Also reject a missing document-root value when checking external credential storage. Full acceptance is rerun; the failed candidate was not merged or released.

Candidate `11fa6b6` run 38375014845 passed native and prior browser flows, then stopped preparing the retained 1.4.0 catalog upgrade fixture. The seed mixed old schema/adapter files with current core, whose CWP_ROOT would load a second staff adapter when the old catalog authenticated its owner. Use the retained release core for this fixture and retain seed stdout/stderr for diagnosis. This changes test setup only; rerun the complete acceptance suite before integration.

## 2026-10-13 — Verified 1.5.0 publication and handoff

Request: Continue With Roadmap. Starting main f659adf, claim e0f8968, implementation 92d2dee, installer correction 11fa6b6, retained-runtime fixture correction a8689fc. Checked PR #16 merged at `3e95bdf81ca0126d99a69c41f2ff6eb0c08da1e1`. Exact-main https://github.com/afterburn25/Cajun_Web/actions/runs/38375934366: 227 native and 401 actual HTTP/Chromium checks, independent 251-check policy and 46-check invitations on PHP 8.2/8.4, docs/build passed. Reviewed actual masked settings and invoice request desktop/320px screenshots. Pinned 1.4.0 update and prior upgrades preserve original data/files. Initial installer handshake and test-fixture failures were corrected before merge; final acceptance has no known failure. Gateway responses use synthetic CLI fixtures; no real merchant credentials/provider success or live host acceptance.

All eight retained assets match exact-main CI/local rebuild and fresh draft/public download verification, including flat/extracted hashes, versions and tag. Published `2026-10-13T09:27:36Z`. Documentation-only publication commit parent `3e95bdf81ca0126d99a69c41f2ff6eb0c08da1e1`, message “Record verified 1.5.0 payment release and handoff”, updates status/roadmap/register/report/matrix/guides/Devin scope. Post authorized issue #2 handoff after records merge. Previous releases unchanged, PR #7 at 55d6067 untouched. No live deployment/customer/provider operations. Next: tokenized sandbox attempts with shared financial locking and verified reconciliation before design-only checkout; securely configured sandbox account needed for real acceptance. No background continuation scheduled.

Candidate 4158d37 failed the exact installer inventory gate in run 38379418282 before application tests: Windows file writes changed line endings before inventory generation. Normalized shipping text to repository LF, regenerated the embedded inventory, and added terminal-observation conflict protection. No release or deployment occurred.

Candidate f36e44a passed native acceptance but HTTP regression stopped at the legacy duplicate-receipt message (run 38379617682). Restored the established message while retaining the new lock/audit behavior, strengthened held/declined response consistency, and added catalog-lock, transaction-collision and preflight/manual-payment race tests.

Candidate 3ebc8a8 passed GitHub push 38379999309 and PR merge-ref 38380005173: 296 native (including 69 trial checks), 421 HTTP/Chromium checks, prior/fresh and actual 1.5.0 upgrades. Reviewed desktop and 320px sandbox screenshots; no overflow/errors. Independent permission/invitation/documentation checks pass. Final wording clarifies that client checkout remains disabled while owner sandbox tokenization is available. Real provider acceptance is still absent; this is synthetic gateway and intercepted browser evidence. PR #18 is the integration vehicle.

## 2026-10-13 — Continue roadmap: sandbox notification candidate

Request: continue accepted roadmap. Start `3dd6d5e82e22ad4a2ab27dbf54d08bde45c50a9a`, application v1.6.0; contract commit `9799e21`. Branch `codex/sandbox-webhooks`. Added callback, additive inbox schema, owner controls, shared atomic observation helper, installer inventory and native/concurrent/HTTP/browser/retained-1.6 upgrade tests. Local syntax/package/docs pass; GitHub acceptance pending, no known runtime result claimed. Provider research: official webhooks and credential guidance linked in notification guide. No real provider traffic, live deployment or customer data. Devin PR #7 retained. Next: execute acceptance, fix failures, checked PR integration and exact-main release verification.

## 2026-10-13 — Notification candidate review and acceptance

**Candidate acceptance passed — 2026-10-13:** `83404d88e8effe251597f4ce5e42990abe57ade7` / [run 38384479875](https://github.com/afterburn25/Cajun_Web/actions/runs/38384479875) passed 358 native (62 notification) and 451 actual HTTP/Chromium checks. Retained 1.6.0 update and earlier upgrade/fresh-install paths pass; desktop and 320px screenshots reviewed with no layout overflow or browser/runtime diagnostics. Signatures/reporting are synthetic. PR #20 review found no remaining release-blocking application issue; final PR checks, merged-main acceptance and draft/public asset verification are still required. No provider operation or live deployment.

Initial `f955b13` run 38383777980 rejected the synthetic fixture API Login ID because it exceeded the existing format limit. Shortened only the fixture. `4dcdaea` run 38384103903 passed 358 native checks but HTTP configuration correctly rejected a stale hard-coded gateway revision; the test now follows the retained current revision. Application validation was not weakened. Reviewed source for fixed sandbox endpoint, binary/raw-body HMAC, owner/support authorization, no provider mutation during receipt, minimal persistence, atomic processing/audit, credential rotation, stale/conflicting reports and preserved Devin boundaries. Release scope is in RELEASE_1.7.0.md. Next: final PR checks, checked merge, exact-main acceptance, eight-asset draft/public download verification and publication handoff.

## 2026-10-13 — Sandbox notification release published and verified

Request: continue roadmap. Start `3dd6d5e82e22ad4a2ab27dbf54d08bde45c50a9a` / 1.6.0; contract `9799e21`, branch `codex/sandbox-webhooks`. Added sandbox callback/schema/owner inbox, atomic observation helper, installer inventory, native/concurrent/HTTP/browser and actual 1.6.0 update tests. PR #20 merged as `1fe0670142755f86aee235fabe1847e5d118ee52`; [exact-main CI](https://github.com/afterburn25/Cajun_Web/actions/runs/38385343738) passes 358 native / 451 HTTP/Chromium checks. Independent policy/invitation/docs pass, screenshots reviewed. All eight release assets match exact-main CI/local rebuild, downloaded draft/public bytes, ZIP/extraction inventory and immutable tag. Documentation publication records do not change application bytes.

CI findings: `f955b13` / run 38383777980 stopped on an overlong synthetic API Login ID; fixture corrected without weakening validation. `4dcdaea` / run 38384103903 passed all 358 native checks and actual upgrade cases, then caught a stale hard-coded gateway revision in the HTTP test; test now uses the current stored revision. Final acceptance passed. No known failing release gate remains. Actual merchant delivery, key compatibility and real HTTPS/shared-host acceptance remain unverified. No production enablement, live deployment, customer mail/charge/data or provider operation occurred. Devin PR #7 preserved; authorized issue #2 handoff is publication documentation, not proof of Devin receipt/running. Next: actual sandbox acceptance and distinct real-money/client design checkout; full 20i/mail/ZIP/SSH remain pending.

## 2026-10-13 — Continue roadmap: invoice ledger candidate

Request: continue roadmap. Start `2eee5719ccd85d6661b2677dbc8c1415853d9ec3` / published v1.7.0, contract commit `61b0b47`, branch `codex/invoice-ledger`. Added additive ledger/migration, strict external receipts/corrections, admin/client views, remaining request balances, installer inventory and retained-1.7/native/concurrent/HTTP/browser coverage. Acceptance has not yet run. Earlier release bytes and Devin PR #7 retained; no live deployment/customer/provider operation. Next: local checks, GitHub acceptance and fixes, reviewed integration and exact-main release/download verification.

Candidate `105c940`, run 38389060369: native checks passed; an older HTTP assertion caught the renamed pre-ledger paid status text. Retained the original receipt label before ledger enablement and uses Marked paid after enablement, so imported legacy balances are not mislabeled as new receipts. Rechecking acceptance.

Candidate `2bc9a442385b2ee18505ca856cd6b0fadfae5bba` passed 427 native and 502 HTTP/Chromium checks in push run 38389358559 and PR run 38389365599. Reviewed PR artifact `42f04d4324c4347df1e64c6b5502aa040f805ab2` screenshots: print retained secondary navigation and low-contrast text despite functional tests. Scoped print theme to white/dark text, hid secondary navigation/progress and added a print contrast assertion; reduced document heading scale for narrow receipts. Rechecking exact candidate before integration.

Pre-merge review also found the payment-request form read displayed balance and review fingerprint separately. Snapshot both under the shared financial lock so a simultaneous external receipt cannot leave an old displayed amount paired with a newer fingerprint. Submission still revalidates under the lock.

## 2026-10-13 — Invoice ledger published and verified

Request: continue roadmap. Start `2eee5719ccd85d6661b2677dbc8c1415853d9ec3` / v1.7.0; contract `61b0b47`, branch `codex/invoice-ledger`. PR #22 merged as `c36a36e80c40485c9e3e7e6cdd4884ced25db961`; exact-main https://github.com/afterburn25/Cajun_Web/actions/runs/38390645499 passes 427 native / 503 HTTP/Chromium checks. Reviewed desktop/320px/print and source; independent policy/invitation/docs pass. All eight draft/public assets match CI/local rebuild, ZIP/extracted hashes and immutable tag. Original releases and Devin PR #7 retained. Earlier test findings and fixes are logged above; final gates pass. Actual merchant/target-host acceptance remains unverified. No real payments, refunds, customer mail, deployment or provider operation. Documentation records leave application bytes unchanged. Next: actual sandbox acceptance and real gateway/client design checkout; broader 20i/mail/ZIP/SSH remain pending. Issue #2 handoff is documentation, not proof Devin received or started work.

## 2026-10-13 — Continue roadmap: prepared client ZIP delivery candidate

Request: continue roadmap. Start main `cb7556a2837a0b374392541180e2ed2d670a1edd`, published v1.8.0, contract `16dcaaf`, branch `codex/client-deliveries`. Added bounded archive inspector, additive schema, private storage, owner review, client history and resumable downloads, support attribution, installer inventory and archive/native/concurrent/HTTP/Chromium/retained-1.8 acceptance tests. Local archive checks 50 pass; local 69-file/45-PHP package syntax/inventory check passes. GitHub acceptance and publication pending. Earlier releases and Devin PR #7 retained, no live deployment/customer/provider operation. Next: execute/review acceptance and fixes, checked merge, exact-main CI and draft/public download verification.

Candidate `103a0d9` / PR #24: GitHub run 38394864282 passes native/archive/database/concurrency checks and the retained-1.8 upgrade/download/credential preservation path. HTTP stops on a test comparing an unescaped URL to escaped HTML; corrected to inspect the parsed link target. Content review also adds explicit exclusions for common dotfile credential stores. Acceptance rerun required; no failed candidate released.

Candidate `b31c4dd` passes 539 native (55 archive + 57 delivery + 427 retained) and 568 HTTP/Chromium checks in push run 38395355168 and PR run 38395362036. All independent policy/invitation/documentation checks pass. Reviewed desktop/narrow screenshots; full-page capture had retained a scrolled viewport, making fixed headers appear mid-image. Test capture now returns to the top and includes the owner draft-publication form plus a later-draft privacy assertion. Application bytes unchanged by this capture/test refinement; rerun before merge.

## 2026-10-13 — Client ZIP delivery published and verified

Request: continue roadmap. Start `cb7556a2837a0b374392541180e2ed2d670a1edd` / v1.8.0; contract `16dcaaf`, branch `codex/client-deliveries`. PR #24 merged as `080f14aea8fe5c35e04e4eba62976609aef7cd67`; exact-main https://github.com/afterburn25/Cajun_Web/actions/runs/38396372080 passes 539 native / 569 HTTP/Chromium checks. Reviewed desktop/320px screenshots and source; independent policy/invitation/docs pass. All eight draft/public assets match CI/local rebuild, ZIP/extracted hashes and immutable tag. Previous releases and Devin PR #7 retained. Earlier test findings/fixes logged above; final gates pass. Full P-06 and actual merchant/target-host acceptance remain incomplete. No customer deployment/mail or provider operation. Publication records leave application bytes unchanged. Next: bounded independent portal/admin work or actual sandbox acceptance before production checkout. Issue #2 handoff is documentation, not proof Devin received or started work.

Publication-record PR #25 run 38397111942 passed native checks but failed the original narrow client workspace assertion after a proposal-decision redirect. This post-publication rerun does not supersede or alter the retained exact-release evidence. Inspection found the assertion measured immediately after the form action without waiting for response feedback or styles/fonts, and captured its screenshot only after passing. The test now waits for the confirmed decision response, network idle and font readiness, captures the screenshot before checking, and retains viewport/overflow diagnostics. No shipping application or released bytes changed. Rerun required before records merge; a persistent rendered overflow would require a new fix release, never replacement of 1.9.0 assets.

## 2026-10-13 — Continue roadmap: workspace activity candidate

Request: continue roadmap. Start main `552ccb7d8c73194e7304af4f903074b0032e53b2`, published v1.9.0, contract `a8c28d2`, branch `codex/workspace-activity`. Added durable-source projection, per-person read state, centers/bells, exact-source links, passive endpoint, installer and native/HTTP/browser/actual 1.9 upgrade acceptance. Local syntax and build refresh pass; database/Chromium/retained release gates pending. Main post-publication CI 38398055537 passed. No customer deployment, email or provider action. Devin PR #7 preserved. Next: finish acceptance and publish only exact-main verified packages.

Candidate c3b7cd4 run 38400915415 passed all native suites, including the new activity suite. The older Chromium gate counted canceled private activity fetches during navigation as network failures; diagnostics now retain failure reasons and exclude only activity endpoint net::ERR_ABORTED cancellations. Dedicated activity polling/error/revocation checks still enforce actual functionality. Integrated browser and release gates remain pending.

Run 38401457738 confirms canceled poll diagnostics were completed bodyless HTTP 204 responses; the exception is narrowed to that exact status and net::ERR_ABORTED. All 603 native checks and retained 1.9.0 upgrade checks pass. HTTP progressed through reviewed-page/new-arrival isolation; an empty second-client center correctly contains no mutation forms, so the cross-client attack test now obtains its own valid CSRF from its portal logout form. No shipping change in this repair. Remaining browser gates pending.

## 2026-10-13 — Workspace activity published and verified

Request: continue roadmap. Start `552ccb7d8c73194e7304af4f903074b0032e53b2` / v1.9.0, contract `a8c28d2`, branch `codex/workspace-activity`. PR #26 merged as `4649a913d40166b9874f9550e57b4186edfc43ab`; exact-main https://github.com/afterburn25/Cajun_Web/actions/runs/38402918742 passes 603 native / 630 HTTP/Chromium checks. Desktop/320px evidence reviewed, independent policy/invitation/docs pass. All eight draft/public assets match exact-main CI/local rebuild, hashes/safe extraction and tag. Earlier findings/fixes are logged above; release gates pass. Previous releases and Devin PR #7 retained. Full P-07, webmail/email dispatch and actual merchant/card checkout remain incomplete. No customer deployment/mail/payment/provider operation. Publication records leave application bytes unchanged. Next: bounded independent roadmap work or actual sandbox acceptance before production card checkout. Issue #2 handoff is owner-authorized documentation, not proof Devin received or started work.

During final release refresh, Devin PR #27 appeared at b69da9626448a2ddfdf30b425dc7c2d5e16d1582; estimator component only, old baseline and 40 isolated checks passing on PHP 8.2/8.4. It is retained separately and not integrated or included in 1.10.0. The next handoff prioritizes current-main adapter/integration review for both PR #7 and #27.

Final candidate `96c4397d3d34c7faf1b641ba5b7e56766339172d` passed branch run 38402179622 and PR run 38402187296: 603 native / 630 HTTP/Chromium checks. All six client destination types and reviews outside the newest hundred are verified. Desktop/320px screenshots were visually reviewed. Earlier cb57443 run was superseded/canceled by the added destination checks; no failing shipping behavior was waived. Exact-main run 38402918742 passed before immutable v1.10.0 publication.

## 2026-10-13 — Sequential Devin integrations: estimator candidate

Request: continue roadmap and integrate finished parallel work. Start: main `6f0120913c93eace492b6c5f333c754fea3bea31`, published 1.10.0. Contract committed first on `codex/integrate-estimator`; merge preserves PR #27 ancestry and current continuity records rather than stale pre-1.1 instructions. Reviewed all six classes/tests; found unchecked integer sum overflow and directly constructible inconsistent results. Added 25 boundary regressions and fixes; 65 total checks pass locally on PHP 8.4.25, plus syntax/documentation checks. Combined GitHub acceptance pending. No shipping files or providers changed. Next: checked estimator merge, then refreshed portfolio adapter review/release. Original Devin authorship and session records remain in PR #27 history.

## 2026-10-13 — Portfolio integration and reviewed estimator merge

Request: continue roadmap and integrate Devin work. Started main `6f01209`; contract `c1e7e40` committed before fixes. Estimator merge `9985012` passed 65 component checks on PHP 8.2/8.4 and full application CI 38405668799 before PR #29 merged at `39de8ff`. Shipping source unchanged by that merge. Devin refreshed both branches during review; latest estimator ancestry `65c2084` is included with no new code; latest portfolio `016a405` includes real-route adapter `c8821f8`, current 1.10.0 and documentation. Original branches untouched.

On `codex/portfolio-browsing`, preserve actual adapter/source and reconcile current docs. Fix numeric category key coercion, duplicate public slug ambiguity, `all` wildcard collision, whitespace mismatch, intercepted modified clicks, missing announced counts and identical-media comparison. Added 55 isolated checks total, native adapter boundaries and real HTTP/Chromium/no-JS/history/keyboard/320px acceptance plus actual retained 1.10.0 update including read markers. Package builder local syntax/inventory verification passes (80/79 files, 55 PHP); 55 portfolio and 65 estimator checks pass PHP 8.4.25. Combined CI/release pending; no full W-01/L-01 completion, provider operations or live deployment. Next: accept exact candidate, review screenshots, checked merge/exact main and immutable draft/public downloads.

## 2026-10-13 — Exact-main portfolio acceptance race correction

Starting main `332af19333a62d3dfcc90eb9cf34871a7fac50ec` after checked PR #30 merge. Branch and merge-ref runs 38407152040/38407159542 passed 618 native / 675 HTTP/Chromium checks and screenshots were reviewed. Exact-main 38408053057 passed native/full earlier suites and actual retained 1.10.0 update, then failed because a synthetic Alt-modified click correctly performed browser navigation asynchronously, interrupting the next test goto. No application assertion failed. On `codex/portfolio-acceptance`, observe whether the app canceled each synthetic modified click, then prevent the test event's browser default. Also exercise actual Ctrl-click and a new filtered browser tab. Python syntax/docs pass locally; combined rerun pending. No shipping-file change, release or live operation. Next: checked test-fix merge and exact-main acceptance before publication.

## 2026-10-13 — Devin W-02 slice: working motion pause + logo sheen

Request: continue the roadmap without stopping. Starting commit: `332af19` (main after PR #30 merged the portfolio integration). Branch: `devin/homepage-motion`. Owned paths recorded on issue #2.

Found a real defect: `site.js` toggled `motion-off` on `body`, but no CSS referenced the class — a manual pause left `float-cue` animating, `scroll-behavior:smooth` active, and `.reveal:not(.shown)` items already hidden by the observer stuck invisible. Moved the class to `documentElement`, appended matching `html.motion-off` rules (scroll-behavior auto, animation/transition kill, force-reveal pending sections, hero-transform reset) and added a restrained 1.5s metallic sheen on `.brand` hover/focus — suppressed by both pause paths via the universal kill rules. Added five Chromium acceptance checks in `tests/browser_acceptance.py` covering the toggle, `aria-pressed`, force-reveal and cue stop under a normal-motion context. `node --check` passes; `tools/build.py --refresh` regenerated installer hashes and verify passes (80 shipping files / 55 PHP). Native/HTTP/Chromium acceptance runs on PR CI. No schema, pricing, identity or shared-module changes.

## 2026-10-13 — Owner priority directory/packaging override

Owner supplied the explicit directory-refactor and three-package requirements while portfolio publication was pending. Stopped that publication; no 1.11.0 tag/draft/assets created. Merged already-tested test-only PR #33 to preserve accepted current baseline (`b975915`), then branched `codex/priority-directory-refactor`. Latest published COMPLETE is 1.10.0, independently inspected and checksum-verified; historical 1.0.8 also inspected. Both use root PHP entry files plus app/assets. Current integrated source has 80 shipping files; largest are installer 95,932 bytes, admin 55,118, studio-admin 23,453, core 19,493. Recorded complete starting inventory/hashes in STRUCTURE_BASELINE_1.11-CANDIDATE.json. PRs #31/#32 remain separate; no worker branch reset. Required scope/contract and priority instructions committed before moves. Refactor/migration/Apache/package acceptance pending; no live host inspection or deployment.

## 2026-10-13 — implement organized source and staged update candidate

From `d7c88d8` on `codex/priority-directory-refactor`: actual git moves/splits for routes, assets, shared security/filesystem/database code, admin actions/views, portal, public templates, one-page installer, canonical foundation bundles, safe storage/upload scaffolds. Staged UPDATE avoids blindly overwriting legacy/customized files; owner password/CSRF/backup confirmation, immutable inventory, retained lock, guarded backups and journal, bounded known-hash replacement/retirement, retry and recovery. Full-project/download builder rewritten for clean commits and three purposes. Existing HTTP upgrade suites now call the actual owner page. Syntax/generation pass locally; database/browser/Apache acceptance not yet complete. No main merge, tag, release or live deployment. Continue acceptance and implementation until owner priority is delivered.

### Directory acceptance expansion

Candidate `77e06fb` passed GitHub application run `38413364962`: 618 native assertions and 753 HTTP/Chromium assertions, including all eleven retained old-layout upgrade paths through the owner update UI. Added unprivileged filesystem fault/recovery/lock/customization acceptance and real Apache 2.4 mod_php with rewrite on/off, root/subfolder one-page setup and internal/private denial. Strengthened procedural direct-access 404 guards and preservation of edited server policy during retry/recovery. These new suites await their first CI run; prior passing results do not establish them.

### Expanded structure acceptance findings

Run `38414568043` retained passing application/component suites but exposed two new-harness gaps: deleting a staged bootstrap dependency failed before the migration verifier, and Apache foreground shutdown shared the CI shell process group. The owner entry now verifies the complete staged payload/history before application loading and returns a useful 503 restore message. Apache runs in its own session. Filesystem tests separately cover missing non-bootstrap payload and the HTTP harness covers missing bootstrap support. Further split studio and marketing screens into specific views, added stale dependency/CSS URL/secret/symlink build guards, explicit unknown-file reporting and repeat application after a recovered attempt with retained journals. Homepage desktop 1440×3161 and mobile 390×6080 PNG pixels from candidate `77e06fb` are identical to retained published 1.10.0 screenshots; admin/client screenshots retain dimensions with small content differences. New tests/review still pending; no release/merge/deployment.

### Server and compatibility acceptance follow-up

Candidate `57e28bf` passes all native/HTTP/Chromium suites plus 41 real filesystem/package/recovery checks. Apache run `38416307606` exercised the real installer, root cookie scope, exact assets and internal-file denials, then found the minimal server harness omitted mod_autoindex; it therefore did not produce the expected directory-listing denial response. Load autoindex to test the production Options -Indexes rule explicitly. Added Apache maintenance/resume checks, legacy authenticated POST and exact-body signed callback checks, standalone PHP guards and nested transport redirect assertions. Expanded assertions remain pending their next combined run.

### Final guard coverage expansion

Run `38417310703` retains passing native, expanded HTTP/Chromium and filesystem recovery suites. Apache now passes fresh root/subfolder setup, asset/session checks, internal denial, no listings and runtime-file protection, but the added interrupted-upgrade fixture needs its worker diagnostic captured before claiming that case. Added exact diagnostics, real builder negative tests (runtime data, missing/moved modules, root logic, weakened guards, symlinks and archive paths), and opcode-cache invalidation checks before activating changed PHP. Apache acceptance will use timestamp validation disabled to exercise stale-code prevention. These additions are not yet accepted.

### 2026-10-13 — initialize Apache cache before requests

Candidate 49eae7f passes the builder negative, native, HTTP/Chromium and filesystem suites. Apache run 38418701581 exposed a harness configuration error: OPcache was enabled per request after startup, which PHP forbids and the installed module crashed on. Configure the isolated Apache PHP scan directory at process startup instead. Preserve all distribution guides and per-artifact manifests in CI artifacts. The interrupted Apache update diagnostic remains to be reached and checked; publication remains pending.

The Apache legacy fixture also needs CWP_BOOT when reading the intentionally guarded synthetic installed configuration. Without that constant the fixture helper exits safely before copying config; it cannot exercise migration. Added the normal guard constant to that fixture-only CLI command. No production configuration changed.

## 2026-10-13 — Dependency status clarification and agent instructions

**Request:** Owner approved replacing ambiguous roadmap "Blocked" classifications for HC-07, HC-09, HC-10 and A-10 and explicitly instructed Codex/Devin not to stop independent development when an external provider/merchant/SSH prerequisite is unavailable.

**Starting main:** `b975915b091d72944dca068399cfd2df1db74e88` (repository tree `3222081021545afa6f9af952bf6bc31a7b21768f`). Read and preserved current AGENTS, roadmap, hosting/client roadmaps, coordination, status/handoff, decisions, changelog and historical log.

**Docs changes:** Added `docs/DEPENDENCY_GATES.md`; inserted mandatory policy links/brief instructions into `AGENTS.md`, `docs/ROADMAP.md`, `docs/HOSTING_AND_BILLING_ROADMAP.md`, `docs/CLIENT_PORTAL_ROADMAP.md`, `docs/AGENT_COORDINATION.md`; updated `PROJECT_STATUS.md`, `SESSION_HANDOFF.md`, `docs/DECISIONS.md`, `CHANGELOG.md` and this log. The four listed labels now describe external validation gaps independently from actual implementation and release status.

**Verification and limitations:** Verified current GitHub main and read the target documentation before composing changes; GitHub write and readback of committed docs will be checked. No application source, release packages, CI tests, provider API, Authorize.net merchant, payments, customer service or live host were changed, exercised or represented as working.

**Next:** Agents should begin with the next safe bounded implementation on current main, track precise external gates for real provider actions, avoid duplicate work/branches, and update evidence/status per reviewed implementation. No owner secrets should be placed in Git.

### 2026-10-13 — preserve concurrent main policy and Devin build work

Merged main 5d810b1 dependency-gate documentation without weakening the active structure priority. Reviewed and integrated Devin PR #42 (19afbfc, original history preserved): explicit UTF-8 reads/Node stdin make Windows builds work without environment overrides. Resolved overlapping diagnostics by retaining return code/stdout/stderr and retained the stricter CSS containment check. Added a synthetic Apache assertion proving old PHP is actually cached with timestamp validation disabled. Combined acceptance remains required before merge/publication.

### 2026-10-13 — complete candidate structure acceptance

Candidate 9e8dedf passes run 38420905892: 618 native, 782 HTTP/Chromium, 41 filesystem/recovery, 203 Apache and 16 builder negative checks (1660 total). Both rewrite modes include cached legacy PHP activation, maintenance owner resume, root/subfolder setup and protected uploads; actual eleven historical upgrade packages remain covered. Windows clean build succeeds with Devin PR #42 integrated. Added final path/branch handoff and acceptance record; cleaned only trailing source whitespace and regenerated installer inventory. Final commit checks, merge, exact-main verification and release download verification remain next; no deployment/publication yet.

### 2026-10-13 — directory correction merged, published and download-verified

PR #38 merged at de70c5da2e8f7bd35fbb92b9b829d0ab3e880372 after candidate 2faa2cb passed both application runs 38421763414/38421754313 and all component/documentation checks. Exact-main run 38422422077 passes all 1660 named application/structure checks. Built all three packages on clean Windows source and compared byte-for-byte to Linux CI; final homepage desktop/mobile screenshot pixels match published 1.10.0 and admin/client layouts were reviewed. Published immutable v1.11.0 with FULL-PROJECT, COMPLETE-UPLOAD, staged UPDATE, individual manifests/directory lists, common checksum/manifest, START-HERE, test report/evidence and migration/tree guides. Fresh draft and public downloads verify all 16 asset bytes, checksums, paths/members and tag target. Previous 1.10.0 asset fingerprints retained unchanged. Updated permanent rules, release report and precise Devin handoff/open-branch inventory. No live deployment, charges, provider writes or unrelated feature integration. Priority correction complete. Next: review bounded pending component/motion PRs against this accepted baseline while preserving external activation gates. This follow-up changes documentation only, not the released source or assets.

## 2026-10-13 — Continue roadmap: motion and organized-update candidate

Request: continue without stopping after verified 1.11.0. Starting main `02fc784`; preserved Devin PR #34 merge `69ed304` on `codex/motion-update-1.11.1`. Changed version/defaults, bounded update recognition/recovery/lock/scaffold preservation, deterministic builder inputs/sort, verified 1.11.0 inventory, test target abstraction and new organized-update/motion acceptance. Existing directory/package contract retained. Current implementation commit identifies itself through Git history; exact acceptance and publication remain pending. Devin #31/#32 review corrections assigned in issue #2; later foundation branches preserved. Next: combined CI, exact-main acceptance, publish/download verification, then next bounded roadmap item. No live provider/payment/mail/SSH or host deployment.

Acceptance follow-up: first PR run 38426685869 passes native, all 59 filesystem/recovery and 241 Apache checks, including actual 1.11.0 update/recovery. Chromium exposed a test-selector error: no-JavaScript pages correctly omit JS-added reveal classes. The assertion now checks actual server-rendered headings/cards and navigates the real work link. Shipping code is unchanged; rerunning the complete suite is required to reach the remaining browser workflows.

Second acceptance run 38427420414 reached actual browser setup and found stale 1.11.0 build literals in the split installer, which rejected valid 1.11.1 JSON responses. Replaced page/JavaScript/header/error labels with the canonical build constant. Also fixed two remaining installer brand-image references from the old assets path, added browser load/version assertions and a builder rejection case. This is a shipping fix; installer inventory is regenerated and full acceptance reruns before merge/publication.

## 2026-10-13 — 1.11.1 merged, tested, published and independently verified

PR #50 merged at cefcc41bcd5297715f2a49d6526900ebe8ad4eb4; exact-main acceptance 38429347222 passes 618 native / 800 HTTP-browser / 59 filesystem / 241 Apache / 17 builder checks. Preserved Devin #34 history; completed motion preferences plus owner update/recovery from actual published organized 1.11.0. Prepared all three artifacts from the same clean source on Windows and compared Linux CI bytes. Draft and public downloads verify all 16 assets, hashes, members, directories and tag. Prior 1.11.0 fingerprints unchanged. Screenshot dimensions/layout retained; subtle logo-edge changes measured and inspected. No live deployment or external activation. Updated exact evidence/current docs/Devin handoff; next actual work is L-01 application adapter while Devin corrects #31/#32 and other foundations await review. This record is documentation only and leaves release bytes immutable.

Next-increment preparation while exact-main acceptance ran: an isolated local estimator adapter draft matched the current shipping calculator across 1,512 priced/missing/malformed/quantity cases on PHP 8.4, without database access or shipping changes. This is prototype parity evidence only; component guards, actual application integration and native/browser/upgrade acceptance remain required. Devin correction priority reiterated in issue #2 comment 6150049591; no acknowledgment or completed fixes inferred.

### 2026-10-13 — Devin A-07 email-template foundation

**Request:** "Continue Without Stopping" — next feasible roadmap item (A-07 template/placeholder contract layer, no external gate). Codex review corrections on #31/#32 were delivered and pushed first this session.

**Starting commit:** `main` `855981b` (published 1.11.1). New branch `devin/email-templates`.

**Changed:** added `foundation/email-templates/` (PlaceholderRegistry, EmailTemplate), `tests/email-templates/run.php`, `docs/EMAIL_TEMPLATES_FOUNDATION.md`, `.github/workflows/email-templates.yml`, and additive continuity entries. No `site/` or shared-path changes.

**Tests:** `php tests/email-templates/run.php` — 50 synthetic checks pass on PHP 8.4.25; all files lint clean. Covers: allowlist construction/validation, unknown-placeholder rejection at draft and edit, draft→active→archived lifecycle, non-mutating edits, duplicate-as-personal, shared/personal/transactional insertion gating, honest preview with named unresolved fields.

**Failures:** none. **Blockers:** none for the contract layer; compose UI, persistence and sending remain Codex integration work.

**Next:** PR `devin/email-templates`; queue now 17 open Devin PRs awaiting Codex sequencing.

## 2026-10-13 — L-01 live estimator integration, 1.11.2 candidate

Continue without stopping from verified 1.11.1 and publication-record main 855981b. Branch codex/estimator-live-adapter preserves reviewed Devin #35/91a12cc in merge9070609. Added app/marketing/estimator.php, protected canonical foundation mirroring and delegation from existing growth_estimate; retained all live input/result/policy semantics, including early unpriced and malformed-rule handling. Added actual-release CLI parity worker/matrix, independent boundary expectations and direct-HTTP denial coverage. Local PHP 8.4: 3,084 parity/boundary cases plus 65 component checks pass; no database/network operation in this comparison. Added published 1.11.1 archive/file/shim inventory and download fixture; expanded Apache/filesystem acceptance across both organized baselines. Candidate version1.11.2 with regenerated installer; combined acceptance/publication pending. No schema changes, live host deployment, provider/merchant/mail/SSH activation. Next: combined PR acceptance, exact-main verification and three-package release; Devin #31/#32 findings remain assigned for correction.

Integration refresh: preserved latest Devin #35 head efc967a (publication-record merge only), retaining the 1.11.2 active scope and verified 1.11.1 records when resolving headings. Observed new #31 head23014c5 and #32 headdf0fdfe containing requested review corrections; review/testing of those corrected components is next, not yet application acceptance.

### 2026-10-13 — Devin A-07 corrections (Codex review)

**Request:** Codex 1.11.2 review found two preview defects on `devin/email-templates`: sequential `str_replace` re-scanned substituted values (a merge value containing `{{project_name}}` leaked that field's content), and malformed double-brace tokens were silently accepted so `preview([])` reported nothing unresolved.

**Changed:** `EmailTemplate::preview()` now substitutes in a single `preg_replace_callback` pass over the original subject/body — merge values are literal data, never re-scanned. New `BRACE_TOKEN` grammar check rejects any `{{`/`}}` that is not a complete `{{lower_snake}}` token (uppercase, spaces, dots, unmatched, stray, triple) at draft and edit. Preview values must be strings. Docblock and guide now record that rendered output is plain text requiring UI escaping, and that a mail adapter must reject header line breaks.

**Tests:** 50 synthetic checks (14 new regressions) pass on PHP 8.4.25 including `php -n` no-extension run.

## 2026-10-13 — 1.11.2 published and independently verified; next mail-template increment

PR #55 merged at 4fd0d5efdc9d94f1f5b29d76be857d944f5f8c36; exact-main acceptance 38433670555 passes 4873 named checks (3084 estimator / 618 native / 802 HTTP-browser / 73 filesystem / 279 Apache / 17 builder). Preserved #35 through efc967a. All three clean-source artifacts match Windows/Linux; all 16 draft and public assets, hashes, members, directories and immutable tag verified. Prior 1.11.1 fingerprints unchanged. Homepage desktop/mobile pixels identical; inspected real screenshots. No live deployment/external activation. Reviewed #56: 36 checks pass, but reproduced second-order substitutions and malformed placeholders falsely resolving; requested precise corrections. #31 still needs no-mbstring portability and #36 accurate APIs/semantics; #32 corrected 71 checks pass locally. Next actual work is A-07 template persistence/admin preview after component correction, with existing staff isolation and no real mail sending. Publication records are documentation only.

### 2026-10-13 — Devin HC-08 hosting lifecycle foundation

**Request:** "Continue Without Stopping" — next feasible roadmap item (HC-08 shared-hosting lifecycle contract layer, no external gate). Also completed: full independent review of Codex PR #58 (A-07 integration) — corrected `f3522f6` head confirmed in ancestry, single-pass/strict-grammar contract live, mirrors byte-identical, all 225 `1.11.2` inventory hashes verified against published bytes, all 6 upgrade shims verified against `build.py`, adapter safety reviewed (owner-gated migration, support-session exclusion, header-injection guards, atomic revision+audit). Review posted on the PR.

**Starting commit:** `main` `a1581d4` (published 1.11.2). New branch `devin/shared-hosting`.

**Changed:** added `foundation/hosting/` (HostingPackage, OperationResult, StackCpGrant, PlanChange, HostingAccount), `tests/hosting/run.php`, `docs/HOSTING_FOUNDATION.md`, `.github/workflows/hosting.yml`, and additive continuity entries. No `site/` or shared-path changes.

**Tests:** 61 synthetic checks pass on PHP 8.4.25 under both `php` and `php -n`; all files lint clean. Covers: package validation (unlimited/unmetered refused), honest operation results incl. non-retryable timeout, grant lifecycle, queued plan changes with over-limit downgrade refusal, full lifecycle, grace/dispute suspension protections, two-actor decommission.

**Failures:** none. **Blockers:** none for the contract layer; provider calls, reconciliation, billing and UI remain Codex integration work.

**Next:** PR `devin/shared-hosting`; queue now 19 open PRs awaiting Codex sequencing.

## 2026-10-13 — A-07 application integration claimed

Started from publication-record main a1581d4 on codex/admin-email-templates; preserve #56 through ef5d4f9. Shared-doc conflicts retain actual published 1.11.2 and historical component records. Original 36 component checks pass, but sequential substitution and malformed-token acceptance are reproduced and assigned to Devin before release. Next: implement additive staff template storage/revisions/permissions/UI and native/browser acceptance while corrections proceed. No production send or account connection. Issue #2 claim and verified 1.11.2 handoff are posted.

## 2026-10-13 — A-07 application implementation ready for combined acceptance

From merge 395e9b9 preserving corrected Devin #56 through f3522f6, added organized mail-template service/controllers/view/schema/migration, explicit staff grant/home/navigation, canonical mirroring and fresh-install setup. New candidate 1.12.0 tracks exact published 1.11.2 hashes and extends organized recovery fixtures. Added native permission/privacy/audit/concurrency checks, real admin/browser drafts/preview/archive/escaping checks and actual latest-package additive setup/file recovery. Fifty component checks under PHP 8.4 without optional extensions, syntax, documentation links and source whitespace pass; full native/browser/Apache acceptance is pending. No messages sent, provider changes or live deployment. Next: run combined CI, resolve findings, inspect screenshots and only then merge/test exact main and publish verified packages.

## 2026-10-13 — A-07 first combined acceptance and browser-test correction

PR #58 head 327c8b5 / run 38437059703 passes 56 new template-native checks, existing native suites, all 826 recorded HTTP checks, 87 filesystem/recovery and 327 Apache checks. The browser suite stops at its new template sign-in navigation wait: test expected admin/index.php without the existing ?view=dashboard query. Corrected that expectation; no shipping-source fix was needed. Retained failed-run evidence locally. Added meaningful native checks for refusing/adopting no populated unknown template state and for paginated immutable history. Next: rerun complete acceptance and inspect the actual template screens. Candidate remains unpublished.

## 2026-10-13 — Template activation assertion corrected from observed rendering

Run 38437939855 at f642f8b passes all 59 template-native checks, 87 filesystem and 327 Apache checks. Browser create/preview and direct HTTP lifecycle pass; the Chromium activation assertion compared lowercase against innerText rendered through the existing capitalize CSS. Server evidence shows successful 303 activation and follow-up GET. Use a retried DOM-text assertion and explicit saved database state. Inspected the actual desktop template preview; mobile capture follows activation in the next run. Updated the administrator grant guide and its organized source paths. No shipping-source change. Candidate release remains gated on the complete passing run and final screenshots.

## 2026-10-13 — Passing template application run and mobile evidence adjustment

PR #58 at 8905ed1 / run 38438750445 passes all named suites: 677 native (59 templates), 3,084 estimator, 832 HTTP/browser, 87 filesystem, 327 Apache and 17 builder checks. Inspected template desktop/mobile screenshots. The mobile image was taken mid-transition immediately after resizing the desktop viewport, leaving the moving sidebar over the form. Added a state-based wait for the closed drawer plus actual open/Escape/focus checks before capturing the final mobile view. No application CSS or behavior change; this improves visual/navigation evidence. Next: passing revised PR checks, exact-main acceptance and independent release verification.

## 2026-10-13 — Keep CSP intact while waiting for mobile drawer state

Run 38439521027 confirms template create/preview/activation plus native/filesystem/Apache passes, but the custom Playwright wait_for_function evaluates a string in the page and is rejected by the site's existing unsafe-eval prohibition. Replaced that test-only wait with built-in viewport assertions, verified against Playwright's documented locator API. The security policy remains unchanged. Also focus the template navigation link before Escape so the check actually exercises focus return to the menu button. Next: complete the revised browser evidence and release gates.

## 2026-10-13 — 1.12.0 template library published and verified

PR #58 merged at c526e960db13246c217f2b9d6f421c17b8d48261, preserving corrected #56 through f3522f6. Exact-main 38441087438: 5026 named checks (677 native, 3084 estimator, 834 HTTP/Chromium, 87 filesystem, 327 Apache, 17 builder). All 16 draft/public assets and tag downloaded/verified; Windows/Linux bytes match; prior 1.11.2 fingerprints unchanged. Homepage pixels retained; real template desktop/mobile screens inspected. Updated current release records/guide/Devin handoff. No live deployment, sending or external provider activation. Next: reviewed A-06 account/configuration/grant adapter from #59 while L-02/L-03 corrections remain independently tracked. The roadmap continues.

Publication follow-through: exact-main desktop/mobile template and retained admin/client workspace screenshots inspected; public home pixel comparison passes. Historical component headings and scope notes are explicitly superseded by the new integration record. Issue #2 integration claim is comment 6151630184. Draft A-06 vault/schema preparation stays outside released source pending component corrections and dedicated native acceptance.
