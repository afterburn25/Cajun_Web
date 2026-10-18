# 20i Inventory Reader Contract

`foundation/twentyi-inventory-reader/InventoryReader.php` is the callable
authenticated read that connects the validated inventory descriptors and
projector (`foundation/twentyi-inventory/`) to the credential resolver and
an injected `HttpTransport`. It is the component a protected refresh
orchestrator calls; it owns no persistence, routing or session state.

## Guarantees

- **One read = one request.** Each call resolves the secret once and issues
  exactly one bounded `send()`. No retries, paging or fan-out.
- **Refusal before contact.** Wrong provider, a non-executable operation, an
  unusable account identity or an unresolvable/invalid secret throw
  `InvalidArgumentException` before any transport call — refused reads
  produce no observation.
- **Implementation and live acceptance are separate.** `packages.list` and
  `cloudservers.list` have bounded implemented projections. Only the package
  read has prior authenticated account evidence. Application authorization
  remains mandatory for each explicit read; descriptors alone grant nothing.
  Other unsupported/sensitive reads still require their own implementation.
- **Exact scope binding.** The observation scope is derived inside the
  boundary as `twenty-i|<accountIdentity>|<operation>` — a caller cannot
  mislabel which configured account or operation produced an observation.
- **Secret boundary.** The raw key is resolved once, attached as
  `Authorization: Bearer base64(<key>)` per the probe convention, unset
  before the snapshot is built, and never retained on the component,
  snapshot or any exception. Descriptors carry no credentials.
- **Honest failures.** `TransportException` maps to the finite
  `transport_*` reasons; any other transport throw maps to
  `transport_unknown`; non-2xx statuses, oversized/malformed bodies and
  wrong shapes map to the projector's closed reason set. Raw exception
  text, request headers and response bodies never escape.

## What it is not

- Not a route, controller or scheduler — callers supply transport,
  credential, operation and observation time.
- Not persistence — the returned `InventorySnapshot` is caller-owned.
- Not authorization — the caller must re-check the current authority,
  provider configuration revision and account binding before and after
  the read; this component only enforces that the descriptor, credential
  and scope it receives are internally consistent.
- Not account-wide coverage — `coverage` stays `unknown`; an incomplete
  or unavailable observation never proves deletion, cancellation or
  entitlement loss.

## Verification

`tests/twentyi-inventory-reader/run.php` exercises refusals without
transport calls, single-request behavior, header shape, scope binding,
secret exclusion, and every failure classification over a scripted
`HttpTransport`. Synthetic fixtures only — no live 20i call is made.
