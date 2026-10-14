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
  strings (>1024), lists >64, map/json beyond depth 8 / 256 nodes all
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
| 2xx | `succeeded` — status is authoritative even if the body is unreadable |
| 401/403 | `failed` `authentication` (bounded message, no body echo) |
| other 4xx | `failed` `http_{status}` — provably rejected |
| 3xx, 429, 5xx | `uncertain` — received; reconcile before any retry |
| connect/TLS/unavailable transport | `failed` — provably pre-send |
| timeout/protocol/unknown transport | `uncertain` — may have landed |

`externalId` is populated only from a caller-named documented id field;
the current descriptors document none, so it stays `null` rather than
guessing a response schema.

## Execution gate (not implemented here)

Constructing a request is not authorization to send it. A future live
path additionally requires: `ProviderGateway` + a durable
`OperationLedger` claim (at-most-once, replay/uncertain semantics),
owner-granted approval records per operation instance, reauthorization
at execution, idempotency keys from the caller, and operation-specific
acceptance for billing/destructive/email effects. None of that exists
yet; the descriptor set is what review can approve or reject.

## Verification

`tests/twentyi-operations/run.php` — 81 named synthetic checks:
catalog closure and flags, refusal of unregistered and shape-incomplete
operations, slot injection/traversal refusal, field allowlist and
required-field enforcement, bounded values, header/body shape of a
valid build, the full status classification table, and a text guard
that the adapter still refuses every operation. No HTTP transport,
credential, database, or provider call.
