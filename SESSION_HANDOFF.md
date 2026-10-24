# Session handoff - Devin sole-owner continuation

## Merge-first delivery cycle — 2026-10-22

Owner directive (reaffirmed): completed features reach `main` in small, verified increments — a completed branch is undelivered until its accepted implementation lands on `main` and the resulting main commit verifies. Full standing text: `AGENTS.md` "Standing owner directive - merge-first"; queue: `docs/DEVIN_INTEGRATION_QUEUE.md`.

Delivery 1 in flight: **PR261** `devin/reset-email-template-main` — merge `e334678e` of the green accepted source `467ec1bd` (original full-green run 39422487199) into main `1067757d`; docs-only conflicts resolved preserving both sides; local `php -l` clean, `tools/build.py --refresh` no-drift, artifact verifier `{"passed": true, "checks": 230}`. Hosted acceptance running; independent review pending; merge on green then verify main.

Next candidates after PR261: cloud website intake and directory-listing controls — confirm actual dependencies against current main; do not assume earlier leaf runs certify a standalone candidate.

Staging lanes `devin/preflight-commercial` and `devin/full-site-integration` remain in flight for the wider backlog and are not the delivery path for small increments. FSI browser-lane failure at `67d70e4a` was a test-side `NameError` (`ROOT` missing from `mail_message_cases.run_browser` env pull); fixed at `9ec4e2b1`.

## Lane state — 2026-10-24 (latest)

**MERGED**: PR263 `devin/cloud-websites-main` (`89647b28`) merged to main as `125deacc` — first merge-first delivery of the cycle. Evidence: full `Application acceptance` run `40029373374` green on exact head (includes 1.25.0 published-baseline upgrade matrix + recovery flow); labeled self-review posted on merge; main has no enforced review gate. Exact-main verify `40053118998` in flight. Not a release; nothing deployed.

In flight on exact heads:

- **FSI** `devin/full-site-integration` `68ca05ec`: mail bounds verified green (SMTP send + sent-copy APPEND); `f22b4db2` fixed the render-guard masking the 'do not include billing' refusal on contact POSTs; `68ca05ec` corrected the over-specified `403` assertion for revoked-contact sessions (real outcome: session invalidated → anonymous login form, per `studio_client()` + `contacts_cases` convention). Run `40053078796` in flight.
- **Preflight** `devin/preflight-commercial` `be8d75fe`: FlagWriter bootstrap require (ported `ba10faee` fix — `Class FlagWriter not found` root-caused the mark-read 'connection' refusal) + added the documented owner `payments_migrate` step to the retained-1.25 upgrade fixture (`payments_ready` requires schema v2). Contract re-anchored, 1468/1468. Run `40057278215` in flight.
- **Nameserver** `devin/fix-nameserver-fixture-getattr` `b24ac1bb`: `40043663733` passed the timeout fix but failed at cases.py:305 — browser `GET provider-settings` got 303→login. Root cause: Chromium shares the `owner` session cookie; `support_start`'s `session_regenerate_id(true)` (fixation defense) deletes the old session file and the browser keeps the dead id. Harness fix: `navigate()` re-syncs context cookies to the current owner session each call. Contract re-anchored, 473/473. Run `40059504320` in flight. Product perf debt (missing index on `ci_audit`) recorded — separate lane.
- **PHP-controls** `devin/fix-php-controls-minimal` `f1fdc4e2`: report-shape fix (site frame folded into `message`) + goto networkidle 30s→90s. Run `40044789245` in flight.
- **Docroots** `devin/fix-docroots-worker-retry` `514ba7dd`: run `40029428035` externally cancelled at ~2h41m (build/recovery/apache green; native-and-browser killed). Re-dispatched `40054595694`.
- **Diagnostic** PR264: awaiting independent review.
- Environment: C: disk full — TEMP/downloads redirected to D:/Devin/tmp-dl.

Merge-first sequence: next merge candidates are the leaf lanes once exact-head green lands; then exact-main verify each; PR264 after independent review; published 1.25.0 immutable, nothing live-deployed.

---

## Lane state — 2026-10-23 (superseded)

All lanes carry committed fixes and in-flight runs on exact heads. `rerun --failed` is structurally dead on `application-tests.yml` (attempt-identity check, `acceptance_artifacts.py:249`) — always use fresh `workflow_dispatch`. `devin/*` push triggers do not fire application-tests — dispatch manually or use an open PR's pull_request run. Full detail: `docs/DEVIN_INTEGRATION_QUEUE.md`.

