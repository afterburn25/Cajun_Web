# Hosting provider foundation

Started 2026-10-13 as an independent Devin increment under the dependency-gate policy ([DEPENDENCY_GATES.md](DEPENDENCY_GATES.md)): HC-07's external gate (`Awaiting API verification`) does not block the implementable portion — the modular adapter contract, credential boundary, connection-test contract and idempotency/reconciliation model. Branch: `devin/provider-foundation`. Scope: the provider contract core only — no admin UI, no persistence, no real 20i calls.

## Implemented component

The standalone PHP 8.2+ classes in [foundation/provider](../foundation/provider) need no framework, database, network access or Composer installation. Keep this directory outside deployment packages until the adapter contract is agreed and the release inventory is updated.

- [ProviderAdapter](../foundation/provider/ProviderAdapter.php) — the provider contract: `id()`/`label()`, a declared `capabilities()` matrix, `supports()`, a non-mutating `probe()` for connection checks, and `execute()` for operations.
- [ProviderCapability](../foundation/provider/ProviderCapability.php) — one operation's support state: `supported`, `unsupported`, or `unverified`. HC-07 requires that unverified endpoints are never treated as supported.
- [ProviderCredential](../foundation/provider/ProviderCredential.php) — account identity plus a lazily-resolved secret. The resolver belongs to the caller (the site adapter decrypts with the installation private key, matching the SMTP credential convention); the object never stores, logs or serializes the secret. `masked()` exposes at most the last four characters.
- [ProviderOperation](../foundation/provider/ProviderOperation.php) — a dotted-lowercase operation name, caller-supplied params, and a caller-generated idempotency key.
- [OperationResult](../foundation/provider/OperationResult.php) — durable outcome: `succeeded` / `failed` / `retryable` / `unsupported`, provider code, external ID, attempt count, replay flag.
- [ConnectionCheck](../foundation/provider/ConnectionCheck.php) — classified probe outcome: `verified` / `authentication_failed` / `unreachable` / `unsupported`. VERIFIED means credentials authenticated; it does not certify any operation.
- [HttpTransport](../foundation/provider/HttpTransport.php), [TransportRequest](../foundation/provider/TransportRequest.php), [TransportResponse](../foundation/provider/TransportResponse.php), [TransportException](../foundation/provider/TransportException.php) — the outbound HTTP boundary. Requests enforce https-only URLs with no embedded credentials; HTTP error statuses are returned while connection-level failures throw.
- [OperationLedger](../foundation/provider/OperationLedger.php) — durable idempotency store (interface; the site adapter implements it over the database with a unique key column). First record for a key wins.
- [ProviderGateway](../foundation/provider/ProviderGateway.php) — the enforcement point callers use: refuses unsupported/unverified operations before any transport call, replays recorded results for known idempotency keys, retries retryable outcomes within a bounded [RetryPolicy](../foundation/provider/RetryPolicy.php), then records the final result.
- [ExternalResource](../foundation/provider/ExternalResource.php) — local↔provider reconciliation record. `link()` records a *claim*; `verify()` confirms only when the provider-reported ID matches, recording a `mismatch` otherwise. Ownership is never inferred from email or domain matches.

## What this component deliberately does not do

- No 20i endpoint URLs, request schemas, or response shapes — those are adapter-time decisions that require inspecting the logged-in My20i API documentation and a test account (external gate: `Awaiting API verification`).
- No credential persistence or encryption — the secret resolver is injected; storage with the installation private key is the site adapter's job.
- No admin settings screens, no schema, no queue — foundation contract only.
- No real provider calls are possible from this component alone; it has no bundled transport implementation.

## Contract semantics

- `ProviderGateway::perform()` is the only execution path. It checks `supports()` first — unverified and unsupported operations return an `UNSUPPORTED` result with zero transport calls and are not recorded.
- A recorded idempotency key replays the stored result (marked `replayed`) without re-executing — including recorded `retryable` failures, so a duplicate submission never silently re-runs a provider write.
- `retryable` outcomes (429/5xx responses or `TransportException`) retry up to `RetryPolicy::maxAttempts` (bounded 1–5, capped backoff) inside the request, then the exhausted result is recorded.
- `probe()` must never mutate provider state; the gateway maps `TransportException` to `unreachable`.

## Integration work for the adapter phase

1. Site adapter: encrypted credential storage (installation private key, per SMTP), `OperationLedger` over an additive table with a unique idempotency-key column, cURL `HttpTransport` with verified TLS, and the concrete 20i adapter once real endpoints/entitlements are verified.
2. Admin → Settings → Providers screens: masked credential entry, connection check, capability display — all owner-only.
3. `build.py` gains `'provider'` in the foundation bundling list and the inventory regenerates; post-refactor destination is `site/includes/foundation/provider/` (`.htaccess` denial + `CWP_BOOT` guards already in place on each file).

## Verification

Run `php tests/provider/run.php`. The dependency-free [test runner](../tests/provider/run.php) uses a conspicuously-marked fixture adapter (`FixtureTwentyIAdapter`, fixture key `fx-key`, `fixture.invalid` endpoints) and scripted transport — **no real 20i responses are used or claimed**. 69 named checks cover credential masking, request validation, the support matrix, idempotent replay, retry exhaustion, probe classification and resource reconciliation. [GitHub provider workflow](../.github/workflows/provider.yml) runs syntax + the suite in official PHP 8.2 and 8.4 CLI containers with no network and no secrets.

Local PHP 8.4.25: 69 checks pass; all 14 PHP files pass `php -l`. These checks do not establish admin UI, persistence, real provider connectivity or deployment — those remain gated on the adapter phase and API verification.
