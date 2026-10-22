# Devin integration queue

Created 2026-10-19 under the [sole-owner takeover](DEVIN_FULL_SITE_TAKEOVER.md); refreshed 2026-10-22 for the merge-first delivery cycle. Authoritative destination is `main`; the wide integration branch `devin/full-site-integration` and `devin/preflight-commercial` are staging lanes, not delivery.

Dispositions: READY-TO-MERGE = hosted acceptance green at exact head + required review complete. PENDING-ACCEPTANCE = exists but unverified/unreviewed at current head. IN-ACCEPTANCE = hosted run in flight at named commit. BLOCKED = named blocker recorded below. INTEGRATED = ancestor of `main`. SUPERSEDED = replaced by named successor.

Merge-first rule (AGENTS.md, standing): prefer the smallest independently-verifiable slice; land one reviewed increment at a time into `main`; a green run on an older base does not validate a re-based merge result.

## Active delivery lane

| Order | Candidate | Head | Base | Scope | Disposition |
|---|---|---|---|---|---|
| 1 | PR261 `devin/reset-email-template-main` | merge `e334678e` of accepted `467ec1bd` into main `1067757d` | main | 20i reset email template: read/review/save-once/readback for StackCP password-reset template; encrypted history; no mail sent | IN-ACCEPTANCE — original source green at [run 39422487199](https://github.com/afterburn25/Cajun_Web/actions/runs/39422487199); merged-candidate acceptance running; independent review pending |
| 2 | Cloud website requests — PR226 `codex/twentyi-client-cloud-websites` `e2c88d9d` (targeting main, CONFLICTING) | `e2c88d9d` | main | Client cloud site requests with owner review/package confirmation; +5833 lines, mostly new files; merge-tree vs main conflicts only in 5 prepend docs | BLOCKED on fresh acceptance — its only full run at `e2c88d9d` (39303433534) was cancelled; code merges clean vs main. Next action: build small candidate off post-PR261 main, dispatch full acceptance, review. PR227 intake (`8fe2787a`) stacks on PR226 |
| 3 | Directory-listing controls — PR246 `codex/twentyi-client-directory-listing` `b0fe332f` | `b0fe332f` | `codex/twentyi-client-force-ssl` | Client directory listing requests | BLOCKED — depends on force-SSL client lane landing first; force-ssl leaf run 39746898847 in flight |

## Staging lanes (not main)

- `devin/full-site-integration` (`-fsi` worktree) — combined Group A (25 merged slices) + Group B leaves; currently red on `native-and-browser`: `tests/mail_message_cases.py` NameError fixed at `9ec4e2b1`, full run restarted. FSI is a staging aggregation lane; its own merge to main waits until small slices have landed and its value is re-evaluated (its content may be delivered via small PRs instead).
- `devin/preflight-commercial` (`-int` worktree) — Group B preflight staging; nameserver upgrade seed fix `b7a84122` pushed, run restarted.

## Pending leaf lanes (Group B, staged for preflight/FSI or direct small PRs)

Each needs its own hosted green at exact head before any merge; contract re-anchors are already committed on the listed heads.

| Lane | Branch tip at record | Pending run | Note |
|---|---|---|---|
| Force-SSL ops whitelist + bob empty-feed CSRF fix | `devin/fix-force-ssl-ops-whitelist` `7757c64c` | 39746898847 | In flight |
| StackCP ops whitelist + SSNFAULT diagnostic | `devin/fix-stackcp-ops-whitelist` `94a3fca5` | 39746902785 | In flight; interpret terminal reason, non-PDO exit is not a pass |
| PHP controls minimal timeout fix | `devin/fix-php-controls-minimal` `7641316f` | 39747028009 | In flight |
| Docroots worker retry (WP cooldown evidence order) | `devin/fix-docroots-worker-retry` `70102197` | 39744957397 | In flight |
| Nameserver fixture getattr (browser TimeoutError redispatch) | `devin/fix-nameserver-fixture-getattr` `2378880d` | 39745221647 | In flight |
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

1. PR261 (`e334678e`): awaiting hosted acceptance (run 39761594725 family) + independent review — next action: merge on green, then verify main.
2. FSI browser lane: prior failure was test-side `NameError` in flag diagnostics (fixed `9ec4e2b1`); earlier `imap` mark-read connection refusal did not recur in that run but its evidence was masked by the crash — next run must produce `qa/imap-connect-failures.jsonl`/`flag-mark-read-diagnostics.json` verdicts.
3. StackCP `ssn_fault` diagnostic (run 39746902785): capture admission state/phase/terminal payload; interpret terminal reason on completion.
4. Preflight Apache lane: nameserver upgrade `seed` helper failed on missing `studio_migrate` owner step (fixed `b7a84122`, re-anchored contract, local verifier 1468 checks) — rerun must pass the seed/apply/recover sequence.

Group A slice detail (25 merged slices A1–A25) remains recorded in the FSI copy of this file at branch `devin/full-site-integration`.
