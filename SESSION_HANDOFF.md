## 2026-10-25 — merged current main (audit fix) into FSI after hosted browser stall

Full acceptance run 40142523950 on  failed : the browser suite hit a 30s  timeout on  — the audit-scan render stall the audit-index lane fixed on main. FSI was 47 commits behind and lacked . Merged  (/ ancestry) into the branch as merge : schema keeps both the indexed binary audit DDL and FSI-only contacts/notification-feed tables;  keeps marker-4 logic and gains the audit collation/index ALTER block; directory-listing/force-SSL case files union both sides' freshness re-reads plus main's recovery diagnostics (read sequences now expect eleven reads); contract unions  +  with merged-universe pins re-anchored (cloud peel first at 160, template predecessor 157, report hashes recomputed);  re-anchored after raising  to 300 minutes (same ceiling risk as nameserver/docroots); installer inventory regenerated (40 packages, 2,188 deps, 2,206 runtime bytes exact). Artifact verifier 288/288 green locally. Redispatching hosted acceptance on the merged head.

# Session handoff - Devin sole-owner continuation

## Merge-first delivery cycle — 2026-10-22

Owner directive (reaffirmed): completed features reach `main` in small, verified increments — a completed branch is undelivered until its accepted implementation lands on `main` and the resulting main commit verifies. Full standing text: `AGENTS.md` "Standing owner directive - merge-first"; queue: `docs/DEVIN_INTEGRATION_QUEUE.md`.

Delivery 1 in flight: **PR261** `devin/reset-email-template-main` — merge `e334678e` of the green accepted source `467ec1bd` (original full-green run 39422487199) into main `1067757d`; docs-only conflicts resolved preserving both sides; local `php -l` clean, `tools/build.py --refresh` no-drift, artifact verifier `{"passed": true, "checks": 230}`. Hosted acceptance running; independent review pending; merge on green then verify main.

Next candidates after PR261: cloud website intake and directory-listing controls — confirm actual dependencies against current main; do not assume earlier leaf runs certify a standalone candidate.

Staging lanes `devin/preflight-commercial` and `devin/full-site-integration` remain in flight for the wider backlog and are not the delivery path for small increments. FSI browser-lane failure at `67d70e4a` was a test-side `NameError` (`ROOT` missing from `mail_message_cases.run_browser` env pull); fixed at `9ec4e2b1`.

---

## 2026-10-19 — reset email template implementation

## 2026-10-25 (latest) — proposals milestone check was a test-literal/format mismatch, not a render defect

- The `Draft detail shows milestone pricing` failure (run `40115508547`) could never pass: `proposal_version_card` emits `number_format(amount/100,2)` → `Total $2,495.88 USD` and `$2,400.00` milestones (product invoice convention), while the lane-new P-02 cases asserted `'$2495.88'` — matching only the no-comma form-prefill format in `proposal_version_prefill`. Deterministic mismatch, not renderer or fixture-selection defect (pid is pinned — one proposal exists at that point).
- `3bdf2823`: corrected both literals (`:24` draft detail + diagnostic missing-list, `:28` client sent-version) to `'$2,495.88'` — still asserting the exact rendered total, not weakened. File unpinned; `py_compile` clean.
- Run `40142523950` auto-dispatched on `3bdf2823`; superseded `40131688282` (c6fea48d, diagnostics-only) cancelled.
- Next: when the run reaches the proposals suite (~3.8k checks in), the corrected literals should pass if the page renders as designed; the retained diagnostic still captures the response if anything else is missing.
 - Devin sole-owner continuation
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

**Continuation 2026-10-19 (later same day):** tip `5da3ef95`. Post-Group A fixes: `cb8d2117` repaired admin notification dispatch shadowing (`payments_notification_*` prefix now precedes the generic `notification*` feed dispatch); `ceb06c05` removed a duplicate plain `require` of notifications.php in the portal router. Stale pre-fix run 39297205239 (on `9896b1f0`) cancelled; run 39298981345 on `cb8d2117` was lost mid-flight during triage. Run `39299409880` on `ceb06c05` is the authoritative combined acceptance — later commits through `f73dd118` are docs-only (paths filter).

**Component ports (base = integration branch, awaiting hosted runs):**
- PR #257 `devin/vps-uncertainty-port` — VPS uncertainty-retention component from `fb395998` (311 local checks).
- PR #258 `devin/ssh-access-port` — SSH access-model from `devin/ssh-access` (70 local checks).
- PR #259 `devin/provider-catalog-port` — CapabilityCatalog/CatalogEntry/TwentyICatalog (50 catalog + 171 provider checks local).
- PR #260 `devin/domains-port` — foundation/domains commerce model (81 local checks).

**PR hygiene:** closed #102–#125 (port parity verified), #32/#36/#37/#43–#46/#60/#61 (superseded; ported where needed), #109/#111 (superseded by released 20i work), and all 25 Group D ancestor PRs. Open PRs now ~101, almost all Group B awaiting CI.

**CI queue triage (2026-10-19):** the ~1,063-run backlog was triaged — 616 queued runs force-cancelled after per-run safety checks (ancestor-of-leaf verification, merged sources, stale SHAs, duplicates; none had executed). ~415 retained: acceptance `39299409880` on `ceb06c05` plus dispatched runs on all Group B leaf tips/heads and port-branch component suites. Full detail: `docs/DEVIN_INTEGRATION_QUEUE.md` → "CI queue triage" and `docs/SESSION_LOG.md` 2026-10-19 tail entry.

**Next actions in order:**
1. Watch run `39299409880` (acceptance on `ceb06c05`; tip `f73dd118` is docs-only past it) — on pass the integration branch is merge-eligible to `main`.
2. Merge the four component-port PRs once their dedicated runs pass (or fold branches in directly before the main merge).
3. Observe dispatched acceptance on the ten Group B leaf tips (`d2903bfb` first — carries catalog/capture/first-invoice; `ff467d7f` php-version-controls is a standalone tenth leaf); merge in dependency order, resolving the known 8–22-hunk stale-base conflicts (catalog resolution pre-staged on `devin/preflight-catalog` `93e24ba0`).
4. Normal-merge integration branch into main after combined acceptance passes, then continue remaining 20i/non-20i gaps and the next verified release.

## Devin leaf-repair session — 2026-10-20 (Group B preflight)

**Branch strategy:** the ten Group B leaf tips each get a `devin/fix-*` repair
branch; hosted acceptance must pass on the repair tip before its leaf merges
into `devin/preflight-commercial` (Group B container) and ultimately
`devin/full-site-integration`. Leaf contracts are cumulative, so each repair
repins its own section and every dependent staged anchor — never delete or
weaken a historical assertion.

**Root causes found and fixed this session (all fixture/shipping defects, no
assertions weakened):**

- Worker-mutex contention class (5 fixtures): `proc_open` children that lost
  the `GET_LOCK` staff-mutex race died on the transient
  `Another account change is in progress. Please retry.` instead of retrying.
  Each worker now uses the bounded 3-attempt retry already proven in
  `registered_nameserver_changes_native.php`; join deadlines raised to 25s.
  Fixes: nameserver requests `bdfb1734` (devin/fix-nameserver-fixture-getattr),
  directory-listing `a9e08b51`, force-ssl `ef5724ce`,
  subdomain-requests on lf-4d (same commit), docroots `c2163e16`.
- Docroots `withdraw-decline` hang, root-caused via process-state capture on
  `8fa6df68` (devin/diag-docroots-worker, diagnostic-only branch): the worker
  was NOT blocked on the mutex — it sat in MariaDB `Statistics` inside the
  `KEY_COLUMN_USAGE JOIN REFERENTIAL_CONSTRAINTS` information_schema scan.
  That join materializes every table's referential metadata, so its cost
  grows with total `cwp_test` table count and flakes late-suite workers.
  Replaced with two constant-filtered single-table lookups joined in PHP —
  verified row-identical and ~350x faster on local MariaDB 10.11
  (486ms→1.4ms per 20 runs). Applied to the three docroot files on the leaf
  (`17f02f4e`+`69c43c25`+`9577021e` on devin/fix-docroots-worker-retry) and to
  `document_roots_storage.php` on both integration branches (fsi `0ce088e3`,
  preflight `c9e245ed`). The same join shape exists in ~35 other
  storage/migrate files; fix on demand if another leaf flakes.
- CRLF/installer-hash trap: `inventory.generated.php` must be regenerated on
  LF-normalized bytes — a Windows CRLF working copy produces a committed
  inventory that CI's LF checkout cannot reproduce
  (`Installer dependency hashes are stale`). Fixed on the docroots leaf by
  normalizing the migrate file to LF and re-refreshing.
