# 20i hosting inventory contract — `foundation/twentyi-inventory`

A read-only projection of provider hosting inventory. This component is a
**contract and parser**, not an integration: nothing here sends a request,
stores a record, links a client, or activates a route.

## Provenance

| Evidence | Source | Scope |
|---|---|---|
| Owner upload `Pasted text(20261014-030432).txt` (SHA-256 `f7c738e7…9ec4d`) | [TWENTYI_OWNER_API_REFERENCE.md](TWENTYI_OWNER_API_REFERENCE.md) | Endpoint discovery; examples, not complete schemas |
| `GET /package` list-item shape | <https://docs.20i.com/api/retrieve-packages-api> (20i Support Database, published 2020-02-18, fetched 2026-10-14) | Documented response fields for the package list only |
| One authenticated `GET /package` read | [issue #65](https://github.com/afterburn25/Cajun_Web/issues/65) | Verifies that read for that account at that time only |
| Full endpoint/field schemas | 20i Apiary console (My20i → Manage Reseller → API), login-gated | Not publicly retrievable; treated as unknown |

Known limits: no pagination contract is documented for `/package` — the
projector assumes the body is the full array; a documented pageable shape
would need revisiting. `packageTypes`, `accountBalance`, and
`/package/{id}` have no publicly shown response schema, so they are
registered but **not projectable**.

## Closed read set

`InventoryRead` emits `TransportRequest` descriptors for exactly four GET
operations on the fixed host `api.20i.com`, 15 s deadline, no body, and
**no Authorization header** — the caller's credential layer attaches the
key, so this component never touches a secret. Allocated IDs are positive
integers (digit-strings normalized); the reseller slot also accepts the
documented `*` self alias. HTTP GET is not treated as proof of a safe
read — every entry's documented purpose is retrieval only.

| Operation | Path | Risk | Evidence |
|---|---|---|---|
| `packages.list` | `GET /package` | read | authenticated-read |
| `packages.detail` | `GET /package/{id}` | sensitive-read | documented |
| `package.types` | `GET /reseller/{id}/packageTypes` | read | documented |
| `account.balance` | `GET /reseller/{id}/accountBalance` | read | documented |

Mutating, credential-returning, DNS/mail-write, power, restore and
transfer endpoints are deliberately absent — not merely unimplemented.

## Projection semantics

- `InventoryPackage` keeps only documented fields: `id`, `created`
  (+epoch), `enabled`, `name`, `names`, `packageTypeName`, `stackUsers`,
  `typeRef`, `packageLabels`; `productSpec` is noted as present/absent
  and dropped. Unknown keys are reported **by name only** in
  `unmappedKeys`; values are never retained. StackUsers stay opaque
  `stack-user:N` references — provider identity, never a client link.
- `InventorySnapshot`: `complete` (all items projected), `partial`
  (some items skipped, reasons named), or `unavailable` (kind only —
  `authentication`, `rate_limited`, `remote_error`, `unexpected_status`,
  `response_limit`, `malformed_body`, `unexpected_shape`,
  `transport_*`). An unavailable observation never looks like an empty
  inventory.
- Duplicate package IDs keep the first entry and mark the snapshot
  partial — a duplicated identity means the response is not trusted.

## Deliberately out of scope

Persistence, scheduling, workers, routes, client ownership inference,
live calls, and every write operation. Client/service linking is an
explicit owner action in the application layer, assigned to Codex.
