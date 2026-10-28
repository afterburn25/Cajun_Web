# Session handoff - Devin sole-owner continuation

## 2026-10-28 — feature refresh after installed notice failure

Continue from the normal merge of exact PR #266 head `cb916c0729525404ccc30f79902620026e174848`. Installed run `40667109804` reached the disabled-mail retry assertion after all earlier software setup/payment/tenant/fulfillment checks passed, then failed because `software_notices.php` lacked the direct migration import defining `software_commerce_ready()`. Devin fixed that at `62a1b601`, then repaired invoice-bound download-link preference and refreshed inventory. Do not duplicate those changes. The current combined merge preserves the integration branch's workspace hash and every independent licensing correction while taking the new feature bytes. Run the fresh exact-head installed/native/package/application workflows; older green runs are supporting evidence only.

Locally available verification on the merge: 283 artifact-helper checks, 69 build rejection checks, exact inventory hashes for the three refreshed files and whitespace all pass. `tools/build.py --refresh` reached dependency/vendor validation but cannot finish here because no PHP executable is installed; do not report PHP lint or native execution from this workspace. No merge to main, release, live transaction or deployment occurred.

## 2026-10-28 — feature head `9a1e9451` normal-merged

Devin advanced PR #266 with the same `software_*` dispatcher repair already present in the integration lane, its inventory refresh, and the missing canonical workspace import in `tests/software_commerce_browser_fixture.php`. The separate integration branch normal-merges exact feature head `9a1e9451`; no duplicate fixture patch or feature-branch rewrite was made. The combined site bytes retain the independently verified owner permission/readiness hardening. Run only the new combined exact head; parent CI does not certify the merge.

## 2026-10-28 — repair owner software action dispatch and readiness loading

The latest independent HTTP matrix reproduced actual controller failures rather than browser timing: valid owner/CSRF `software_commerce_setup` returned `Unknown administration action`, while `studio_license_deactivate` and `studio_license_reactivate` reached an undefined `software_commerce_ready()`. The separate integration lane now dispatches both `studio_*` and `software_*` actions to the existing studio controller, maps all `software_*` actions to owner-only `staff.manage`, and explicitly loads the commerce migration contract before the lifecycle/deactivate/reactivate readiness calls. Keep the existing real-form software setup and paid-off owner control assertions; the new fast routing contract is supplemental. Re-run exact-head policy, focused financing and installed application acceptance before accepting the fix.

The licensing integration PR base is now included in the existing full Application acceptance and Software focused installed acceptance triggers. The focused path list includes the admin dispatcher and staff permission file. Protected Application acceptance retains a per-SHA group and `cancel-in-progress: false`; do not redispatch queued runs. Treat a skipped, absent or parent run as no evidence for the current candidate.

The existing software package source, fast and full workflows also now admit the integration PR base. Require their exact-head results for generated-source integrity, all three direct-extract archives, checksum/member safety, no nested ZIPs and the pinned v1.25 delta audit. VERSION is still `1.25.0`, so this is candidate packaging evidence—not the real populated v1.25-to-next-version upgrade gate.

All remaining software-only gates now admit the same PR base: browser-source syntax, fast/native commerce databases, exact gateway amount, payment CSP, refund/void reversal, subscription lifecycle and signed webhook contracts. These are distinct from the real Authorize.Net sandbox workflows and must not be described as provider transactions.

Feature run `40664435482` exposed the next installed-test blocker at `Software browser fixture sandbox-on succeeds`: the CLI fixture loaded `payments.php` without the workspace import that defines `studio_ready()` and loads support-session state. The integration fixture now imports the canonical client workspace immediately after software-commerce migration. Preserve the real `payments_ready()` check and rerun focused installed acceptance; no gateway call or production behavior changed.

## 2026-10-28 — integrate focused domain-cooldown correction

The integration lane now includes exact PR #272 head `9eecf573`, stacked after #269/#270. `software_grant_recent_deactivation_blocks_add()` compares serialized audit IDs so equal-second replacement and later standalone deactivation events cannot be confused. The correction retains same-domain recovery, owner override, disabled/expired cooldown and the installation cap; 12 native service cases and a dynamic-port PHP8.2/8.4 × MariaDB/MySQL workflow are included. Run the new exact combined head rather than accepting #272's isolated evidence as combined acceptance.

