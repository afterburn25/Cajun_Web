## Reviewed future shared-file union — 2026-10-16 UTC

An external four-file proposal reconciles root111808e2 with current main33ef2aed without importing it. Independent proof work/main33ef-four-file-independent-review.json SHA25655cfd128378e11f8b02c84f4c9a56080198107a39166b87e81987ba9bb6acafd confirms: main service plus root physical-storage helper; root deployment plus licensing marker2; combined support/physical/ready portal guard and retained20i/recurring controls; and setup form before the unprepared physical-storage return and queries. Four PHP syntax checks pass. This is a prepared merge resolution, not licensing/native acceptance; populated upgrade, internal-master customer delivery, legacy invoice reconciliation and unsupported-marker prerequisites below remain Devin-owned. Final installer metadata must be regenerated after actual integration.

## Main33ef bundle filtering review — 2026-10-16 UTC

Read-only exact main33ef2aed22270c387ee70091171921496be10d57 adds symlink-file skipping for core/module bundles and limits core config/ to .htaccess/config.example.php while excluding .env paths. The functional fixture adds config allowlist and .env assertions. Eight reviewed migration/schema/service/fulfillment/customer-download/workspace/admin/deploy files remain byte-identical toee97; bundle authorization still has no is_master exclusion. Earlier populated pre-master upgrade, internal-master customer delivery, legacy invoice reconciliation and unknown/future marker findings remain open under Devin. Preserve marker2, unique invoice_id and serialized helpers at eventual integration. This is exact source comparison only, not bundle/native/upgrade acceptance. Proof work/main-33ef-licensing-source-review.json SHA256a15294c8e48e7eaec635400233c1414264c4b85cde9b383d59884aafecb744ff. No licensing code was imported or edited.

## Main ee97 invoice-linked licensing review — 2026-10-16 UTC

Read-only exact main `ee97ce3bbe965d9878b5a7b4b7b6ff368ce04893` adds invoice-linked licensing over reviewed187 through runtime dce58a9b. New licenses have nullable unique invoice_id, paid fulfillment looks up that invoice, and a second paid invoice can create a second license for the same product. Migration and fresh installation use license_schema_version2. Preserve these changes and serialized mutation helpers at eventual integration; earlier guidance to retain licensing marker1 is superseded by marker2. Inventory marker5 and root physical-storage/support/invoice guards remain required. Six current preview conflicts include both handoff and session log; the unprepared setup-form ordering concern remains.

Source findings still open under Devin:

- The migration adds invoice_id but still has no ALTER for pre-master is_master, and unmarked populated adoption is refused. Existing pre-master installations still need a supported preserved-data upgrade.
- Five customer/master-delivery files are byte-identical to187: the portal can show an active customer-associated master key, and bundle creation/direct download still lack the internal-master exclusion described below.
- Existing version1 licenses receive NULL invoice_id. New fulfillment searches only by invoice_id, so retrying a historical invoice already fulfilled under version1 can create another license. Provide a supported legacy reconciliation policy and upgrade/retry tests; customer/product alone cannot establish a unique historical invoice association.
- The migration writes marker2 for every nonempty prior marker after schema validation. Unknown/future-marker refusal and interrupted-upgrade behavior need explicit handling and tests.

The five new native assertions exercise fresh invoice fulfillment and unique-index rejection; `tests/license_native.php` does not invoke `license_migrate()`. This source review does not claim a native upgrade reproduction or acceptance of newly reported licensing workflows. Exact changed/retained hashes and normal merge preview are in work/main-ee97-licensing-source-review.json, SHA256ba25c238b124788c79981a2a2c71d4cd3963a517b7f5ea9a119ba5e3c010642f. New main was fetched and reviewed only; no licensing source was imported or edited, no purchase/mail occurred, and no direct Devin message was sent.

## Main187 documentation-only delta — 2026-10-16 UTC

Exact18766495dd36b53eb1313cc535ac2a7c8a794d26 is a direct docs-only child of reviewed94c879: only SESSION_HANDOFF.md changes to report acceptance38855488508. Its entire site tree remains14cfbbbfde6b3ee93455171dafaa10d83c54f778; tests/workflows and11 reviewed licensing/generated files are identical. No new or resolved source finding. The populated pre-master upgrade and internal-master customer projection/full-key/bundle/direct-download issues below remain open; retain serialized mutation helpers and root support/storage/invoice guards. Proof work/20i-document-roots-observations/qa/main187-licensing-delta-review.json. This source comparison does not independently verify the newly reported run, import licensing or prove Devin is active.

## Main94 merge preservation map — 2026-10-16 UTC

