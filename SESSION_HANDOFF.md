# Session handoff - Devin sole-owner continuation

## Port merge status — 2026-10-23 (SSH access port, PR #258)

Merged current `main` (`a331599a`, audit utf8mb4_bin + detail index fix) into `devin/ssh-access-port` for exact-head acceptance. Merge commit `b41b961b`. Same union resolution as the parallel ports: routing union, marker-aware `studio_migrate` + additive guarded audit migration, binary audit DDL + lane schema tail, build-area union, re-settled contract predecessor chain, regenerated inventory. `PYTHONUTF8=1` artifact verifier: {"passed": true, "checks": 288}.

Dedicated component runs: `40142690407` on pre-merge `65e10f41` green; merge-head run `40144533735` on `b41b961b`: **success**. PR base `devin/full-site-integration`; merge held until the in-flight FSI acceptance run resolves.

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

## Integrated increments — devin/full-site-integration

Integration branch `devin/full-site-integration` (from main `1067757d`). Merged in dependency order:

- **Mail chain** (`devin/non20i-mail-html-compose` tip `cf837f65`, PRs #161/#189–#195): complete webmail reply/forward on protected copied drafts, receive/send attachments, Sent-folder synchronization, reply-all, folder-scoped browsing, explicit read/unread flag marking, and optional sanitized HTML alternatives on drafts/submissions. All coordinator review findings addressed including PERMANENTFLAGS `\Seen` correctness and marker-1 draft compatibility.
- **Contacts/account chain** (`devin/non20i-portal-account` tip `0a919545`, PRs #203/#204/#205/#209–#213): scoped company contacts, version-decision attribution, per-contact activity routing, staff-only project notes, structured milestones, per-actor preferences, client action items, and the portal account page. All integration-review findings propagated through the chain.

Branch-specific session history remains on each source branch and in `docs/SESSION_LOG.md`.

## 2026-10-18 — client cloud website journey

From main `e397d9a6e293850e8dff61a593e3ba39ee178400`, branch `codex/twentyi-client-cloud-websites` implements primary-client request/cancel, owner decline/explicit creation, retained outcomes and exact returned-package confirmation navigation. Client dispatch and the existing shared grant commit atomically; replay never sends again. The current intake requires a complete cloud observation within15 minutes. [Behavior, authority, evidence and remaining gates](docs/TWENTYI_CLIENT_CLOUD_WEBSITES.md).

Local evidence:141 document and98 modeled UI checks per PHP mode; four actual module cold loads;34 legacy-context checks per mode;271 final artifact-helper checks;35 portable installed-update guards;19 actual published-baseline negative checks. Original1.24 and1.25 ZIP bytes validate against their retained member inventories, with all27 earlier release maps unchanged. Independent service, browser, installed-update and shared-wiring review is recorded in the feature guide. Native SQL/concurrency, packaged browser pixels, installed Linux/Apache execution and full combined acceptance remain pending. No live operation, new release or deployment; published1.25 and all separate original CI handles remain unchanged.

## Starting source and actions taken

Main read before the handoff: `a86f6f4fa592e6f715a9420aec8de4622258642e`. Latest release read: v1.25.0 at `b79c67110829126916e3a65e616191542448bccc`. PR216 is still an open candidate, not a new publication. Refresh all facts at Devin startup.

This session writes the takeover directive and current root/coordination instructions, and updates the assignment issues. These changes are documentation only. No application PR was merged, no PHP/schema/installer/workflow/version file changed, no application acceptance was rerun and no new software release or deployment occurred. No active Devin session or external-worker acknowledgement is claimed. The enclosing commit identifies the instruction revision.


## Devin integration session — 2026-10-19 (Group A complete)

**State:** `devin/full-site-integration` at `9896b1f0` contains main `1067757d` plus all 25 Group A non-20i merges. Branch worktree: `D:/Devin/Cajun_Web-int`. Push trigger added to `application-tests.yml`; combined acceptance run 39297205239 is in flight.

**Rules learned for this integration:**
- Every Union-projection conflict resolves to matching `author_type` + `sort_sequence` columns on all branches.
- Contact actors scope through `$actor['client_id']`; never test `$actor['type']==='client'` alone.
- `tools/acceptance-contract.json` must be merged semantically (additions list + after-map union, recompute `source_sha256`/`unchanged_test_sha256` for unioned files, `native_commands` rewritten from the workflow recipe). After editing, `git add` it BEFORE committing — staging order once left a markered contract committed (fixed via amend `1f217bdd`).
- `tests/apache_acceptance.py` conflicts are usually giant list unions; check INC-only quoted items first — often HEAD is already a strict superset (`git checkout --ours`).
- Regenerate `site/install/inventory.generated.php` via `python tools/build.py --refresh` after every site change; artifact check is `python -B tests/acceptance_artifacts_test.py --report qa/parallel-helper-tests.json` (expects 229).

**Next actions in order:**
1. Watch run 39297205239 to completion; fix any combined failures.
2. Port-parity check vs superseded codex #102–#125 heads; then close the 23 already-integrated ancestor PRs.
3. Observe actual CI on Group B Codex 20i heads (catalog f67b1631, capture b7c97953, commercial d2903bfb, nameservers 024b50dc, runtime, subdomain 4dc05d8c, StackCP ffee2e3a, renewal 632362f6, reset-template 95ce538e) before any merge.
4. Normal-merge integration branch into main after combined acceptance passes, then continue remaining 20i/non-20i gaps and the next verified release.

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