## 2026-10-28 — feature head refreshed without overwriting Devin

PR #266 advanced by two commits to `726fc7db`. `codex/licensing-integration-review` normal-merges that exact head, preserving Devin's 404-before-layout correction for unavailable or foreign finance agreements and regenerating the combined installer inventory. The installed foreign-customer regression now requires HTTP404 plus `no-store, private` and `no-referrer`, not merely absent agreement text. Earlier exact-parent runs do not certify this merge. Continue only the new combined-head workflows; do not duplicate pending CI. The financing owner-reactivation browser diagnostic, complete PHP/database/browser/package matrices, independent review, real sandbox, next-version upgrade and merged-main gates remain open.

## 2026-10-28 — recover interrupted financing setup

The three financing setup migrations now persist an owner-audited pending marker before nontransactional DDL. A retry may resume only that exact recognized preparation while every existing partial table is empty; it validates all schema/indexes and clears the marker atomically with the version. Unmarked tables or partial tables containing records remain blocked for manual review. Run the updated financing native/source-contract lanes on the exact integration head before accepting this recovery fix.

## 2026-10-28 — payoff no longer overrides owner license control

Treat the prior auto-reactivation behavior as a high-severity commercial-control defect. A paid-off plan remains permanently free of financing suspension, but `website_licenses.is_active` is again an independent owner decision: validation does not self-heal it and payoff synchronization does not rewrite it. Updated native/browser cases prove owner deactivate/reactivate on the same key while financing remains `paid_off`; standalone domain removal remains blocked and replacement remains available. Run the exact-head hosted matrix before accepting this correction.

## 2026-10-28 — refreshed combined PR266 review lane

Continue from `codex/licensing-integration-review`, refreshed to feature `726fc7db` with review evidence `25e2d34f`, routing correction #269 and lifecycle correction #270. The stale forged-support assertion is resolved against the actual dispatcher: the POST omits the private finance view, requires the blocked-action copy and one exact denied audit increment, and proves both offer and payment-request rows unchanged. The one validation-rate wait now precedes the combined payoff security probes. Software release publication additionally refuses noncanonical, Windows-reserved and case-colliding ZIP members. Local non-PHP checks pass; run only the refreshed exact-head hosted matrix rather than redispatching superseded heads. PHP8.2/8.4, MariaDB/MySQL, installed browser/Apache, package, real sandbox, independent review, next-version v1.25 upgrade and merged-main gates remain mandatory.

## 2026-10-28 — continued native verification

`tests/license_finance_webhook_native_cases.php` adds 40 real SQL routing/lifecycle checks to the existing signed zero-down fixture in `tests/license_financing_native_cases.php`. This extends #270 without shipping changes or browser-fixture edits. Local PHP 8.2/8.4 × MariaDB 10.11/MySQL 8.4 passes all 40; preserve the separate full-Windows failure at the 0600 private-file requirement. The prior correction commit `168c4999` now has a complete passing hosted native matrix (588 recurring checks per leg) and 55 focused regressions on both PHP versions. Re-run the normal hosted matrix for the enclosing test commit; installed acceptance remains outstanding. The existing integration writer retains PR #266 and the integration branch. [Current report](docs/CODEX_LICENSING_REVIEW_2026_10_28.md).

## 2026-10-28 — delayed event correction for integration review

Independent review additionally reproduced delayed financing notifications clearing reversal/replacement fences and downgrading confirmed cancellation/suspension. `codex/licensing-lifecycle-ordering` is stacked on #269 and changes only the lifecycle service, its regression/workflow and generated hash plus documentation. Integrate the routing correction first, then this focused commit; refresh inventory if resolving changes from other branches. All 45 isolated lifecycle cases pass locally; native SQL, full installed acceptance, real sandbox and release remain unverified. The existing **Complete Licensing Integration** chat retains integration ownership. [Evidence](docs/CODEX_LICENSING_REVIEW_2026_10_28.md).

## 2026-10-28 — independent correction for the licensing integration writer

