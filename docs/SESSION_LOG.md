# Session log

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
