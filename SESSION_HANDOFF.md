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

**Continuation 2026-10-19 (later same day):** tip `5da3ef95`. Post-Group A fixes: `cb8d2117` repaired admin notification dispatch shadowing (`payments_notification_*` prefix now precedes the generic `notification*` feed dispatch); `ceb06c05` removed a duplicate plain `require` of notifications.php in the portal router. Stale pre-fix run 39297205239 (on `9896b1f0`) cancelled — acceptance runs on `cb8d2117`/`ceb06c05`/`5da3ef95` are authoritative.

**Component ports (base = integration branch, awaiting hosted runs):**
- PR #257 `devin/vps-uncertainty-port` — VPS uncertainty-retention component from `fb395998` (311 local checks).
- PR #258 `devin/ssh-access-port` — SSH access-model from `devin/ssh-access` (70 local checks).
- PR #259 `devin/provider-catalog-port` — CapabilityCatalog/CatalogEntry/TwentyICatalog (50 catalog + 171 provider checks local).
- PR #260 `devin/domains-port` — foundation/domains commerce model (81 local checks).

**PR hygiene:** closed #102–#125 (port parity verified), #32/#36/#37/#43–#46/#60/#61 (superseded; ported where needed), #109/#111 (superseded by released 20i work), and all 25 Group D ancestor PRs. Open PRs now ~101, almost all Group B awaiting CI.

**Next actions in order:**
1. Watch the acceptance runs on the integration branch tip; fix any combined failures.
2. Merge the four component-port PRs once their hosted runs pass (or fold branches in directly before the main merge).
3. Observe actual CI on Group B Codex 20i heads (catalog f67b1631, capture b7c97953, commercial d2903bfb, nameservers 024b50dc, runtime, subdomain 4dc05d8c, StackCP ffee2e3a, renewal 632362f6, reset-template 95ce538e) before any merge — runs dispatched, queue is starved by account concurrency.
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