The owner explicitly retained the **Complete Licensing Integration** chat as writer of `codex/licensing-integration-review`; this local reviewer prepares the separate `codex/licensing-webhook-isolation` correction. Frozen parent `6045b801`, refreshed feature `5ba2a37d`, main `fdf40a25`. Review/integrate the two optional-financing webhook routing fixes and regenerated hashes, then test the combined candidate. [Review report](docs/CODEX_LICENSING_REVIEW_2026_10_28.md) records 10 passing local regression cases, 12 existing suite passes, baseline package hashes, historical failure causes, unavailable sandbox secrets and remaining release gates. No approval, merge, release, live charging or deployment is claimed.

## 2026-10-28 — owner reconciliation commit checks

One-time/subscription/replacement owner reconciliation now reauthenticates inside serialized mutations. Four new native rejection cases pass on local MariaDB; the full local suite stops at the unchanged Windows private-permission guard. [Exact source-bound partial evidence](docs/evidence/pr266-owner-reauth-native.json). Continue original Linux CI, full application/package acceptance, real sandbox and independent review.

## 2026-10-28 — financed replacement recovery hardening

The Codex companion now also repairs replacement enrollment after the original start date and requires verified terminal retirement of the prior ARB before creating parallel billing. Policy and synthetic gateway regressions pass; full installer refresh passes. Hosted native/browser and independent security/real-sandbox gates remain open. [Exact evidence and current gates](docs/PR266_INTEGRATION_ACCEPTANCE.md).

## 2026-10-28 — PR266 Codex correction lane

Continue `codex/licensing-integration-review` from `6045b801`, preserving Devin’s feature branch. [Evidence and remaining gates](docs/PR266_INTEGRATION_ACCEPTANCE.md). Next: inspect original companion-PR financing/browser and document-roots matrix results; fix actual next failures, complete security review and source-bound release acceptance. Do not redispatch pending checks or treat local contracts as installed acceptance. Production payments/deployment remain owner gated.

## Port-PR sequencing resolved — 2026-10-26

PRs #257–#260 (vps-uncertainty, ssh-access, provider-catalog, domains ports → `devin/full-site-integration`) each carry a byte-identical copy of the shared `foundation/desk` + `foundation/notifications` layer (sha-verified across all four branches) — merge order is arbitrary and component code never conflicts. The only shared conflicts are the usual docs/config union files (AGENTS, CHANGELOG, STATUS, HANDOFF, SESSION_LOG, ROADMAP, integration queue, `application-tests.yml`, `provider-cloud-sites.yml`) plus each port's own new workflow. Still gated on hosted evidence — proceed 257→260 once runners free.

## CI queue-hygiene fix — 2026-10-26 (in flight)

Owner directive: audit all workflows, fix queue accumulation, protect release certification — implement, not recommend. Branch `devin/ci-concurrency-hygiene` from main `a331599a`.

**Root cause** (documented in `docs/CI_HEALTH.md`): 32 development-check workflows used concurrency groups containing `github.sha` — each push created a new size-1 group, so `cancel-in-progress` could never supersede an obsolete run; 16 of them additionally set `cancel-in-progress: false`. Two dev checks had no concurrency at all. Combined with ~35 workflows firing per licensing-lane push and a ~4-5-slot effective account runner ceiling, the backlog reached ~1000 queued runs (repo-reported ~2500). Not a GitHub outage — status page green; the accumulated runs were legitimate queue depth.

**Fix**: all 32 sha-scoped dev checks regrouped to `<key>-${{ github.event.pull_request.number || github.ref }}` with `cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}` — superseded dev runs cancel per-PR/per-branch; main pushes serialize for evidence. `mail-receiving`/`mail-transport` gained the same block. Protected non-canceling runs untouched: `application-tests` (per-ref+sha+mode+matrix, deliberate evidence preservation), `release-licensing`, `release-upgrade-diagnostic`, `stackcp-access-read-diagnostic`, `import-preserved-source`, `twentyi-catalog-schema`, `twentyi-cloud-root`, and the inert `codex/finish-1.1.0`-scoped recovery workflows. All 76 workflow files parse.

**New**: `tools/ci_health.py` (queued/running counts, oldest pending age, per-branch/per-workflow backlog, long legs, cancellation stats) + weekly `ci-health.yml` (Monday 06:00Z, step-summary report, cannot self-flood) + `docs/CI_HEALTH.md` diagnosis table + permanent rules in `AGENTS.md`.