- StackCP leaf `3f88eb09` (devin/fix-stackcp-ops-whitelist): three native
  fixtures used plain `require` for `clients/accounts.php` after
  `schema/base.php` had already loaded it transitively → function
  redeclaration. Now `require_once`. Also rebaselined the StackCP access
  render contract for a post-freeze header change (`3d449d79` era).
- PHP-controls leaf `23213c52` (devin/fix-php-controls-minimal):
  (a) MariaDB auto-names CHECK constraints `CONSTRAINT_1` per table, so the
  lifecycle validator's `CHECK_CONSTRAINTS` join matched every table's
  same-named check — scoped the join to its own table (`4d262e1f`);
  (b) CI `php -n` lacks the tokenizer — replaced `token_get_all` extraction
  in two `-n` fixtures with an extension-free scanner (`b27694e7`);
  (c) `SHOW TABLES LIKE ?` bound-param probes fail under server-side
  prepares — switched to the information_schema lookup the suite already
  uses (`23213c52`).
- Cloud-intake leaf `fe989846` (devin/fix-cloud-intake-ctype): `ctype_digit`
  is absent under `php -n` — regex equivalent (also applied to the merged
  copies on both integration branches).
- Catalog schema validators (fsi `0c1f2ad9` + preflight equivalent):
  `catalog/pipeline/proposals` migrate fixtures expected plain INDEX names in
  the unique-keys set — a merge-resolution artifact; expected keys corrected
  to `['request_key']` only, matching both ancestors' intent.
- Backup fixture (fsi `63040237` + preflight `b44d48ef`/`d9615381`):
  `backup_drills` (FK child) must drop before `backups`; the legacy-manifest
  check needed the fully-qualified
  `\CajunWebPro\Foundation\Backups\BackupManifest`.
- Support-tickets native report was produced but unregistered; added
  `support-tickets-native-results.json` to both report lanes and re-anchored
  the entire staged-era contract chain (preflight verifier green at 1212
  checks).
- Upgraded-suite portal diagnostic: `fields()` helpers now name the rendered
  page when no login form is found (both integration branches).
- Upgraded-site opcache check on lf-02: an opcache OOM restart wiped
  `num_cached_scripts` mid-probe — the check now warms `defaults.php`
  immediately before asserting (proves OPcache active + timestamps=0).
- Portal `contact_activate` does not auto-login: the upgraded-suite
  invitation flow lands on `contact_activate`, not `login` — diagnostics
  confirmed the page renders correctly; the check reads the wrong form name.

**Leaf contract-verifier green states recorded locally:** nameserver 473,
stackcp 547, php-controls 390, docroots 360, preflight 1212, fsi 229.

**Dispatched hosted runs pending observation (2026-10-20):**
fsi `0ce088e3`→39370796221; preflight `c9e245ed`→39370792288;
docroots `9577021e`→39371157407; php-controls `23213c52`→39369886724;
nameserver `bdfb1734`→39369797516; stackcp `3f88eb09`→39369583927;
cloud-intake `fe989846`→39365510876 (redispatched after apt-get 403 infra
failure). dir-listing/force-ssl/reset-email fix branches pushed; dispatch on
demand if their last green runs age out.

**Not merge claims:** green contract verifiers are local; no leaf merges
until its hosted run passes. No PR lifecycle state asserted.

## Owner directive — 2026-10-20 (merge-first, standing)

Owner directive recorded: merging completed, tested work into `main` is a standing top priority; keep `main` caught up with completed development. Full text recorded in `AGENTS.md` under "Standing owner directive - 2026-10-20 (merge-first)". The integration queue at `docs/DEVIN_INTEGRATION_QUEUE.md` stays current; status reports distinguish implemented / awaiting-test-or-review / ready-to-merge / merged / verified-on-main / released.

Current merge-blocker state at recording: nine corrected-head acceptance runs in flight (fsi `f6cacdcd`->39474415962, preflight `4cb0f4a3`->39477280053, leaves: stackcp `1727f6fc`->39477425662, cloud `d39fae8c`->39477432779, docroots `5adfac76`->39477439335, php-controls `a3c4bd1c`->39477446040, nameserver `a982ae71`->39477452542, force-ssl `02e7b0d4`->39477459453, dir-listing `ace1f69b`->39477467791). Leaf lanes merge into `devin/preflight-commercial` (Group B) only after their own green run, then preflight verifies, then the combined FSI acceptance, then the normal main merge — each stage gates the next; nothing lands on an assumed pass.

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

## Leaf handoff entries — devin/fix-reset-email-ops-whitelist (merged after run 39422487199)

## 2026-10-19 — reset email template implementation

Parent `a86f6f4fa592e6f715a9420aec8de4622258642e`; branch `codex/twentyi-reset-email-template`; enclosing commit **feat: review and confirm 20i reset email templates**. Root owns UI/shared transport and acceptance wiring; Catalog owns encrypted runtime/storage; Domains owns typed provider contracts and independent review; VPS owns additive SQL/browser tests. [Exact scope and next gates](docs/TWENTYI_PASSWORD_RESET_TEMPLATE.md).

Continue actual exact-source native/browser acceptance, inspect produced images, integrate sequentially and complete combined-main/release gates. Do not interpret local modeled checks as installed acceptance or rerun unchanged queued jobs. My20i sign-in for the missing full catalog/registrar schemas remains pending. Full roadmap goal stays active.

Implementation and independent source review are finished with no remaining concrete findings. Local foundation, modeled runtime/forms, handler regressions, 68 root source checks and 230 acceptance-helper checks pass in their documented scopes. Installer refresh, all shipping PHP/JavaScript checks, documentation and whitespace pass; generated shipping mirrors/inventory are included. Detailed receipts and the preserved initial Windows extension-path harness failure are linked from the feature guide. No native SQL or installed browser result is claimed.

Payment-link fix is separately committed/pushed as `b1948eeb`, draftPR253 targeting DevinPR202. Its original full run39247478772 was manually dispatched once because that companion base is outside the full workflow PR branch list. PR252 source632362f6 and all original evidence stay preserved. Published1.25.0 is unchanged; no live deployment.

## 2026-10-19 — closed queue remainder; preserve candidate heads

Parent `dcd6966c4e0c209d15f9d88c8832ef601765c336`; branch `codex/roadmap-continuity-2026-10-19`; enclosing commit **docs: record verified queue remainder and candidate handoff**. [Current work and exact gates](docs/ROADMAP_CONTINUATION_2026_10_19.md) supersede historical pending-cleanup statements below.

All 139 previously untouched candidates were freshly reviewed and conditionally cancelled in three new 64/64/11 batches. Independent terminal review and root evidence binding confirm139 cancellations/551 unexecuted jobs. Original totals are466 cancellations/1,848 unexecuted jobs/31 preserved; none remain untouched or unverified. [New receipt](docs/evidence/ci-superseded-queue-remainder.json), SHA256 `df7f3ee715647db45b10417c086fa9ec3f6e15c2c25e3d956eb9b0688b349673`. Original completion 222646 and all old request/error evidence remain unchanged. All operator/verifier processes exited. Do not replay any cancellation or start an old cleanup plan.

Frozen PR249 is 0397a0e4; its exact package verification is complete, installed/native/full acceptance pending. Frozen PR250 is 80050ef8 based on PR203 cefb; it removes one duplicate test DDL statement and awaits its first native/full results. Keep both branches intact. Main has no corresponding application change. Source-bound receipts and thirteen timestamped original gate states are retained in [continuation evidence](docs/evidence/roadmap-continuation-2026-10-19.json).

Next inspect original gates when their states change, integrate only accepted candidates sequentially, then perform exact-main and release-delivery acceptance. Devin retains non-20i ownership; Codex retains 20i/integration. No complete 20i, new publication or production action is claimed.

## 2026-10-19 — component filters and verified queue cleanup

Parent `847722b4960aa229b8e9b0da282a9424fa620194`; branch `codex/ci-final-component-pr-filters`; enclosing commit `ci: finish component PR filters and verify queue cleanup`. Service-plan and staff-invitation workflows now filter pull requests using their actual source, test and workflow dependencies. Only 13 trigger lines are added; every existing job, command, matrix, push/manual trigger and release gate remains unchanged. This avoids four unrelated jobs per documentation-only PR after integration; it does not cancel existing runs.

Read-only verification after API recovery confirms all 327 acknowledged cancellations, covering 1,297 jobs with no execution evidence. There are zero outstanding terminal verifications, 31 preserved candidates and 139 untouched candidates. The bounded pass is verified; the original 497-candidate plan is not complete. No cancellation request was repeated. [New completion receipt](docs/evidence/ci-superseded-queue-completion.json), SHA256 `222646256b082e916fc52b33ddb13c9456e86621f63a52190d9f6abb0b56172b`. Preserve the earlier partial receipt98b3405e and every original request/error. The final indexb6583ddb and local root delta review remain under `work/queue-review-2026-10-18` and `work/ci-final-pr-filters-review-2026-10-19`. All verifier processes exited. Do not restart the cancellation helper or batch4.

