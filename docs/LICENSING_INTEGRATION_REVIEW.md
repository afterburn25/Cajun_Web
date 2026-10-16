## Independent current-schema setup finding — 2026-10-15

Independent review of exact main35ddbbff confirms both earlier findings and identifies another repeatability blocker. `license_migrate.php:20` reuses `catalog_validate_schema.php:7`, whose column regex recognizes INT/BIGINT, CHAR/VARCHAR, DATETIME and text, but not TINYINT. The current website_licenses schema has8 declared columns; only6 are matched because is_active and is_master are TINYINT. The license_domains schema has6 columns;5 are matched. Existing current tables therefore fail the column-count check, including empty tables and repeated setup after initial preparation.

The independent pure PHP regex diagnostic confirms these counts using the exact source schema. Fix the licensing schema validation and test existing current tables, repeated preparation, interrupted preparation and a populated pre-master migration. Retain exact key/index/relationship validation when correcting the type handling. This finding is not a database execution claim. Evidence: `work/domain-agent-main35dd-licensing-review.json`, with10 source hashes and line excerpts. Both master delivery and populated-upgrade findings below remain open; Devin retains implementation ownership.

## Latest main follow-up — 2026-10-15

Read-only review now includes `35ddbbff8d64383f52fb867c510c73be05ce7648`, runtime `780761a6`, afterb8c. The new owner-password setup action and entry-point readiness guards are useful additions, but the two required corrections below remain open.

`site/database/migrations/license_migrate.php` calls `catalog_validate_schema()` on an existing table before doing anything else. It contains no ALTER adding `is_master`, so a pre-master table is refused instead of upgraded. Even an exact current populated schema without the new marker is refused by the explicit nonempty-table check. This is fresh additive preparation, not a supported populated upgrade. The added native checks only toggle the marker and do not exercise the migration. Preserve records while testing the actual pre-master upgrade and retry path.

The customer workspace still selects active customer licenses without `is_master=0`; direct bundle download adds only `license_ready()`, and bundle generation has no master exclusion. Existing customer-associated master bundles remain downloadable. Enforce the internal-key boundary in both generation and direct download, including bundles created before the correction. This is source review, not reproduced database/HTTP acceptance. New main remains outside the20i queue pending these fixes and combined verification.


# Licensing integration review for Devin

The owner assigns Devin software-product license generation and installation licensing for products sold by CajunWebPro. Codex retains 20i implementation and combined release coordination. Preserve the existing licensing implementation and keep CajunWebPro's own installer one-page and keyless.

Reviewed source: `b8c432caa49d41a268f1cc83f2f9131c193a0b70`, including master-license implementation `f0a27fe142cac82ef25d01a022d7009e35feb90c`, against previously integrated `b8f8528b72520e60ee052119dcff1e8b2c829737`. These newer main changes have not yet been merged into the 20i candidate. This review traces source only; it does not claim database, real purchase, bundle, Website_Core or production execution.

## Required before integration

1. **Provide a supported migration for existing licensing databases.** The [new schema](https://github.com/afterburn25/Cajun_Web/blob/b8c432caa49d41a268f1cc83f2f9131c193a0b70/site/database/schema/license_schema.php#L13) adds `is_master` only to fresh CREATE statements. The new [license INSERT](https://github.com/afterburn25/Cajun_Web/blob/b8c432caa49d41a268f1cc83f2f9131c193a0b70/site/app/licenses/service.php#L38) and [validation SELECT](https://github.com/afterburn25/Cajun_Web/blob/b8c432caa49d41a268f1cc83f2f9131c193a0b70/site/api/handlers/license.php#L69) require that column. The installer refuses existing tables, the workspace migrator handles only workspace schema, and the file updater performs no database migration. A database created before this change therefore cannot create licenses or validate them with the new queries; validation catches the SQL error as HTTP 500. Add a supported, repeatable migration that preserves existing licenses, domains, bundles and audit history, with old licenses defaulting to ordinary licenses. Test an actual populated pre-master database, repeated preparation, failure/retry, normal validation and revocation after migration.

2. **Keep internal master keys out of customer deliveries.** The [admin create action](https://github.com/afterburn25/Cajun_Web/blob/b8c432caa49d41a268f1cc83f2f9131c193a0b70/site/admin/controllers/studio.php#L55) requires a customer association even for a master key. Its bundle action accepts any active license, and [bundle generation](https://github.com/afterburn25/Cajun_Web/blob/b8c432caa49d41a268f1cc83f2f9131c193a0b70/site/app/licenses/bundle.php#L81) embeds the complete key. The customer portal lists active customer licenses without excluding masters; the [download authorization](https://github.com/afterburn25/Cajun_Web/blob/b8c432caa49d41a268f1cc83f2f9131c193a0b70/site/api/bundle-download.php#L31) checks the active license and customer association, also without a master exclusion. Generating such a bundle therefore gives that customer a download containing an unrestricted key, despite the portal's masked display. Enforce the internal-only boundary in service and direct download authorization as well as the interface. Test already-created master bundles and direct download URLs, ordinary customer delivery, and revoked keys. A hidden button or warning alone does not close this path.

The permission trace also shows licensing views/actions falling back to `website.manage`; this review does not assume an undocumented owner-only role policy. Make the intended staff authority explicit when implementing master-key controls.

## Handoff and acceptance

Work in a separate licensing branch and provide its PR/head, migration instructions and named test evidence. Root will review the actual shared billing, portal, routing, schema and installer changes, regenerate combined dependencies and run exact combined acceptance. Keep previous branches, tags and released assets intact. Do not represent a successful fresh-schema test as an upgrade test.

Read-only evidence is retained locally at `work/20i-service-observations/qa/main-b8c-master-license-review.json`, including exact source hashes and line excerpts. This repository document is the portable handoff. No direct message to Devin was sent and no active Devin session is inferred.