**Not done / limits**: docker layer caching across runs (42 workflows rebuild `tests/invitations/Dockerfile` per leg) is a documented follow-up, not implemented — needs buildx+gha-cache evaluation first. `main` branch protection returned 404 — no required checks exist to preserve or break. PR #266 coverage untouched; its lanes keep their own workflow copies until they merge main.

## Merge-first delivery cycle — 2026-10-22

Owner directive (reaffirmed): completed features reach `main` in small, verified increments — a completed branch is undelivered until its accepted implementation lands on `main` and the resulting main commit verifies. Full standing text: `AGENTS.md` "Standing owner directive - merge-first"; queue: `docs/DEVIN_INTEGRATION_QUEUE.md`.

Delivery 1 in flight: **PR261** `devin/reset-email-template-main` — merge `e334678e` of the green accepted source `467ec1bd` (original full-green run 39422487199) into main `1067757d`; docs-only conflicts resolved preserving both sides; local `php -l` clean, `tools/build.py --refresh` no-drift, artifact verifier `{"passed": true, "checks": 230}`. Hosted acceptance running; independent review pending; merge on green then verify main.

Next candidates after PR261: cloud website intake and directory-listing controls — confirm actual dependencies against current main; do not assume earlier leaf runs certify a standalone candidate.

Staging lanes `devin/preflight-commercial` and `devin/full-site-integration` remain in flight for the wider backlog and are not the delivery path for small increments. FSI browser-lane failure at `67d70e4a` was a test-side `NameError` (`ROOT` missing from `mail_message_cases.run_browser` env pull); fixed at `9ec4e2b1`.

---

## 2026-10-19 — reset email template implementation

Parent `a86f6f4fa592e6f715a9420aec8de4622258642e`; branch `codex/twentyi-reset-email-template`; enclosing commit **feat: review and confirm 20i reset email templates**. Root owns UI/shared transport and acceptance wiring; Catalog owns encrypted runtime/storage; Domains owns typed provider contracts and independent review; VPS owns additive SQL/browser tests. [Exact scope and next gates](docs/TWENTYI_PASSWORD_RESET_TEMPLATE.md).

Continue actual exact-source native/browser acceptance, inspect produced images, integrate sequentially and complete combined-main/release gates. Do not interpret local modeled checks as installed acceptance or rerun unchanged queued jobs. My20i sign-in for the missing full catalog/registrar schemas remains pending. Full roadmap goal stays active.

Implementation and independent source review are finished with no remaining concrete findings. Local foundation, modeled runtime/forms, handler regressions, 68 root source checks and 230 acceptance-helper checks pass in their documented scopes. Installer refresh, all shipping PHP/JavaScript checks, documentation and whitespace pass; generated shipping mirrors/inventory are included. Detailed receipts and the preserved initial Windows extension-path harness failure are linked from the feature guide. No native SQL or installed browser result is claimed.

Payment-link fix is separately committed/pushed as `b1948eeb`, draftPR253 targeting DevinPR202. Its original full run39247478772 was manually dispatched once because that companion base is outside the full workflow PR branch list. PR252 source632362f6 and all original evidence stay preserved. Published1.25.0 is unchanged; no live deployment.

## Owner request recorded 2026-10-19

Transfer all remaining CajunWebPro work to Devin alone. Start by reviewing and merging completed Codex AND Devin branches into main; then finish the entire 20i and non-20i roadmap, including integration and releases. The authoritative instructions are [docs/DEVIN_FULL_SITE_TAKEOVER.md](docs/DEVIN_FULL_SITE_TAKEOVER.md) and [DEVIN_START_HERE.md](DEVIN_START_HERE.md).

This supersedes old non-20i-only and Codex-only integration/release restrictions. Devin owns shared billing/auth/schema/navigation/installer/CI changes and final delivery, subject to existing tests, review, protections and operation-specific approvals. Other workers must preserve their work and not start overlapping implementation; their external sessions have not been stopped by this document.

## 2026-10-18 — client cloud website journey

From main `e397d9a6e293850e8dff61a593e3ba39ee178400`, branch `codex/twentyi-client-cloud-websites` implements primary-client request/cancel, owner decline/explicit creation, retained outcomes and exact returned-package confirmation navigation. Client dispatch and the existing shared grant commit atomically; replay never sends again. The current intake requires a complete cloud observation within15 minutes. [Behavior, authority, evidence and remaining gates](docs/TWENTYI_CLIENT_CLOUD_WEBSITES.md).