Read-only preview against94c879ebab28fe94e71690b870f4104e002d3515 identifies five explicit conflicts and a silent automatic merge that returns on missing physical licensing tables before displaying main's preparation form. Keep the unprepared-marker setup form first, then the root physical-storage guard before queries. Preserve main's transaction/row-lock/internal mutation helpers, root license_storage_ready and the combined workspace guard `!support_current() && license_storage_ready() && license_ready()`. Keep recurring invoice guards,20i navigation, inventory marker5 and licensing marker1; regenerate installer hashes from final bytes.

All nine relevant upgrade/master-delivery files and six reviewed readiness/creation/read functions remain unchanged from2796; the prior populated upgrade and internal-master customer projection/bundle/download findings remain. Exact source/ancestry map and hashes: `work/20i-domain-expiry-observations/qa/main-integration-preview/{resolution-map.md,main94-integration-review-proof.json,source-and-ancestry-proof.json}`. No licensing merge, source edit, database run or direct Devin message occurred.

## Latest main delta — 2026-10-16 UTC

Read-only review of main `94c879ebab28fe94e71690b870f4104e002d3515` is complete: mutation serialization must be preserved, while populated pre-master migration and internal-master customer-delivery findings remain open. Five explicit merge conflicts and one silent setup-form obstruction are mapped; this is source review, not merged/native acceptance.

Exact main `2796fa48aeba6dd02731945d45fd613c2c5759e9` changes one handoff line afterc0e087027. Its entire site tree remains `ea6e06f4251ba5534e6fb9e65d48a746dd76021a`; licensing migration/service/bundle/download/workspace bytes are unchanged. The findings below remain open. Root427 already retains `!support_current()`, `license_storage_ready()` and `recurring_billing_invoice_guard()` in the shared workspace; preserve them during eventual integration. Read-only delta proof: `work/catalog-agent-main279-licensing-delta-review.json`. No licensing source was modified/imported or database/HTTP acceptance repeated.

## Current main full-key display — 2026-10-16 UTC

Read-only review of exact main `c0e0870278eaf158499a501954164f9f33ff052a` (runtime f55db177) confirms both required corrections below remain open. The populated migration and bundle authorization are unchanged from1aa421e5. The new [workspace display](https://github.com/afterburn25/Cajun_Web/blob/c0e0870278eaf158499a501954164f9f33ff052a/site/portal/views/workspace.php#L9) selects every active customer-associated license and now prints the complete key. Because master creation requires a customer and this query does not exclude masters, an active customer-associated master key is exposed directly even without a bundle. The same source also lacks the support-session exclusion; preserve the20i branch's existing `!support_current()` and `license_storage_ready()` guards during integration.

Required correction: enforce internal-master exclusion in customer projection, bundle creation and direct download; test ordinary keys, master keys, existing master bundles, revoked keys and support identity. Retain the populated pre-master upgrade requirement. This is source evidence only; no database, HTTP, bundle or production execution is claimed. Exact11-file hashes/excerpts: `work/domain-agent-main-c0-licensing-review.json`. No licensing source was modified or imported; Devin retains ownership. Shared workspace integration must preserve20i navigation and invoice readiness rather than replace the whole file.

## Current main reactivation follow-up — 2026-10-15

Exact main `1aa421e57d36d6c47d3623f7dadad2967a907d1b` adds license reactivation after794, with an admin action/view and native cases. Exact source comparison verifies the previous licensing service is unchanged after removing only the new reactivation function/comment. The schema validator, migration, bundle generator, direct-download handler and customer workspace are byte-identical to794. Both populated pre-master migration and internal-master customer-delivery findings below therefore remain open; the parser correction remains retained. This is source comparison only, not new database/HTTP execution. Proof `work/main-1aa-licensing-source-review.json`. Devin keeps ownership; these newer changes are not imported into the20i candidate.

## Latest main source correction - 2026-10-15

Exact main `79434bb87cf3535c61ccd6182a88aa08dea7919c` corrects the schema parser to recognize TINYINT and normalize its display width. Root's pure PHP diagnostic now matches all8 website_licenses columns, all6 license_domains columns and all5 license_bundles columns; tinyint/int/bigint display-width normalization is also verified. Validator SHA256 is `4cbaf88d46cf9f09e627055866faa59f9a50de44b76d09d1eb7d95b3e639f17f`. The parser finding below is resolved in source; actual existing-schema/repeated-setup database acceptance is not claimed by this diagnostic.

The migration, licensing service, bundle generator, direct download and customer workspace are byte-identical to previously reviewed35dd. Therefore the populated pre-master upgrade and internal-master customer delivery findings remain open. This review makes no new database, bundle or production execution claim. Local proof: `work/main-794-licensing-source-review.json`. Devin retains implementation ownership; newer main remains outside the20i integration queue pending the remaining corrections and combined acceptance.

## Historical parser finding before79434bb8

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
