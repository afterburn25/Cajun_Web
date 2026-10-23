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

- `devin/full-site-integration` (`-fsi` worktree) — combined Group A (25 merged slices) + Group B leaves; flag mark-read root cause **confirmed**: `foundation/mail-receiving/bootstrap.php` required every sibling except `FlagWriter.php`, and no autoloader maps `CajunWebPro\Foundation` — `FlagWriter::mark` threw class-not-found inside the bounded catch, surfacing `connection` in ~3ms before any socket (run 39778838428 trace: 19 invoke+connect pairs, zero invoke for the flag request). Real product defect, not a fixture issue — the injected-transport native suite could never see it. Fixed at `ba10faee` (canonical bootstrap + mirror + refreshed inventory); run 39792613490 in flight. FSI is a staging aggregation lane; its own merge to main waits until small slices have landed and its value is re-evaluated (its content may be delivered via small PRs instead).
- `devin/preflight-commercial` (`-int` worktree) — Group B preflight staging; nameserver upgrade seed fix `b7a84122` + retention-cell declaration `24433b08` pushed, run 39774861880 in flight.

## Pending leaf lanes (Group B, staged for preflight/FSI or direct small PRs)

Each needs its own hosted green at exact head before any merge; contract re-anchors are already committed on the listed heads.