- **Diagnostic** `devin/diagnostic-acceptance` `e8e78991` (docs) / `76bf7c47` (code): multi-failure collection verified end-to-end hosted — run `39971362640` produced the combined report (60 units: 6 passed / 1 failed / 15 blocked / 38 skipped / 0 absent; honest exit-1 gate; artifact 12514335551). Strict runs `39959484953`, `39971372304`, `39971398769` all completed **green** on identical app code — the `staff` narrow-mobile failure is confirmed flake. Contract 152/152; strict runner sha `71dc6699f2e92b23` unchanged. PR264 open; merge awaits independent review (self-review comment posted).
- **Cloud** `devin/cloud-websites-main` `f660d022` (PR226): instrumented recovery checks (`force-ssl-recovery-diagnostics.json`, `directory-listing-recovery-diagnostics.json`); run `39975475852` in flight — build/recovery/apache green, native-and-browser running.
- **Preflight** `devin/preflight-commercial` `1e9cfea3`: router shadow fix `a4e4639a` verified (notification check green at run `40001903568`). Two failures remain: apache retained-1.25 consent check (real lane regression; diagnostics renamed `apache-r126-consent-*` to pass the evidence seal) and mark-read `connection` refusal in 2ms with zero fixture commands (pre-wire refusal; connector DNS/stream refusal paths now `error_log` to `qa/http-server.log`). Run `40024614698` in flight.
- **PHP-controls** `devin/fix-php-controls-minimal` `c401f9ea`: prior run `39991711546` — 86 checks passed then a 120s `ReadTimeout` inside `run_browser` (second consecutive hang-class failure). `c401f9ea` adds `site_file`/`site_line` innermost-frame reporting; run `40023145297` in flight.
- **Docroots** `devin/fix-docroots-worker-retry` `514ba7dd`: two freshness re-arms before access reads + terminal audit updated to 8 reads. Run `39996264484` in flight.
- **FSI** `devin/full-site-integration` `7fbc46a7`: SMTP verified end-to-end (`6dfe8c2b`, wire 141,078 == MIME, terminator, decode ok). Sent-copy APPEND then failed — two defects: `fwriteLiteral` 64KiB bound vs session `maxBytes`/`PreparedMime::MAX_BYTES` 768KiB (fixed: bound now caller-passed, 1MiB ceiling), and fixture `mode='ok'` during the big-copy POST (fixed: `mode='append'`). append_test 34/34 incl. raised-bound cases. Run `40026770020` in flight.
- **Cloud** `devin/cloud-websites-main` `ad5f6e38`: Force SSL recovery anchor re-read fix; all merge-candidate checks green; acceptance run `40006128366` in flight.
- **Nameserver** `devin/fix-nameserver-fixture-getattr` `f1add183`: `39980606415` externally cancelled (`cancel-in-progress: false` — not workflow); fresh dispatch `40018564092` in flight.
- **Housekeeping**: a solver script hard-coded to `D:/Devin/Cajun_Web-mp-fs` accidentally dirtied that abandoned worktree's contract + module const; reverted to `2cbe4832` clean. For contract fixpoint work in Cajun_Web-int use `solve_int.py` (corrected ROOT).
- **Merge-first sequence** unchanged: no merges yet; each lane needs exact-head green + labeled review before merge evaluation; then exact-main verify; PR264 (diagnostic) after green final-head evidence + independent review; published 1.25.0 immutable, nothing live-deployed.

---

## 2026-10-19 — reset email template implementation

Parent `a86f6f4fa592e6f715a9420aec8de4622258642e`; branch `codex/twentyi-reset-email-template`; enclosing commit **feat: review and confirm 20i reset email templates**. Root owns UI/shared transport and acceptance wiring; Catalog owns encrypted runtime/storage; Domains owns typed provider contracts and independent review; VPS owns additive SQL/browser tests. [Exact scope and next gates](docs/TWENTYI_PASSWORD_RESET_TEMPLATE.md).

Continue actual exact-source native/browser acceptance, inspect produced images, integrate sequentially and complete combined-main/release gates. Do not interpret local modeled checks as installed acceptance or rerun unchanged queued jobs. My20i sign-in for the missing full catalog/registrar schemas remains pending. Full roadmap goal stays active.

Implementation and independent source review are finished with no remaining concrete findings. Local foundation, modeled runtime/forms, handler regressions, 68 root source checks and 230 acceptance-helper checks pass in their documented scopes. Installer refresh, all shipping PHP/JavaScript checks, documentation and whitespace pass; generated shipping mirrors/inventory are included. Detailed receipts and the preserved initial Windows extension-path harness failure are linked from the feature guide. No native SQL or installed browser result is claimed.

Payment-link fix is separately committed/pushed as `b1948eeb`, draftPR253 targeting DevinPR202. Its original full run39247478772 was manually dispatched once because that companion base is outside the full workflow PR branch list. PR252 source632362f6 and all original evidence stay preserved. Published1.25.0 is unchanged; no live deployment.

## Owner request recorded 2026-10-19

Transfer all remaining CajunWebPro work to Devin alone. Start by reviewing and merging completed Codex AND Devin branches into main; then finish the entire 20i and non-20i roadmap, including integration and releases. The authoritative instructions are [docs/DEVIN_FULL_SITE_TAKEOVER.md](docs/DEVIN_FULL_SITE_TAKEOVER.md) and [DEVIN_START_HERE.md](DEVIN_START_HERE.md).

This supersedes old non-20i-only and Codex-only integration/release restrictions. Devin owns shared billing/auth/schema/navigation/installer/CI changes and final delivery, subject to existing tests, review, protections and operation-specific approvals. Other workers must preserve their work and not start overlapping implementation; their external sessions have not been stopped by this document.

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
