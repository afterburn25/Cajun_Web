# Hosting reconciliation without another dispatch

## Durable adapter increment — prepared, native acceptance pending

The separate `codex/hosting-reconciliation-storage` branch preserves canonical PR88/b597559. Two additive tables retain observations and the single conclusive decision per original dispatch. Owner/password setup validates exact columns, collations, unique indexes and foreign keys; it refuses populated unrecognized tables. Existing operation setup and fresh installation do not activate these tables.

`hosting_reconciliation_record()` is trusted internal code, not an HTTP callback or operator outcome form. It requires an exact retained unresolved result, dispatch fingerprint and bounded observation containing `outcome` (`unknown`, `applied`, `not_applied`), `observed_at`, exact `resource_ref`, opaque `evidence_ref` and SHA256 evidence digest. The future operation-specific verifier must establish finality; absence from partial inventory or an action still running cannot become `not_applied`. The digest supplies integrity identity only. No real20i evidence verification is implemented here.

Unknown observations retain the unchanged unresolved journal. A conclusive observation atomically appends the new state-only command, retains its decision/evidence, adopts a unique resource when appropriate and writes strict audit. Validation replays the observation's exact original journal prefix and compares all dispatch/resource bindings. Original timeout or accepted-without-identity results stay unchanged. Exact observation replay is no-op even after later actions; changed identity, new decisions for settled/old attempts and missing/tampered proof fail closed. The internal finalizer can retain reality after access loss, without granting another operation. Audit excludes evidence contents and credentials.

Provisioning retry after validated absence still needs a separate current-owner/password action; it only returns local pending state. Dispatch then separately checks the current active client/provider and revisions. Deletion retry after validated non-removal still needs the original currently authorized second approver and retained retention approval, and commits its own fresh fence. Legacy `reconcile` stays refused. Missing dispatch results are deliberately not fabricated from journal state; a later verified crash-recovery boundary is required before this adapter can settle them.

Canonical parent CI passes1,820 named executions. The new database suite is prepared for actual PHP8.2/8.4 MariaDB, with all94/79/44/67 existing suites retained. A local Windows run stopped before reconciliation assertions because the platform cannot retain the required0600 provider key permissions; no protection was relaxed. Native acceptance and independent additive review are pending. No route, real provider request, worker, fresh-storage activation or release.

## Original canonical checkpoint and design requirements

Unreleased work on `codex/hosting-reconciliation` from deletion-retry87/bd0e46e, preserving parent85/21ebe69. This first increment adds a canonical transition and journal command. Durable observation storage, verification, current-authorized retry adaptation and provider coordination described below are next work, not implemented acceptance. There is no route, provider request, credential access, worker, schema activation or new version.

## Canonical observation boundary

`HostingAccount::reconcileWithoutDispatch()` and the closed journal command `reconcile_without_dispatch` settle only the exact unresolved attempt. They require nonempty evidence, a named actor, a time no earlier than current history and consistent resource identity. Applied provisioning needs a resource id; confirmed absent provisioning cannot simultaneously name one. Other operations retain their original resource. Unknown results must not be mapped to false: the caller must separately verify a conclusive observation before using this transition.

| Original uncertain operation | Confirmed applied | Confirmed not applied |
| --- | --- | --- |
| Provision | Adopt the actual resource and become active | Remain failed, eligible only for a separate retry decision |
| Suspend | Become suspended | Remain active |
| Resume | Become active | Remain suspended |
| Deprovision | Become deprovisioned | Become deletion-failed, with no pending attempt; require a separate retry |

Every new-path outcome clears the old uncertainty without allocating a fresh attempt. A later explicit canonical retry mints the next identity only when its separate transition executes. The application must commit that retry's dispatch fence atomically under current authorization; a model result is not a network claim. Original operation observations, exact attempt and evidence stay in history. The immutable journal round-trips the new command and treats exact event replay as idempotent; changed/repeated decisions cannot rewrite a settled attempt.

The original `reconcileTimeout()` and `reconcile` command preserve their historical semantics, including legacy negative deletion reconciliation arming a fresh identity. This avoids silently changing existing serialized journal meaning. The durable application adapter currently refuses reconciliation events, including the new command, until its verified persistence layer is implemented. Generic local-record actions cannot select either reconciliation command. Do not expose the legacy rearming path through a future endpoint.

## Required durable integration — next implementation

Retain immutable unknown observations separately; they neither close uncertainty nor permit a new claim. A conclusive decision must bind the committed dispatch fingerprint, original account/client, provider identity/revision, exact attempt, original target and verified observation. Preserve an observation after later initiating-owner/client/provider access loss; that is not authorization for another action.

Commit the conclusive decision, canonical event, any adopted unique resource association and strict audit together. A persistence failure must preserve the original unresolved fence. Exact replay of a retained decision remains a no-op even after a later attempt; conflicting input or a late decision for another attempt refuses. Missing original fence/result/evidence must not be reconstructed from a state label. A crash with no recorded result stays unknown and requires a verified internal reconciliation path, never automatic retransmission.

Preparing another provisioning attempt after confirmed absence remains a separate current-owner/password action. Retrying a confirmed-not-removed deletion requires the original currently authorized second approver, retained retention approval, same provider/client/resource, current revisions and a new atomic dispatch fence. Extend existing retry eligibility only when its corresponding immutable reconciliation decision is validated. Today's definite-failure retry functions still refuse these new histories.

Provider evidence must establish the exact operation/resource outcome. Absence from a partial or unavailable inventory is not proof that a resource was never created or deleted. The read-only20i inventory contract belongs to Devin under issue2 assignment6167449082; Codex owns this persistence/coordinator/UI lane. Do not invent verified20i schemas or expose a raw operator form that asserts a remote outcome.

## Current evidence and release scope

Local PHP8.4 default/no-ini each passes95 new named controls plus unchanged168 supplied,21 review,23 input and148 journal controls. The first local fixture emitted a by-reference notice when reading history; it was corrected before these clean runs. Original controls are unchanged, so old command compatibility remains exercised. New cases cover all four operations/both conclusive outcomes, no new pending attempt, exact history/JSON replay, later explicit retry identity, delayed old results, malformed evidence/actor/time/resource and legacy/new command distinction. These tests use synthetic observations and do not verify a provider, database persistence or dispatch authority. PHP8.2/8.4 CI and independent review remain pending.

Parent87/e6 has downloaded568 native executions per matrix (67 deletion plus94/79/44 retained per PHP8.2/8.4). Its bd parent merge has a green native job whose reports still require inspection. Parent85/a9 full8,234/all21 proof is retained; newer1.23 candidate21 full38562463459 continues independently through recovery. Published1.22 remains immutable. Next: verify this canonical increment and review while implementing durable observations/finalization and deliberate retry eligibility, then actual provider/owner/client workflows. [Operations](HOSTING_OPERATIONS.md), [deletion retry](HOSTING_DEPROVISION_RETRY.md), [journal](HOSTING_JOURNAL.md).
