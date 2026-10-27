# CajunWebPro current status
## 2026-10-19 — subdomain request notifications candidate

Branch `codex/twentyi-subdomain-notifications` builds on frozen PR237 source `d1493f77f4c32d49b62256df1645dc60b9768cb9`. It adds [owner/client subdomain notices](docs/TWENTYI_SUBDOMAIN_NOTIFICATIONS.md) to the existing activity center and bell, with exact-request links, current authority checks and independent read state. Uncertain sends remain distinct from later hostname observations. Local checks and independent source review are recorded in the [receipt](docs/evidence/subdomain-notifications-local.json); native SQL and installed browser execution remain required. No release or live deployment is claimed.

Root owns the shared activity integration and documentation; the existing three agents supplied provider projection, native/browser extensions, acceptance wiring and independent review. Original PR237/PR220/catalog full runs remain queued at the latest read and are preserved. Main is `dcd6966c4e0c209d15f9d88c8832ef601765c336`; published1.25.0 remains immutable, and Devin retains non-20i work. Next: submit the stacked draft and its first exact-head full acceptance, inspect actual results and captures, then integrate ready work sequentially.

## 2026-10-18 — installed subdomain workflow coverage

Branch `codex/twentyi-client-subdomain-browser` starts from [PR236](https://github.com/afterburn25/Cajun_Web/pull/236), source `af956844690b1ed3be93a9a83574f06127fa8763`. It adds [packaged owner/client HTTP and Chromium coverage](docs/TWENTYI_CLIENT_SUBDOMAIN_BROWSER_ACCEPTANCE.md) for the submitted subdomain workflow. The original PR236 focused/full runs remain queued on their first attempts. Source preparation and offline fixture checks are separate from the installed results that remain required. Published 1.25.0 and the live site are unchanged.

Local evidence: 90 new Handler checks, all 82 outcomes from four unchanged Handler contracts, 521 portable acceptance outcomes preserving every prior 429, and 14 callback source/failure-path checks pass. The [receipt](docs/evidence/client-subdomain-browser-local.json) records hashes and limits. No shipping files changed. Next: first full candidate run, actual six-capture review, then normal combined integration and release acceptance.

## 2026-10-18 — client subdomain request candidate

Branch `codex/twentyi-client-subdomain-requests` starts from `b8c316b6fd138167b11c251d5db24a6d31f2bd88` ([PR234](https://github.com/afterburn25/Cajun_Web/pull/234)). It adds [primary-client intake, owner review and saved progress](docs/TWENTYI_CLIENT_SUBDOMAIN_REQUESTS.md) on top of the existing owner subdomain operation. Root owns shared navigation, routing, integration and documentation; the three existing agents own backend persistence, views and independent native acceptance. This checkpoint is unreleased and does not change the live site.

Source-specific local PHP checks, the canonical installer refresh, 25 touched PHP lints, 429 acceptance-helper outcomes and 53 synthetic layout checks pass. The [receipt](docs/evidence/client-subdomain-requests-local.json) names sources, reports and limits. Native SQL and installed owner/client flow coverage remain required; no new installed-browser producer or capture is claimed. Nine new mandatory commands/reports preserve every prior requirement. Next: inspect the first focused/full candidate runs, add and run installed workflow coverage, then perform normal combined integration and release acceptance.

Original full runs for PR234, hosting-checkout [PR235](https://github.com/afterburn25/Cajun_Web/pull/235) and catalog [PR216](https://github.com/afterburn25/Cajun_Web/pull/216) were still queued at the latest read. Preserve their first attempts. Published 1.25.0 remains immutable; Devin retains the separate non-20i assignment.

## 2026-10-18 — subdomain additions in development

Branch `codex/twentyi-subdomain-add`, from main `f688f79ee838e86e4774bfa5ec71fc7fb79126a5`, adds an [owner subdomain request and independent readback workflow](docs/TWENTYI_SUBDOMAIN_ADDITIONS.md). Requests retain one-use approval and history, and uncertainty prevents another add for the same hostname. Separate names remain eligible for fresh approval. Local verification is recorded in the session log; native SQL, installed application acceptance and release remain pending.

The latest bounded CI inspection found GitHub-hosted jobs waiting for runners while older jobs were starting and finishing. At 2026-10-19 02:25 UTC, GitHub reported 2,500 queued workflows; this is a queue count, not an identified account quota. StackCP [PR233](https://github.com/afterburn25/Cajun_Web/pull/233) originals 39189388126/39189388293 remain intact. Hosting and nameserver candidates, Devin's non-20i branches and published 1.25.0 are preserved.

## 2026-10-18 — five pure-suite PR filters

## 2026-10-26 - CI queue-hygiene repair (in flight)

Branch `devin/ci-concurrency-hygiene`: all 34 superseded-run-blocking workflows audited; 32 development checks regrouped from per-`github.sha` concurrency (which made `cancel-in-progress` a no-op) to per-PR/per-branch groups with main-exempt cancellation; two unguarded checks gained the same block. `tools/ci_health.py` + weekly `ci-health.yml` + `docs/CI_HEALTH.md` added. Protected release/acceptance workflows unchanged. Backlog root cause was sha-scoped groups + a ~35-workflow-per-push lane against a ~4-5-slot account runner ceiling, not a GitHub outage. Not merged; no release or deployment implied.

## 2026-10-22 - merge-first delivery cycle begins

Per the standing merge-first directive (AGENTS.md), completed work is delivered to `main` in small accepted increments. First increment: **PR261** merges the accepted 20i reset-email-template source `467ec1bd` (original full-green run [39422487199](https://github.com/afterburn25/Cajun_Web/actions/runs/39422487199)) into main — candidate merge `e334678e`, hosted acceptance and independent review in progress. Integration queue: `docs/DEVIN_INTEGRATION_QUEUE.md`. No release or deployment is implied.
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

## 2026-10-18 — client cloud website journey

From main `e397d9a6e293850e8dff61a593e3ba39ee178400`, branch `codex/twentyi-client-cloud-websites` implements primary-client request/cancel, owner decline/explicit creation, retained outcomes and exact returned-package confirmation navigation. Client dispatch and the existing shared grant commit atomically; replay never sends again. The current intake requires a complete cloud observation within15 minutes. [Behavior, authority, evidence and remaining gates](docs/TWENTYI_CLIENT_CLOUD_WEBSITES.md).

Local evidence:141 document and98 modeled UI checks per PHP mode; four actual module cold loads;34 legacy-context checks per mode;271 final artifact-helper checks;35 portable installed-update guards;19 actual published-baseline negative checks. Original1.24 and1.25 ZIP bytes validate against their retained member inventories, with all27 earlier release maps unchanged. Independent service, browser, installed-update and shared-wiring review is recorded in the feature guide. Native SQL/concurrency, packaged browser pixels, installed Linux/Apache execution and full combined acceptance remain pending. No live operation, new release or deployment; published1.25 and all separate original CI handles remain unchanged.

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
