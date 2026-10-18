# CajunWebPro current handoff

## Release 1.25.0 priority — 2026-10-16

New20i capabilities and nonessential refactors are frozen until1.25.0 publication and all-asset download verification. Canonical [codex/release-1.25.0](https://github.com/afterburn25/Cajun_Web/tree/codex/release-1.25.0) is published at `b98b96eb2a33e4bdeb72e15217213431885aad67`, normally merging current main35d8 and mature3fa. The [frozen scope](https://github.com/afterburn25/Cajun_Web/blob/codex/release-1.25.0/docs/RELEASE_SCOPE_1.25.0.md) and [integration map](https://github.com/afterburn25/Cajun_Web/blob/codex/release-1.25.0/docs/RELEASE_1.25_INTEGRATION_MAP.md) are also copied into main for discovery. The owner wants the mature increment now, without waiting for complete20i.

Root and agents are closing existing licensing defects, extending real populated1.24 root/subfolder client-data upgrades, and preparing complete PHP8.2/8.4 × MariaDB10.11/MySQL8.4 application/browser/update runs. Devin continues separate non20i work; Codex owns the narrow release licensing corrections and shared seams. Proposed PRs are not automatically included. Unfinished branches and original failed evidence remain preserved.

Merge checkpoint passes canonical installer generation, all shipping PHP/JavaScript syntax, documentation and exact2886-file staged preservation checks. Imported3fa focused38933590118 passes11 reports/1440 executions in each of4 environments and its Windows packages. These are scoped results, not combined release acceptance. Exact-candidate full checks/images, independent review, normal main merge, exact-main full checks, packages, publication and download verification remain required. Published1.24 is unchanged; no1.25 release or live deployment exists.

This main checkpoint changes documentation only; runtime remains35d8. Follow the release branch for implementation and acceptance. Earlier current-status labels below are historical where superseded.

Previous checkpoint: https://github.com/afterburn25/Cajun_Web/blob/35d8fb7efc94f6f851399d0755fa93097f1fce66/SESSION_HANDOFF.md

## Devin non-20i lane — 2026-10-17

All earlier Devin PRs verified green: #198 protected previews `21198602` (48/48), #202 payment links `95a3db9b` (48/48), #203 company contacts `37324121` (48/48), plus stacked #200 stage history `93cb8f3a` (43/43), #204 contact decision attribution `c70dc0e0` (37/37), #205 contact activity routing `58c5d4af` (41/41) — long lanes for stacked PRs run after Codex retargets them to main.

Current tips (stacked chain on devin/non20i-contact-activity):
- #209 `devin/non20i-staff-notes` @ `3e6db257` — staff-only internal project notes via `studio_messages.author_type='internal'` (no schema change); portal conversation allow-lists visible author types. `3e6db257` corrects a flash-assert regression in `d203b5ea`.
- #210 `devin/non20i-milestones` @ `868e967c` — additive `studio_milestones` (state + optional YYYY-MM-DD target date) via existing `studio_migrate`; admin manage forms, portal visibility, milestone activity events.
- #211 `devin/non20i-activity-prefs` @ `3b8b5ffd` — additive `activity_prefs`; per-actor hiding of update types from feed/unread/open/mark, re-run `activity_setup` provisions on existing installs.
- #212 `devin/non20i-client-actions` @ `01dce055` — read-only derived "What we need from you" (pending version decisions; issued invoices when billing access applies) and "Open blockers" (blocked milestones) on the portal project dashboard; no schema.

php -l + test-compile + canonical inventory pass on all. Component lanes queued (GitHub runner starvation); long lanes run after Codex retargets to main.

No merge, deployment, provider call, email, payment or SSH occurred. Codex owns 20i integration and release coordination; Devin owns licensing and non-20i roadmap items only.

## Devin review corrections — 2026-10-17 (round 2)

Owner review produced four findings; all corrected and pushed:

- #203 `devin/non20i-client-contacts` @ `52f524bc` — GREEN 48/48 incl. native-and-browser. Revoked/disabled contact no longer degrades to primary-client identity (full auth context invalidated at lookup failure); bundle download requires `contact_can('downloads')`; contact multipart upload accepted with `author_type='contact'` attribution.
- #202 `devin/non20i-payment-links` @ `40eb7f29` — `payments_master(true)` refuses a replacement key when sealed `payment_requests.link_secret` history exists, even with gateway credentials absent/cleared. `40eb7f29` also fixes a bound-parameter bug: `table()` returns a backtick-quoted identifier and never matched `information_schema`; uses the unquoted prefixed name. Matrix re-running.
- #209 `devin/non20i-staff-notes` @ `82c62c98` — GREEN 43/43. Stale `admin/views/studio/job.php` inventory pin refreshed.
- #212 `devin/non20i-client-actions` @ `b85d87db` — GREEN 41/41. Action links navigate via `focus_review` so a pending decision outside the 100-row window lands on its exact decision form.
- #213 `devin/non20i-portal-account` @ `480b0fcd` — GREEN 41/41 (docs head).

## Devin review corrections — 2026-10-17 (round 3)

Owner follow-up reviews produced three more work items; all corrected and verified green:

- #203 `devin/non20i-client-contacts` @ `9015ef8f` — GREEN 48/48 incl. native-and-browser. Upload write boundary serializes under `staff_locked` + fresh `FOR UPDATE` client/contact/permission revalidation before any private-file or row write; job locked with expected client id; rollback cleans the file. Native coverage: four committed interleavings (disable, rotation, permission removal, client deactivation) via worker processes. `fcebfd08` fixed a test-side bug (billing contact has no upload form to `submit()` through — forged POST instead); `9015ef8f` added a bounded warm-up + decomposed print to the shared organized-update OPcache probe after a deterministic-but-external `rewrite 1.17.0` probe failure (bisect run 39067511358 on previously-green `40eb7f29` confirms environment, all probes now report `cached:true`).
- #213 `devin/non20i-portal-account` @ `3411de28` — GREEN 41/41. `studio_password_change` is atomic: fresh `FOR UPDATE` actor reads under `staff_locked`, current password verified against the locked row, credential + audit committed in one transaction, session rotates only post-commit. Native coverage: interleavings + audit-failure rollback. HTTP test now asserts revocation, re-signs the contact, one persistent session for CSRF GET+POST, DOM identity verified. Branch restacked (`de343e95` merge) after base `91f4599e` touched the same test file.
- #212 `devin/non20i-client-actions` @ `5e1e043d` — GREEN 41/41. `91f4599e` added the required boundary regression: pending review + 101 newer versions of another kind, follow the generated `focus_review` link, assert the exact pending review and its decision form render; other-job and superseded controls preserved.

Round-4 review (comment 6239686025) found a latent fixture interaction in that regression: the 101 filler versions' `review:` events displace `review:<vid>` from the first activity page (30-row window, `occurred_at DESC, event_key DESC`), breaking the later feed assertions — and broad `kind:<id>` substrings can false-match other ids. Corrected:

- #212 `devin/non20i-client-actions` @ `6bf64fce` — boundary block moved after all activity-feed assertions; feed checks match exact `data-event-key="kind:id"` attributes. Component lanes re-running; `native-and-browser` (which exercises `contacts_cases.py`) verifies on retarget per convention.
- #213 `devin/non20i-portal-account` @ `9a5534a8` — same fix propagated via base merge `f4835eda` plus session-log record.
- Round-3 reviews confirmed: #203 source fix accepted, #213 implementation accepted; #202/#209 accepted previously.

Reconciled the reported `organized_update_cases.py` single-probe state: the bounded warm-up + diagnostic lives on `devin/non20i-client-contacts` tip `9015ef8f` and is intact; `portal-account` legitimately lacks it until the base propagates — no revert to reconcile.

Round-4 reviews delivered two more corrections plus a full stack propagation:

- #210 `devin/non20i-milestones` @ `72ba94c3` — GREEN 19/19. `2e06f185`: milestone targets require real calendar dates (`checkdate()`; `2027-02-29` refused, `2028-02-29` accepted); milestone events are revision-qualified `milestone:<id>:<hash>` so a later update resurfaces unread and an older ack cannot consume it — read-then-update covered for primary client and projects-permission contact.
- #211 `devin/non20i-activity-prefs` @ `5d325631` — GREEN 19/19. `0cd5ce7a`: the pref-isolation check now uses the client-authored reply (eligible for staff feeds) with exact `data-event-key` identity; admin-authored key asserted absent (staff author filter preserved).
- PR203 dependency propagation per #212/#213 follow-throughs: contacts `9015ef8f` merged forward through contact-decisions (`308805de` 17/17) → contact-activity (`17f0227b` 18/18) → staff-notes (`b4d25087` 19/19) → milestones → activity-prefs → client-actions (`66cc00b7`) → portal-account (`eb6c8082`, docs tip `4f72f3fb`). Union merges preserved every feature; inventory pins regenerated.
- Combined acceptance on the first propagation heads FAILED deterministically at `tests/native.php` "Additive upgrade creates seven workspace tables" — stale count now that #210's `studio_milestones` makes eight (runs 39073761930 / 39073810828; both native lanes, not yet HTTP).
- `0be1fc7a` on `devin/non20i-milestones`: the check now compares against `studio_schema()`'s sorted key set. `fecf93f9`: client + contact suites submit the superseded milestone key over HTTP and assert the mark is rejected while the new revision stays unread. `9ab408b0` on activity-prefs: receivable-kind assertion also requires `milestone` membership.
- Acceptance rounds 2-3 on the propagated heads: native step now PASSES; HTTP step fails at `staff_cases.py` "Milestone change enters client activity feed" — alice's `/existing/` feed does not render the milestone event.
- Diagnosis so far: (a) run 39079050602 raised "Form missing activity_setup" at `?view=activity` — an enable-submit attempt (`66841f3a`) was removed again by `a5f6fe59`; (b) run 39081460330's dump showed `403` + `[]` — `activity_actor` threw, meaning `activity_ready()` is FALSE on `ci_` (`native.php` seeds legacy schema + `studio_migrate` only; nothing sets `activity_schema_version` on `ci_`); (c) run 39083645344's expanded dump never printed — its `db()` probe used nonexistent `ci_studio_milestones` columns (`active`,`client_id`) → `fixture_db.php` exit 255.
- `1e478ef8` on milestones: dump now queries real milestone columns, wraps `db()` probes so they can't abort the suite, and prints `ci_settings` rows for the four `*_schema_version` keys plus the response body head. Propagated: activity-prefs `ef222bb0` → client-actions `d795be7c` → portal-account `ee03cfca` (+docs `c78bf77a`).
- Diagnostic acceptance **39084434485** dispatched on `ee03cfca` — queued. Open question the dump settles: why the `?view=activity` enable card was absent in run 39079050602 when `activity_ready()` is false and the owner session is valid (redirect vs suppressed card vs transient).
- Component lanes green/running on all four propagated heads; all stacked PRs (#204→#213) MERGEABLE.

Next: read the corrected ACTIVITY PAGE DEBUG dump from run 39084434485 — `ci_settings` discriminates between "activity never enabled on /existing/" (re-add a working `activity_setup` submit before the feed check) and "enabled but projection/session gap" (deeper fix). Then remove diagnostic noise, propagate the real fix through milestones → activity-prefs → client-actions → portal-account, rerun exact-head acceptance. Codex sequential retarget/integration of the chain (#203 → #204 → #205 → #209 → #210 → #211 → #212 → #213) follows after verified heads; long lanes re-run per retargeted parent. Devin fixes failures on exact new commits.
