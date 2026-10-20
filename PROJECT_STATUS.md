# CajunWebPro current status
## 2026-10-19 — Group A non-20i integration merged (A1–A25)

## 2026-10-19 — owner 20i reset email template controls

Leaf `devin/fix-reset-email-ops-whitelist` (from `codex/twentyi-reset-email-template`), merged into `devin/full-site-integration` after full acceptance run 39422487199, adds explicit reading, exact replacement review, one admitted save and separate provider readback for the StackCP password-reset email template. Encrypted history and current owner/account checks protect the flow; no email is sent. [Behavior, actual evidence and remaining gates](docs/TWENTYI_PASSWORD_RESET_TEMPLATE.md). This is integrated source, not a release.

Payment-link companion [PR253](https://github.com/afterburn25/Cajun_Web/pull/253) is pushed at `b1948eeb` with its first full acceptance queued. Separate renewal-invoicing PR252 and immutable published1.25.0 are preserved.

## 2026-10-19 — verified remainder and candidate handoff

All 25 reviewed Devin non-20i slices are sequentially merged into `devin/full-site-integration` (tip `9896b1f0`, based on main `1067757d`). Families: mail (replies/flags/attachments/copies/HTML compose), contacts and account permissions, protected previews, stage history, payment links + isolation, backups runtime/archive/restore-drill/database-export, project progress and planning, design-payment agreements, recurring billing, notifications (announcements/events/outbox), support tickets + notifications, service plans, pipeline, proposals, revisions, comparison registry + sources, billing calendar, homepage device depth, and the support-access contract foundation.

Each merge preserved HEAD's contact-aware permission gates (`contact_can`, `client_id` scoping), `author_type`/`sort_sequence` UNION column parity, read-locked activity records, support-session refusals and the complete 152-command predecessor native recipe plus five additions in order (157 commands). Installer inventory regenerated per merge; `tools/acceptance-contract.json` rebuilt semantically and pinned to actual file hashes — 229/229 artifact checks on the final tree. Local suites: mail 35/49/71, recurring forms 76/76, design-payments 85, service-plans 83, pipeline 31, proposals 35, revisions 30, comparison-facts 30, support-access 37.

Two real defects fixed during integration: a closure missing the `$user` capture in the merged stage insert (`studio.php`), and recurring-forms validation placed in the POST path instead of the GET query parser. The A16 contract was initially committed with conflict markers; amended to `1f217bdd` (force-with-lease on the Devin-owned branch).

Hosted combined acceptance is triggered by the integration-branch push filter. The run on pre-fix `9896b1f0` (39297205239) was cancelled as stale; the runs on `ceb06c05`-era tips are authoritative. Nothing is merged to main, released or deployed yet.

Follow-on corrections after Group A: `cb8d2117` fixed notification dispatch shadowing in the admin action dispatcher (`payments_notification_*` before the generic `notification*` handler); `ceb06c05` removed a fatal duplicate `require` of the notification layout in the portal router.

Unported Group C components were extracted as component-only ports on the integration tip — `devin/vps-uncertainty-port` (PR #257, 311 checks), `devin/ssh-access-port` (#258, 70), `devin/provider-catalog-port` (#259, 50 catalog + 171 provider), `devin/domains-port` (#260, 81) — all green locally, awaiting hosted runs. Verified-superseded PRs closed in bulk: #102–#125, #32/#36/#37, #43–#46, #60/#61, #109/#111, and all 25 Group D ancestor PRs. ~101 open PRs remain, nearly all Group B.

Known remaining: Group B Codex 20i drafts still pending observed CI (runs dispatched; GitHub-hosted concurrency starvation serializes the queue); normal merge into `main` follows combined acceptance; the non-20i/20i gap review follows after the combined run.

## 2026-10-19 — contacts native fixture correction
## 2026-10-19 — payment-link view correction

A separate companion to Devin's [PR202](https://github.com/afterburn25/Cajun_Web/pull/202) prevents revoked, superseded, expired or changed payment links from exposing current invoice metadata or a stale amount due. Prepared links display the same invoice snapshot used to validate their original fingerprint and client/project binding. [Scope, reproduction and remaining acceptance](docs/PAYMENT_LINK_VIEW_REVIEW.md). This branch starts at `750f1ab8`; it is not the main branch or a release. Native and installed-browser acceptance remain pending.

## 2026-10-18 — reduce unrelated CI work

Parent `cefb08d6c29f4faca104089bb5941b322418d2ba`, current [PR203](https://github.com/afterburn25/Cajun_Web/pull/203); branch `codex/contacts-native-fixture`; enclosing commit **Fix duplicate contacts setup in service-plan fixture**. This addresses the observed domain-renewal workflow39130068128 failure inherited by PR210: renewal checks passed, then the service-plan fixture attempted to create `spln_studio_contacts` twice. The canonical base schema already creates that table. Remove only the redundant second DDL call, retaining the schema marker and all contact, consent, worker and authority assertions.

[Named evidence](docs/evidence/service-plan-contact-fixture.json) retains three original artifact digests and the87-pass/1-failure service-plan reports. Nine actual extracted schema/setup guards pass in each default/minimal PHP mode; both syntax modes and independent source review pass. These are not corrected native SQL results. Main, published1.25 and the separate20i candidates do not contain this unintegrated contact extension. Devin retains feature ownership; root supplies this bounded test correction at the earliest affected branch so the downstream stack can inherit it through normal integration.

Next: first corrected-source native/full GitHub acceptance, then sequential integration with PR203 and descendants. Preserve original failed runs; no source or result is replaced and no production action occurred.

## 2026-10-18 — cloud endpoint supplied no catalog details

## 2026-10-19 - owner transfers the complete project to Devin

**Devin now owns all remaining 20i and non-20i implementation, integration, testing, review coordination and releases. The immediate priority is to review and merge completed work from both workers into main, then finish the remaining full roadmap independently.** [Full directive](docs/DEVIN_FULL_SITE_TAKEOVER.md) · [Devin start here](DEVIN_START_HERE.md) · [Current handoff](SESSION_HANDOFF.md).

Earlier instructions assigning 20i or releases exclusively to Codex and limiting Devin to non-20i are superseded. Existing quality, data-preservation, repository approval and live-operation gates remain. This is a documentation/assignment change only: it does not merge any application candidate, publish a new software release, deploy the site, stop an external worker or start a Devin session.

## Verified baseline at this handoff

- Main before this documentation change: `a86f6f4fa592e6f715a9420aec8de4622258642e`.
- Latest published release read from GitHub: [v1.25.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.25.0), source `b79c67110829126916e3a65e616191542448bccc`. Prior source/tag/assets remain unchanged.
- [PR216](https://github.com/afterburn25/Cajun_Web/pull/216) remains an open direct-hosting-catalog candidate at `f67b1631683b29096dd8ec1daee405e104dc0b83`. Its capture successor and numerous hosting, nameserver, runtime, StackCP, client and non-20i branches require current-source review and combined integration.
- No new completion percentage is established by this instruction update. Prior chat estimates are not audited project counters.

The [takeover inventory](docs/DEVIN_FULL_SITE_TAKEOVER.md) identifies initial PR families, concrete contacts/payment-link/VPS caveats, required acceptance and the complete remaining scope. Devin must refresh all source/CI/release states, build the dependency-based integration queue and merge only accepted useful slices. An old PR body may lag current job results; a passed parent cannot certify a successor.
Previous checkpoint: https://github.com/afterburn25/Cajun_Web/blob/35d8fb7efc94f6f851399d0755fa93097f1fce66/PROJECT_STATUS.md

## Devin non-20i lane — 2026-10-17

Devin PRs verified green: #198, #202, #203 on main (48/48 each) and stacked #200, #204, #205 on their parents (long lanes run after retarget). New tip `devin/non20i-staff-notes` @ `b6d20382` stacked on #205: staff-only internal project notes via `studio_messages.author_type='internal'` — invisible to clients, contacts, support sessions and activity feeds; no schema change. Syntax/test-compile/canonical inventory pass; PR pending GitHub API rate limit reset.

Nothing merged, deployed or contacted externally. 20i/provider/hosting/SSH work remains Codex-owned.


## Devin lane update — 2026-10-17 (later)

Current tips (stacked chain on devin/non20i-contact-activity):
- #209 `devin/non20i-staff-notes` @ `3e6db257` — staff-only internal project notes via `studio_messages.author_type='internal'` (no schema change); portal conversation allow-lists visible author types. `3e6db257` corrects `d203b5ea`'s flash assertion ('Project updated' is the shared `studio_*` group flash; 'Project saved.' is only for `studio_job`).
- #210 `devin/non20i-milestones` @ `868e967c` — additive `studio_milestones` (state + optional YYYY-MM-DD target date) via existing `studio_migrate`; admin manage forms, portal visibility, milestone activity events.
- #211 `devin/non20i-activity-prefs` @ `537c2c98` — additive `activity_prefs`; per-actor hiding of update types from feed/unread/open/mark, re-run `activity_setup` provisions on existing installs.

php -l + test-compile + canonical inventory pass on all. Component lanes queued (GitHub runner starvation); long lanes run after Codex retargets to main.

Previous checkpoint: https://github.com/afterburn25/Cajun_Web/blob/35d8fb7efc94f6f851399d0755fa93097f1fce66/PROJECT_STATUS.md
Original [run 39090394774](https://github.com/afterburn25/Cajun_Web/actions/runs/39090394774), attempt 1, checked out `1159648f968d46439f7e88a9cd0f6dbee3758643`. Its 39 offline transport/disclosure tests passed; the live step and overall run failed, and the bounded shape artifact was uploaded. `package_types` observed an array of objects with `id` integer, `label` string and `platform` string. `cloud_server_product_data` returned the diagnostic outcome `unexpected_root`; the actual root kind was not retained, so an empty catalog or missing entitlement cannot be inferred.

## Immediate next actions

Create or safely reuse a Devin-owned main-based integration branch, classify all pending Codex/Devin work, resolve mature blockers and merge ready slices sequentially. Complete a verified incremental release with install/update ZIPs and actual screenshots before allowing another indefinite branch backlog. Then continue all remaining 20i and non-20i capabilities without waiting for Codex or a new continue instruction.

CI cleanup is not feature completion. Preserve the completed narrow queue-cleanup records and original required tests; do not replay old cancellation batches or redispatch queued work. Review and publication follow [the existing release workflow](docs/RELEASE_WORKFLOW.md).

## Preserved detailed history

The complete pre-takeover status and every dated source/test/queue checkpoint remain at the [exact previous main version](https://github.com/afterburn25/Cajun_Web/blob/a86f6f4fa592e6f715a9420aec8de4622258642e/PROJECT_STATUS.md). Feature guides, [continuation evidence](docs/ROADMAP_CONTINUATION_2026_10_19.md), [1.25 publication evidence](docs/RELEASE_1.25.0_PUBLICATION.md), roadmaps, original failures and release assets remain intact. The current ownership above supersedes old allocation prose; it does not reinterpret historical results.
