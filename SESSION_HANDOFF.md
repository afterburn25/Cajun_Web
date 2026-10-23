# Session handoff - Devin sole-owner continuation

## Merge-first delivery cycle — 2026-10-22

Owner directive (reaffirmed): completed features reach `main` in small, verified increments — a completed branch is undelivered until its accepted implementation lands on `main` and the resulting main commit verifies. Full standing text: `AGENTS.md` "Standing owner directive - merge-first"; queue: `docs/DEVIN_INTEGRATION_QUEUE.md`.

Delivery 1 in flight: **PR261** `devin/reset-email-template-main` — merge `e334678e` of the green accepted source `467ec1bd` (original full-green run 39422487199) into main `1067757d`; docs-only conflicts resolved preserving both sides; local `php -l` clean, `tools/build.py --refresh` no-drift, artifact verifier `{"passed": true, "checks": 230}`. Hosted acceptance running; independent review pending; merge on green then verify main.

Next candidates after PR261: cloud website intake and directory-listing controls — confirm actual dependencies against current main; do not assume earlier leaf runs certify a standalone candidate.

Staging lanes `devin/preflight-commercial` and `devin/full-site-integration` remain in flight for the wider backlog and are not the delivery path for small increments. FSI browser-lane failure at `67d70e4a` was a test-side `NameError` (`ROOT` missing from `mail_message_cases.run_browser` env pull); fixed at `9ec4e2b1`.

## Lane state — 2026-10-23 (latest)

- PR261 (`aeb713c6`) and merge-first docs (PR262 `bc5777f5`) are merged on main and verified. Cloud candidate `devin/cloud-websites-main` dispatch `39886854693` on `4fbce9db` was all-green; PR-triggered `39886951265` on docs head `e0511354` failed `Directory-Listing-Control-Recovery-Owner` — the recover form needs the packages-list observation inside the 900s `service_observations_fresh` window; `f1c53d61` re-arms hosting+flag reads before the interrupted request pins its binding source. PR263 head is `d2a3cc9c` (docs over `f1c53d61`), mergeable. Runs `39912832426`/`39912825915` (`f1c53d61`) + `39912956591` (`d2a3cc9c`, merge-authoritative) in flight.
- FSI lane: two-gate fix — `b61bcd22` ages dispatch cooldown before refused send (held: `submission_status=accepted`), then `e7840260` ages `ci_mail_copy_attempts` before the refused copy (run 39912016536 showed the copy POST refused at the 2s per-account copy gate, `wait_gate=true`). Runs `39922239566`/`39922213870` in flight.
- Preflight lane `devin/preflight-commercial`: owner-GET 403 root-caused to `studio_schema_version` '2'→'4' gap on upgraded 1.25 sites — `130a8da2` runs the real owner `studio_migrate` workspace upgrade form before `before_setup` (contract re-settled, verifier 1468/1468). `80d0a16c` restores the raw `audit` row in DR-orphan teardown (normalized `domain_contracts_audit_row` shape caused `Unknown column 'actor_id'`). `39906752652`/`39916738437` cancelled (superseded); `39917250169` (`80d0a16c`) apache red on the same already-classified owner-GET check, native still running; `39924991516` (`130a8da2`, contains both fixes) is the authoritative head — in flight.
- Nameserver `4310ee17` (conditional registrar-freshness re-arm): run `39901898005` was **externally cancelled** (workflow is `cancel-in-progress:false`) — no evidence; redispatched `39928784590` on `b2d4c714`. PHP-controls: `4f1f93ac` freshness fix held but run 39900556800 red on "Owner form performs one exact setter" (`phase:not_sent`, 0 calls, no ledger — swallowed throw in the pre-`$entered` send span); `45131d4d` adds a fixture `gate` probe replaying each authorize/endpoint/credential/operation predicate post-failure — run `39927330966` in flight. Docroots: `39911349541` showed the WP fix held but directory-listing hit the same +30s repost escape (`calls_delta=2`); `795211c4` extends the admission-serialized repost to all five remaining siblings — run `39928567734` in flight.
- Systemic note: the sequential cooldown repost remains on all unfixed lanes (-int, -fsi, -lf-02, -lf-pc, -audit, -cw); port `795211c4` reactively per lane. See `docs/DEVIN_INTEGRATION_QUEUE.md` tail note.
- Diagnostic mode (`devin/diagnostic-acceptance` `2c9c14c7`, `-diag` worktree): multi-failure collection runner built — `tests/diagnostic_acceptance.py` (per-unit exception boundary, ported strict prelude) + `diagnostic_registry.py` (54 modules, strict order, 10 groups) + `diagnostic_report.py` (combined JSON/MD, missing groups = blocked) + dispatch-only `diagnostic-tests.yml` (isolated DB per group job). Strict runner untouched/pin-verified; offline contract 142/142. First hosted run **39932643299** in flight; collect results → PR → review → normal merge evaluation.
- Merge readiness: main has no branch protection; PR262 merged normally. Next merge gate = green `39912956591` on PR263 exact head `d2a3cc9c` + labeled self-review. After merge + exact-main verify: evaluate force-SSL leaf `7757c64c` (green run 39746898847, filtered skips) then PR246 (`b0fe332f`, already inside preflight lane).
- No merges since PR262; PR226 integration still gated on exact-head green + review. Published 1.25.0 immutable; nothing live-deployed.

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
