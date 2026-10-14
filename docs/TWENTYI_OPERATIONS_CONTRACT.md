# 20i write-boundary contract — `foundation/twentyi-operations`

Scope: the documented **package-lifecycle write surface** from
`docs/TWENTYI_OWNER_API_REFERENCE.md` (§1, owner upload hash
`f7c738e746c2aebb9e1d047a555f3874a0766255795ed80edeebe8e599b9ec4d`),
represented as closed descriptors plus a request builder and outcome
classifier. This component is **descriptive, not executable**: it
constructs and classifies bounded requests. It never resolves a
credential, attaches `Authorization`, opens a socket, or records a
result. The live adapter (`foundation/twentyi/TwentyIAdapter`) still
returns `supports() === false` for every operation; nothing is wired.

## Register (`TwentyIOperationRegister`)

Four documented operations, literal method/path from the reference:

| Operation | Endpoint | Shape | Risk flags |
|---|---|---|---|
| `packages.create` | `POST /reseller/{resellerId}/addWeb` | complete (7 fields) | billing, welcome email via `stackUser` |
| `packages.update` | `POST /reseller/{resellerId}/updatePackage` | complete (7 fields incl. `delete-id`) | destructive, billing |
| `packages.userstatus` | `POST /package/{packageId}/userStatus` | **incomplete** — body fields not shown | refused by builder |
| `packages.delete` | `POST /reseller/{resellerId}/deleteWeb` | **incomplete** — body fields not shown | destructive; refused by builder |

`shapeComplete` separates "endpoint named" from "request buildable".
The builder throws `InvalidArgumentException` for shape-incomplete
operations rather than inventing fields. `exclusions()` names the
write surfaces deliberately not catalogued — StackCP users/grants,
domain register/transfer/renew/nameservers/DNS, VPS create/renew/
power/VNC, SSH access, email, direct billing — each with its refusal
reason.

## Request builder (`TwentyIRequestBuilder`)

`build(ProviderOperation) → TransportRequest`, all-or-nothing:

- operation must be registered **and** shape-complete;
- `resellerId` slot accepts a positive allocated id or the documented
  `*` self alias; `packageId` accepts a positive integer only — nothing
  else reaches the URL path;
- body keys are exactly the documented allowlist; unknown keys,
  missing required fields, wrong types, control bytes, oversized
  strings (>1024), lists >64, map/json beyond depth 8 / 256 nodes counting every visited value including scalar leaves all
  refuse before a request object exists;
- serialized body is canonical JSON bounded to 8 KiB;
- `Content-Type: application/json`, `Accept: application/json`,
  30 s timeout, fixed host `https://api.20i.com`;
- **no `Authorization` header ever** — credential attachment is an
  execution-boundary concern for a future gateway path, not this layer.

## Result classification (`TwentyIResultClassifier`)

Conservative write semantics; a request that may have reached the
provider is never classified as a clean failure:

| Outcome | Classification |
|---|---|
| 200/201/204 | `succeeded` — status is authoritative even if the body is unreadable |
| other 2xx, incl. 202 | `uncertain` — accepted/queued is not a completed write; reconcile before retrying |
| 401/403 | `failed` `authentication` (bounded message, no body echo) |
| other 4xx | `failed` `http_{status}` — provably rejected |
| 3xx, 429, 5xx | `uncertain` — received; reconcile before any retry |
| connect/TLS/unavailable transport | `failed` — provably pre-send |
| timeout/protocol/unknown transport | `uncertain` — may have landed |

`externalId` is populated only from a caller-named documented id field;
the current descriptors document none, so it stays `null` rather than
guessing a response schema.

## Execution gate

`TwentyIOperationAdapter` (`foundation/twentyi-operations/`) implements
`ProviderAdapter` over this catalog: `supports()` is true only for
shape-complete operations; `execute()` builds the bounded request,
resolves the credential and attaches `Authorization` at this boundary
only, sends once through the injected transport, and classifies with
`TwentyIResultClassifier` — ambiguous post-send outcomes are
`uncertain`, never silently retried. `packages.update` is the only
operation reported idempotent (re-sending the same body is
state-setting); `packages.create` gets exactly one attempt.

Constructing and sending a request is still not authorization to run
it. `provider_operations_execute()` in `site/app/providers/operations.php`
requires, in order: owner password + current profile revision under the
staff lock, a shape-complete registered operation, and an active
unexpired grant for that exact operation+scope — all before any
credential is opened or address resolved. Execution then runs through
`ProviderGateway` over the durable `ProviderOperationLedger`
(at-most-once claim, replay/conflict/uncertain semantics) and the
`EndpointPolicy`-bounded `FixedHttpTransport`. Owner access is
re-verified at credential attach time and after the call; access that
changed around a possibly-landed write reports `uncertain` for
reconciliation rather than a false clean verdict.

Idempotency keys come from the caller; no client route can reach the
executor — it is an application-layer function that itself demands
staff-owner authority. Live provider operation remains gated on
operation-specific acceptance; this component changes what is
*buildable and authorized*, not what the business has approved sending.

## Verification

`tests/twentyi-operations/run.php` — 81 named synthetic checks:
catalog closure and flags, refusal of unregistered and shape-incomplete
operations, slot injection/traversal refusal, field allowlist and
required-field enforcement, bounded values, header/body shape of a
valid build, and the full status classification table. No HTTP
transport, credential, database, or provider call.

`tests/provider_operations_execute_native.php` — native MariaDB suite
over the real gateway + durable ledger + adapter with a scripted
transport only: owner/administrator/stale-revision refusal, grant
missing/changed-scope/revoked refusal, unsupported and shape-incomplete
refusal before any claim, a granted create executing exactly once with
the documented path, allowlisted body and send-time `Authorization`,
key replay and foreign-fingerprint conflict without re-sending,
5xx/timeout → `uncertain` persisted and replayed, pre-send connect →
`failed`, mid-send access change → `uncertain` while the durable row
keeps the provider-truthful result, and a durable-storage secret scan.
No provider call is made.

## Reconciliation

`uncertain` results reconcile through
`provider_operations_reconcile` — an owner-password, revision-bound,
single evidence-backed observation of the package list (the only read
with authenticated evidence). `packages.create` reconciles on the
approved `domain_name` appearing in observed package names;
`packages.update` with `delete-id` reconciles on the target's absence;
other field-only updates are `inconclusive` with no read sent. The
outcome is recorded on the ledger row (`reconciled_outcome`) without
rewriting the provider-truthful result, and audited. A reconcile never
re-sends the request.
