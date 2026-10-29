# Session handoff - Devin sole-owner continuation

## PR #266 release stabilization — 2026-10-27/28 (in flight)

Owner directive: clear genuine release-blocking failures on `feature/software-license-commerce-20261025` (PR #266), no feature expansion; blocker-matrix discipline — failing run → root cause → smallest repair → local targeted regression → cross-component check.

**Cleared this arc (oldest→newest):**

- `5e2db4b` — payment-links restack raised `payments_ready()` to schema `2` but fixtures seeded `'1'`; seeded `'2'` in `ledger_native`, `notifications_native`, `domain_renewal_dispatch_native` and added the canonical `payments_migrate` submit to `recurring_billing_upgrade_cases` (all verified locally: 108/62/84 checks).
- `2a18049`/`73d4c37`/`6045b80`/`5ba2a37` — financing lane: fixture dir/file modes unreadable by apache (`0700`/`0600` → `0755`/`0644`); stale `pending_initial` plan shared `job_id=1` locking the active release (dedicated `studio_jobs` row); unqualified `RecurringBillingTerms` in fixture global scope (FQCN).
- `cbffd00` — `financing.php` 404 status set after layout output (moved offer lookup before `site_head`); **software dispatch**: all 15 `software_*` admin actions lived in `studio_admin_action` but the POST router only forwarded `studio_`-prefixed actions → added `software_` prefix. Verified locally: owner preparation stores `software_commerce_version=3`.
- `9a1e945` — software browser fixture never loaded `app/clients/workspace.php` → `payments_ready()`/`studio_ready()` fatal.
- `4b17cd3` — `software_notices.php` called `software_commerce_ready()` without its defining migrate file → fatal on owner delivery retry.
- `94d24ce` — downloads query `ORDER BY l.id` surfaced the trial row first so `$shown` dedup bound the link to the trial license → invoice-bound rows first via `(e.invoice_id IS NULL)`.
- `a0d21c1` — trial license's own add-install control leaked onto My Software → gate the control on `e.invoice_id !== null`.
- `4f75380` — support-blocked POST rendered generic 403, losing the blocked-action reason → `fail_page($error,403)` before the gate (avoids the CSP contract's source-ordering assertion on `site_head`).
- `93bfa54` — test tail still looked for retired `studio_license_deactivate`/`reactivate` forms → `software_license_lifecycle` + transition + reason (assertions unchanged).
- `2c0579c` — `software_commerce_ready()` called without migrate require in `software_license_lifecycle`, `studio_license_deactivate` and `studio_license_reactivate` branches → added `require_once` to each. Suspend/resume verified end-to-end locally (is_active flips, portal renders 'Standard · Suspended'/'Active', KEYPAID key shown).
- `5326ecb` — sealed `Application acceptance` failed at `2c0579c` on the payments-schema-`2` class (recurring_setup 403, payments_trial_setup form missing): every cumulative upgrade fixture (1.5→1.10, release_125 later-records, `existing` legs) now submits the canonical `payments_migrate` form before payments-gated views; record compares scoped to pre-upgrade columns (`link_hash`/`link_secret` added by migrate); settings checks declare `payments_schema_version` '1'→'2' and assert `'2'`.
- `d062bd5` — apache leg of release_125 upgrade acceptance emitted `PHP Warning: Undefined variable $mail_drafts_account` at `admin/views/studio/licenses.php:59` — stray `$` made a variable-function call instead of `mail_drafts_account($a['id'])` (functions loaded via `financing_offers.php`→`app/mail/drafts.php`). Fixed; apache leg green on `d062bd5`.
- (next) — `native-and-browser` leg on `d062bd5` (AND `main` run 40457205972 on 10-27, identical signature): `Page.goto` 30s timeout on `?view=provider-settings&stackcp_page=N&stackcp_access_page=N`. Root cause is a real perf bug: `ProviderStackCpReadProjection::accessFamilyReady`/`familyReady` re-verify the whole StackCP history chains per render with ~6 (access) and ~3 (family) correlated `audit` subqueries PER history row — O(chain × audit-scan) as suite-accumulated history grows past the 30s goto budget. Rewrote both walks to grouped LEFT JOINs (COUNT/MIN(id) by detail, and by detail+user_id for actor counts — `audit` is `utf8mb4_bin` so grouping is byte-exact, identical to BINARY comparisons; COALESCE→0 preserves COUNT miss semantics; NULL min-id preserved). Verified: `tests/stackcp_access_read_snapshot_native.php` 78/78 — byte-identical HTML vs pinned original, exact projections, fewer statements.

**Result**: sealed `Application acceptance` on `d062bd5`: apache leg green; native-and-browser red only on the StackCP walk latency — fixed pending push. Infra flake noted: `Provider settings storage checks` once failed on runner port-bind collision (33306), cleared on rerun — not code.

**Codex integration matrix** (verified via `git merge-base --is-ancestor`):
- `codex/software-acceptance-guard-order` (`0d18707b`) already contains: feature branch through `d062bd5` + PR #268 (`3789bb50`, `refs/codex-review`) + webhook lane #269/#270/#272 + acceptance-order lane #294→#304→#306. It does NOT contain `2d8d32d` (my StackCP fix).
- Still pending, stacked on `codex/licensing-integration-review`: reconcile chain `refs/codex-301` (#287→#289→#301), payoff chain `refs/codex-ledger` (#290→#291→#295→#296→#297→#298→#299→#300→#303), `refs/c293` (#293, includes #285), `refs/codex-286` (#286), `refs/codex-288` (#288 upgrade rehearsal). Sandbox lane #271→#279→#283→#284→#292 targets `main` (post-merge work).
- Merge surface vs `2d8d32d`: only `SESSION_HANDOFF.md` + `site/install/inventory.generated.php` overlap — regenerate inventory, hand-edit handoff.
- `0d18707b`'s lone sealed failure `Independent active financing does not unlock the unpaid initial agreement` = 10/min validate.php per-IP fixed-minute rate bucket shared across suites — reorder puts software (~8 POSTs) + financing (~5) clusters adjacent → tail POST gets 429. NOT an authz defect: verified locally on Codex's own overlaid code (pending_initial denial holds through setup/release ops). Fix = `time.sleep(61)` before the late POST, mirroring their own `36721397` pattern.
- Codex `3789bb50` "Focus StackCP provider acceptance view" + `0ad3df4d` "Focus cloud assignment workspace" bound the page's scope — complementary to `2d8d32d`'s batched audit verification (different files, no conflict).

**Local repro stack** (this VM): `~/bin/php` docker wrapper (`cwp-inventory-test:latest`); `docker exec cwpdb mariadb ...` resets `cwp_test` (creds cwp_test/local-ci-only, prefix `ci_`); installed site `test-runtime/web/existing` served at `127.0.0.1:8091` (owner@example.invalid / alice@example.invalid, 'Synthetic … passphrase 2026'). After editing `site/portal/views/*` or `site/admin/controllers/*` locally, `cp` the file into `test-runtime/web/existing/...` to exercise it. `tools/build.py --refresh` + commit `site/install/inventory.generated.php` after every `site/` change.

**Next**: confirm sealed `Application acceptance` green, then final integration/security review of PR #266 → merge readiness report to owner (merge needs explicit approval; no live deploy, no live Authorize.net).

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
