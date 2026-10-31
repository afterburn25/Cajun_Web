# VPS lifecycle foundation

This is an isolated PHP contract component. It has no provider adapter, persistence, catalog UI, staff authentication, deployment wiring or live provisioning. The original PR45 foundation remains preserved at `8dcce53f8998915992a36475cb68f9a315a2b0c7`; this companion corrects how that model represents uncertain outcomes before any adapter integration.

The other three classes are unchanged: `VpsSpec` validates caller-supplied resource/region/image values; `VpsOffering` requires a configured price to be orderable; `QuotaUsage` distinguishes unknown entitlement from zero. Their fixture values are synthetic, not real 20i offerings.

## Lifecycle and uncertainty

Every provisioning, suspension, resume or approved decommission admission creates a random local attempt ID and an immutable attempt record containing ordinal, operation, provider reference, prior stable state, intended state and supplied actor. `currentAttempt()` exposes the unresolved attempt. `attempts()` retains all admissions, including those later confirmed, not applied or left uncertain.

The unconfirmed-failure methods `fail`, `suspendFailed`, `resumeFailed` and `decommissionFailed` enter `UNCERTAIN`. They retain the original provider reference and unresolved attempt. No retry, cancellation, new admission, teardown withdrawal or evidence-free completion can escape this state. A missing response, timeout, current state that has not changed, absent resource or ordinary HTTP status cannot establish failure or make a retry safe.

`reconcile()` consumes terminal operation evidence bound to the exact current attempt. A confirmed applied outcome leads to the admitted target state. A confirmed not-applied provisioning outcome leads to `FAILED`; only then can a caller deliberately invoke `retry()` or `cancel()`. Retry preserves the old reference and history; the next provisioning receives a new attempt ID and may bind a replacement reference. For other operations, not-applied evidence must also establish the original stable state remains valid before returning there. A not-applied teardown clears its request only after reconciliation, so another teardown requires a new request and a distinct supplied approver identifier.

A completed or stale evidence witness cannot be replayed or rebound to a later attempt. Both its local evidence ID and digest are single-use within this model instance. Every history entry keeps the previous fields (`status`, `at`, `actor`, `note`) and adds `provider_ref`, the exact unresolved `attempt` when applicable, and the consumed `evidence` on resolution. The terminal event retains its attempt even though `currentAttempt()` becomes null. Returned arrays are copies, including detached scalar evidence values when the caller originally passed reference-valued fields.

## Reconciliation witness and trust boundary

The input is an array with exactly these string fields:

| Field | Meaning |
| --- | --- |
| `server_id`, `client_ref` | Exact local order and client identities |
| `attempt_id` | Random ID of this object's current unresolved attempt |
| `provider_ref` | Exact reference recorded at admission |
| `operation` | `provision`, `suspend`, `resume` or `decommission`, matching admission |
| `outcome` | Terminal `applied` or terminal `not_applied` |
| `resulting_state` | Exact state established by the evidence and allowed by the transition below |
| `evidence_id` | Local retained evidence identifier, 32 lowercase hexadecimal characters |
| `evidence_sha256` | Digest of the future adapter's retained evidence, 64 lowercase hexadecimal characters |

| Operation | Applied resulting state | Not-applied resulting state |
| --- | --- | --- |
| Provision | `active` | `failed` (no provisioned VPS) |
| Suspend | `suspended` | `active` |
| Resume | `active` | `suspended` |
| Decommission | `terminated` | Its admitted prior `active` or `suspended` state |

The adapter must authenticate and retain evidence of a terminal outcome, establish that the attempt cannot still take effect, verify the resulting state, and verify exact account/resource/attempt correlation **before** calling this API. The component checks local binding and shape only. An arbitrary hash or caller-supplied `not_applied` string is not proof that a provider operation failed. There is no implemented provider response parser or evidence verifier here; the schema is a local component contract, not a claim about 20i response fields.

Actor identifiers remain caller-supplied strings. The different-identifier teardown rule is preserved, but the future adapter must authenticate canonical, distinct accounts and their permissions. The pure class does not authenticate either person. Callers must also supply safe classified notes rather than raw provider errors or credentials.

Attempt and evidence uniqueness are per object, not a durable global lock, signature or restart/replay guarantee. A future persistence adapter must atomically retain state, all attempts and consumed evidence; serialize concurrent access; and enforce authorization and provider idempotency at its own boundary. Do not wire this model to real operations until those contracts and the actual provider schemas are verified.

## Public API compatibility

Read methods `status`, `providerRef`, `history` and `terminal` remain. `providerRef` now means the last admitted reference and no longer becomes null on retry. History preserves its original keys and adds the evidence/attempt fields above. `attempts` and `currentAttempt` are new read methods.

Admission methods and the two supplied-actor teardown flow retain their signatures. `fail` now accepts only an admitted provisioning attempt; a pending order without a remote attempt cannot invent a failed attempt. All four failure methods now mean uncertain, never known failure or stable-state rollback.

`reconcile(array $evidence)` is new. Existing completion methods retain their names but require the same validated applied evidence: `markActive(string $note = ..., ?array $evidence = null)`, `markSuspended(?array $evidence = null)` and `markTerminated(?array $evidence = null)`. Omitting evidence throws without mutation. In uncertain state, use explicit `reconcile`; completion wrappers remain limited to their original in-flight phases. This is an intentional fail-closed API change. The exact PR45 source has no callers outside `tests/vps/run.php`, and those callers are updated in this companion.

## Verification and integration limits

Run `php tests/vps/run.php` or `php -n tests/vps/run.php`. An optional first argument writes a JSON report of actual named outcomes, for example `php tests/vps/run.php /existing/output/vps-results.json`. The report parent directory must already exist. A reporting failure exits nonzero; ordinary stdout and pass/fail exit behavior remain compatible.

The suite keeps the original non-lifecycle assertions and all applicable lifecycle checks. Five unsafe expectations change explicitly: unconfirmed provisioning is no longer `failed`; retry no longer clears the reference; and unconfirmed suspension, resume and decommission no longer roll back to a stable state. Additional actual class tests cover every uncertainty fence, both terminal outcomes, all exact witness bindings, stale/reused/malformed evidence, unchanged state after refusal, retained attempt history, two-actor retry, and PHP array-reference detachment.

All tests use synthetic adapter witnesses. Passing them does not prove remote state, provider response semantics, native database behavior or a working VPS service. The component remains outside `site/` and the deployment foundation inventory. The companion workflow is prepared to run normal and plain `-n` checks on PHP 8.2/8.4 and retain their named result artifacts; exact-source GitHub execution remains pending. No adapter, schema or shipping change is part of this companion.