Next: normal integration of this bounded CI/documentation increment, then return to preserved original20i acceptance and corrected image review, normal candidate integration, exact-main acceptance and release-download verification. The latest original39137551561 build remains queued; no rerun or frozen feature-head change is part of this work. Devin retains non20i ownership. PR240 is merged, and the older handoff below is historical.

## 2026-10-19 — remaining isolated pure-suite PR filters

Branch `codex/ci-remaining-pure-pr-filters` starts at main `7e1507f54901497dd0d0ba9a2ae1dccdeccc9eed`; enclosing commit `ci: scope remaining isolated pure PR checks`. Exactly12 workflow lines are additive across three pure suites. [Complete receipt](docs/evidence/ci-remaining-pure-pr-filters.json), SHA256 `691e0456102f0dba43e94d9b175071b13cc516b7a7a7d24857fa57c76e4f2679`. Producer36 checks and independent source review pass; root confirms every applied hash, exact inverse and all29 source bindings to this parent.

Next: normal PR review/integration of this CI-only change. Preserve the pending cleanup terminal checks and original20i/capture/release gates. PR239's actual repository check39203822461 passed before normal merge; sourceafb24946 and merge7e1507f5 have identical trees. The connected GitHub app is available while the CLI REST verifier is rate-limited; its normalized first-page summaries omit runner/all-attempt fields and cannot close the103 strict audit gaps. The latest summary still shows catalog39098578401 acceptance and capture39137551561 build queued. No cancellation or acceptance rerun is authorized by this filter change.

## 2026-10-19 — superseded component queue checkpoint

Branch `codex/ci-superseded-queue-cleanup` starts at main `8f734de11031938eff81a02744d5195ecdcab17c`; enclosing commit `docs: record superseded CI queue checkpoint`. Recorded 327 acknowledged cancellation requests for superseded, unexecuted component runs. Independent evidence confirms 224 terminal cancellations covering 888 unexecuted jobs; 103 requests still need terminal verification. Preserved 31 candidates with existing work/results and left 139 of the 497 planned candidates untouched.

[Guide](docs/CI_SUPERSEDED_QUEUE_CLEANUP.md) and [partial receipt](docs/evidence/ci-superseded-queue-cleanup.json), SHA256 `98b3405ea2f13e67d3b88db37761571aaf0efcf1935c8e70aed2292fa72f5a23`. Preserve workspace `work/queue-review-2026-10-18` and `work/queue-cleanup-continuation-proposal`; all original plans, errors, requests and reviews remain. No cleanup process is running. Do not repeat any of the327 requests. Batch4 never started.

Next after API recovery: perform only GET verification for the103 unresolved requests, starting with39132937949 (acknowledged request, missing immediate post snapshot) and39111082537 (cancelled run, stale individual-job states). Domains' local continuation verifier and VPS's append-only recorder preserve the original failures. Do not replace partial receipts; add corrections. Keep139 untouched candidates deferred. Then inspect the preserved original catalog/capture results, review actual corrected captures, and complete normal integration/exact-main/release-download gates. Do not rerun queued acceptance or completed local artifact reviews.

## 2026-10-18 — original catalog runtime review

Documentation branch `codex/release126-original-evidence` starts from main `f688f79ee838e86e4774bfa5ec71fc7fb79126a5`; enclosing commit `docs: record original catalog runtime evidence`. [Evidence, source identities and remaining gates](docs/RELEASE_1.26_ORIGINAL_RUNTIME_REVIEW.md). No application, test, workflow, installer or version changes are part of this branch.

Preserve the four original f67 runs39098578401/39098583508/39098588864/39098593805 and the separate default8.3 run39098482550. All producer jobs are successful; hosted final jobs remain queued. Runtime identities come from authenticated envelopes, not assumed run ordering. The later capture source `b7c97953f323004e1fdd527bdef0b2da63f01c71` remains in PR220 with original run39137551561 queued. Inspect it and the actual changed captures when ready, then complete corrected-candidate, integration, exact-main and release-download gates. Do not substitute local evidence reconciliation for a hosted completion or rerun queued work.

The four original local aggregates and package/upgrade reviews are finished: each aggregate passes 216 reports and 28,873 named executions; packages match exact f67 and each other. Root rechecked all 864 report hashes and frozen review identities. [Committed receipt](docs/evidence/catalog126-original-runtime-review.json), SHA256 `bb81afeb131100c2e8a24dd7f779f427fd4def57c43436f3eeea236f69e36336`. Do not repeat this completed artifact review without a changed source, artifact or unresolved finding.

The first runtime's original envelopes and compact receipts are under workspace `work/catalog126-original-acceptance-review`; new large downloads and isolated assemblies use `D:/CodexArtifacts/Cajun_Web/catalog126-2026-10-18` because C: has about2.5GiB free. Its `storage.json` maps these locations. Retain both locations. No existing files were deleted or moved. Published1.25.0, Devin branches and other Codex candidates stay separate. PR237 remains `d1493f77f4c32d49b62256df1645dc60b9768cb9`, with its original full run39198491747 pending; do not mistake this documentation checkout for that candidate.

## 2026-10-18 — pure-suite CI follow-through

