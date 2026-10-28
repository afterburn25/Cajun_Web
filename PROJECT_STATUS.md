# CajunWebPro current status

## 2026-10-28 — high-severity payoff/owner-control correction

Independent source review found that a paid-off financing plan blocked every owner whole-license deactivation and validation silently reactivated an inactive paid-off key. Payoff must permanently remove only financing suspension, not owner authority for fraud, breach or unauthorized resale. The integration candidate now preserves explicit owner deactivation, keeps the paid-off financing verdict immutable, and permits deliberate reactivation of the same key. Native and installed-browser regressions are updated; hosted execution remains required. Domain binding and default no-resale/no-distribution rights are unchanged.

## 2026-10-28 — combined PR266 integration candidate refreshed

`codex/licensing-integration-review` now contains Devin feature head `9af42ba7`, prior review head `25e2d34f`, optional-finance routing correction #269 and delayed-lifecycle correction #270. The support forgery browser case posts through the real support action dispatcher and requires one attributed denial plus unchanged agreement/payment state. Canonical installer generation, 283 artifact-helper cases, 69 build rejection cases, documentation and Python syntax pass locally; PHP/native/browser execution is unavailable in this workspace and remains a hosted gate. The exact feature acceptance lanes are queued, not duplicated. No merge, release, live charging or deployment.

## 2026-10-28 — independent delayed-financing-event correction

Branch `codex/licensing-lifecycle-ordering`, stacked on [routing correction #269](https://github.com/afterburn25/Cajun_Web/pull/269), preserves cancelled, reversal-recovery and replacement states under delayed gateway lifecycle events, and retains confirmed suspension after a delayed failure. The real-service regression has 45 passing isolated cases (17 failed before the fix). Both corrections remain proposed for the existing integration writer; native/gateway/installed acceptance is still required. [Review and evidence](docs/CODEX_LICENSING_REVIEW_2026_10_28.md).

## 2026-10-28 — independent licensing webhook correction

Branch `codex/licensing-webhook-isolation` prepares two focused fixes for PR #266: optional financing no longer queries absent finance tables for software captures or requires financing production activation for unrelated software refunds/voids. Ten actual-service regression cases pass locally; broader acceptance, sandbox and release gates remain open. The owner retained the separate **Complete Licensing Integration** chat as integration writer. [Exact source, findings, evidence and handoff](docs/CODEX_LICENSING_REVIEW_2026_10_28.md). Not merged, released or deployed.

## 2026-10-28 — financed replacement recovery hardening

The Codex companion now also repairs replacement enrollment after the original start date and requires verified terminal retirement of the prior ARB before creating parallel billing. Policy and synthetic gateway regressions pass; full installer refresh passes. Hosted native/browser and independent security/real-sandbox gates remain open. [Exact evidence and current gates](docs/PR266_INTEGRATION_ACCEPTANCE.md).

## 2026-10-28 — PR266 independent correction candidate

Owner assigned Codex a separate licensing/financing integration pass on `codex/licensing-integration-review`, refreshed to Devin parent `6045b801`. Preserves Devin’s project-isolation fix; adds installed independence assertions, a dynamic document-roots CI port, and narrowly filtered companion-PR acceptance. Local checks pass; hosted candidate acceptance and security/release gates remain pending. [Exact failures, evidence and remaining gates](docs/PR266_INTEGRATION_ACCEPTANCE.md). No production activation or deployment.

## 2026-10-26 - CI queue-hygiene repair (in flight)

Branch `devin/ci-concurrency-hygiene`: all 34 superseded-run-blocking workflows audited; 32 development checks regrouped from per-`github.sha` concurrency (which made `cancel-in-progress` a no-op) to per-PR/per-branch groups with main-exempt cancellation; two unguarded checks gained the same block. `tools/ci_health.py` + weekly `ci-health.yml` + `docs/CI_HEALTH.md` added. Protected release/acceptance workflows unchanged. Backlog root cause was sha-scoped groups + a ~35-workflow-per-push lane against a ~4-5-slot account runner ceiling, not a GitHub outage. Not merged; no release or deployment implied.

## 2026-10-22 - merge-first delivery cycle begins

Per the standing merge-first directive (AGENTS.md), completed work is delivered to `main` in small accepted increments. First increment: **PR261** merges the accepted 20i reset-email-template source `467ec1bd` (original full-green run [39422487199](https://github.com/afterburn25/Cajun_Web/actions/runs/39422487199)) into main — candidate merge `e334678e`, hosted acceptance and independent review in progress. Integration queue: `docs/DEVIN_INTEGRATION_QUEUE.md`. No release or deployment is implied.

## 2026-10-19 — owner 20i reset email template controls

Separate branch `codex/twentyi-reset-email-template`, from main `a86f6f4f`, adds explicit reading, exact replacement review, one admitted save and separate provider readback for the StackCP password-reset email template. Encrypted history and current owner/account checks protect the flow; no email is sent. [Behavior, actual evidence and remaining gates](docs/TWENTYI_PASSWORD_RESET_TEMPLATE.md). Native and installed-browser acceptance remain pending; this is not a release.

Payment-link companion [PR253](https://github.com/afterburn25/Cajun_Web/pull/253) is pushed at `b1948eeb` with its first full acceptance queued. Separate renewal-invoicing PR252 and immutable published1.25.0 are preserved.

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

## Immediate next actions

Create or safely reuse a Devin-owned main-based integration branch, classify all pending Codex/Devin work, resolve mature blockers and merge ready slices sequentially. Complete a verified incremental release with install/update ZIPs and actual screenshots before allowing another indefinite branch backlog. Then continue all remaining 20i and non-20i capabilities without waiting for Codex or a new continue instruction.

CI cleanup is not feature completion. Preserve the completed narrow queue-cleanup records and original required tests; do not replay old cancellation batches or redispatch queued work. Review and publication follow [the existing release workflow](docs/RELEASE_WORKFLOW.md).

## Preserved detailed history

The complete pre-takeover status and every dated source/test/queue checkpoint remain at the [exact previous main version](https://github.com/afterburn25/Cajun_Web/blob/a86f6f4fa592e6f715a9420aec8de4622258642e/PROJECT_STATUS.md). Feature guides, [continuation evidence](docs/ROADMAP_CONTINUATION_2026_10_19.md), [1.25 publication evidence](docs/RELEASE_1.25.0_PUBLICATION.md), roadmaps, original failures and release assets remain intact. The current ownership above supersedes old allocation prose; it does not reinterpret historical results.
