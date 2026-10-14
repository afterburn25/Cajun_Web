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