| Lane | Branch tip at record | Pending run | Note |
|---|---|---|---|
| Force-SSL ops whitelist + bob empty-feed CSRF fix | `devin/fix-force-ssl-ops-whitelist` `7757c64c` | 39746898847 | **GREEN** at `7757c64c` — candidate for small-PR evaluation |
| StackCP ops whitelist + SSNFAULT diagnostic | `devin/fix-stackcp-ops-whitelist` `32f5ec56` | 39793814737 | Relocation at `fad2c1f1` (run 39769387589) did **not** fix — same rate-window check still failed; `32f5ec56` logs per-attempt elapsed and in-window admission epochs to distinguish elapsed-window lapse from a counting defect; rerun must still reach SSNFAULT interpretation |
| PHP controls minimal timeout fix | `devin/fix-php-controls-minimal` `d10b2520` | 39775103325 | Prior run hit a 45s direct-POST read timeout with all 86 checks green; `d10b2520` raises remaining direct POST bounds to 120s (shared-helper fix precedent) |
| Docroots worker retry (WP cooldown evidence order) | `devin/fix-docroots-worker-retry` `424a86c5` | 39794557286 | `5deeb932` twin-session approach failed in run 39769970280: the repeat serialized behind the global staff mutex and was admitted at +61s (second real read, calls+2). `424a86c5` restores the sibling convention — immediate same-session repost of the recorded POST body (PHP/document-roots/force-SSL repeat checks all passed that run) |
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
2. FSI browser lane: **root cause confirmed and fixed**. Run 39778838428's `imap-connect-attempts.jsonl` trace showed 19 invoke+connect pairs all succeeding and **zero** `invoke` events for the flag request — the socket override was never called. Cause: `foundation/mail-receiving/bootstrap.php` requires every sibling component except `FlagWriter.php`, and the vendored Composer autoloader has no `CajunWebPro\Foundation` mapping (no autoload section in `composer.json`), so `FlagWriter::mark` in `mail_flag_transport` threw `Error: class not found` → bounded `catch(\Throwable)` → `result_code='connection'` in ~3ms, before any socket. The injected-transport native suite (47/47 green) never loads `FlagWriter`, and the earlier "green" flags-lane run 39132752919 was docs/component jobs only — the HTTP flag check had never passed anywhere. This is a genuine product defect (flag marking would fail identically in production). Fixed at `ba10faee`: added the `require_once` to the canonical bootstrap, mirrored to `site/`, inventory refreshed, `php -l` clean; full run 39792613490 in flight.
3. StackCP run 39769387589 completed-failure: the relocated rate-window block (`fad2c1f1`) still failed — 'Sixth actual staff admission is rate limited before effects' stayed undenied after 3 attempts. The move gained ~28 lines of journal, far less than needed; also `staff_locked` is a global DB mutex so admission evaluation can itself serialize behind the slow provider-call phase. `32f5ec56` adds bounded diagnostics (per-attempt elapsed + the staff's in-window admission epochs to STDERR) — run 39793814737 will distinguish a 60s window lapse from a real admission-counting defect. SSNFAULT terminal-reason interpretation still owed when a run reaches it.
4. Docroots run 39769970280 completed-failure: the concurrent twin-session repost (`5deeb932`) posted promptly but the twin request serialized behind the primary's staff-locked provider call — its admission evaluated +61s after the primary start, the 30s window lapsed, and it executed a second real read (`provider_calls` 105 vs expected +1; artifact `directory-listing-response-diagnostics.json` shows two `package.directory-indexing` reads at epochs …915/…976). Fixed at `424a86c5`: reverted to the sibling convention used by document-roots/PHP/force-SSL (all green in this run) — same-session repost of the recorded `post.last` body immediately after the primary response, with no extra page GET. Assertions unchanged. Run 39794557286 pending.
5. Preflight Apache lane: nameserver upgrade `seed` helper failed on missing `studio_migrate` owner step (fixed `b7a84122`); follow-up run 39762085448 passed seed but hit the previously-unexercised retention assert — the migrate legitimately advances `settings.studio_schema_version` 2->4. `24433b08` declares that exact hashed cell via `changed_cells` and records whitelisted failure messages for future diagnosis; full run 39774861880 pending. NOTE: run 39762085448 (superseded head) also showed runner environmental failures — `errno=28 No space left on device` and a staff advisory-lock collision in the fulfillment worker — not product assertions.
6. PHP controls run 39747028009 completed-failure, classified: `ReadTimeout` (45s) on a direct owner/client POST to the packaged :8095 server, all 86 checks green — the earlier helper-only bump missed eleven direct `timeout=45` sites. Fixed at `d10b2520`; run 39775103325 pending.
7. Nameserver run 39745221647 completed-failure, classified: uncaught Playwright `TimeoutError` (21458ms) escaped the check harness in the Chromium nameserver-refresh block — `qa/http-results.json` recorded 3137 checks, all green, with the suite marked failed by the bare `failure` field. `5a262898` bounds all three nameserver browser blocks' `goto`/`expect_navigation`/`expect` calls at 60s (finite, same precedent as the PHP-controls 120s bump) and re-anchors `registered_nameserver_requests` (pin + source_delta replacements). Local artifact verifier green at 473 checks. Run 39779736188 pending.

## Tracked follow-ups (non-blocking)

- PR261 should-fix #1: `provider_password_reset_template_pending` can permanently fence a profile after a routine settings `revision` bump — verification/recovery re-binds to stored context, so an interrupted change + label edit can never be resolved by the owner. Evaluate a bounded dismissal path or owner-visible explanation.
- PR261 should-fix #2: `provider_operation_revoke` lacks a `consumed_key IS NULL` guard — a crafted owner POST could revoke a consumed template grant. Consider refusing revocation of consumed grants.

Group A slice detail (25 merged slices A1–A25) remains recorded in the FSI copy of this file at branch `devin/full-site-integration`.

## 2026-10-22 update — current lane heads and runs

| Lane | Current head | Pending run | State |
|---|---|---|---|
| Preflight commercial | `830b2e99` (docs `859abd56`) | 39906752652 | Run 39892127894 apache job re-failed `r126_ron_` on the *owner* GET variant — assertion short-circuits across status/marker/state/`exact_business` raise. `830b2e99` adds a `qa/apache-release126-<prefix>-owner-get.json` per-conjunct diagnostic (no assertion weakened) and re-settles the full 14-era predecessor chain via spy-captured real priors selected by call-site + embedded-era signature (first-record selection picks tamper images), oldest→newest byte-patch, incremental updates (deferred updates embed stale values — the 90b6ac37/ecf1b35c mismatch); nsreq `CONTRACT_SHA256` re-mirrored `e1a5c432` + file re-pinned `e610f68d`. Verifier 1468/1468 clean. Superseded runs 39892127894/39895419341 still in flight for evidence. |
| Full-site integration flags | `b61bcd22` (docs `d490aaef`) | 39912018481 | Run 39902215470 classified via conjunct dump: `not_accepted=true`, `copy_rows=[]`, `submission_status=not_submitted` — the refused submission's send was refused by the 30s per-account dispatch cooldown (`submissions.php` started_epoch>now-30), so the copy POST refused at the `accepted` gate before any attempt row. `b61bcd22` ages dispatches before the refused send (same convention as lines 164/183/277). In flight. |
| Cloud websites PR226 merge candidate | `f1c53d61` (docs `d2a3cc9c`) (`devin/cloud-websites-main`) | 39912832426 / 39912825915 | Dispatch 39886854693 on `4fbce9db`: ALL 5 JOBS GREEN. PR-triggered 39886951265 on `e0511354` red on `Directory-Listing-Control-Recovery-Owner` Playwright capture — recover form absent because `provider_directory_indexing_binding` requires the packages-list observation inside `service_observations_fresh` 900s window and the suite crossed it before the recovery section. Re-arm `f1c53d61` re-reads hosting + flag before the interrupted request so its binding pins a fresh source. In flight. |
| StackCP ops whitelist | `f7b51f02` | 39846615525 | **GREEN** — all five jobs success on `f7b51f02` (build/recovery/apache/native-and-browser/acceptance). Covers PR255 stack + 39 Devin hardening commits incl. batched event-audit matching `388a63f2` + fixture `f7b51f02`. PR255 still open, no reviews; merge sequencing stays behind PR226/PR246. |
| Nameserver getattr | `4310ee17` (docs `b2d4c714`) | 39901898005 | Run 39882705846 red at second `data-nameserver-refresh` form: page rendered 7 link cards all carrying "Refresh registered domains and confirm this assignment first." — `provider_registrar_fresh` 900s TTL on the domains.list observation expired mid-suite (same class as the PHP-controls freshness failure). `4310ee17` adds conditional `refresh_domains()` re-arms via a real `provider_inventory_registrar_refresh` on the bootstrap-owner session (preserves the fixture owner's 20/hour refresh budget) inside `read_form` and before each gated change/recover/verify/relink site; `source_*` exclusion keeps pending requests valid. Contract re-settled (requests prior `f104d41c`, local 473/473). In flight. |
| PHP controls minimal | `4f1f93ac` | 39900556800 | Run 39883955725 red at second owner verify: dumped GET decodes to `context===null` early return (access=false, empty history, stale-delegation card) — `provider_php_binding` listed denial. Only wall-clock candidate: `service_observations_fresh` 900s TTL on the packages-list refresh (first verify passed inside window, +30-90s GET crossed it — deterministic on hosted cadence). `4f1f93ac` re-arms `fixture("inventory")` before verify/recovery sections (same-resource tolerates source change) + `binding_probe`/`inventory_age` status probe + state snapshot in form-missing dumps; pins re-anchored, local 390/390 green. In flight. |
| Docroots worker retry | `6a45884f` (docs `5d876aaa`) | 39911349541 | Run 39892375277 red at `WordPress installation owner form sends one exact assigned-package GET` — second escape mode for this check: the refresh POST's own ~30s server-side time pushed even the direct `post.last` repost past the +30s boundary (reads exactly 30s apart, `provider.calls` 121→122). `6a45884f` submits the identical repost from a second owner session the moment the refresh's admission row is visible — the repost serializes on the staff mutex (held only for admission+record, not render) so refusal lands inside the window; sequential repost remains as fallback. Assertions unchanged. In flight. |
| Audit action index | `d819f044` (docs `21c11930`) (`devin/audit-render-index`) | 39858223574 | **GREEN** — all five jobs success. Render benchmark from checkpoint log: 851 provider-settings renders median ~0s / p90 ~0s / max 34s (prior failure-era baseline: late-suite median ~15s, p90 ~27s). Residual tail tracked as `provider_refreshes.operation` follow-up. Green candidate, not reviewed, not merged. |

Cancel/queued runs are not green; earlier superseded runs were cancelled to free the queue without losing head-specific evidence.

## 2026-10-22 (cont.) — verified PR→lane ancestry map (git merge-base)

| Devin lane | Open PRs it contains |
|---|---|
| `devin/preflight-commercial` | 227, 228, 229, 230, 231, 235, 244, 245, 246, 247, 248, 250, 252, 253 |
| `devin/cloud-websites-main` | 226 |
| `devin/full-site-integration` | 250, 253 (+ main-era content) |
| `devin/fix-docroots-worker-retry` | 225, 249 |
| `devin/fix-force-ssl-ops-whitelist` | 234, 236, 237, 242 |
| `devin/fix-nameserver-fixture-getattr` | 230 |
| `devin/fix-stackcp-ops-whitelist` | 233, 243, 255 |

Open PRs not in any devin lane: 256 (vps-uncertainty-repair); devin-owned port PRs 257–260 carry their own branches. A green leaf-lane run validates its full stack, but each codex PR still needs its own review record before merge; the preflight lane green would validate the combined umbrella.
