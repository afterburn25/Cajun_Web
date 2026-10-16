# CajunWebPro current status

Fresh observation, 2026-10-16: original full819c run38917429508 reached a real populated-inventory upgrade defect in the owner view: StackCP readiness queried operation on a legacy schema without that column. The native job failed and browser execution skipped; original evidence is retained (8e973156). The cloud-assignment descendant is held for a bounded compatibility correction. Build and recovery evidence remain valid for their own scope.

Updated 2026-10-16 14:41 UTC. **Published 1.24.0 is unchanged; 1.25.0 and complete 20i are not released.**

Devin owns all remaining non-20i site work, including existing licensing. Codex and its three agents own complete 20i, shared integration and releases. [Devin start here](DEVIN_START_HERE.md); assignment [#157](https://github.com/afterburn25/Cajun_Web/issues/157) remains current. Devin [acknowledged the assignment and reported work](https://github.com/afterburn25/Cajun_Web/issues/157#issuecomment-6217621738). Licensing PR #159, tickets #158 and project progress #160 are open; their proposed changes require independent acceptance and sequential integration.

## Current checkpoint

The corrected StackCP access candidate is [819c3ea3](https://github.com/afterburn25/Cajun_Web/commit/819c3ea39b4ebbef8f37280dc5ede59cf06fd01c) on `codex/twentyi-stackcp-access-observations`. Its original four-way focused run [38915851687](https://github.com/afterburn25/Cajun_Web/actions/runs/38915851687), attempt 1, finished successfully. Domains and root independently verified all four actual archives: 18 reports and 2,045 passing named executions per environment, including 103 native access checks each (8,180 executions total). Exact Windows package verification now passes. The correction changes only seven test/documentation files; shipping bytes match preserved parent 13879b20.

The first full run for exact 819c3ea3, [38917429508](https://github.com/afterburn25/Cajun_Web/actions/runs/38917429508), was dispatched once with `checkpoint_only: false` and is in progress; its full acceptance remains pending. Earlier registration candidate 9779879b remains frozen. Original full run [38909577278](https://github.com/afterburn25/Cajun_Web/actions/runs/38909577278) is still in its HTTP/Chromium stage. Build, recovery and Apache partitions are independently verified; the native step succeeded. Browser evidence, screenshots and final acceptance remain pending. Those results do not certify descendant 819c3ea3.

The [current 20i checkpoint](docs/TWENTYI_CURRENT_CHECKPOINT.md) records exact evidence, original failures and next gates. Cloud assignment authority remains an external proposal with integration and native/HTTP acceptance pending. The old-account fallback and a subsequent lock-entry active-client race have bounded source/synthetic corrections; neither is full cloud acceptance. StackCP membership and whole-user grant observations do not provide login sessions, remote scope restriction or SSH.

Main retains existing licensing and the four unresolved [integration findings](docs/LICENSING_INTEGRATION_REVIEW.md), assigned to Devin. Its runtime has not been imported into the separate 20i candidate. Preserve both histories and billing, support-session, storage and invoice-purpose guards during later integration.

## Devin non-20i branches — 2026-10-16

- `devin/non20i-licensing` (PR #159) resolves all four findings: populated pre-master upgrades gain `is_master` additively, master keys are excluded from portal/bundle/download surfaces, legacy `invoice_id IS NULL` fulfilment retries claim only exact-one candidates, and unknown/future schema markers are refused before DDL.
- `devin/non20i-support-tickets` (PR #158) ports the preserved ticket slice — owner-prepared storage, `support.tickets` permission, admin queue/detail/create, portal flows, private notes, per-viewer read cursors, exact-message activity links.
- `devin/non20i-project-progress` (PR #160) ports the preserved progress slice — owner-prepared `studio_progress_*` storage, hash-linked dated history in `studio_job` saves, portal timeline, `progress:` activity items.
- `devin/non20i-mail-replies` (PR #161) completes webmail Reply/Forward — opt-in raw retention, message-screen controls, protected copied drafts, `mail_reply_drafts` storage, format-2 submissions with `In-Reply-To`/`References`, source-Read revalidation on every copied-content path.
- `devin/non20i-backups-foundation` (PR #162) ports the A-03 contract layer — `BackupManifest` sealed item scopes with structural secrets exclusion, `RestorePlan` deliberate preview→authorize→applied states.
- `devin/non20i-pipeline-foundation` (PR #163) ports the lead-pipeline contract layer — `PipelineBoard`/`PipelineStage`/`LeadRecord` ordered stages with validated transitions and non-drag control parity.
- `devin/non20i-proposals-foundation` (PR #164) ports the proposal contract layer — `Proposal`/`ProposalVersion`/`ApprovalDecision` immutable versions with deliberate approval states.
- `devin/non20i-notifications-foundation` ports the P-07 notification contract layer — `Notification`/`NotificationFeed`/`NotificationPreferences`/`DeliveryAttempt`/`MetricSample` with bounded delivery and honest stale/absent metric handling.
- `devin/non20i-notifications-runtime` builds on it — directed per-recipient feed storage (`notification_feed`/`notification_prefs`/`notification_deliveries`, distinct from payment webhooks), owner-password setup migration, private admin/portal feeds, recipient-only read state, per-kind email opt-outs, honest `sent`/`failed` delivery records, and emit hooks for inquiries, project messages, review decisions, delivery publication and invoice-paid events.
- `devin/non20i-ticket-notifications` merges the tickets and notification-runtime branches and wires ticket events into the feed — staff ticket creation and public reply/status events notify the owning client, client opens/replies/status changes notify staff holding `support.tickets`, private staff notes and assignments never reach the client feed, support-session replies notify nobody, and deterministic `ticket-*` dedupe keys keep request replays from duplicating rows.

All are local/CI validation only — no live deployment, mail, provider or customer action.

## Continuity

The [previous checkpoint at 155db254](https://github.com/afterburn25/Cajun_Web/blob/155db254e8e3d0c018200abf6a9325d04c3578ca/docs/TWENTYI_CURRENT_CHECKPOINT.md), [published 1.24 report](docs/TEST_REPORT_1.24.0.md), [roadmap](docs/ROADMAP.md) and [coordination](docs/AGENT_COORDINATION.md) preserve earlier evidence. This main checkpoint changes documentation only; it changes no runtime, installer, version, asset or live installation.