Starting main `e397d9a6e293850e8dff61a593e3ba39ee178400`; branch `codex/ci-pure-family-pr-filters`; enclosing commit `ci: limit isolated pure suites to relevant PR paths`. Five workflow files plus continuity/evidence documentation change. No shipping source, generated inventory, application workflow or published asset changes. [Trigger contract, actual checks and policy caveat](docs/RELEASE_WORKFLOW.md#2026-10-18--pure-suite-pull-request-triggers).

Next: normal PR review/integration of this CI-only increment, then inspect original hosting and nameserver results without duplicate dispatch. Keep hosting `3042f24e` ([PR231](https://github.com/afterburn25/Cajun_Web/pull/231)) and nameserver `024b50dc` ([PR230](https://github.com/afterburn25/Cajun_Web/pull/230)) frozen separately; local package/source checks do not certify installed acceptance. Hosting original runs39184742627/39184742510 were queued at the latest read. Review Devin's changed PR heads against the retained findings before selecting any non-20i integration. Do not assume those corrections are accepted. Published 1.25.0 is immutable.

## 2026-10-18 — CI trigger correction

Parent `6caf654c99cb5befb610f405e40df69b0c174cb6`; branch `codex/ci-pr-path-filters`. Ten focused workflows now use matching push/PR path lists, retaining prior paths and covering shared runtime, native Docker and mail dependencies. Source review verifies unchanged jobs and every non-path setting; 119 finite source/applicability checks passed. This is trigger validation, not new application acceptance. [Details](docs/RELEASE_WORKFLOW.md#2026-10-18--dependency-aware-pull-request-triggers).

Integrate this CI-only change through a normal PR. Existing queued/running runs and original failure evidence stay intact; do not dispatch replacement runs to address queue delay. The release and hosting feature branches are unchanged. Continue reviewing the original corrected 1.26 runs `39098578401`, `39098583508`, `39098588864`, `39098593805` and focused hosting run `39103179668` as their results arrive. The first corrected build passed; remaining acceptance is pending. Keep published 1.25.0 immutable. No new release or live deployment is authorized by this CI change.

## 2026-10-18 — cloud endpoint supplied no catalog details

The separate [cloud-only diagnostic](docs/TWENTYI_CLOUD_ROOT_DIAGNOSTIC.md#original-follow-up-outcome--2026-10-18), original run39090973425 at source `bf788894b1acd4c07daf74e718c4f95774acef85`, completed successfully after 42 passing offline checks. Its one fixed GET observed a JSON `null` root. This establishes the response type for that request and credential context, not an empty catalog, missing entitlement, provider fault or valid import contract. No cloud product fields were supplied. The original two-operation run39090394774 remains failed and unchanged; this later observation cannot retrospectively identify its scalar kind.

Continue the hosting catalog import using the observed `id`/`label` fields and documented package-type ID relationship. Do not substitute cloud data or automatic provider images. Three agents now own the hosting projection/importer, durable observations, and focused tests; root coordinates the admin flow and integration. No repeated cloud request is planned without new evidence. Published 1.25.0 and production remain unchanged. Earlier preparation blocks are historical.

## 2026-10-18 — separate cloud response-type check prepared

The [first catalog diagnostic](docs/TWENTYI_CATALOG_SCHEMA_DIAGNOSTIC.md#original-outcome--2026-10-18) remains failed and preserved. A separate [cloud-only follow-up](docs/TWENTYI_CLOUD_ROOT_DIAGNOSTIC.md) addresses its missing root-kind observation with one fixed metadata GET. It never repeats the hosting catalog request. Only a finite JSON type or the same closed container shape is exported; values, URLs and unknown names remain withheld. This source checkpoint prepares the follow-up, without claiming a live outcome or a valid cloud catalog. Hosting import implementation proceeds separately from the observed hosting fields and documented type-ID relationship. Released 1.25.0 is unchanged.

## 2026-10-18 — preserve first catalog outcome; review bounded follow-through

Start this documentation follow-up from `1159648f968d46439f7e88a9cd0f6dbee3758643` on `ops/twentyi-catalog-schema-read`. Original [run 39090394774](https://github.com/afterburn25/Cajun_Web/actions/runs/39090394774), attempt 1, failed after 39 passing offline tests: package-type field/type observations were retained, while the cloud operation ended with `unexpected_root` and no retained root kind. Its safe artifact is [copied byte-for-byte](docs/evidence/twentyi-catalog-schema-39090394774.json); [the diagnostic record](docs/TWENTYI_CATALOG_SCHEMA_DIAGNOSTIC.md#original-outcome--2026-10-18) binds the source, job, artifact and limits.

Next, review Domains' hosting follow-through against these observed spellings/types and VPS's proposed single cloud root-kind read. Keep unknown-key semantics, complete catalog coverage, provider prices and image contracts unresolved. The original failure is not a successful import and must not be retried automatically. This documentation-only update changes no diagnostic code or workflow and triggers no new provider read; root owns applying it with a documentation commit message. No provider writes or image downloads occur here. Preserve immutable release `b79c6711` and its published assets. Earlier pending-read statements below describe the preparation checkpoint, before this original execution.

## 2026-10-18 — next original catalog metadata read

The published release remains immutable b79c6711. Root's `ops/twentyi-catalog-schema-read` branch starts from documentation main bdbdf3e1 and adds the reviewed [catalog diagnostic](docs/TWENTYI_CATALOG_SCHEMA_DIAGNOSTIC.md). Preserve its first run, checkout identity and shape artifact after execution. Do not rerun automatically or treat lexical field/type observations as proven product/pricing/image semantics. At this source checkpoint the read is pending; no actual provider response is claimed. Owner-requested import work authorizes these two necessary metadata GETs only; this diagnostic performs no provider writes or image download.

# CajunWebPro 1.25.0 release handoff

## 2026-10-18 — resume after verified publication

[CajunWebPro 1.25.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.25.0) is published and freshly download-verified from immutable source `b79c67110829126916e3a65e616191542448bccc`, tree `f862cc9205e073513f419cdf5ff24cacd1f801df`, after the normal [PR180](https://github.com/afterburn25/Cajun_Web/pull/180) merge. All 18 release assets match their accepted bytes; 1.24.0 is unchanged. This is a GitHub release, not a live deployment.

The acceptance, assembly, draft and public verification stages are complete; [the publication record](docs/RELEASE_1.25.0_PUBLICATION.md) binds the original runs, all 330 reviewed canvases and the exact public proof. Preserve the released tag/assets and all earlier failed/cancelled evidence. This documentation follow-up starts from `b79c67110829126916e3a65e616191542448bccc`; its later commit does not become the release source.

For the existing website, use UPDATE and the same-release migration instructions. Never delete installed configuration or locks to run setup; fresh start is a separate explicit option and does not recover missing original keys. No live deployment has occurred.

Next: Codex resumes held 20i work from the documented gaps and existing branches; Devin continues non-20i proposals separately. The full owner API reference is already retained. Root is preparing a reviewed, bounded read-only schema diagnostic for the owner-requested catalog import, limited to GET /reseller/*/packageTypes and GET /reseller/*/cloudServerProductData; no writes, arbitrary endpoints or image downloads. It has not yet executed. Unaccepted Devin proposals still require exact-head review and fresh acceptance before later integration.

Earlier dated candidate and branch checkpoints below retain their original evidence and failures. Their pending-work statements do not supersede this publication record.

## 2026-10-17 — resume from delivery selector correction

The enclosing correction starts from `de97e63b3354f52ee26508bfc0609e951a6ee20c`. Original default run[39047519450](https://github.com/afterburn25/Cajun_Web/actions/runs/39047519450) failed browser acceptance at the delivery manifest: generic `summary` matched the new navigation groups as well. Preserve its seven original archives and failed-job logs. The two manifest clicks now use `.delivery-detail > details > summary`, with an additive exact fixture pin; shipping source and every release gate remain intact.221 helper checks and12 actual Chromium checks with exact PHP renderers/synthetic rows passed; this is not native/HTTP/full application acceptance.

Require a fresh exact corrected candidate, default PHP8.3 and four supported runtime runs, maintained checks and330 actual images. Keep earlier de97 runs/proofs as parent evidence; their build/recovery/Apache and maintained passes do not certify the successor. Then normal-merge to main and complete fresh exact-main/publication/download gates. Main remains `d7153b07`,1.25 unpublished,1.24 immutable and production unchanged. Devin's separate proposals have concrete review feedback on PRs202,203,205,209,210,211,212,213; preserve those branches and resolve their findings before later integration. Full20i catalog/image fields remain unresolved; the owner has already supplied the retained full reference and must not be asked to resend it.

## 2026-10-17 — combined owner additions ready for acceptance

Release branch `codex/release-1.25.0` normally integrates installer `345b335e` ([PR206](https://github.com/afterburn25/Cajun_Web/pull/206)), navigation `e8ccefc9` ([PR207](https://github.com/afterburn25/Cajun_Web/pull/207)), and observed hosting import `ecf52ee4` ([PR208](https://github.com/afterburn25/Cajun_Web/pull/208)). Integration commit `4b4ce250064036b228747db7550f7c14d3a2bcb0` has exactly the reviewed importer tree, including its installer and navigation ancestors. This enclosing documentation checkpoint records that integration before the fresh full acceptance dispatch. [Release PR180](https://github.com/afterburn25/Cajun_Web/pull/180) remains draft.

Actual local verification includes the shipping PHP/JS build refresh, all 2,894 installer hashes, 221 offline acceptance-helper checks, and the documented installer/navigation/import checks. A native-test-only missing studio form helper was corrected and independently verified with the actual PHP include graph; no application behavior was changed by that correction. Native database, multipart HTTP, combined browser images and full release acceptance remain pending. New expected report/image totals are 208 and 330, retaining all prior assertions and adding the import cases. Earlier parent results cannot certify this combined source. Main remains `d7153b07`, 1.25.0 is unpublished, 1.24.0 remains immutable, and production was not changed. Next: full combined candidate tests, then the documented main and publication gates.

## 2026-10-17 — observed hosting draft imports and product images

The owner requested admin import of 20i products including images. This bounded increment starts from reviewed navigation source `e8ccefc931829aa9245a71d4ba0d310db2d0add6` on `codex/twentyi-product-import-1.25.0`. It adds an owner-only catalog panel to create hosting drafts from named types in the latest saved 20i inventory, with editable local details and image upload/replacement. Complete provider catalogs, provider pricing/images, hosting publication and ordering remain unfinished. See [scope, setup and evidence](docs/TWENTYI_PRODUCT_IMPORT.md).

Current owner, saved-observation and credential checks protect preview/import; repeated requests preserve the existing draft. The import source is retained, family changes cannot bypass draft restrictions, and media cleanup shares the import lock. Uploaded images retain the existing public-URL behavior, explained in the form. Public catalog and invoice projections remain unchanged.

Actual local checks: 48 document cases in each of default/minimal PHP 8.4.25, changed-source syntax, and 221 offline helper checks with the exact reviewed CI wiring, now applied. Independent owned-source and wiring reviews are clear. Native database, genuine multipart uploads, browser pixels and combined release acceptance remain pending. The source-derived expectations are 65 new native and 24 HTTP checks; these are not passing results. Original 149 native recipes, all earlier reports/assertions and 328 release images are retained; 3 commands/reports and 2 images are additive. Published 1.24.0 is unchanged, 1.25 is unpublished, and no live deployment occurred.

## 2026-10-17 — grouped administration and public service navigation

The owner-requested navigation increment starts from installer correction `345b335eeea8331bba5800aa926a3bc1c7507100` on `codex/admin-navigation-1.25.0`. It groups all 36 existing administrator destinations into collapsible categories with current-account permissions, active-page opening and mobile keyboard controls. The public menu adds Hosting, Web Design and Products; Hosting is consultation-only and Products preserves the existing published service allowlist. See [behavior, evidence and remaining gates](docs/NAVIGATION_1.25.0.md).

Local actual-render PHP/Chromium checks passed: 134 admin and 91 public checks with explicitly synthetic data seams. Native database, combined candidate and exact-main release acceptance remain pending. Installer correction is committed in [PR206](https://github.com/afterburn25/Cajun_Web/pull/206); bounded 20i product import continues separately. Main remains `d7153b076e91b524ff2f99a9a7aa5c910551fc5e`; 1.25 is unpublished and no live deployment occurred.

## 2026-10-17 — installer layout, administrator passwords and explicit fresh start

Owner-requested correction on `codex/installer-layout-1.25.0`, starting from `bc5ec277580aa681f43aaf2c9b97df730f6954f1`. Server checks move left on desktop; Website details follows Website files; the exact-permission report and redundant one-form sentence are removed. Administrator setup, activation, reset and account changes use a minimum of 12 Unicode characters and the existing 72-byte maximum. Client password policy is unchanged.

Ordinary file replacement retains configuration and settings. Missing configuration with a surviving local installation marker now shows recovery guidance rather than redirecting into setup. An optional, default-off **Start a fresh website** choice selects separate tables and external private storage, retaining previous data. Configuration and installation locks still block setup; no old tables, locks or media are deleted. Retained data is not a recoverable backup without its original configuration and encryption keys. See [behavior and verification](docs/INSTALLER_FRESH_START_1.25.0.md).

Local password boundaries and isolated PHP/Chrome layout checks passed within their documented scope. Native database, complete fresh-start/install/file-overwrite acceptance, final source review and release acceptance remain pending. Parent bc5 has verified Windows/four-Linux build partitions and maintained compatibility (54 reports, 3,280 named executions). At 15:02 UTC its five original full runs still had native checks active, with successful build/recovery/Apache jobs. Those parent results do not certify this shipping change. Main remains `d7153b076e91b524ff2f99a9a7aa5c910551fc5e`; 1.25 is unpublished, 1.24 immutable and production unchanged.

Next: finish independent source/UI review and focused fixtures, commit this branch, normal-merge into the release lane, then bind fresh complete candidate acceptance. Preserve original bc5 runs/evidence and all prior failures. Publication still requires exact-main acceptance and fresh verification of all 18 assets.

## 2026-10-17 - complete acceptance job allowance

The enclosing correction starts from `88f116f0caf4a9bd8ea6f7faafd1e0eadb432039`. Its supplemental PHP 8.3/MariaDB [run39024070818](https://github.com/afterburn25/Cajun_Web/actions/runs/39024070818) reached the final scheduler flow before GitHub cancelled the combined native/browser job at its three-hour limit. All seven original archives and five completed logs are preserved. The checkpoint is incomplete, not a pass. Independent timing review supports increasing only this job's overall allowance from 180 to 240 minutes. Every test command, native-to-browser state, assertion, application/request/browser/provider timeout, report and image requirement remains unchanged. See [the timing evidence](docs/ACCEPTANCE_JOB_BUDGET_1.25.0.md).

Evidence cutoff: **2026-10-17 13:51 UTC**. Parent88f has three fully verified original environments (both PHP 8.2 databases and PHP 8.4/MySQL), maintained/minimal compatibility, all four recovery and Apache/published1.24-upgrade partitions, and actual328-image review with independently checked coverage and explicit capture limits. PHP 8.4/MariaDB was still running. These are parent results; the correction still requires fresh exact-candidate full acceptance and review, normal main merge, fresh complete exact-main acceptance/images, all18 assets, draft/public fresh-download verification and the owner UPDATE handoff. The corrected default PHP8.3 full run must also finish; the interrupted run cannot stand in for it. Main remains `d7153b076e91b524ff2f99a9a7aa5c910551fc5e`;1.25 is unpublished,1.24 immutable and production unchanged. No new20i capability is added.

Later observation, **2026-10-17 13:58 UTC**: required PHP 8.4/MariaDB run `39024087120` also concluded cancelled after its combined native/browser job reached the three-hour cap; final aggregation failed. Its original build, native database, recovery and Apache phases passed, while browser acceptance remains incomplete. The original job annotation and five logs are retained; archive collection is in progress. This does not change the earlier cutoff or certify a passing candidate. Local validation of the correction passed all 215 acceptance-helper checks and the documentation check (265 Markdown files, 1,781 local links). Root and independent source review confirmed that the complete workflow differs semantically only in the aggregate 180-to-240-minute allowance. Shipping code and tests are unchanged.

## 2026-10-17 - verified bounded StackCP correction integrated for full acceptance

Normal integration preserves parent `60c053b5c9f5a5fded943930500ea57cddd20ff8` and reviewed correction `fd1d4799de8c72cb88a2426f5811101f788446e0`; the enclosing merge commit identifies the new candidate. All four original 60c full runs and the supplemental PR run failed and remain preserved. The MariaDB lanes timed out loading the prepared access section; both MySQL lanes failed the latency-sensitive scheduler assertion. Their partial passes do not certify the correction.

Original isolated [diagnostic run 39023255633](https://github.com/afterburn25/Cajun_Web/actions/runs/39023255633), attempt 1, passed on PHP 8.3.6 / MariaDB 10.11.19: 78 native, 56 extracted-package HTTP and eight runtime checks. On its retained twenty-card dataset, access-section database statements fell from 6,293 to 2,823 and elapsed time from 3.951 to 1.264 seconds. Four complete 30-second-load browser navigations completed in 2.279 / 2.017 / 0.442 / 0.436 seconds with unchanged data/key hashes. All 2,887 COMPLETE members match exact fd1d Git and all 2,886 installer hashes agree. Root 61 and separate 90 evidence checks passed; the latter reviewer authored the fixture and reviewed execution evidence, not independent authorship. The runtime/fixture source had separate independent reviews. These are bounded 21-link diagnostic results, not accumulated full-browser or PHP 8.2 performance proof. Four diagnostic PNGs grant no credit toward release image review.

The additive full workflow now runs the 78-check comparison immediately after the prerequisite snapshot fixture: 149 exact native recipes, 161 browser / 205 final reports and 124 source pins. All 148 preceding recipes, original report requirements, 320+8 images and timeouts remain. The finite seed receipt is diagnostic metadata and cannot mask a failed native report. All 215 offline helper regressions passed; whole-object/source inverse reviews found no weakened gate. The scheduler fixture retains exact event + 1,800 seconds within its request window, with every other outcome/privacy assertion intact.

Evidence cutoff: **2026-10-17 10:12 UTC**. Required next: fresh exact-candidate Windows/four-environment/maintained acceptance, actual 328-image review and independent aggregate review; normal main merge; fresh complete exact-main acceptance and images; 18-asset draft/fresh-download verification, publication and separate fresh public verification. Main remains `d7153b076e91b524ff2f99a9a7aa5c910551fc5e`; 1.25 is unpublished, published 1.24 is immutable and no production deployment occurred. New 20i capabilities remain frozen until verified publication.

Continue the release-first scope in [status](PROJECT_STATUS.md), [frozen scope](docs/RELEASE_SCOPE_1.25.0.md), [integration map](docs/RELEASE_1.25_INTEGRATION_MAP.md) and [release workflow](docs/RELEASE_WORKFLOW.md). The main35d8/mature3fa integration and subsequent main documentation merge already exist; do not repeat feature imports. Preserve the current licensing serialization, provider authority, original histories and installer/source parity.

Use actual source-bound receipts to determine the next unfinished gate: complete candidate acceptance and independent visual/source/package review; normal main merge; complete exact-main acceptance; immutable tag/release assets; then fresh all-asset download verification. Do not infer completion from this handoff or a prior source. Preserve original failures and reviewed corrections. New capabilities and the external PHP snapshot proposal remain held until publication is verified.

Keep all148 original native recipes plus the new access snapshot comparison (149 total), complete PHP 8.2/8.4 × MariaDB10.11/MySQL8.4 environments, the maintained/minimal lane and the current161 browser / 205 final report contracts. Actual published 1.24 upgrades must preserve original keys/data/history in root/subfolder and both rewrite modes, including interruption/recovery/reapplication. Recurring/licensing did not exist in stock1.24; their post-upgrade preservation is a separate check.

The tracked [acceptance report](docs/TEST_REPORT_1.25.0.md) has an explicit source/evidence cutoff. Final exact-main and publication facts belong in the external release report and receipts once those events occur; do not amend an accepted source merely to insert its own future test results. Verify identical versioned/compatibility report asset bytes before publication. No live deployment is authorized. Resume remaining20i only after the release and downloads are verified.

## 2026-10-17 - paired domain fixture timestamps

Continue from parent `c0ed18397349a023224623027ba5bfd3bf9d7e67` with only the reviewed timestamp correction in `tests/domain_expiry_native.php` and `tests/domain_contracts_native.php`, their two acceptance-contract pins and these five continuity documents. Capture one `time()-600` value for both rows in each fixture; do not relax the production equal-epoch guard or any assertion. The full source-binding inventory becomes 122 pins; all 148 native recipes, 160/204 reports, 320+8 images, workflows and timeouts remain. Root owns application, commit and fresh identities.

At **2026-10-17 06:24 UTC**, original PHP 8.4/MySQL run39005276959 had failed after 67 domain-expiry passes, skipped Chromium and failed its final aggregate. Its seven archives/five logs remain preserved; the other three full runs were active. Parent c0ed's build, maintained, recovery and Apache passes are bounded evidence only. Review the actual correction and local evidence, freeze/build/bind its observed source, then complete every candidate/maintained and visual gate before normal merge, complete exact-main rerun and all-asset publication/download verification. No future commit/run/Windows outcome is assumed. Main `d7153b07`, published 1.24 and production stay unchanged; 1.25 is unpublished.

## 2026-10-17 - scheduled-run browser response completion

Continue from the narrow correction to parent `cc0ec4a9b4131498831fb761d4a2d7f93ade4604`: wait for the scheduled-run Chromium page load before the separate requests client fetches its next form, and pin the corrected fixture in the acceptance contract. All original requests, assertions, timeouts, captures and shipping/workflow bytes remain. Independent actual localhost reproduction passes 13 checks with the same 15-second request timeout; its 20-second render tail is synthetic and does not profile the application database.

The original PR run38994351830 failure after 3,003 passing checks remains preserved with all seven artifact archives and completed logs. At **2026-10-17 05:07 UTC**, all four original cc0 full runs are still executing Chromium; their build, native workflow stages, recovery and Apache jobs passed. Complete parent browser acceptance and actual image inspection remain absent. Retain their eventual outcomes; do not count them as corrected-source evidence. Build and bind the actual descendant, run every complete candidate/maintained lane and review all 328 images, then normal-merge and repeat complete exact-main acceptance before publication and fresh verification of all 18 assets. Main `d7153b07`, immutable 1.24 and production remain unchanged; 1.25 is unpublished.

## 2026-10-17 - exact WordPress client projection assertion

This release-only correction starts from `d55a347ec0dec8e351e6cf325e3f3a16cba3a821`; its enclosing commit identifies the corrected candidate. The original [PR acceptance run](https://github.com/afterburn25/Cajun_Web/actions/runs/38989916313) failed the WordPress client projection assertion after 34 successful named checks. Its preview source has the exact candidate tree. The fixture searched all JSON for sibling package digits `2202`, which also occur in a valid timestamp and can occur in an opaque observation ID. The failed per-call projection was not retained; source inspection and modeled reproduction establish the assertion defect without claiming that operand was captured.

Continue from this correction, preserving the exact original failed run and its artifacts. The fixture change and contract pin are the only executable/contract differences from d55; shipping/workflow bytes remain unchanged. Rebuild and bind the actual corrected source, dispatch its original complete four-environment matrix plus maintained lane, review exact artifacts and actual pixels, then follow normal merge/exact-main/publication gates. Do not use earlier passes to certify the new source.

## 2026-10-17 - payment throttle fixture boundary correction

This release-only correction starts from `9e7b62e9520ad728744bfde62cff0e5ab1dce37f`; the enclosing commit identifies its descendant. Evidence cutoff is **2026-10-17 01:12 UTC**. Original PHP8.2/MySQL full run [38985873076](https://github.com/afterburn25/Cajun_Web/actions/runs/38985873076) failed the native payment assertion `Authentication attempts are rate limited` after32 passing checks. Its job ran across01:00 UTC, a fixed15-minute rate-window boundary. Source inspection and a deterministic reproduction support the rollover explanation; original per-call bucket rows/timestamps were not retained. The failed report, original logs and incomplete full run remain preserved and are not accepted.

The isolated `payci_` fixture now prepares the current and immediately following owner-specific counter rows, calls the actual payment verifier once, requires its precise throttle refusal with no provider call or gateway/audit change, verifies exactly one counter increment, and restores the original two rows in `finally`. Unexpected errors, other state changes and clocks outside those two windows fail the assertion. All54 literal check-label occurrences remain in order. Shipping code, rate-limit policy, workflows, all148 native commands,160 browser/204 final reports and320+8 required images are unchanged; the acceptance contract pins the corrected fixture.

Local validation consists of12 source/syntax/inverse checks and13 modeled boundary/fault cases using the exact production limiter/verifier and proposed fixture with deterministic clock and in-memory database boundaries. These are not native database, HTTP or full application acceptance. The first probe's12/13 result is preserved: its harness incorrectly expected unrelated deliberately injected mutations to be erased. The corrected harness verifies refusal, restoration of only the fixture's own rows and continued visibility of the unrelated mutation; it did not change the proposed fixture.

Parent9e has verified Windows/four Linux package agreement and a complete maintained/minimal runtime lane. Those bounded passes do not certify its failed full matrix or this descendant. Next: build the corrected source, run all four full environments and maintained lane, inspect actual browser/upgrade images, obtain independent exact-candidate review, normal-merge to main, rerun complete exact-main acceptance, and publish/freshly verify all18 assets. Main remains `d7153b07`;1.25 is unpublished,1.24 immutable, complete20i unfinished and no live deployment occurred.

## 2026-10-17 — PHP 8.4 upgrade fixture compatibility

This release-only correction starts from `155838eb8574dcc96f7d038cc306677d2d4784a0`; its enclosing commit identifies the descendant. Both original PHP 8.4 full runs, [MariaDB 38975755408](https://github.com/afterburn25/Cajun_Web/actions/runs/38975755408) and [MySQL 38975766343](https://github.com/afterburn25/Cajun_Web/actions/runs/38975766343), passed native integration and stopped at HTTP check126: `upgrade setup cannot be reused after update` (125 pass, one fail). The installer was physically absent; the retained request logs show HTTP200 for its removed URL. That response body was not retained, and the original failed reports/artifacts remain preserved.

The [PHP built-in server manual](https://www.php.net/manual/en/features.commandline.webserver.php) documents a PHP8.4 change: a missing filename can use the directory index. Six related fixture assertions incorrectly required the pre8.4 HTTP404 behavior. The corrected shared helper retains physical installer absence before and after real GET/POST requests, rejects redirects and setup UI, and permits the documented200 fallback only on observed PHP8.4 when its complete homepage DOM matches a separately fetched index after normalizing the one validated CSP nonce. All six original named checks remain; earlier runtimes still require404. Shipping code, Apache404 expectations, database assertions, all148 native recipes and the160 browser/204 final report requirements remain unchanged.

Local validation: 66 offline helper/source guards and an independent 12-scenario/29-request real PHP8.4.25 helper run pass. The latter uses synthetic HTML and does not establish full application acceptance. Root confirms all273 existing named checks in the six edited fixtures remain unchanged. Independent source review found no concrete blocker in this narrow correction.

Parent155 has verified builds across Windows/four Linux environments, all four Apache/published1.24-upgrade/recovery partitions, the maintained runtime lane, and the supplemental licensing flow. Eight upgrade and six supplemental licensing images were actually reviewed. These are bounded parent results; neither failed full run is accepted. The corrected descendant still requires its complete four-environment/maintained acceptance, actual browser review, a normal main merge, complete exact-main acceptance, and publication with fresh verification of all18 assets. Main remains `d7153b07`; v1.25.0 is unpublished, v1.24 is immutable and no live deployment occurred.

## 2026-10-16 — separate licensing page and action refusals

Start the fixture-only correction from `f77c9fb7f4b1b57d13ce352dd7e4fc51b1330695`. The shared licensing HTTP helper incorrectly expects an action-denial page after posting to an owner-only view. Actual run38965358937 preserves HTTP403 and the section-denial message at check39; all four native114-check jobs pass. Root and independent source review identify the same later nonowner legacy-review mismatch. Production authority remains correct.

Retain the twenty exact response records, six images, all existing history comparisons and strict denial expectations. Add actual staff-login and exact403/no-history-change page checks; route only the two ordinary-staff POSTs through the accessible dashboard. Align the changed helper's acceptance-contract hash. No shipping or workflow change is needed. Intended commit: “Separate licensing page and action refusal checks”. Evidence cutoff is **2026-10-16 22:03 UTC**; corrected real execution is still pending.

Run the corrected packaged licensing diagnostic first, using a temporary diagnostic ref if needed to avoid dispatching a full matrix before the blocker clears. `codex/release-1.25.0` remains the sole release integration branch; use the same reviewed descendant there, not an alternate release source. Then bind and run every required complete candidate/maintained lane, actual visual/package checks, normal merge and complete exact-main acceptance before eighteen-asset publication and fresh download verification. Preserve f77's original failed/partial evidence and any explicitly retired incomplete runs. No production deployment or previous-release modification.

## Historical checkpoint — licensing fixture and release runtime provisioning

Batch the reviewed release-only corrections from exact `8f54d36e418ee112ae5c3cb8ac4c63ceb277be32`: one licensing helper import and contract hash, one supplemental runner/job, four guarded PHP 8.2/8.4 mirror selections, and five continuity records. Intended commit description: “Repair licensing fixture and release runtime provisioning”. Preserve original signing scope/key bytes and current fingerprint, strict APT/TLS/probes and every application assertion. Distro PHP 8.3 stays unchanged. Shipping code is unchanged.

Keep the parent-evidence cutoff at **2026-10-16 20:43 UTC**: two build-only Linux/Windows agreements; two PPA 503 setup failures before application tests; three focused native environments × 755 plus distro 260, with one native job pending. Retain the original 13a HTTP failure and missing historical stderr separately from the proven missing-workspace reproduction. B42's 666-check/eight-image actual upgrade diagnostic is supplemental parent evidence. Exact receipts are recorded in the test report; subsequent parent results stay in external receipts.

The new licensing diagnostic is unexecuted. The mirror has source review, 41 offline guards and four shell syntax checks, not an APT installation result. Verify both on the committed descendant, then complete all four full environments and maintained runtime, actual visual/package review, normal merge and complete exact-main acceptance before publication and fresh download verification. Preserve old runs and prior release assets. No new feature, release clearance or live action is included.

## Historical checkpoint — package acquisition retry and retained b42 evidence (2026-10-16)

Base this combined correction on frozen `b42dda4cf763ca48a90b145673214a9641b2e024`; do not invent its descendant identity or reuse parent acceptance. Batch only the reviewed APT acquisition policy (`6dd1e097`, workflow `dacb9292`) and neutral/historical instruction addendum (`76906652`) with these checkpoint records. The policy changes eight updates/eight installs, uses five acquisition retries and update error mode `any`, and leaves application tests, package selection, signatures and TLS unchanged.

Preserve b42 exact Windows proof `45f34633` (5915/2887/2874 members), e713 focused maintained-cloud proof `36aaf524` (four 755-execution environments plus 260 distro/minimal checks), and original setup classification `fe776245` (eight PPA HTTP 503 failures: seven DEB/one index; two successful same-runtime/source comparisons). Source/script review is not a package-acquisition or application pass. Older failed/cancelled records and immutable 1.24 assets remain intact.

The original b42 upgrade diagnostic 38951170365 attempt 1 was in runtime setup at the retained 19:15 UTC snapshot; no pass is claimed. Root follows that original run. Only after actual diagnostic success should the frozen descendant receive a new complete four-environment matrix and maintained lane. Bind new evidence helpers to its observed source/run IDs, preserving earlier chains. Required exact-candidate/main, upgrade, image, package and downloaded-release gates remain. Last verified main is `d7153b07`; no 1.25 publication or live deployment has occurred.

## Historical checkpoint — actual upgrade fixture correction and focused diagnostic (2026-10-16)

The correction starts from `e713623cb93c98642ca8fd1dd61f66a9b72112e2` on `codex/release-1.25.0`; the enclosing Git history identifies the corrected source. The original13a full run [38942023179](https://github.com/afterburn25/Cajun_Web/actions/runs/38942023179), Apache job117113081566, passed its prior Apache checks but stopped after five actual1.24 upgrade checks during original mail-account seeding. Failure proof `ab76032d` retains the source/preview tree, artifact digest,116-member partition and explicit exception. No upgrade scope or new upgrade capture completed.

Root applied the independently reviewed three-file document-root correction (`dc7072d1`, root proof `3d2c62d5`), aligned its three acceptance-contract source hashes and added one supplementary PHP8.3/MariaDB10.11 diagnostic workflow (`059dee57`, review `7ad08c6c`, root application `7d02f281`). The legacy CLI fixture now receives the actual validated Apache public root. Its old callers retain their defaults. All production privacy checks and complete148-command acceptance workflow remain intact. The diagnostic builds its exact source, uses only the pinned published1.24 package, exercises all four layout/rewrite scopes and preserves bounded evidence after success or failure. It cannot replace complete candidate/main acceptance.

Actual e713 focused [probe38946655478](https://github.com/afterburn25/Cajun_Web/actions/runs/38946655478) passed both PHP versions: seven reports and1,063 named executions each, verified proof `84a92ff2`. Actual e713 focused [ForceSSL38946655205](https://github.com/afterburn25/Cajun_Web/actions/runs/38946655205) passed all four PHP/database environments: seven reports and876 named executions each, verified proof `6553f1dd`. These results certify their focused source only. Original failures and earlier cancelled partial evidence remain preserved.

Local correction evidence is limited to28 portable path/guard cases,47 diagnostic YAML/AST/source/evidence cases,664 documentation proposal checks, syntax and source review. The subsequent original1.24 form audit (`7549afae`) found no further concrete mismatch on inspection; it is not HTTP execution. Next run the first corrected-source focused upgrade diagnostic, resolve any concrete failure, then run the complete original four-environment matrix and maintained lane on the frozen corrected source. Inspect actual images and packages before normal merge; repeat complete exact-main acceptance before publication and fresh all-asset verification. At this source cutoff main remains `d7153b076e91b524ff2f99a9a7aa5c910551fc5e`,1.25 is unpublished and no live deployment occurred.

## Historical handoff checkpoints

The records below retain their original source-specific instructions and failures. They do not override the release sequence or current versioned evidence above.

### Historical checkpoint: Observation-fixture correction checkpoint — 2026-10-16

Canonical `codex/release-1.25.0` remains in [draft PR #180](https://github.com/afterburn25/Cajun_Web/pull/180). This correction starts from `0614089482073e8779f7b130b0c8306f7d1f6c6f`; its enclosing commit records the new exact candidate. Frozen included/held features are unchanged. Only four native observation fixtures and continuity documentation change; application, schema, installer, workflows and named test sequences remain unchanged.

Original [Force SSL workflow 38942022993](https://github.com/afterburn25/Cajun_Web/actions/runs/38942022993), source13a/merge-preview8d6, failed in the inherited PHP-observation privacy assertion after29 passing PHP checks. ForceSSL119, document-roots115 and pure165/165 checks passed first; the other three environments passed all7 reports/876 executions each. Four archives, metadata and the failure log are retained. The original compound assertion did not retain its projection, so the exact historical failing operand remains unknown.

Exact-source controlled projection reproductions prove that blanket JSON substring `702` can reject valid random public references or observation timestamps. PHP, ForceSSL, directory-listing and document-root fixtures now compare the complete permitted client shape, exact scalar/map, timestamp and current flag, plus the exact receipt only where exposed. Extra private fields and sibling data remain refused. All original named assertions and sibling/other-client/support checks remain. Safe mismatch diagnostics contain booleans only.200 extracted-code checks across four fixtures/default/minimal PHP and four syntax checks pass; these do not substitute for native database execution. Independent source review is retained with the correction.

Parent061 Windows packages pass exact source, member/directory/checksum, installer and updater checks (5914/2887/2874 members). Parent13a cloud run38941928822 passes four native755-execution environments and260 distro/minimal checks. The frozen external full-run verifier has independent reviews and111 additive/132 preparation checks, including retained original keys and published1.24 upgrades. These are bounded evidence, not full candidate acceptance.

Parent061 original full runs38943147018/38943159324/38943171219/38943182554 and maintained38943195376 stay preserved; they cannot certify this new source. Next: push the corrected candidate, rebuild exact packages, dispatch its original complete four-environment matrix and maintained lane, verify all actual results/captures and review the exact source. Then normal-merge, repeat complete exact-main acceptance, publish and download-verify every asset. Main remains `d7153b07`; stable1.24/tag/assets remain unchanged. No1.25 release or live deployment exists.

### Historical checkpoint: Frozen candidate CI checkpoint — 2026-10-16

Candidate `13a7b08cc8f8ac1cf2a5e1ea6b4027a28cf2a597` (tree `62eeed74de3fa89347589ec5fce5be1ebdd069b5`) is pushed on `codex/release-1.25.0` in [draft PR #180](https://github.com/afterburn25/Cajun_Web/pull/180). The four original full runs `38941882420`, `38941894576`, `38941906749` and `38941919998` failed during runner setup: selected PHP8.2/8.4 apt packages were unavailable after the cached runtime setup. Application/native/browser/upgrade tests did not run in those attempts. All eight original artifacts, metadata and failure logs remain retained; repair provisioning before starting corrected full runs. No gate or existing test is removed.

Original licensing run [38941836904](https://github.com/afterburn25/Cajun_Web/actions/runs/38941836904) passes 114 exact named native checks in each of four supported environments (456 executions,114 distinct cases), including actual missing-history, interrupted-setup, master isolation and invoice-review cases. Downloaded archives, runtime versions, PDO/ZIP, hashes, CRC and source/attempt identity were independently verified. This is native licensing evidence only. Exact13a independent migration/authority/upgrade review records66 checks across38 files with no remaining P1/P2 within that scope. Clean Windows FULL/COMPLETE/UPDATE packages match exact Git bytes, installer inventory, strict updater metadata/shims/payload, all checksums and directory/member lists (5914/2887/2874 members). They remain candidate artifacts.

The default PHP8.3 PR run `38942023179` builds the separate merge-preview SHA `8d6c90287d9417e073aa4178d33ebac4fa6cc51b`; its successful build cannot substitute for exact-branch full acceptance. Focused probe run `38942022550` failed because the inherited test sent a delete-operation body to the strict renewal endpoint. The reviewed fixture now uses a valid renewal body for the authority gate and separately checks malformed renewal rejection before authority/network; local default/no-INI/disabled-cURL results pass52/53/53 checks. Both original artifact archives and failure logs remain preserved. No shipping behavior changes. Main remains `d7153b07`, published1.24 remains immutable, no1.25 release exists and no production deployment occurred. Full supported runs, actual packaged HTTP/Chromium and populated upgrades, pixel review, final independent review, normal merge, complete exact-main rerun and publication/download checks remain mandatory.

The correction commit following13a changes only the full-run provisioning workflow, the stale probe fixture and continuity documentation. PHP8.2/8.4 CLI/extensions/Apache now use the same signed Ubuntu Noble package repository; PHP8.3 keeps its distribution path. Every148 native recipe, runtime probe, report, timeout and remaining workflow step is retained. The integrated transfer/provenance helper still passes208 checks. These source/local checks require fresh complete CI at the correction commit.

### Historical checkpoint: CajunWebPro release handoff

Continue **release 1.25.0 first**, not feature expansion. Read [status](PROJECT_STATUS.md), [frozen scope](docs/RELEASE_SCOPE_1.25.0.md), [integration map](docs/RELEASE_1.25_INTEGRATION_MAP.md) and [release workflow](docs/RELEASE_WORKFLOW.md). Canonical `codex/release-1.25.0` starts at main `35d8fb7efc94f6f851399d0755fa93097f1fce66` and normally merges `3fa72d7b3b447fa3d281cc981ed861c508f850f2`. Git history identifies the resulting integration SHA. Main and all published assets remain unchanged during candidate work.

The shared union keeps main serialized licensing/checksums/marker3 and candidate physical/support/invoice-purpose guards plus provider inventory5. Root applied the independently reviewed16-file licensing correction and optional-storage fixture fix, preserving selected PHP runtime handling. Master keys stay outside client deliveries; populated older adoption is additive; a retained invoice boundary prevents automatic duplicate historical fulfillment; explicit owner review requires exact evidence/password. Future markers and missing established tables refuse recovery bypass. Native/HTTP acceptance is still required. Preserve both native test sets and setup-form-first ordering. Root owns shared edits and installer regeneration.

Catalog owns additive full PHP8.2/8.4 × MariaDB10.11/MySQL8.4 acceptance wiring, including actual selected Apache/runtime evidence; retain distro/minimal compatibility and every existing native/browser/report requirement. Domains owns authentic published1.24 root/subfolder rewrite-on/off client/project/file/invoice/support/mail/provider preservation and repeat/interruption/recovery/reapply tests. Recurring/licensing absent from actual1.24 must be tested as post-upgrade additions, never invented baseline tables. External proposals need root review before application.

Source3fa original focused38933590118 passes all four environments (1,440 named executions each) and Windows5890/2885/2872 package verification. Evidence `efa6da57` and `faa38a5b` is retained; parent5bfd five-job cloud/distro proof `ab93db13` is also retained. These are not combined-release acceptance. Original cccf/2a failures and9779 timeout remain immutable; do not rerun unchanged sources or replace historical evidence. New PHP snapshot proposal stays external and held.

After exact candidate gates and independent review, normal-merge to main, rerun complete acceptance at exact main, build FULL/COMPLETE/UPDATE and all reports/manifests/checksums, publish immutable v1.25.0, then download and verify every asset. Deliver the UPDATE package and owner backup/migration instructions. Do not stop at planning, build, candidate checks, merge or tag. No live deployment authorization. Then resume complete20i incrementally.

Devin's separate assignment and posted repairs158/159/161 remain visible in [start here](DEVIN_START_HERE.md); preserve dependent PRs through178. Release integration does not silently accept those PRs. Main remains documentation checkpointd7153b07; candidate parent85f0b489 preserves it normally. [Acceptance report](docs/TEST_REPORT_1.25.0.md) and [owner update instructions](docs/MIGRATION-INSTRUCTIONS-1.25.0.md) are now tracked. The approved portal concept image is unavailable and its dedicated shell is not implemented; preserve existing layout and do not claim a visual match. Published1.24 remains stable; complete20i remains unfinished.

The final licensing recovery correction also checks surviving audit history when both tables and setup markers are missing. It refuses silent replacement and preserves exact interrupted fresh setup. Independent source review and 33 portable migration cases in each PHP mode pass; native SQL and full release acceptance remain pending.

## 2026-10-24 (late) — FSI classifications and cooldown-repost fix

- Run `40087665482` (`bf9d8584`) — staff-feed fix held; suite advanced to check 3134/3135: 'WordPress installation immediate repeat obeys common cooldown'. Diagnostics showed the repeat POST through the form helper made provider call 106 and a fourth read — the helper's preliminary provider-settings GET now routinely consumes the entire 30s shared-cooldown window (the provider-settings render degradation), so the repeat executed instead of being refused. Applied the directory_listing pattern: `post.last` capture + direct repost (up to 3×) until 'Wait 30 seconds' appears — `fc56a32b`, applied identically to the stale copies in force-ssl, php and document-roots before they hit the same race. Redispatch `40099274221` (push-triggered).
- Earlier in this cycle: ticket staff-feed recipient fix `bf9d8584` (derive expected set from enabled staff holding `support.tickets` — all 15 synthetic owners + the ticket admin), recurring-invoice guard fix `cc41212a` held through 3,620 checks.

**Runs in flight:** FSI `40099274221` (`fc56a32b`); preflight `40098870633` (`5e82f92b` — notice-open `folder` field fix + contract-chain re-settle); audit `40094584278` (`cb8d2b91` — composite `ci_audit(action,detail(191))` index, critical path); php-controls `40091116828` (`d2a99737` diagnostics).

### 2026-10-24 (cont.) — contact-feed attribution product fix + preflight classification aid

- Run `40099274221` (`fc56a32b`) — the cooldown-repost fix held; suite reached 3775 checks then failed `Primary feed attributes the contact-authored message` (contacts_cases.py:199). Diagnosis: `activity_projection`'s member author filter admitted only `admin` messages, so a project contact's message never reached the primary account's feed — a product gap, since the senders map and the check both expect contact attribution for the primary audience. Fix `55ff6c52`: non-contact member author filter `='admin'` → `IN ('admin','contact')`; contact audience unchanged (`IN ('admin','client')` still excludes contact-authored items there). `php -l` + build refresh green; not contract-pinned.
- Redispatch `40115508547` (push-triggered) on `55ff6c52`.
- Sibling lane note: preflight `40098870633` failed `Server folder refusal records a bounded failure without copy claims` — refused Sent-copy POST landed one epoch-second after the successful append inside the 2s per-account copy bound; fixed by aging copy_attempts before the refused POST (`8eb0570b`, preflight lane).

### 2026-10-24 (cont.) — run 40115508547 advanced past contacts; new failure at proposals

- `40115508547` (`55ff6c52`) failed at check 3809 — 'Draft detail shows milestone pricing' (`tests/proposals_cases.py`). The contact-feed attribution fix held (suite progressed ~34 checks past the prior failure).
- The detail GET returned 200 but lacked one of 'VERSION 1'/'$2495.88'/'Draft'. Product render path verified correct by inspection; `ci_proposals` has no other browser-suite writers, so the actual page body is needed. Added bounded diagnostics (`qa/proposal-detail-diagnostics.json`: pid, final URL, status, missing markers, 600-char excerpt) without weakening the assertion.
- Pushed `c6fea48d`; push-triggered run `40131688282` in flight.

### 2026-10-25 (cont.) — run 40157399569 root cause: missing seed migration

- Apache lane: the 3233-check partition passed; the step then failed at `provider_cloud_site_requests_upgrade.py` seed — the fixture never ran the candidate `studio_migrate()` after `apply`, so the `'4'` readiness gate saw baseline `'2'`. Native lane: same gate, fixture seeded `'2'` directly.
- Fixed: seed mode now runs `if(!studio_ready())studio_migrate()` (matching sibling fixtures); native fixture seeds `'4'` (the `3ef9f6ab` correction). Pins re-anchored; verifier green.
