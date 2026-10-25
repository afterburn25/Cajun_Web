# Devin integration queue

Created 2026-10-19 under the [sole-owner takeover](DEVIN_FULL_SITE_TAKEOVER.md).
Integration branch: `devin/full-site-integration` from main `1067757d1a18bffca7c5d28aeb4ec99cbf2ab138`.
PR snapshot: 152 open PRs read via `gh pr list` at ~16:00Z. Branch recency checked via `git for-each-ref`: last Codex push 2026-10-19 08:11 -0500 (before the 10:36 -0500 takeover commit) — no active concurrent writer detected at queue build time.

Dispositions: READY = review findings addressed + locally verified, hosted acceptance pending. PENDING-ACCEPTANCE = exists but unverified/unreviewed at current head. FOUNDATION = incomplete slice, preserved. SUPERSEDED = replaced by a named successor/PR. INTEGRATED = ancestor of current main.

## Group A — MERGED into `devin/full-site-integration` (2026-10-19)

All 25 slices merged sequentially with conflict resolution preserving HEAD's
contact-aware gates, `author_type`/`sort_sequence` union parity, and the full
native recipe. Each merge regenerated the installer inventory and passed the
acceptance contract (227–229 checks). Local suites run green; hosted combined
acceptance is triggered by this branch's push (workflow filter now includes it).

Merge commits: A1 `aad0feea`-era mail chain; A2 `a9`-era contacts; A3 fixture;
A4 `b6928d7c`; A5 `8992bfea`; A6; A7; A8 stage-history; A9 `ba238981`;
A10 `575483ed`; A11 `0e11fdab`; A12 `9e073f88`; A13 `2f340c32`; A14 `264ccc53`;
A15 `d177a539`; A16 `1f217bdd`; A17 `057a74ee`; A18 `5ff2c54e`; A19 `c080ee0f`;
A20 `f3572e07`; A21 `56fbd8d3`; A22 `a859b397`; A23 `9e17471e`; A24 `c1eef1e7`;
A25 `9f1f8ae3`.

Original classification retained below for traceability.

| Order | Tip PR / head | Includes | Scope | Disposition |
|---|---|---|---|---|
| A1 | #195 `cf837f65` devin/non20i-mail-html-compose | #161→#189→#190→#191→#192→#193→#194 | Mail replies/forwards, attachments, sent sync, reply-all, folders, flags, sanitized HTML compose | READY |
| A2 | #213 `0a919545` devin/non20i-portal-account | #203→#204→#205→#209→#210→#211→#212 | Contacts, decisions, activity, staff notes, milestones, prefs, action items, account page | READY |
| A3 | #250 `80050ef8` codex/contacts-native-fixture (bases on #203) | — | Duplicate contacts-table fixture fix; merge after A2 | READY |
| A4 | #176 `7464c4ce` devin/non20i-backups-runtime | + #162 foundation `e71b353c` (independent branch, same content) | Backup manifest/archive runtime | READY |
| A5 | #196 `b2b039f5` devin/non20i-backup-restore-drill | — (shares foundation) | Isolated restore drill | READY |
| A6 | #197 `24da7935` devin/non20i-backup-database-export | — (shares foundation) | DB snapshot in backup archives | READY |
| A7 | #198 `f717a118` devin/non20i-protected-previews | — | Protected preview uploads bound to versions | READY |
| A8 | #200 `1a537618` devin/non20i-stage-history | — (independent of #198) | Project stage history | READY |
| A9 | #253 `b1948eeb` codex/payment-link-view-isolation | #202 `750f1ab8` | Payment links + expired/changed-link privacy | READY |
| A10 | #174 `88f03ccb` devin/non20i-recurring-billing | #160→#170→#171 | Project progress, planning, design payments, recurring billing | READY |
| A11 | #187 `2395f3d4` devin/non20i-renewal-notifications | #174 chain | Renewal invoice notifications | READY |
| A12 | #182 `de8eeec3` devin/non20i-notifications-announcements | #165→#181 | Notification feed runtime + announcements | READY |
| A13 | #186 `9748c5ae` devin/non20i-notification-events | #184 | Freshness metrics + studio events | READY |
| A14 | #188 `04457d25` devin/non20i-notification-outbox | #184 | Bounded email outbox | READY |
| A15 | #175 `935c634d` devin/non20i-ticket-screenshots | #158 | Support tickets + screenshot readiness | READY |
| A16 | #183 `47e81656` devin/non20i-ticket-notifications | #158 | Ticket events into notification feed | READY |
| A17 | #173 `87dc871d` devin/non20i-service-plans | #169 | Service plans runtime | READY |
| A18 | #177 `d4631b29` devin/non20i-pipeline-runtime | #163 | Lead pipeline runtime | READY |
| A19 | #178 `97c8b71c` devin/non20i-proposals-runtime | #164 | Versioned proposals | READY |
| A20 | #179 `87123ee4` devin/non20i-revisions-runtime | #166 | Content revision history | READY |
| A21 | #185 `ca4e2983` devin/non20i-comparisons-runtime | #168 | Comparison-claims registry runtime | READY |
| A22 | #201 `c7c8f6ba` devin/non20i-comparison-sources | — | Comparison source registry | READY |
| A23 | #172 `e2b29a05` devin/non20i-billing-calendar | — | Billing calendar | READY |
| A24 | #199 `4640383e` devin/non20i-homepage-device-depth | — | Homepage device-depth + measured evidence | READY |
| A25 | #167 `02555724` devin/non20i-support-access-foundation | — | Support-access contract foundation | READY (foundation) |

