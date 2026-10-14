# Provider operation ledger and owner grants

Durable substrate the provider write path requires before anything may
execute against a real provider. This layer contains **no credential
resolution, request construction, transport or provider call** — it is
the at-most-once ledger plus the owner approval records an execution
path must satisfy first.

## Ledger (`ProviderOperationLedger` → `provider_operations`)

Implements `CajunWebPro\Foundation\Provider\OperationLedger`, the
contract `ProviderGateway::perform()` claims through:

- `claim(key, fingerprint, now)` performs one atomic `INSERT`. The
  unique `idempotency_key` primary key makes concurrent claims safe:
  exactly one caller wins `claimed`.
- A duplicate key with a **different** fingerprint → `conflict` (the
  key is already bound to another request).
- A duplicate with a **resolved** row → `replay` of the recorded
  `OperationResult` fields (status, message, provider code, external
  id, attempts).
- A duplicate still `claimed` inside `RESERVATION_TTL` (120 s) →
  `in_progress`.
- A `claimed` row older than the reservation TTL → `uncertain`: the
  owner of the claim crashed between claim and record, so the remote
  effect is unknown and must be reconciled — never silently retried.
- `record()` updates only a still-`claimed` row with the matching
  fingerprint; anything else throws, which the gateway records as
  `uncertain`.

Only bounded classification fields persist — status, short message,
provider code, external id, attempts, timestamps. No request body,
Authorization material, or raw transport text can reach this table.

## Owner grants (`provider_operation_grants`)

An execution path must verify an active grant before claiming:

- `provider_operation_grant(profile, operation, scopeParams, password,
  ttl)` — owner-password action under the staff lock; requires the
  profile be enabled; records a grant bound to the canonical scope
  fingerprint (installation-keyed HMAC of operation + recursively-
  sorted params, invalid UTF-8 map keys refused) AND the approved
  profile revision, with an expiry of 60 s–24 h and an audit event.
- `provider_operation_grant_active(profile, operation, scopeFp)` —
  finds the newest grant for that exact scope directly (indexed query,
  not a bounded scan); true only when it is unrevoked, unexpired, and
  the profile is still enabled at the SAME revision approved at grant
  time — any settings save, credential replacement or disable/enable
  cycle changes the revision and suspends the grant until re-approved.
- `provider_operation_revoke(grantId, password)` — durable revocation;
  unknown or already-revoked grants refuse.

A re-grant after expiry supersedes; revocation always wins. Grants are
evidence of approval, not authority to skip the ledger — execution
still claims through `ProviderGateway` first.

## Schema and migration

`provider_operations_schema()` creates both tables under strict
validation (exact columns, indexes, FKs, collations); existing foreign
data is never adopted. `provider_operations_migrate()` is the
owner-password migration recording `provider_operations_schema_version
= 1`; fresh installs create the tables and flag via `deploy.php`.

## Verification

`tests/provider_operations_native.php` — native MariaDB suite covering
schema validation/refusals, claim/in-progress/conflict/stale-uncertain/
replay semantics, record-after-resolve refusal, gateway at-most-once
behavior through a synthetic adapter (replay does not re-execute;
unsupported operations never reach the ledger), a real cross-process
claim race via a worker fixture, grant issue/verify/expiry/revoke/audit,
and a durable-storage secret scan. No provider call is made.

## Execution (`provider_operations_execute`)

One owner-approved provider write, wired end-to-end on
`devin/twentyi-execution`. A grant is the complete approved request —
it stores the canonical JSON of the approved parameters alongside the
scope fingerprint, and is consumed atomically when executed
(single-use, `consumed_key`/`consumed_at`):

1. Under the staff lock: owner password + current profile revision via
   `provider_checks_authority`, storage ready, then the grant row —
   matching profile, unexpired, unrevoked, unconsumed, stored scope
   reconstructing its fingerprint, operation shape-complete — checked
   and consumed in one transaction, all before any credential is opened
   or an address is resolved.
2. Shape-incomplete or unregistered operations cannot be granted at
   all; a missing/expired/revoked/consumed grant refuses
   `failed/no_grant` and the refusal is audited. No ledger row is
   written for a refusal.
3. The endpoint address resolves once through `EndpointPolicy` outside
   the lock; a resolution failure returns `failed/unreachable`.
4. `provider_operations_perform` builds the credential with a resolver
   that re-checks owner authority at attach time, then runs
   `ProviderGateway` over the durable ledger and
   `EndpointPolicy`-bounded `FixedHttpTransport`. The gateway claims
   the idempotency key before any send; replay/conflict/in-progress/
   stale-uncertain semantics are the ledger's, and a failed durable
   record yields `uncertain`.
5. `TwentyIOperationAdapter` builds the closed bounded request and
   attaches `Authorization` only at send; `TwentyIResultClassifier`
   maps outcomes conservatively (2xx succeeded, ordinary 4xx failed,
   3xx/429/5xx uncertain, provably pre-send transport failures failed,
   anything else uncertain).
6. Owner access is re-verified before and after the call. Access that
   changed around a possibly-landed write returns `uncertain` for
   reconciliation — never a false clean verdict.

Callers supply the idempotency key; there is no client-facing route.
Live sends additionally require operation-specific acceptance —
execution capability is not business authorization.

`tests/provider_operations_execute_native.php` exercises the full path
on real MariaDB with a scripted transport: authority gates, grant
scope/expiry/revocation, exactly-once send, replay and conflict without
re-sending, uncertain propagation, mid-call access change, and a
durable-storage secret scan.

## Admin surface (`provider-operations`)

The Studio "20i operations" page (`staff.manage` view; every action
re-enters the owner password) offers: storage setup, approving one
exact documented request (operation + bounded JSON scope + 5 min–24 h
expiry), listing approvals with their state (active / used / expired /
revoked), running an approval with a caller-chosen idempotency key, and
revoking. The operation ledger history — key, operation, status,
attempts, timestamps — is shown for reconciliation; `uncertain` rows
carry the reconcile-before-retrying notice. Granting a missing scope
field is refused by the register's required-field rules, and the stored
scope must reconstruct its fingerprint before execution.

## Reconciliation (`provider_operations_reconcile`)

An `uncertain` ledger row is never silently retried; reconciliation
answers "did the approved effect land?" through the one evidence-backed
read (`GET /package` via `InventoryReader`). The owner re-enters their
password and the exact profile revision; the row must exist as
`uncertain` and be backed by the consumed grant's stored scope.
Outcomes are honest and durable:

- `observed` — the approved effect is visible in a fresh observation
  (created domain present, or delete-id package absent).
- `not_observed` — the effect is not visible; the request may not have
  landed. The owner verifies in the 20i panel before any new request.
- `inconclusive` — the operation's effect is not observable from the
  package list (e.g. label/limit updates); no read is sent.
- `unavailable` — no usable observation (credential, transport,
  access-change or refused-read); the row stays uncertain.
- `refused` — the row is not uncertain, the key is unknown, the
  approved scope record is missing/mismatched, or the operation has no
  observation check. Audited as `provider.operation_reconcile_refused`.

The outcome is stored on the ledger row (`reconciled_outcome` /
`reconciled_epoch`, schema v4) and audited
(`provider.operation_reconcile`); the recorded provider result is
immutable. Owner access is re-verified at the send boundary and after
the read — a mid-observation access change yields `unavailable`.
