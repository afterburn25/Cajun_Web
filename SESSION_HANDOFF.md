# Session handoff - Devin sole-owner continuation

## Owner request recorded 2026-10-19

Transfer all remaining CajunWebPro work to Devin alone. Start by reviewing and merging completed Codex AND Devin branches into main; then finish the entire 20i and non-20i roadmap, including integration and releases. The authoritative instructions are [docs/DEVIN_FULL_SITE_TAKEOVER.md](docs/DEVIN_FULL_SITE_TAKEOVER.md) and [DEVIN_START_HERE.md](DEVIN_START_HERE.md).

This supersedes old non-20i-only and Codex-only integration/release restrictions. Devin owns shared billing/auth/schema/navigation/installer/CI changes and final delivery, subject to existing tests, review, protections and operation-specific approvals. Other workers must preserve their work and not start overlapping implementation; their external sessions have not been stopped by this document.

## Integrated increments — devin/full-site-integration

Integration branch `devin/full-site-integration` (from main `1067757d`). Merged in dependency order:

- **Mail chain** (`devin/non20i-mail-html-compose` tip `cf837f65`, PRs #161/#189–#195): complete webmail reply/forward on protected copied drafts, receive/send attachments, Sent-folder synchronization, reply-all, folder-scoped browsing, explicit read/unread flag marking, and optional sanitized HTML alternatives on drafts/submissions. All coordinator review findings addressed including PERMANENTFLAGS `\Seen` correctness and marker-1 draft compatibility.
- **Contacts/account chain** (`devin/non20i-portal-account` tip `0a919545`, PRs #203/#204/#205/#209–#213): scoped company contacts, version-decision attribution, per-contact activity routing, staff-only project notes, structured milestones, per-actor preferences, client action items, and the portal account page. All integration-review findings propagated through the chain.

Branch-specific session history remains on each source branch and in `docs/SESSION_LOG.md`.

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