Independent foundations merged only where a runtime needs them; pure contract-only PRs stay open until their runtime lands.

## Group B — PENDING-ACCEPTANCE (Codex 20i drafts, hosted gates queued at takeover)

| Family | PRs / heads | Disposition |
|---|---|---|
| Catalog + captures | #216 `f67b1631`, #220 `b7c97953` | PENDING — original catalog f67 gates + capture run 39137551561 were queued at takeover; verify actual results before merge |
| Commercial stack | #223→#228→#229→#231→#235→#245→#247→#248 (`d2903bfb` tip) | PENDING — stacked on catalog; first-invoice identity must be preserved |
| First invoice | #219 `1db32352` (base codex/twentyi-hosting-full-acceptance) | PENDING — check base branch state |
| Nameservers | #221 `fbe00172`→#222→#230 `024b50dc` | PENDING |
| Runtime/folders | #224 `ff467d7f`, #225 `f545cf93`+#249 `0397a0e4`, #244 `1a8ae850`+#246 `b0fe332f` | PENDING — PHP setter evidence gap noted in takeover |
| Subdomain/cloud websites | #226 `e2c88d9d`+#227, #234 `b8c316b6`→#236→#237→#242 `4dc05d8c` | PENDING |
| StackCP | #233 `3d449d79`→#243→#255 `ffee2e3a` | PENDING |
| Renewal invoicing | #252 `632362f6` | PENDING |
| Reset email template | #254 `95ce538e` | PENDING |
| Old codex draft stacks #102–#125 (tickets/progress/planning/payments/recurring) | heads on codex/* bases | CLOSED SUPERSEDED — port parity verified (no missing files; codex support-tickets.yml redundant with the pinned native recipe); all closed 2026-10-19 |

Merge preflight vs the integration tip (`git merge-tree` conflict-hunk counts, 2026-10-19):
catalog `f67b1631` 11 · capture `b7c97953` 11 · first-invoice `1db32352` 16 ·
nameservers `024b50dc` 15 · runtime `f545cf93` 8 · `b0fe332f` 10 · `0397a0e4` 11 ·
subdomain `4dc05d8c` 16 · StackCP `ffee2e3a` 14 · renewal `632362f6` 8 ·
reset-template `95ce538e` 9 · commercial `d2903bfb` 22. All conflicts are the
known stale-base family (docs/SESSION records, generated inventory, acceptance
contract, apache/browser test lists, shared routers) — site source merges
cleanly.

