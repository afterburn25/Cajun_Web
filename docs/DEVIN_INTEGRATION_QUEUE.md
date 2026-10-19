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

## Concrete blockers and next actions

1. Group A hosted acceptance never executed — queue starvation kept all lanes queued at takeover. Push integration branch to trigger the full required matrix on the combined source; that run IS the combined acceptance.
2. Group A doc conflicts in SESSION_HANDOFF.md/SESSION_LOG.md — resolve by union per merge.
3. #176/#200 show CONFLICTING on GitHub only because their PR bases are sibling branches, not main — verified clean against main locally.
4. Codex Group B: must inspect actual run results on current heads before any merge; queued-at-takeover gates were never observed completed.
5. #250 is a Codex correction onto Devin's contacts branch — merge as part of A2/A3.

First merge group: A1–A3 (mail + contacts/account + fixture fix) — most-reviewed slices with reviewer-closed findings.
