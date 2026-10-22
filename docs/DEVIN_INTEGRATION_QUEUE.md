# Devin integration queue

Created 2026-10-19 under the [sole-owner takeover](DEVIN_FULL_SITE_TAKEOVER.md); refreshed 2026-10-22 for the merge-first delivery cycle. Authoritative destination is `main`; the wide integration branch `devin/full-site-integration` and `devin/preflight-commercial` are staging lanes, not delivery.

Dispositions: READY-TO-MERGE = hosted acceptance green at exact head + required review complete. PENDING-ACCEPTANCE = exists but unverified/unreviewed at current head. IN-ACCEPTANCE = hosted run in flight at named commit. BLOCKED = named blocker recorded below. INTEGRATED = ancestor of `main`. SUPERSEDED = replaced by named successor.

Merge-first rule (AGENTS.md, standing): prefer the smallest independently-verifiable slice; land one reviewed increment at a time into `main`; a green run on an older base does not validate a re-based merge result.

## Active delivery lane

| Order | Candidate | Head | Base | Scope | Disposition |
|---|---|---|---|---|---|
| 1 | PR261 `devin/reset-email-template-main` | merge `e334678e` of accepted `467ec1bd` into main `1067757d` | main | 20i reset email template: read/review/save-once/readback for StackCP password-reset template; encrypted history; no mail sent | IN-ACCEPTANCE — original source green at [run 39422487199](https://github.com/afterburn25/Cajun_Web/actions/runs/39422487199); merged-candidate acceptance running; independent review pending |
| 2 | Cloud website requests — PR226 `codex/twentyi-client-cloud-websites` `e2c88d9d` (targeting main, CONFLICTING) | `e2c88d9d` | main | Client cloud site requests with owner review/package confirmation; +5833 lines, mostly new files; merge-tree vs main conflicts only in 5 prepend docs | BLOCKED on fresh acceptance — its only full run at `e2c88d9d` (39303433534) was cancelled; code merges clean vs main. Next action: build small candidate off post-PR261 main, dispatch full acceptance, review. PR227 intake (`8fe2787a`) stacks on PR226 |
| 3 | Directory-listing controls — PR246 `codex/twentyi-client-directory-listing` `b0fe332f` | `b0fe332f` | `codex/twentyi-client-force-ssl` | Client directory listing requests | BLOCKED — depends on force-SSL client lane landing first; force-ssl leaf run 39746898847 completed **success** at `7757c64c` — prerequisite's leaf evidence is now green |

## Staging lanes (not main)

- `devin/full-site-integration` (`-fsi` worktree) — combined Group A (25 merged slices) + Group B leaves; `native-and-browser` still red: run 39761830011 (`9ec4e2b1`) reached the flag mark-read check and failed `connection` in 4ms with a live listener, matching ports, 19 accepted reads and no errno capture; connector stage tracing added at `0b128ed5`, full run 39778838428 in flight. FSI is a staging aggregation lane; its own merge to main waits until small slices have landed and its value is re-evaluated (its content may be delivered via small PRs instead).
- `devin/preflight-commercial` (`-int` worktree) — Group B preflight staging; nameserver upgrade seed fix `b7a84122` + retention-cell declaration `24433b08` pushed, run 39774861880 in flight.

## Pending leaf lanes (Group B, staged for preflight/FSI or direct small PRs)

Each needs its own hosted green at exact head before any merge; contract re-anchors are already committed on the listed heads.

| Lane | Branch tip at record | Pending run | Note |
|---|---|---|---|
| Force-SSL ops whitelist + bob empty-feed CSRF fix | `devin/fix-force-ssl-ops-whitelist` `7757c64c` | 39746898847 | **GREEN** at `7757c64c` — candidate for small-PR evaluation |
| StackCP ops whitelist + SSNFAULT diagnostic | `devin/fix-stackcp-ops-whitelist` `fad2c1f1` | 39769387589 | Prior run failed on rate-window timing (journal too large late in fixture); block moved earlier at `fad2c1f1`; rerun must still reach SSNFAULT interpretation |
| PHP controls minimal timeout fix | `devin/fix-php-controls-minimal` `d10b2520` | 39775103325 | Prior run hit a 45s direct-POST read timeout with all 86 checks green; `d10b2520` raises remaining direct POST bounds to 120s (shared-helper fix precedent) |
| Docroots worker retry (WP cooldown evidence order) | `devin/fix-docroots-worker-retry` `5deeb932` | 39769970280 | Prior run failed on directory-listing cooldown repeat admitted at +30s under congestion; concurrent-dispatch fix at `5deeb932` |
| Nameserver fixture getattr (browser TimeoutError redispatch) | `devin/fix-nameserver-fixture-getattr` `5a262898` | 39779736188 | Run 39745221647 classified: uncaught Playwright `TimeoutError` in the Chromium nameserver-refresh block after 3137 green checks; `5a262898` gives every nameserver navigation/expectation a finite 60s bound and re-anchors the requests section |
| Reviewed merge-prep leaf tips | docroots `fe314730`, phpctl `e95bdf2e`, nameserver `18f0cf08`, forcessl `eb8dcd50` | — | Hold for small-PR evaluation or staging merge after leaf runs report |

## Larger pending families (from takeover inventory; not for blind merge)

| Family | PRs / heads | Disposition |
|---|---|---|
| Direct catalog + captures | #216 `f67b1631`, #220 | PENDING-ACCEPTANCE — refresh actual hosted outcomes |
| Commercial stack | #223→#228→#229→#231→#235→#245→#247→#248 (`d2903bfb` tip) | PENDING-ACCEPTANCE — stacked on catalog |
| Nameservers | #221 `fbe00172`→#222→#230 | PENDING-ACCEPTANCE |
| Runtime/folders | #224, #225+#249, #244+#246 | PENDING-ACCEPTANCE |
| StackCP remote login | #233 `3d449d79`→#243→#255 `ffee2e3a` | PENDING-ACCEPTANCE |
| Renewal invoicing | #252 `632362f6` | PENDING-ACCEPTANCE |
| Payment-link privacy | #253 `b1948eeb` | PENDING-ACCEPTANCE |
| Old codex draft stacks #102–#125 | codex/* | SUPERSEDED — port parity verified, closed 2026-10-19 |

## Blockers on record

1. PR261 (`e334678e`): independent review complete (no blockers; 2 should-fix + 4 nits below). Awaiting hosted acceptance (run 39761594725) — next action: merge on green, then verify main.
2. FSI browser lane: run 39761830011 (`9ec4e2b1`) failed at 'Explicit mark-read issues exactly one bounded flag write' — flag transport recorded `connection` in 4ms while the synthetic listener stayed alive on the correct port with 19 prior accepted connections and zero errors, and `qa/imap-connect-failures.jsonl` was never written. Analysis proves the flag path uses the same `ReceivingStream`/override as reads (app code identical to the green `non20i-mail-flags` lane at `d66e39ea`), so the refusal is path/environment-level: either a connector policy throw before the socket call or a real loopback RST. `0b128ed5` logs every connector stage (invoke/isolation/policy/config/connect + errno); run 39778838428 in flight.
3. StackCP run 39746902785 completed-failure, classified: not SSNFAULT — the late-fixture rate-window block admitted five staff sessions over >60s while replaying the grown event journal, so the sixth was never refused (90/92 passed). Fixed by moving the unchanged six-admission sequence earlier at `fad2c1f1` with fresh review approvals reissued; run 39769387589 pending. SSNFAULT terminal-reason interpretation still owed when a run reaches it.
4. Docroots run 39744957397 completed-failure, classified: directory-listing `Shared cooldown` repeat was dispatched only after the first response returned; under congestion the repeat was admitted at +30s, executed a real second read, and failed 'one exact assigned-package GET' (105 vs 104 calls). Fixed at `5deeb932`: primary refresh dispatched on a thread, repeat posted from a second owner session as soon as the ledger records admission; assertions unchanged. Run 39769970280 pending.
5. Preflight Apache lane: nameserver upgrade `seed` helper failed on missing `studio_migrate` owner step (fixed `b7a84122`); follow-up run 39762085448 passed seed but hit the previously-unexercised retention assert — the migrate legitimately advances `settings.studio_schema_version` 2->4. `24433b08` declares that exact hashed cell via `changed_cells` and records whitelisted failure messages for future diagnosis; full run 39774861880 pending.
6. PHP controls run 39747028009 completed-failure, classified: `ReadTimeout` (45s) on a direct owner/client POST to the packaged :8095 server, all 86 checks green — the earlier helper-only bump missed eleven direct `timeout=45` sites. Fixed at `d10b2520`; run 39775103325 pending.
7. Nameserver run 39745221647 completed-failure, classified: uncaught Playwright `TimeoutError` (21458ms) escaped the check harness in the Chromium nameserver-refresh block — `qa/http-results.json` recorded 3137 checks, all green, with the suite marked failed by the bare `failure` field. `5a262898` bounds all three nameserver browser blocks' `goto`/`expect_navigation`/`expect` calls at 60s (finite, same precedent as the PHP-controls 120s bump) and re-anchors `registered_nameserver_requests` (pin + source_delta replacements). Local artifact verifier green at 473 checks. Run 39779736188 pending.

## Tracked follow-ups (non-blocking)

- PR261 should-fix #1: `provider_password_reset_template_pending` can permanently fence a profile after a routine settings `revision` bump — verification/recovery re-binds to stored context, so an interrupted change + label edit can never be resolved by the owner. Evaluate a bounded dismissal path or owner-visible explanation.
- PR261 should-fix #2: `provider_operation_revoke` lacks a `consumed_key IS NULL` guard — a crafted owner POST could revoke a consumed template grant. Consider refusing revocation of consumed grants.

Group A slice detail (25 merged slices A1–A25) remains recorded in the FSI copy of this file at branch `devin/full-site-integration`.
