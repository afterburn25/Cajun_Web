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

`InventoryRead` emits `TransportRequest` descriptors for exactly eight GET
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
| `vps.list` | `GET /vps` | read | documented |
| `managedvps.list` | `GET /managed_vps` | read | documented |
| `domains.list` | `GET /domain` | read | documented |
| `reseller.servicechanges` | `GET /reseller/{id}/serviceChangeData` | sensitive-read | documented |

Mutating, credential-returning, DNS/mail-write, power, restore and
transfer endpoints are deliberately absent — not merely unimplemented.
`InventoryRegister::exclusions()` enumerates the boundary in code:
`vps.vnc` (credential-bearing), `vps.stop`/`vps.start` (GET-labelled
mutations), `stackusers.grants` and `packages.stackUserList`
(access-control identity detail) and `domains.pendingTransferStatus`
(possible transfer-authorization detail, schema unverified). An
exclusion is a refusal: none may join the read set without fresh
review and verified schema.
`GET /domain`'s list-versus-summary shape is unresolved by the owner
reference; it is registered for identity projection only and a missing
`id` key there is an honest partial/empty observation, not a schema
claim.

## Projection semantics

- `InventoryPackage` keeps only documented fields: `id`, `created`
  (+epoch), `enabled`, `name`, `names`, `packageTypeName`, `stackUsers`,
  `typeRef`, `packageLabels`; `productSpec` is noted as present/absent
  and dropped. Unknown keys are reported **by name only** in
  `unmappedKeys`; values are never retained. StackUsers stay opaque
  `stack-user:N` references — provider identity, never a client link.
- `InventorySnapshot`: `complete` (all *returned* items projected),
  `partial` (some items skipped, reasons named), or `unavailable`
  (closed reason set — `authentication`, `rate_limited`,
  `remote_error`, `unexpected_status`, `response_limit`,
  `malformed_body`, `unexpected_shape`, `transport_*`). An unavailable
  observation never looks like an empty inventory.
- **Coverage is never claimed**: `coverage` is always `unknown` — no
  pagination/completeness contract is documented, so a fully projected
  response is not proof of account-wide inventory. `scope` records the
  caller-supplied provider account/operation identity so stored
  observations cannot be confused between accounts.
- JSON decode preserves object/list identity: `{ }` and
  `{"0": <item>}` are `unexpected_shape`, never an empty or populated
  inventory; list items must be JSON objects.
- The reported collection is preserved exactly — a documented `name`
  absent from `names` is an inconsistency skip, not a unioned guess.
  `created` must be a real calendar instant (impossible dates are
  rejected, not normalized). Unmapped keys are reported only when
  printable UTF-8 and truncate at character boundaries;
  `unmappedCount` records the total so unprintable names still surface
  as drift.
- The snapshot constructor validates contents: records must be
  projected package records, counts/timestamps non-negative, reasons
  bounded printable, states consistent, and unavailable reasons drawn
  from the closed set.
- Duplicate package IDs keep the first entry and mark the snapshot
  partial — a duplicated identity means the response is not trusted.
- `InventoryResource` is the identity-only record for list endpoints
  with no documented schema: it retains **only** an allocated numeric
  `id` plus the producing operation, and reports every other field by
  name in `unmappedKeys` (values dropped — credential-bearing fields
  like VNC data can never persist). Items without an `id` are skipped;
  the projector never guesses alternative identity keys. This supplies
  the reconciliation primitive "provider still lists resource N" while
  claiming nothing about the fields.
- `InventoryRegister::projectable()` stays limited to `packages.list`
  (full documented projection); `identityProjectable()` covers the
  four extended list reads plus `packages.list` fallback.

## Deliberately out of scope

Persistence, scheduling, workers, routes, client ownership inference,
live calls, and every write operation. Client/service linking is an
explicit owner action in the application layer, assigned to Codex.