Local evidence:141 document and98 modeled UI checks per PHP mode; four actual module cold loads;34 legacy-context checks per mode;271 final artifact-helper checks;35 portable installed-update guards;19 actual published-baseline negative checks. Original1.24 and1.25 ZIP bytes validate against their retained member inventories, with all27 earlier release maps unchanged. Independent service, browser, installed-update and shared-wiring review is recorded in the feature guide. Native SQL/concurrency, packaged browser pixels, installed Linux/Apache execution and full combined acceptance remain pending. No live operation, new release or deployment; published1.25 and all separate original CI handles remain unchanged.

## Starting source and actions taken

Main read before the handoff: `a86f6f4fa592e6f715a9420aec8de4622258642e`. Latest release read: v1.25.0 at `b79c67110829126916e3a65e616191542448bccc`. PR216 is still an open candidate, not a new publication. Refresh all facts at Devin startup.

This session writes the takeover directive and current root/coordination instructions, and updates the assignment issues. These changes are documentation only. No application PR was merged, no PHP/schema/installer/workflow/version file changed, no application acceptance was rerun and no new software release or deployment occurred. No active Devin session or external-worker acknowledgement is claimed. The enclosing commit identifies the instruction revision.

## Devin's first executable milestone

1. Preserve local work; fetch current main, all relevant feature branches and paginated PR/run/review evidence. Check for concurrent writers.
2. Create or inspect a Devin-owned integration branch from current main. Build `docs/DEVIN_INTEGRATION_QUEUE.md` and a stable requirements tracker for the entire project.
3. Identify the first ready merge group across both workers. Fix specific mature blockers, including contacts fixture/payment-link corrections where still applicable. Preserve dependencies and current-main provider/billing hooks. Do not merge all open drafts blindly.
4. Run required combined acceptance/review and normal-merge ready slices into main. Preserve all old source/failed evidence. Continue an independent task while genuinely blocked hosted gates wait.
5. Complete exact-main acceptance and publish the next appropriate verified incremental release, with FULL/COMPLETE/UPDATE assets, migration notes and actual build screenshots. Download and verify every release asset.
6. Continue every remaining 20i and non-20i capability. Do not stop at a plan, one PR or one release, and do not wait on Codex to take action.

## Source and gate caveats

Read the takeover document's PR map. The open catalog/capture family, hosting commercial stack, nameserver/folder/runtime/subdomain/StackCP candidates and Devin mail/project/billing/backup/contact stacks are independent or stacked histories, not one accepted combined release. PR256 is a pure VPS component on an old foundation application tree: never deploy that tree. PR250 is a contacts test correction; PR253 is a payment-link privacy correction. Current acceptance must be checked before carrying forward old failures or old passes.

The prior 497-candidate cleanup disposition is recorded, not a job to restart. Preserve required original catalog/capture/application/release runs. Queue delay alone never authorizes duplicate dispatch or a passing claim.

Do not deploy live, issue real provider writes/charges/refunds/customer mail/SSH, buy resources or overwrite old tags/assets under this handoff. Record exact external prerequisites and continue unblocked implementation. Do not commit secrets or customer data.

## Prior full handoff and evidence

The complete earlier handoff, source pins, local evidence paths and dated run IDs remain at [pre-takeover SESSION_HANDOFF.md](https://github.com/afterburn25/Cajun_Web/blob/a86f6f4fa592e6f715a9420aec8de4622258642e/SESSION_HANDOFF.md). Also read [current continuation evidence](docs/ROADMAP_CONTINUATION_2026_10_19.md), [release workflow](docs/RELEASE_WORKFLOW.md), [whole roadmap](docs/ROADMAP.md) and [20i requirements](docs/TWENTYI_REQUIREMENTS_EVIDENCE.md). Historical allocations do not override the new owner directive; historical results remain historical.
# 2026-10-28 licensing key-entropy handoff

The PR266 integration candidate corrects new license generation from 64-bit truncated keys to the intended 128-bit value, formatted as eight four-hex groups. Existing keys remain accepted and no migration rewrites stored identities. Run the exact refreshed native/browser/package matrix before merge.
