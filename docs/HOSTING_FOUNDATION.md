# Hosting foundation (HC-08 contract layer)

## Unreleased journal preparation

HostingJournal adds bounded immutable command encoding/reconstruction outside deployment, with148 local round-trip/refusal checks per PHP default/no-ini mode. [Format and persistence boundary](HOSTING_JOURNAL.md). Parent b6d4d24 integration has downloaded848 focused and6,912 application checks; independent delta review remains pending. No provider or application activation.

## Canonical integration scope

Preserved from Devin PR60 sourcec53dc4a on current mainff3ce72. Codex adds exact SKU end matching and bounded complete ASCII domain-label validation before state/history mutation. The existing alphabetic-TLD policy remains; Unicode/punycode TLD expansion is separate. This does not establish ownership, registration or provider capability. Local168 supplied +21 independent review +23 new input controls pass default/no-ini PHP8.4; integration matrix pending. Classes remain outside `site/`; durable state, concurrency, actual authorization, provider adapters and UI remain required.

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
| `foundation/hosting/HostingPackage.php` | Our SKU → concrete `providerPackageType` + region + named integer quotas. `null` quota = "not metered by this plan". Names containing `unlimited`/`unmetered` and negative/non-integer quotas are refused — an unbounded promise can never be stored. Validated quotas are copied into a fresh reference-free array, so caller-held PHP references can never mutate a stored package. `fits($usage)` requires every observation to be a non-negative integer and only *declared* `null` means unmetered: an observed metric the package never declared cannot prove a fit, so it is refused rather than silently passed. |
| `foundation/hosting/OperationResult.php` | Recorded outcome of one real provider operation: `applied` (receipt required) / `failed` (named error + retryable flag) / `timeout`. Timeout is a distinct honest "unknown" — retrying a timed-out create is the classic duplicate-package bug, so timeouts are never marked retryable. Every result carries the operation name, the subject it resolves, and optionally the immutable `attemptRef` minted at dispatch — consumers bind on all three, so a stale receipt from an earlier attempt is refused without mutation. `receipt` is the provider's operation reference and `resourceRef` separately carries a verified provider resource id only when actually reported. |
| `foundation/hosting/StackCpGrant.php` | Control-panel access grant: account, grantee, `manage`/`read` level, issuer, optional bounded expiry, revocation. A grant cannot authorize before its issuance time, at/after expiry, or once revoked. Panel access is always an explicit grant. |
| `foundation/hosting/PlanChange.php` | Queued `upgrade`/`downgrade`/`renewal` with requester/effective-at. `approve()` is a *pre-dispatch* decision: it enforces the effective time and, for downgrades, requires an observed reading for every metered target limit that provably fits — a partial observation cannot prove the unmeasured quotas fit, so it is rejected outright, and refusal happens before any provider call. `dispatch()` mints an immutable attempt identity and closes cancellation — once a provider call may be in flight the requester can no longer withdraw. `recordResult()` accepts only a result bound to the expected operation, account and dispatched attempt, so a receipt from an earlier change can never satisfy a later one. Timeout → `uncertain`, closed only by `resolve()` with reconciliation evidence that names the exact dispatched attempt — a replayed callback for an earlier attempt is refused before any mutation, so it can never clear a newer in-flight dispatch. An unapplied resolution returns to `approved` and discards the stale attempt so re-dispatch mints a fresh identity. |
| `foundation/hosting/HostingAccount.php` | The lifecycle state machine (below) with attributed history and bound, recorded operation results. |

## Lifecycle

```
pending → provisioning → active ⇄ suspending ⇄ suspended ⇄ resuming
pending/provisioning → failed → retry() → pending
active/suspended → cancellation_requested → deprovisioning → deprovisioned
deprovisioning → deprovision_failed → retryDeprovision() → deprovisioning
pending/failed → cancelled            (pre-provisioning withdrawal only)
```

Protections built into the state machine:

- **Bound results** — a provider result resolves only the operation it
  names (`hosting.provision`/`suspend`/`resume`/`deprovision`), for the
  account it names (`subjectRef`), under the exact `attemptRef` minted
  when that dispatch began. Each `beginProvisioning`/`suspend`/
  `beginResume`/`approveDeprovision` — and each reconciled deprovision
  re-attempt — mints a fresh `acct#n` identity, so a stale receipt from
  an earlier attempt of the same operation is refused. A mismatched
  result throws without mutating status, history or the operations log;
  `providerRef` is set only from a reported resource id, never an
  operation receipt.
- **Identity required to activate** — an applied provisioning result
  that reports no `resourceRef` cannot activate the account; it is held
  `awaitingReconciliation()` until `reconcileTimeout` supplies the
  provider resource id (the same adoption path as a timeout).
- **Intent vs confirmed** — `suspend()`/`beginResume()` enter
  `suspending`/`resuming`; `suspended`/`active` are only reached through
  an applied provider result.
- **Unresolved timeouts** — a `timeout` result records
  `awaitingReconciliation()`. While unresolved the account cannot be
  order-cancelled ("never provisioned" cannot be claimed while the remote
  create may have landed), cannot be retried, and accepts no further
  result — a delayed response for the timed-out attempt can never
  rewrite later or terminal state; only `reconcileTimeout()` with
  evidence closes it, and the callback must name the exact unresolved
  `acct#n` attempt — a replayed reconcile for an earlier attempt is
  refused before any mutation, so it can never clear a newer dispatch
  still in flight. Reconciliation either adopts a confirmed remote
  resource (`resourceRef` required — adopted, never recreated) or
  confirms nothing landed so retry is safe.
- **Failed deprovision is not an armed request** — a `failed` deprovision
  result lands in `deprovision_failed` with **no** attempt in flight.
  Only the explicit `retryDeprovision()` decision arms a fresh `acct#n`
  dispatch, and only when the provider marked the failure retryable —
  a `retryable:false` outcome can never re-dispatch the destructive call.
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

`tests/hosting/run.php` — 168 synthetic checks covering package
validation (typed/bounded observations, absent-vs-unmetered,
caller-reference detachment), bound operation results, grant
issuance/expiry/revocation boundaries, queued plan changes
(pre-dispatch downgrade refusal with complete-usage requirement,
early-dispatch refusal, dispatch/attempt binding, in-flight
cancellation refusal, stale-receipt rejection across changes, misbound
results, attempt-bound uncertainty resolution with replayed-callback
refusal), the full account lifecycle with intent/confirmed split,
attempt-bound results and reconciliations (replayed reconcile for an
earlier attempt refused), unresolved-timeout reconciliation and
adoption, identity-required activation, late-result refusal while
unresolved, grace/dispute protections, non-empty two-actor
decommission, failed-deprovision holding with guarded
`retryDeprovision()` (retryable and non-retryable cases), and
attributed history. Fixtures only; runs clean under `php -n`. CI:
`.github/workflows/hosting.yml` on official PHP 8.2/8.4 with
`--network none`.
