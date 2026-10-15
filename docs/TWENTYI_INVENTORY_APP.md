# 20i inventory application slice — contract

Status: implemented on `devin/twentyi-refresh` (draft PR #100). This document describes the runnable application path built on the read-only contracts in `TWENTYI_INVENTORY_CONTRACT.md` and `TWENTYI_INVENTORY_READER.md`. Everything below is exercised only through synthetic fixtures and injected resolver/transport seams; no live 20i call, real credential, mutation or deployment has occurred.

## Scope

One explicit owner action — **Refresh package inventory** — performs a single authenticated `GET /package` read against the saved provider profile, validates every item against the documented shape, and persists a durable observation. Owners may then explicitly link an observed package identity to an active client account; clients see only their linked services.

Not included: provider mutations, plan-type/balance/detail projections (undocumented schemas), VPS/domain reads (separate `-vps` layer), background refresh, or any automatic operation.

## Storage

`site/database/schema/provider_inventory_schema.php` prepares seven InnoDB tables, all under the shared strict validator (`catalog_validate_schema` + per-table checks in `provider_inventory_migrate.php`):

| Table | Purpose |
|---|---|
| `provider_refreshes` | Durable refresh **intent**: random hex `id` PK, `seq` auto-increment insertion order, profile id, exact profile revision, actor, started at/epoch. |
| `provider_refresh_results` | Bounded **result** per intent: state, finite reason, scope, coverage, counts, skipped reasons, finished at, elapsed ms. `id` references the intent. |
| `provider_observation_packages` | Verbatim validated package rows keyed `(refresh_id, package_id)`. Rows hang off the intent so a later failure can never erase a prior observation. |
| `provider_package_links` | Explicit owner-approved mapping `(profile_id, package_id) → studio_clients.id`, unique per target, with `linked_by`/`linked_at`, plus the **approved binding context**: `key_fingerprint` of the credential that produced the observation and the `profile_revision` approved at link time. |
| `provider_package_link_events` | Ordered audit trail of `link`/`relink`/`unlink` events (auto-increment id; insertion-stable ordering). |
| `provider_domain_links` | Explicit owner-approved mapping `(profile_id, domain) → studio_clients.id` for one observed package domain, carrying the carrying `package_id`, `linked_by`/`linked_at`, and the same `key_fingerprint`/`profile_revision` binding context. |
| `provider_domain_link_events` | Ordered audit trail of domain `link`/`relink`/`unlink` events. |

`provider_refresh_results.key_fingerprint` is `hash_hmac('sha256', secret, vault_key)` computed at unseal time — an installation-bound fingerprint that identifies the **credential context** an observation was made under without storing key material. Refreshes that never reach the credential record `''`.

Fresh installs create empty ready tables via `schema_statements` and record `provider_inventory_schema_version=3`. Existing installs run `provider_inventory_migrate($password)` — owner password + staff mutex, strict validation of any pre-existing tables, never rebuilds or truncates. Version-1 tables gain the binding columns through appended `ALTER TABLE` statements; version-3 adds the two domain-link tables without touching existing rows; pre-existing links get empty fingerprints/revision `0`, which treats them as unverified until re-approved.

## Refresh protocol

`provider_inventory_refresh($profileId, $revision, $password)` mirrors the connection-check protocol:

1. Staff mutex + transaction: owner re-authorization with the exact current profile revision and password; per-profile 30-second cooldown and 20/hour per-actor limit enforced inside the lock; durable intent row written and audited — intent failure or audit failure prevents any network work.
2. DNS resolution outside the lock through `EndpointPolicy` — private/link-local addresses refuse before credentials open.
3. Under the mutex again: owner re-authorized, credential unsealed (`provider_settings_unseal`) only after a mid-flight authority check; the lazy secret resolver and the transport both re-check authorization. `InventoryReader::read` over `FixedHttpTransport` performs exactly one bounded request.
4. After the read, authority is checked once more — any change downgrades the outcome to `access_changed` and the snapshot is discarded.
5. A bounded result row is persisted: validated `complete`/`partial` snapshots keep package rows; `unavailable`, `refused`, `access_changed` and `credential` failures record finite reasons only.

Return codes: `complete | partial | unavailable | refused | access_changed`. Package rows persist only for `complete`/`partial`.

**Last-good semantics:** reads of the "latest" result and the "last good" observation (`complete`/`partial`) order by `started_epoch DESC, seq DESC`; the stored `seq` insertion sequence makes ties deterministic. Unavailable refreshes never displace the last good observation.

## Linking

`provider_inventory_link($profileId, $packageId, $clientId, $revision, $password)` — inside staff mutex + transaction: owner password + exact revision; the last good observation must have been recorded under that **exact same revision** (a stale observation is refused — refresh first); the package must exist in that observation; the client must be an active `studio_clients` row; duplicate `(profile, package)` links are refused. The link persists the observation's `key_fingerprint` and the approved revision. An auditable `link` event is recorded.

`provider_inventory_relink($linkId, $revision, $password)` — explicit owner **re-approval** after the provider configuration changed. Requires the exact current revision plus a last good observation recorded under it that still contains the package; updates the binding to the current context and records a `relink` event. A package absent under the new context can only be unlinked.

`provider_inventory_unlink($linkId, $password)` — owner password + readiness; deletes the mapping and records an `unlink` event. Deleting a link never deletes observation data, and unlink remains available for stale mappings.

**Context binding (why replacement keys cannot reuse old mappings):** every save — label, state or credential — advances `profile.revision`, and a credential change also changes the fingerprint. The application cannot prove two different keys belong to the same provider account, so ordinary same-account key rotation and an account change take the same path: the mapping is suspended until the owner re-approves it under a fresh current-revision observation. Client service reads and link management enforce both bindings: `link.profile_revision` must equal the profile's current revision, and the served observation's fingerprint must equal the approved one. A changed or unverified context suppresses the package data entirely — the client sees a pending-review marker, never another context's data.

## Domain linking

`provider_inventory_domain_link($profileId, $packageId, $domain, $clientId, $revision, $password)` assigns **one observed domain** to a client independently of the hosting package that carries it — a reseller commonly hosts several clients' domains on one package. The gate mirrors package linking: owner password + exact current revision inside the staff mutex; the `(package_id, domain)` pair must appear in the `names` of a package row in the last good observation recorded under that same revision; the domain is normalized to lowercase and shape-validated; the client must be active; duplicate `(profile, domain)` links are refused. `provider_inventory_domain_relink` re-approves after a context change only while the domain still appears on the same package under a current-revision observation; `provider_inventory_domain_unlink` removes the mapping. Both record `link`/`relink`/`unlink` events and settings audits.

Links are never inferred from provider fields (names, domains, stack users), and identical package ids across contexts never carry authority by themselves. Support sessions and non-owner staff cannot link, unlink, refresh or read inventory (`provider_settings_manager`/`provider_checks_authority` enforce owner role + `staff.manage` + no support context).

## Views

- **Admin provider settings** (`site/admin/views/providers.php`): prepare/refresh forms (password-confirmed), latest refresh badge/reason, observed package cards (type, enabled state, names, labels, stack users) with owner link/unlink controls, a "linked services not in the latest observation" section so stale mappings remain visible and unlinkable, ordered link history, and paginated refresh history.
- **Portal `?services`** (`site/includes/layout/provider_services.php`): shows only packages explicitly linked to the signed-in client whose approved context still matches the current profile, with the last good observed state or an honest absent marker — absence never implies deletion/suspension. Context-changed mappings show a pending-review message instead of package data. Owner-linked domains appear the same way, carrying only their approved package ID while present; sibling primary names and unrelated package names are excluded. Stack-user identities and unmapped keys are not exposed to clients.

## Failure model

| Condition | Result |
|---|---|
| Wrong password / stale revision / non-owner / support session / client | refusal before any intent or network work |
| Disabled profile / no credentials | `refused` — no contact |
| Audit insert failure | no durable intent; nothing starts |
| Private/unresolvable DNS | `unavailable` (bounded reason) |
| `TransportException` kinds | `unavailable` with `transport_*` reason |
| HTTP 401 | `unavailable` / `authentication` |
| Malformed/oversized/object-shaped body | `unavailable` / finite reason |
| Authority or revision change mid-flight | `access_changed`, snapshot discarded |
| Result-audit failure | retained incomplete intent (no fabricated result) |

No arbitrary exception text, credential material or ciphertext is persisted; the native suite asserts no durable row contains the secret, its base64 form, or `Bearer`/`Authorization` material.

## Verification

`tests/provider_inventory_native.php` + `.github/workflows/provider-inventory.yml` (PHP 8.2/8.4 + MariaDB 10.11): 77 named assertions covering storage preparation, the version-1 in-place upgrade path (including pre-upgrade mappings loading unverified), authorization and revision checks before any network access, cooldown and hourly limits, injected resolver/transport classification, complete/partial/unavailable persistence, last-good preservation, link restrictions and isolation, unlink events, mid-flight races via pause-worker fixtures, credential-context binding (stale-observation link refusal, replacement-context suppression, explicit re-approval rebinding, settings-only suspension), and secret-material absence from durable rows. Named results retained as `provider-inventory-<php>-<sha>` artifacts.

## Codex takeover correction

## 2026-10-15 — Codex P0 takeover and first inventory repairs

Owner transferred the complete 20i lane to Codex and paused unrelated development. From main68e4d41, preserved #122/#115 via normal merges through9744110; repaired old-schema readiness, generated-column validation, moved-domain/context display, sibling-name disclosure and two fixture counters. Added genuine populated/interrupted/repeated upgrade and isolation coverage;38 local PHP8.4/MariaDB checks pass. Expanded current PHP/database and packaged HTTP acceptance is pending. Full release review and later hosting/domain/VPS/SSH/billing implementation continue. [Scope, ancestry and evidence](TWENTYI_CODEX_TAKEOVER.md). v1.23 stays unchanged; no live action.

Both views require inventory version3. Populated1/2 must use the existing owner-password/CSRF preparation; GET never changes schema. Failed/interrupted additive preparation retains the old stamp, original rows and owner retry. A missing established table is a recovery requirement, not a fresh installation. Domain cards compare the approved package plus revision/fingerprint with the current observation. A changed package is not reapproved by the old link; remove that link and explicitly approve the new pair. Hosted names do not establish registrar entitlement. The38-case upgrade suite renders real PHP owner views against MariaDB; the full original race suite and packaged paths still require current CI.
