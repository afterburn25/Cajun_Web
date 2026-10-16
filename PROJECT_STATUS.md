# CajunWebPro current status

Updated 2026-10-16 13:47 UTC. **Published1.24.0 remains immutable;1.25.0 and complete20i remain unfinished.**

Devin owns the remaining non-20i roadmap, including existing licensing; Codex and three agents own complete20i and final integration/releases. [Devin start here](DEVIN_START_HERE.md). This supersedes older unrelated-work pauses for Devin. External-session acknowledgement is not assumed.

## Current work and next action

Read the [current20i checkpoint](docs/TWENTYI_CURRENT_CHECKPOINT.md) for exact source, test results, original failures, parallel ownership and release gates. Candidate9779879b is frozen on `codex/twentyi-registration-drafts-integration`; its four-way focused registration checks and exact Windows packages pass independent verification. First full acceptance38909577278 is in_progress; conclusion pending. Follow that original run; do not cancel or rerun unchanged source. Current browser/images/Linux comparisons and complete release gates remain unproven.

Cloud assignment authority and StackCP membership/grant observations are separate source-reviewed increments with native/browser fixtures still under review. Integrate their shared patches sequentially on a new source and preserve the registration canonical builder area. No login or SSH completion is claimed.

Main retains the existing licensing implementation and four unresolved [integration findings](docs/LICENSING_INTEGRATION_REVIEW.md), assigned to Devin. The separate20i candidate has not imported that runtime. Reinspect both histories before integration; preserve billing, support-session, storage and invoice-purpose guards.

## Devin non-20i branches — 2026-10-16

- `devin/non20i-licensing` (PR #159) resolves all four findings: populated pre-master upgrades gain `is_master` additively, master keys are excluded from portal/bundle/download surfaces, legacy `invoice_id IS NULL` fulfilment retries claim only exact-one candidates, and unknown/future schema markers are refused before DDL.
- `devin/non20i-support-tickets` (PR #158) ports the preserved ticket slice — owner-prepared storage, `support.tickets` permission, admin queue/detail/create, portal flows, private notes, per-viewer read cursors, exact-message activity links.
- `devin/non20i-design-payments` (stacked on the planning branch) ports the preserved `codex/design-payment-schedules` + `codex/design-financial-activity` stack — proposal-bound quotes/schedules, explicit client consent, one bound invoice per accepted stage, ledger balance guards, `financial:` activity notices. No checkout is activated.
- `devin/non20i-project-planning` (stacked on the progress branch) ports the preserved `codex/project-planning*` stack — owner-prepared `studio_plan_*` storage, `ProjectPlan`/`ProjectPlanEvent` canonical contracts, staff editor + client dashboard, milestone dependencies, `planning:` activity items, and populated-upgrade retention.
- `devin/non20i-project-progress` ports the preserved progress slice — owner-prepared `studio_progress_*` storage, hash-linked dated history in `studio_job` saves, portal timeline, `progress:` activity items. Local pure-model checks pass; native/browser/upgrade lanes wired into CI.

All are local/CI validation only — no live deployment, mail, provider or customer action.

## Continuity

The [previous status at80ce8b19](https://github.com/afterburn25/Cajun_Web/blob/80ce8b19a6fb5b05a92821b097b9f8ce2d5b214f/PROJECT_STATUS.md) retains the earlier licensing implementation, acceptance claims and release history. It is historical evidence, not proof of this candidate. [Published1.24 report](docs/TEST_REPORT_1.24.0.md), [full roadmap](docs/ROADMAP.md), [coordination](docs/AGENT_COORDINATION.md) and [release workflow](docs/RELEASE_WORKFLOW.md) remain available.

This main update changes documentation only. Runtime, schema, installer, versions, released assets and live operations remain unchanged.
