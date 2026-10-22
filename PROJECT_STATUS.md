# CajunWebPro current status

## 2026-10-22 - merge-first delivery cycle begins

Per the standing merge-first directive (AGENTS.md), completed work is delivered to `main` in small accepted increments. First increment: **PR261** merges the accepted 20i reset-email-template source `467ec1bd` (original full-green run [39422487199](https://github.com/afterburn25/Cajun_Web/actions/runs/39422487199)) into main — candidate merge `e334678e`, hosted acceptance and independent review in progress. Integration queue: `docs/DEVIN_INTEGRATION_QUEUE.md`. No release or deployment is implied.

## 2026-10-19 - owner transfers the complete project to Devin

**Devin now owns all remaining 20i and non-20i implementation, integration, testing, review coordination and releases. The immediate priority is to review and merge completed work from both workers into main, then finish the remaining full roadmap independently.** [Full directive](docs/DEVIN_FULL_SITE_TAKEOVER.md) · [Devin start here](DEVIN_START_HERE.md) · [Current handoff](SESSION_HANDOFF.md).

Earlier instructions assigning 20i or releases exclusively to Codex and limiting Devin to non-20i are superseded. Existing quality, data-preservation, repository approval and live-operation gates remain. This is a documentation/assignment change only: it does not merge any application candidate, publish a new software release, deploy the site, stop an external worker or start a Devin session.

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