Containment verified via `merge-base --is-ancestor`: `d2903bfb` contains
`f67b1631`+`b7c97953`+`1db32352`; `0397a0e4` contains `f545cf93`; `8fe2787a`
contains `e2c88d9d`; `b0fe332f` contains `1a8ae850` (force-ssl);
`024b50dc` contains `fbe00172` (registrar-nameservers). Minimal merge set —
ten leaf tips: `d2903bfb`, `024b50dc`, `0397a0e4`, `b0fe332f`, `4dc05d8c`,
`ffee2e3a`, `632362f6`, `95ce538e`, `8fe2787a`, `ff467d7f`
(php-version-controls — a standalone leaf, contained in nothing else).
`d2903bfb` merges first (it carries the catalog the queue called the
stack base); the rest are independent and may merge in any order.

Prepared resolution: `devin/preflight-catalog` (`93e24ba0`) holds the fully
resolved catalog merge locally — docs unioned, contract rebuilt semantically
(268/268 checks), inventory regenerated, baseline-driver deltas re-anchored to
the Group A predecessor bytes, 109 package-type pure checks pass. It is a
reference for the real merge, not a merge substitute.

CI requirement: each head's dispatched application-acceptance run must be
observed green before its merge. Current dispatched runs are recorded in
SESSION_HANDOFF.

Leaf repair branches (2026-10-20): defects found during leaf-tip acceptance
are fixed on `devin/fix-*` branches so merges carry verified tips —
nameserver `bdfb1734` (worker retry), directory-listing `a9e08b51` (worker
retry), force-ssl+subdomain `ef5724ce` (worker retry), docroots `9577021e`
(worker retry + information_schema FK-join split + LF inventory), StackCP
`3f88eb09` (require_once + render baseline), php-controls `23213c52`
(CHECK join scoping + tokenizer-free extraction + SHOW TABLES probe),
cloud-intake `fe989846` (ctype-free validation), reset-email `5c8af321`
(ops whitelist). Diagnostic-only `devin/diag-docroots-worker` `8fa6df68`
captured the docroot worker's blocked-state and is evidence, not a merge
candidate. See SESSION_LOG 2026-10-20 for root causes.
| Older codex hosting/service-plan drafts not already integrated | #84–#92 covered; others check per-branch | mixed |

## Group C — FOUNDATION / INCOMPLETE

| PR | Scope | Disposition |
|---|---|---|
| #256 `fb395998` codex/vps-uncertainty-repair (bases devin/vps-foundation) | VPS uncertainty component on old foundation tree | PORTED — component-only port on `devin/vps-uncertainty-port` (PR #257, base = integration branch; 311 local checks); never deploy old tree |
| #46 ssh-access, #43 provider catalog, #44 domain commerce | Isolated contract components absent from the tree | PORTED — `devin/ssh-access-port` (PR #258, 70 checks), `devin/provider-catalog-port` (PR #259, 50 catalog + 171 provider checks), `devin/domains-port` (PR #260, 81 checks); originals closed |
| #45 vps-foundation, #47 proposals, #48 revisions, #49 comparisons, #51 pipeline, #52 backups, #54 support-access, #57 notifications, #60 shared-hosting, #61 service-desk | Isolated contract foundations | CLOSED SUPERSEDED — superseded by A-group ports / newer integrated equivalents (hosting has newer ACCEPTED_PENDING semantics; desk is byte-identical); vps-foundation's component carried by PR #257 |
| #32/#36/#37 | Consultation/review/scheduling contract docs | CLOSED SUPERSEDED — #36's doc byte-identical on tree; #32's component released in 1.15.0; #37's SCHEDULING_ADAPTER.md carried onto the integration branch (`5da3ef95`) |
| #93/#109/#111 | Early Devin 20i inventory/ledger/execution drafts | CLOSED SUPERSEDED — capabilities released in 1.24/1.25; current operations migration/suites are strictly newer |

## Group D — ALREADY INTEGRATED (ancestor of main `1067757d`)

#80, #81, #82, #83, #84, #87, #88, #89, #90, #91, #92, #94, #99, #100, #133, #135, #136, #138, #139, #140, #148, #149, #150, #151, #156 — verified via `git merge-base --is-ancestor`. All 25 closed 2026-10-19 with ancestry-verification notes.

## CI queue triage — 2026-10-19

