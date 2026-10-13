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
| `foundation/hosting/HostingPackage.php` | Our SKU → concrete `providerPackageType` + region + named integer quotas. `null` quota = "not metered by this plan". Names containing `unlimited`/`unmetered` and negative/non-integer quotas are refused — an unbounded promise can never be stored. `fits($usage)` answers whether observed usage is inside the package. |
| `foundation/hosting/OperationResult.php` | Recorded outcome of one real provider operation: `applied` (receipt required) / `failed` (named error + retryable flag) / `timeout`. Timeout is a distinct honest "unknown" — retrying a timed-out create is the classic duplicate-package bug, so timeouts are never marked retryable. |
| `foundation/hosting/StackCpGrant.php` | Control-panel access grant: account, grantee, `manage`/`read` level, issuer, optional bounded expiry, revocation. Panel access is always an explicit grant. |
| `foundation/hosting/PlanChange.php` | Queued `upgrade`/`downgrade`/`renewal` with requester/effective-at. `approve()` records the provider result; a **downgrade whose current usage exceeds the target is refused rather than applied**. Only the requester can cancel a queued change. |
| `foundation/hosting/HostingAccount.php` | The lifecycle state machine (below) with attributed history and recorded operation results. |

## Lifecycle

```
pending → provisioning → active ⇄ suspended
pending/provisioning → failed → retry() → pending
active/suspended → cancellation_requested → deprovisioning → deprovisioned
pending/failed → cancelled            (pre-provisioning withdrawal only)
```

Protections built into the state machine:

- **Grace windows** — `suspend()` is refused before the recorded
  `graceUntil` deadline.
- **Dispute protection** — `suspend()` is refused while a billing
  dispute is open; access and backups are preserved per policy.
- **Two-actor decommission** — `requestCancellation` records one actor;
  `approveDeprovision` requires a *different* actor plus an explicit
  `retentionConfirmed` flag. A single account cannot destroy hosting.
- **Honest results** — timeouts and failures are stored verbatim in
  `operations()`; a timed-out provision leaves the account `failed`
  pending reconciliation, never silently retried.
- **Suspension reasons** — a recorded non-empty reason is required.
- **Domain linking** — `linkDomain` validates host syntax and requires
  `active`.

## Invariants

- A package always names the provider package type it provisions.
- No stored promise of "unlimited" anything.
- Terminal states (`deprovisioned`, `cancelled`) are unreachable backward.
- Order cancellation is impossible once provisioning has begun.
- Every status change is history-attributed; every provider attempt is
  recorded — including unknown outcomes.

## Not included (separate gates)

- 20i API calls, package creation, reconciliation polling, usage
  metering sync, billing coupling, persistence schema, client/staff UI,
  StackCP SSO, email/database/DNS sub-controls (capability register).
- Website-delivery ZIPs stay P-06; provider backup snapshots are
  explicitly not delivery artifacts.

## Tests

`tests/hosting/run.php` — 59 synthetic checks covering package
validation, honest operation results, grant lifecycle, queued plan
changes (including over-limit downgrade refusal), the full account
lifecycle, grace/dispute protections, two-actor decommission, and
attributed history. Fixtures only; runs clean under `php -n`. CI:
`.github/workflows/hosting.yml` on official PHP 8.2/8.4 with
`--network none`.
