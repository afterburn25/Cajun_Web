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

Next: Codex sequential retarget/integration of the chain (#203 → #204 → #205 → #209 → #210 → #211 → #212 → #213); long lanes re-run per retargeted parent. Devin fixes failures on exact new commits.
