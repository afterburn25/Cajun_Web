# Direct 20i hosting catalog imports

This development increment extends the owner-only service catalog beyond hosting types found in existing accounts. It is on `codex/twentyi-direct-hosting-catalog`, starting from main `6caf654c99cb5befb610f405e40df69b0c174cb6`. It is not part of the already published [1.25.0 release](RELEASE_1.25.0_PUBLICATION.md). Database, packaged browser and subsequent release acceptance remain pending at this source checkpoint.

## Admin flow

In the service catalog, enable imports and choose **20i hosting catalog**. Enable its observation storage with the current owner password, then use **Refresh 20i hosting catalog**. The existing provider connection and hosting inventory storage must already be configured. Refresh is an explicit authenticated action; viewing the page or changing the source does not call 20i.

Review a returned hosting type, choose a local SKU and service period, and create its draft. Types can be offered even if no hosting account currently uses them. Price, features and service terms remain for local review. Upload your own JPG, PNG or WebP using the existing image flow. Provider pricing and automatic provider images are not imported. Hosting publication and ordering remain unavailable.

**Existing hosting inventory** stays available as a separate source. Once direct catalog storage is enabled, the admin view defaults to the catalog; existing service calls without a source argument keep their previous inventory behavior. A failed submission preserves its selected source, SKU, period and captured preview while clearing the password.

## Identity and retained data

The [original metadata observation](TWENTYI_CATALOG_SCHEMA_DIAGNOSTIC.md#original-outcome--2026-10-18) established integer `id` and string `label` fields for hosting types. The [official provisioning reference](https://docs.20i.com/api/provision-hosting-package-api) and retained owner reference line573 explicitly connect `addWeb.type` to the ID from `/packageTypes`. Existing inventory uses that same hosting type identity. The projection keeps only a valid positive ID and bounded UTF-8 label; it does not infer platform meanings, specifications, prices, terms or image fields.

The production reader permits one fixed bodyless `GET /reseller/*/packageTypes` for the connected reseller. Numeric/other reseller selectors, arbitrary URLs and other unsupported operations remain refused. Invalid JSON, duplicate keys or IDs, unsupported fields needed for identity, excessive responses and partly valid lists cannot become importable catalogs. Unknown fields are discarded. An empty valid list records zero observed types and deletes no products; account-wide completeness is still unknown.

New storage is additive. Each refresh retains an intent before network access, an exact owner authentication revision and connection fingerprint, and an independently checked saved result. The latest intent by insertion sequence controls import readiness. A failed or unfinished newer attempt cannot silently fall back to an older successful catalog. Current owner, profile revision and credentials are checked at the read boundary and again before a result or product is saved. Missing established history requires recovery rather than empty replacement tables.

The existing `(profile, type)` import identity is preserved across both sources. Re-importing a type reuses its draft without overwriting edited wording, pricing, image or original provenance. The listing identifies whether the original import came from inventory or the direct catalog, even if the type has since been renamed. Earlier credential contexts cannot silently reuse a product as a new import. Existing import hashes and multipart image behavior remain unchanged.

## Cloud and artwork limits

The separate [cloud diagnostic](TWENTYI_CLOUD_ROOT_DIAGNOSTIC.md#original-follow-up-outcome--2026-10-18) returned JSON null. No cloud catalog contract or cause was established. This implementation does not substitute invented cloud products or image URLs. Automatic provider artwork and complete 20i product coverage remain unfinished; the owner does not need to resend the already retained API reference.

## Verification status

The owned source proposals and admin integration have passed syntax/source review and focused offline checks. New tests exercise direct catalog parsing, durable observations, current owner/credential changes, source-bound previews, type IDs absent from inventory, cross-source duplicate preservation, malformed and failed reads, local multipart artwork and desktop/mobile layout. Database and HTTP tests are prepared for isolated fixtures with synthetic transport; a passing result is not claimed until they actually run. Existing release acceptance and old import tests are retained, with only the prior unsupported-package-types expectations updated for the newly implemented operation.

Local verification on the combined source: the canonical build refresh passed PHP/JavaScript syntax and regenerated installer inventory; 228 acceptance-helper checks passed; documentation links and whitespace checks passed. PHP 8.4.25 ran the 109 new pure checks plus the existing 48 import, 139 inventory and 29 reader checks in both default and minimal modes. Five loopback TLS route checks passed. These are local/offline results, not native database or packaged browser results.

The focused GitHub workflow runs PHP 8.2/8.4 with MariaDB 10.11/MySQL 8.4 in disposable databases. The full acceptance recipe retains the original 152 native commands and adds three catalog commands, three named reports and two browser captures. Its expected report sets are 167 browser and 211 final reports. This source checkpoint does not publish changed 1.25.0 packages; the next release also requires a real upgrade from the immutable published 1.25.0 baseline.
