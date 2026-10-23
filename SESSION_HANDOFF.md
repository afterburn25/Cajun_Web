# Session handoff - Devin sole-owner continuation

## Merge-first delivery cycle — 2026-10-22

Owner directive (reaffirmed): completed features reach `main` in small, verified increments — a completed branch is undelivered until its accepted implementation lands on `main` and the resulting main commit verifies. Full standing text: `AGENTS.md` "Standing owner directive - merge-first"; queue: `docs/DEVIN_INTEGRATION_QUEUE.md`.

Delivery 1 in flight: **PR261** `devin/reset-email-template-main` — merge `e334678e` of the green accepted source `467ec1bd` (original full-green run 39422487199) into main `1067757d`; docs-only conflicts resolved preserving both sides; local `php -l` clean, `tools/build.py --refresh` no-drift, artifact verifier `{"passed": true, "checks": 230}`. Hosted acceptance running; independent review pending; merge on green then verify main.

Next candidates after PR261: cloud website intake and directory-listing controls — confirm actual dependencies against current main; do not assume earlier leaf runs certify a standalone candidate.

Staging lanes `devin/preflight-commercial` and `devin/full-site-integration` remain in flight for the wider backlog and are not the delivery path for small increments. FSI browser-lane failure at `67d70e4a` was a test-side `NameError` (`ROOT` missing from `mail_message_cases.run_browser` env pull); fixed at `9ec4e2b1`.

## Lane state — 2026-10-23 (latest)

All lanes carry committed fixes and in-flight runs on exact heads. `rerun --failed` is structurally dead on `application-tests.yml` (attempt-identity check, `acceptance_artifacts.py:249`) — always use fresh `workflow_dispatch`. `devin/*` push triggers do not fire application-tests — dispatch manually or use an open PR's pull_request run. Full detail: `docs/DEVIN_INTEGRATION_QUEUE.md`.

- **Diagnostic** `devin/diagnostic-acceptance` `e8e78991` (docs) / `76bf7c47` (code): multi-failure collection verified end-to-end hosted — run `39971362640` produced the combined report (60 units: 6 passed / 1 failed / 15 blocked / 38 skipped / 0 absent; honest exit-1 gate; artifact 12514335551). Strict runs `39959484953`, `39971372304`, `39971398769` all completed **green** on identical app code — the `staff` narrow-mobile failure is confirmed flake. Contract 152/152; strict runner sha `71dc6699f2e92b23` unchanged. PR264 open; merge awaits independent review (self-review comment posted).
- **Cloud** `devin/cloud-websites-main` `f660d022` (PR226): instrumented recovery checks (`force-ssl-recovery-diagnostics.json`, `directory-listing-recovery-diagnostics.json`); run `39975475852` in flight — build/recovery/apache green, native-and-browser running.
- **Preflight** `devin/preflight-commercial` `a4e4639a`: fixed `?view=notifications` route shadowed by stale payment-sandbox dispatch (`router.php:65` merge artifact); full router contract-chain re-anchor (5 era pins, 3 deltas, 14 predecessor hashes, rnr module const cascade) — verifier 1468/1468. Fresh dispatch `40001903568`. Stale-head run `39997889998` (`56cfe02e`) still in flight and will yield the retained-1.25 conjunct diagnostics for the apache failure.
- **PHP-controls** `devin/fix-php-controls-minimal` `f6af4bb1`: diagnostics redirected to flat `qa/` + pin re-anchor (390/390). Run `39991711546` in flight.
- **Docroots** `devin/fix-docroots-worker-retry` `514ba7dd`: two freshness re-arms before access reads + terminal audit updated to 8 reads. Run `39996264484` in flight.
- **FSI** `devin/full-site-integration` `5132de7f`: attachment-MIME failure root-caused to a **product bound** — `SmtpSession::client_send`/`SubmissionChannel::write` session send cap `73728` vs the feature's own 768KiB `PreparedMime::MAX_BYTES` (deterministic 73,449-byte mid-DATA cut, no terminator). Raised both caps to 1 MiB (1048576), scaled the native `total_bytes` probe to 520x2048B writes, mirrors/inventory regenerated. Fresh dispatch `40001791189`. Earlier `recv(1)`-drain hypothesis corrected (buffered reader `619591b8` retained but was not the cause).
- **Nameserver** `devin/fix-nameserver-fixture-getattr` `f1add183`: fresh dispatch `39980606415` in flight (bypasses the dead rerun mechanism).
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