At takeover the Actions backlog held ~1,063 queued runs — accumulated
push/PR-triggered component suites on intermediate codex branches, stale commit
heads, and already-integrated `devin/non20i-*` source branches. Nothing was
executing for ~22h; hosted acceptance was starved, not failing.

Cancelled 616 redundant queued runs (verified safe per-run before cancelling):

- 523 on intermediate/ancestor codex branches — every one confirmed an ancestor
  of a retained leaf tip (`merge-base --is-ancestor`), so leaf-tip runs cover
  their content; plus already-integrated correction branches.
- 55 on `devin/non20i-*` Group A source branches — content already merged into
  `devin/full-site-integration`; the combined acceptance covers them.
- 38 stale-SHA queued runs whose branch tips had moved on.
- Duplicated push/PR runs of the same workflow on the same head.

Retained (leaf-tip coverage + gates):

- `39299409880` — Application acceptance on `ceb06c05` (devin/full-site-integration).
  Tip `f73dd118` differs only in docs; `application-tests.yml` has a paths
  filter, so `ceb06c05` is the current authoritative code acceptance.
- Fresh dispatched `workflow_dispatch` application runs on every Group B leaf
  and head: `d2903bfb`, `024b50dc`, `0397a0e4`, `b0fe332f`, `1a8ae850`,
  `4dc05d8c`, `8fe2787a`, `ffee2e3a`, `632362f6`, `95ce538e`, `ff467d7f`,
  `f67b1631`, `b7c97953`, `1db32352`, `e2c88d9d`.
- Distinct component-suite runs on retained leaf branch tips (the authors'
  per-component native suites — unique coverage, not duplicates).
- Dedicated component workflow runs on the four port branches (#257–#260).

Cancelled runs retained no evidence — none had ever executed (all were
perpetually `queued`, zero produced logs or reports).

## Merge-first standing priority — 2026-10-20

Owner directive: land completed, tested work into `main` promptly; keep `main`
caught up; check this queue before opening new branches. Full text in
`AGENTS.md` (Standing owner directive 2026-10-20). Merge order stays
dependency-gated: green leaf -> Group B (`devin/preflight-commercial`) ->
combined `devin/full-site-integration` acceptance -> normal merge to `main`.

## Concrete blockers and next actions — refreshed 2026-10-23

1. Audit render fix `a331599a` is MERGED to `main` (PR #265). The
   `utf8mb4_bin` collation on audit `action`/`detail` plus `audit_action` and
   `audit_detail(detail(191))` indexes cleared the ~30s provider-settings
   renders; branch acceptance on `8891a591` was fully green inside the job
   ceiling. Exact-main verify `40135493307` on `a331599a` in flight.
   Superseded duplicate dispatch `40135533714` cancelled — retained as
   evidence, not success.
2. Six application-acceptance lanes in flight on corrected heads; merge each
   only after its own run reports green:
   - preflight `49bdeaea` -> `40142115998` (cherry-pick reverts of marker-aware
     `studio_migrate` + schema tail repaired; contract 1468/1468)
   - fsi `3bdf2823` -> `40142523950` (proposal totals asserted with product
     thousands separator)
   - php-controls `bd0bbce5` -> `40136175063` (audit fix ported; earlier stub
     proved POST received + uncertain ledger while client timed out)
   - nameserver `2993656f` -> `40136678842` (audit fix ported)
   - docroots `50c1a3f0` -> `40137290812` (audit fix ported; inventory
     re-anchored)
3. Port PRs #257–#260 (base `devin/full-site-integration`): current `main`
   merged into each branch; contract verifier 288/288 green locally; dedicated
   component workflows GREEN on merged heads — #257 `d5dc0b3c`/`40145014173`,
   #258 `b41b961b`/`40144533735` + `454f3105`/`40145799872`, #259
   `95dbab38`/`40144765934` + `51ac85f9`/`40145804668`, #260
   `1f2d933a`/`40144990577` + `268441a8`/`40145809933`. Merge gate satisfied;
   hold until the in-flight FSI acceptance run resolves to avoid superseding
   its head.
4. The 50+ Group A source PRs auto-resolve when their heads reach `main`.

