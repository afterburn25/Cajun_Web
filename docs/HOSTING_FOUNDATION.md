# Hosting foundation (HC-08 contract layer)

Canonical contract classes for the roadmap's shared-hosting lifecycle:
sellable package definitions, a guarded account state machine, queued
plan changes, StackCP access grants, and honest provider-operation
results.

This is a **model layer only**. It owns no 20i calls, no credentials,
no persistence, no billing and no UI. Real provisioning, reconciliation,
sync and client/staff views are the integration gate.

## Files

| File | Role |
| --- | --- |
| `foundation/hosting/HostingPackage.php` | Our SKU → concrete `providerPackageType` + region + named integer quotas. `null` quota = "not metered by this plan". Names containing `unlimited`/`unmetered` and negative/non-integer quotas are refused — an unbounded promise can never be stored. `fits($usage)` requires every observation to be a non-negative integer and only *declared* `null` means unmetered: an observed metric the package never declared cannot prove a fit, so it is refused rather than silently passed. |
| `foundation/hosting/OperationResult.php` | Recorded outcome of one real provider operation: `applied` (receipt required) / `failed` (named error + retryable flag) / `timeout`. Timeout is a distinct honest "unknown" — retrying a timed-out create is the classic duplicate-package bug, so timeouts are never marked retryable. Every result carries the operation name and the subject it resolves; `receipt` is the provider's operation reference and `resourceRef` separately carries a verified provider resource id only when actually reported. |
| `foundation/hosting/StackCpGrant.php` | Control-panel access grant: account, grantee, `manage`/`read` level, issuer, optional bounded expiry, revocation. A grant cannot authorize before its issuance time, at/after expiry, or once revoked. Panel access is always an explicit grant. |
| `foundation/hosting/PlanChange.php` | Queued `upgrade`/`downgrade`/`renewal` with requester/effective-at. `approve()` is a *pre-dispatch* decision: it enforces the effective time and, for downgrades, requires a complete validated usage observation that provably fits the target — refusal happens before any provider call. `recordResult()` accepts only a result bound to the expected operation and account; timeout → `uncertain`, closed only by `resolve()` with reconciliation evidence. Only the requester can cancel an undispatched change. |
| `foundation/hosting/HostingAccount.php` | The lifecycle state machine (below) with attributed history and bound, recorded operation results. |

## Lifecycle

```
pending → provisioning → active ⇄ suspending ⇄ suspended ⇄ resuming
pending/provisioning → failed → retry() → pending
active/suspended → cancellation_requested → deprovisioning → deprovisioned
pending/failed → cancelled            (pre-provisioning withdrawal only)
```

Protections built into the state machine:

- **Bound results** — a provider result resolves only the operation it
  names (`hosting.provision`/`suspend`/`resume`/`deprovision`) for the
  account it names (`subjectRef`). A mismatched result throws without
  mutating status, history or the operations log; `providerRef` is set
  only from a reported resource id, never an operation receipt.
- **Intent vs confirmed** — `suspend()`/`beginResume()` enter
  `suspending`/`resuming`; `suspended`/`active` are only reached through
  an applied provider result.
- **Unresolved timeouts** — a `timeout` result records
  `awaitingReconciliation()`. While unresolved the account cannot be
  order-cancelled ("never provisioned" cannot be claimed while the remote
  create may have landed) and cannot be retried. `reconcileTimeout()`
  requires evidence and either adopts a confirmed remote resource
  (`resourceRef` required — adopted, never recreated) or confirms nothing
  landed so retry is safe.
- **Grace windows** — `suspend()` is refused before the recorded
  `graceUntil` deadline.
- **Dispute protection** — `suspend()` is refused while a billing
  dispute is open; access and backups are preserved per policy.
- **Two-actor decommission** — `requestCancellation` records one
  non-empty actor; `approveDeprovision` requires a *different* non-empty
  actor plus an explicit `retentionConfirmed` flag. A single account, an
  empty identity, or an approval without confirmed retention cannot
  destroy hosting. (Identity comparison is not authorization — the future
  adapter must enforce current authorization and retention evidence.)
- **Suspension reasons** — a recorded non-empty reason is required.
- **Domain linking** — `linkDomain` validates host syntax and requires
  `active`.

## Invariants

- A package always names the provider package type it provisions.
- No stored promise of "unlimited" anything.
- Terminal states (`deprovisioned`, `cancelled`) are unreachable backward.
- Order cancellation is impossible once provisioning has begun, and
  impossible while a provisioning timeout is unresolved.
- Every status change is history-attributed; every provider attempt is
  recorded — including unknown outcomes. Unresolved operations stay
  explicit until reconciled with evidence.

## Not included (separate gates)

- 20i API calls, package creation, reconciliation polling, usage
  metering sync, billing coupling, persistence schema, client/staff UI,
  StackCP SSO, email/database/DNS sub-controls (capability register).
- Website-delivery ZIPs stay P-06; provider backup snapshots are
  explicitly not delivery artifacts.

## Tests

`tests/hosting/run.php` — 111 synthetic checks covering package
validation (typed/bounded observations, absent-vs-unmetered), bound
operation results, grant issuance/expiry/revocation boundaries, queued
plan changes (pre-dispatch downgrade refusal, early-dispatch refusal,
misbound results, uncertainty resolution), the full account lifecycle
with intent/confirmed split, unresolved-timeout reconciliation and
adoption, grace/dispute protections, non-empty two-actor decommission,
and attributed history. Fixtures only; runs clean under `php -n`. CI:
`.github/workflows/hosting.yml` on official PHP 8.2/8.4 with
`--network none`.
