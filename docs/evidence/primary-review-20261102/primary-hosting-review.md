# Primary84 hosting catalog, checkout and provider inventory review

## Decision and exact boundary

**No additional concrete hosting blocker was established in the reviewed source paths. This is a bounded implementation review, not approval of all PR317, a release, deployment, a provider operation or live checkout.** The separate finance review's confirmed P1 findings remain unchanged and must not be obscured by this result.

- Baseline: `1c44ca120e7768998fafa720b3e1c4c85990c330`.
- Published target: `84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd`.
- Read-only checkout: `/workspace/scratch/8cc2b4f257ab/Cajun_Web_primary_review`; HEAD and every inventoried file were compared to that exact Git tree.
- Review date: 2026-11-02. Reviewer: independent `primary_finance_review` subagent, separate hosting lane.
- Method: local source/diff review, immutable historical-tree comparisons, retained documentation/evidence inspection. No source edits, PHP application execution, database/service action, provider/payment/mail activity, remote write or Actions polling occurred.

The machine-readable companion [primary-hosting-review.json](sandbox:/workspace/scratch/8cc2b4f257ab/primary-hosting-review.json) contains **142 exact changed paths**, baseline/target Git blob IDs, full SHA256 values, predecessor comparisons and per-file coverage limits. The wide inventory includes related workspace UI paths that were deliberately **not** given implementation clearance. Counts: 8 reviewed_foundation_delta, 23 reviewed_scoped_clauses, 51 inventoried_not_reviewed, 33 reviewed_source_module, 4 parent_owned_identity_clause, 15 reviewed_schema_or_migration_delta, 8 byte_equivalent_packaged_mirror.

## Existing evidence and what can be reused

| Evidence | Source relationship and valid use | Limit |
|---|---|---|
| `docs/evidence/catalog126-original-runtime-review.json` | Explicitly records independent review of original producer artifacts at `f67b1631683b29096dd8ec1daee405e104dc0b83`. Eight core files are byte-equal to84: canonical InventoryReader, PackageTypes, InventoryPackageType, InventoryRegister, InventorySnapshot, plus application inventory.php, package_types.php and package_types_storage.php. The complete mapping is in JSON. | It is an artifact/runtime review receipt, not blanket independent implementation approval. External original archives/reports were not redownloaded here. It predates publication/checkout. |
| `docs/TWENTYI_PLAN_CATALOG.md` and `docs/TWENTYI_CATALOG_READINESS.md` | Retain older independent findings, corrections and source-bound native/browser evidence for owner mappings. Used to identify invariants: latest pointer/history, immutable original mapping, current role/key/review expiry and separate fulfillment authority. | Current plan_catalog.php has a new composed writer; surrounding integration differs. Older acceptance is not exact84 acceptance. |
| `docs/ADMIN_20I_SESSION_INTEGRATION_2026-10-30.md` | Identifies recovered stack `d2903bfb81c93eb9da756c128536f23a3d34e822`, PR248, original artifact12221806544/run39231739716. Thirty-seven of45 core inventory/hosting/plans source files are exactly equal to that predecessor. Checkout service/documents/storage and most hosting module bodies are among the matches. | Source recovery/provenance and portable/fixture results do not prove an independent completed review of the recovered checkout. Seven existing core files differ and workspace.php is new. |
| `docs/ADMIN_20I_1261_STATUS.md` | Retains explicit limits of portable checks, modeled rendering and file-updater rehearsal; identifies native/installed/review gates. | This lane did not promote those results into current SQL/browser acceptance. |
| `docs/evidence/twentyi-catalog-schema-39090394774.json` and schema diagnostic document | Provider observation establishes the bounded lexical ID/label shape used by the read parser. The original combined diagnostic remains a failure because of its separate cloud result. | No inferred prices, platforms, account entitlement, complete coverage or provider image contract. No external provider read performed. |

All8 canonical foundation files selected for this lane match their packaged `site/includes/foundation` mirrors byte-for-byte. Historical equality is recorded from immutable Git trees, not guessed from filenames or commit subjects. The prior d290 match set includes source modules with unchanged internal behavior even where their shared session/parent dependencies now differ; dependency differences remain relevant.

## Core reviewed behavior

### 1. Package-type reads and stale inventory

`PackageTypes::project` bounds body/depth/node work, accepts a list only, projects strictly positive integer wire IDs with bounded UTF-8 labels, rejects duplicate identities and duplicate JSON keys, and refuses the entire malformed catalog instead of importing a valid subset. Unknown fields are not retained. `InventoryReader` permits this operation only for the configured reseller-self selector `*`; the changed transport allowlist is one literal HTTPS GET path.

The application writes the common refresh intent, package-type binding, audit witness and count before DNS/transport. The binding preserves original actor authorization revision, provider profile revision and HMAC fingerprint of the key. `provider_package_types_current_context` checks the owning staff mutex, current authority and original key identity. The transport authorization callback revalidates the retained intent, and successful persistence revalidates exact scope, key, profile, timestamps, complete state and typed list. A timeout/error/incomplete read remains distinguishable from an empty successful catalog.

Import and publication select the newest intent by retained sequence. If it is incomplete, failed or for another profile revision, an older success cannot authorize new import/publication. Existing products/history remain retained. No claim that these reads establish full reseller coverage is made; the stored coverage value remains `unknown`.

### 2. Import identity, batching and billing variants

`provider_catalog_import_context` executes inside the shared staff mutex/transaction, rechecks current owner/catalog access, current enabled profile, newest complete source and actual saved key fingerprint, and constructs a preview hash that binds source operation, original refresh, owner authorization and the exact type list. Imported drafts carry original profile/type/refresh/key provenance and an independent creation audit witness.

A repeated canonical type resolves the original product instead of rewriting an edited product or creating a duplicate. Changed-key adoption is refused. Batch import validates the whole source, existing canonical identities and SKU conflicts before writing; each reviewed index window is bounded to50 so replay cannot silently advance to a new batch. Product/revision/import/count/audit writes share one transaction.

A monthly/annual variant is a new explicit retail product with a canonical import/product pair, a fresh request identity, exact reviewed source/owner input, a bounded family and separate image history. Variant readiness validates all records/counters/audits; a child cannot replace its canonical import or escape the hosting draft/retired restrictions. These paths neither publish nor charge nor provision. The general catalog still prevents imported hosting from entering ordinary public product publication by changing family/status.

### 3. Owner setup and publication

Setup takes its name, amount, currency, cadence and provider type from validated saved product/import facts. The form refuses submitted overrides for those fields. Current owner review includes provider defaults and known limits/cost evidence. Plan creation, activation and mapping save compose under the same owned mutex/transaction with three original distinct identities; partial identity presence is refused.

New publication requires matching exact active plan terms, current mapping, enabled profile and key, exact latest complete package-type observation, and both original publisher and mapping reviewer's active owner/authentication revisions. `HostingCatalogPublication::checkCurrent` is used again for public availability and client confirmation, so product changes, newer publication, withdrawal, changed mapping/profile/key, reviewer revocation and expiry invalidate a new purchase. Public fields are an explicit projection excluding credentials, private provider costs, raw mapping and retained document JSON.

The binding follows the selected latest observation; it does not invent an automatic freshness interval beyond retained observation identity and the explicit mapping review expiry. A fresh successful provider observation with a different ID requires a fresh owner publication review even when labels look unchanged.

### 4. Primary-client checkout and exact commercial consent

The checkout controller has POST/CSRF checks and a bounded action allowlist. The service rejects contact provenance before another auth helper can normalize the session. Under the shared lock and its own transaction it rereads the active primary client, session authentication revision and time limits, then checks exact project ownership. A query/POST project ID is an instruction and never ownership proof. Login return destinations are fixed local hosting states, not arbitrary URLs.

The server derives price, USD currency, quantity1, zero setup fee, original plan/publication, billing timezone/alignment and first period from retained publication. Review lifetime is bounded by policy (maximum900seconds), mapping expiry and the next local calendar date. Confirmation requires the original review hash, explicit assent, unexpired current publication and the same locked project facts.

`hosting_checkout_confirm` uses one accepted timestamp and one transaction for optional new project, exact two-event service agreement (real client consent followed by narrowly attributed system activation), one initial recurring schedule, one issued/unpaid invoice, issuance map, retained consent/result, counters and independent audit records. There is no card charge or provider request in this path. Replay authenticates the requesting client and reconstructs the original graph; it returns the original invoice without replacing dates, amounts, terms or identity.

The structural document layer cannot supply present authority by itself. Native readers reciprocally compare the actual original publication, consent, subscription prefix, recurring prefix, issuance map and invoice snapshot. Format2 cannot be relabeled as legacy format1; only the designated second subscription event may have `system:hosting-checkout` attribution. Later legitimate lifecycle history can coexist with those fixed initial prefixes.

### 5. Order, payment and provisioning handoff

Checkout origin retains the **exact first invoice ID**, so newer invoices with matching amount cannot replace it. The order reader/offer path checks this identity and the original active agreed plan. The owner purchase UI retrieves the original invoice directly. Current customer/project ownership is rechecked on retained result/purchase reads, and visible invoice status is separate from setup state.

For a checkout-origin agreement, `allow_manual` is refused before replay, assessment ignores the manual policy, and both stored service association and fulfillment approval require a verified-payment basis with no manual-event ID. The exact original mapping ID/digest is mandatory; a later substitute mapping requires a new reviewed offer/agreement.

The unchanged fulfillment boundary was traced where it consumes these changes: a newly committed approval/fence alone returns dispatch authority; replay returns no claim. It checks accepted order, actual payment evidence, client, profile revision and encrypted-credential digest, then checks them again before credential use/send. Provider I/O occurs after claim commit and outside a database transaction while the shared authorization mutex is held. A missing/unknown provider result becomes a conservative timeout outcome; it does not clear an unresolved attempt for a retry. This is a focused handoff review, not an exhaustive new review of legacy provider results, reconciliation, retries or all lifecycle operations.

### 6. History recovery and explicit schemas

Publication and checkout stores retain strict schemas, unique result mappings, counts and exact audit witnesses. The checkout reader proves reciprocal original subscription/schedule/invoice origins. Native format2 subscription history is inspected before optional hosting markers, so losing a marker/table/result cannot turn a checkout into an ordinary agreement with manual fulfillment authority.

New setup remains explicit owner work. Service-plan and recurring attribution use ordered, recognized additive DDL stages and a preparing marker; they validate retained legacy/current histories before and after the known steps. Unknown or partial schema state, missing established tables and unexpected format2 adoption fail closed. No migration was executed in this review. File recovery takes the same staff lock, rechecks current owner, rejects unknown/partial hosting namespaces and blocks older-file restoration while retained publications require image/history protection.

### 7. Bounded workspace/UI observations

The workspace selector has seven fixed names and a literal include map. Catalog search uses bounded strings, closed family/status choices and bound SQL values with escaped LIKE wildcards. Checkout/result and purchase controllers use private/no-store headers and closed safe projections. Rendered submitted identities/hashes remain original on failures; stale review does not silently become fresh assent. Public card/customer-facing terms inspected use escaping and fixed local image/link destinations.

The newly grouped StackCP audit SQL was reviewed as a changed query composition with downstream count/actor/minimum-ID checks retained. This does not establish database query-plan/collation behavior, full StackCP authorization or performance across production histories. Parent's identity/routing review owns the widespread password-removal/session-helper clauses.

## Concrete findings and remaining work

**Additional confirmed defect findings in this hosting lane: none.** This conclusion applies only to the clauses above. It is not evidence that every inventoried path or surrounding feature is defect-free.

- No native PHP/database, HTTP/browser, installed upgrade, schema recovery, transport probe or provider activity executed. Exact84 full acceptance belongs to parent/root and is not inferred from this review.
- Historical f67 artifact-review receipt is retained and source-mapped, but its external artifacts were not downloaded again. It predates hosting checkout and cannot clear that flow. d290 equality is provenance, not proof that an independent implementation review was completed.
- Child provider-workspace views/controllers, other provider controls, all route dispatch, global admin_session_matches implementation, and CSS/visual/accessibility behavior were not fully reviewed in this lane. See per-path matrix; parent owns shared identity/routing.
- StackCP grouped-audit SQL delta was inspected but full StackCP invariants, collation behavior/query plans and supported database execution were not independently repeated.
- Legacy hosting operations, retry/reconciliation algorithms, transport implementation beyond changed allowlist, ledger gateway settlement and later plan-change semantics have not received a new exhaustive review here; required unchanged claim/authorize/unknown-outcome seams were traced only.
- Public offer readiness validates retained history under the shared staff mutex; total cost grows with history. No production-scale latency/contention measurement is claimed.
- Current provider account capability, full catalog coverage, platform/price/limit/region/default semantics and actual provisioning remain unverified by this source-only lane; observations deliberately retain unknown account coverage.

## Test evidence boundaries

No tests or probes were executed. The executable fixtures are present and source-hashed in the JSON companion: package-type, hosting catalog, variants, checkout document/native, service-plan/recurring checkout migrations, purchase handoff and HTTP browser producers. Selected native source snippets show genuine database fixture paths with synthetic transport, rather than live provider acceptance. I did not claim those fixtures passed here or that all their assertions were independently reviewed.

The current root acceptance run and any later correction run must preserve their original source identity, full named reports and failures. Existing portable totals or a previous successful source do not close a new source gap. A later finance correction needs its own frozen-delta independent review; no finance implementation or missing-successor source hunt occurred in this hosting lane.

## Key source hashes

Full path/hash/coverage inventory, including unreviewed UI paths, is in the JSON companion.

| Exact target path | SHA256 |
|---|---|
| `site/app/hosting/catalog_checkout.php` | `b0d2538dced06e1e2ed78bbaf7ec284d6a9462b3564a3700cbeef41229f886c2` |
| `site/app/hosting/catalog_checkout_storage.php` | `eaf98711efa2de3985fe74ad0b9dd85bc2c2e48a17e4bd4624b1f578e6d61e76` |
| `site/app/hosting/catalog_offers_document.php` | `ad492921f29b83fc53098d8e7ede6f6036aae532e610929dd9cdabf524a9f314` |
| `site/app/hosting/catalog_offers_context.php` | `3f086c5d7f0cc873270a5ccb120a499132fa8f484db43b20e2655f24516e916b` |
| `site/app/providers/package_types.php` | `aac5e823b599b4bd886841410b077b6bc55cf887c867439f3767c8ab853a45df` |
| `site/app/providers/package_types_storage.php` | `c87a4db88e75642c42a7b9f51ea2df2a7a26592731e81e318cf0b6d077dda183` |
| `site/app/providers/catalog_import.php` | `61d12498e56bb28be0a9c211bf0f06da9896a47d135e8bfc423ba2444d62ebf0` |
| `site/app/providers/catalog_variants.php` | `89ff0ca2ef72bb14f544b4481b6992510599f72883b4943451ce2a4158d414a5` |
| `site/app/plans/documents.php` | `8ae0627a5eebf4840033140a0a6812c63365d0b35eae64c60c894a7f072aea0c` |
| `site/app/plans/records.php` | `2d9df2440c7ef3d21cea9b6d640006fa5a6545783ef90659c24555f74d04531c` |
| `site/app/hosting/orders.php` | `eeb54f4621554762a0600f715601d8bd989522c8f56ba9e7946b2605edc637db` |
| `site/app/billing/recurring_billing_invoices.php` | `a49fa4f6bfdc00fd6afad867b2a3867f622a759217ce40bf0a0616a31ad6dbc0` |

## Exact changed scope inventory

The following are inventoried paths, not a uniform clearance list. Per-path coverage in JSON is authoritative.

- `foundation/recurring-billing/RecurringBillingSchedule.php` — reviewed_foundation_delta.
- `foundation/recurring-billing/RecurringBillingTerms.php` — reviewed_foundation_delta.
- `foundation/twentyi-inventory-reader/InventoryReader.php` — reviewed_foundation_delta.
- `foundation/twentyi-inventory-reader/PackageTypes.php` — reviewed_foundation_delta.
- `foundation/twentyi-inventory/InventoryPackageType.php` — reviewed_foundation_delta.
- `foundation/twentyi-inventory/InventoryRegister.php` — reviewed_foundation_delta.
- `foundation/twentyi-inventory/InventorySnapshot.php` — reviewed_foundation_delta.
- `foundation/twentyi/FixedHttpTransport.php` — reviewed_foundation_delta.
- `site/admin/controllers/catalog.php` — reviewed_scoped_clauses.
- `site/admin/controllers/catalog_variants.php` — reviewed_scoped_clauses.
- `site/admin/controllers/hosting_catalog.php` — reviewed_scoped_clauses.
- `site/admin/controllers/hosting_purchases.php` — reviewed_scoped_clauses.
- `site/admin/controllers/provider_cloud_confirmation.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_cloud_providers.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_cloud_site_request_confirmation.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_cloud_site_requests.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_directory_listing.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_directory_listing_controls.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_document_roots.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_force_ssl_controls.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_forwarding.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_mailboxes.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_package_force_ssl.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_package_php.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_package_wordpress_installed.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_password_reset_template.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_registrar.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_registration_periods.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_schedule.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_stackcp_access.php` — inventoried_not_reviewed.
- `site/admin/controllers/provider_stackcp_prerequisites.php` — inventoried_not_reviewed.
- `site/admin/controllers/providers.php` — inventoried_not_reviewed.
- `site/admin/views/catalog_import.php` — inventoried_not_reviewed.
- `site/admin/views/catalog_variants.php` — inventoried_not_reviewed.
- `site/admin/views/hosting_catalog.php` — inventoried_not_reviewed.
- `site/admin/views/hosting_purchases.php` — reviewed_source_module.
- `site/admin/views/provider_cloud.php` — inventoried_not_reviewed.
- `site/admin/views/provider_domain_search.php` — inventoried_not_reviewed.
- `site/admin/views/provider_forwarding.php` — inventoried_not_reviewed.
- `site/admin/views/provider_registrar.php` — inventoried_not_reviewed.
- `site/admin/views/provider_registration_periods.php` — inventoried_not_reviewed.
- `site/admin/views/provider_schedule.php` — inventoried_not_reviewed.
- `site/admin/views/provider_workspace/advanced.php` — inventoried_not_reviewed.
- `site/admin/views/provider_workspace/cloud.php` — inventoried_not_reviewed.
- `site/admin/views/provider_workspace/connection.php` — inventoried_not_reviewed.
- `site/admin/views/provider_workspace/domains.php` — inventoried_not_reviewed.
- `site/admin/views/provider_workspace/services.php` — inventoried_not_reviewed.
- `site/admin/views/provider_workspace/website.php` — inventoried_not_reviewed.
- `site/admin/views/providers.php` — reviewed_scoped_clauses.
- `site/app/billing/catalog.php` — reviewed_scoped_clauses.
- `site/app/billing/catalog_browse.php` — reviewed_scoped_clauses.
- `site/app/billing/recurring_billing.php` — reviewed_scoped_clauses.
- `site/app/billing/recurring_billing_actions.php` — parent_owned_identity_clause.
- `site/app/billing/recurring_billing_invoices.php` — reviewed_scoped_clauses.
- `site/app/hosting/catalog_billing.php` — reviewed_source_module.
- `site/app/hosting/catalog_checkout.php` — reviewed_source_module.
- `site/app/hosting/catalog_checkout_billing_document.php` — reviewed_source_module.
- `site/app/hosting/catalog_checkout_document.php` — reviewed_source_module.
- `site/app/hosting/catalog_checkout_storage.php` — reviewed_source_module.
- `site/app/hosting/catalog_login.php` — reviewed_source_module.
- `site/app/hosting/catalog_offer_setup.php` — reviewed_source_module.
- `site/app/hosting/catalog_offer_setup_document.php` — reviewed_source_module.
- `site/app/hosting/catalog_offer_setup_views.php` — reviewed_source_module.
- `site/app/hosting/catalog_offer_views.php` — reviewed_source_module.
- `site/app/hosting/catalog_offers.php` — reviewed_source_module.
- `site/app/hosting/catalog_offers_context.php` — reviewed_source_module.
- `site/app/hosting/catalog_offers_document.php` — reviewed_source_module.
- `site/app/hosting/catalog_offers_storage.php` — reviewed_source_module.
- `site/app/hosting/catalog_recovery.php` — reviewed_source_module.
- `site/app/hosting/checkout_purchases.php` — reviewed_source_module.
- `site/app/hosting/checkout_purchases_views.php` — reviewed_source_module.
- `site/app/hosting/fulfillment.php` — reviewed_scoped_clauses.
- `site/app/hosting/fulfillment_views.php` — reviewed_scoped_clauses.
- `site/app/hosting/order_link_views.php` — reviewed_scoped_clauses.
- `site/app/hosting/order_links.php` — reviewed_scoped_clauses.
- `site/app/hosting/order_views.php` — reviewed_scoped_clauses.
- `site/app/hosting/orders.php` — reviewed_scoped_clauses.
- `site/app/hosting/records.php` — parent_owned_identity_clause.
- `site/app/plans/documents.php` — reviewed_scoped_clauses.
- `site/app/plans/records.php` — reviewed_scoped_clauses.
- `site/app/providers/catalog_import.php` — reviewed_source_module.
- `site/app/providers/catalog_import_document.php` — reviewed_source_module.
- `site/app/providers/catalog_import_storage.php` — reviewed_source_module.
- `site/app/providers/catalog_variants.php` — reviewed_source_module.
- `site/app/providers/catalog_variants_document.php` — reviewed_source_module.
- `site/app/providers/catalog_variants_storage.php` — reviewed_source_module.
- `site/app/providers/checks.php` — reviewed_scoped_clauses.
- `site/app/providers/cloud_site_requests_views.php` — inventoried_not_reviewed.
- `site/app/providers/directory_listing_controls_views.php` — inventoried_not_reviewed.
- `site/app/providers/domain_registration_drafts.php` — parent_owned_identity_clause.
- `site/app/providers/domain_registration_drafts_views.php` — inventoried_not_reviewed.
- `site/app/providers/domain_renewal_dispatch_views.php` — inventoried_not_reviewed.
- `site/app/providers/domain_renewals_views.php` — inventoried_not_reviewed.
- `site/app/providers/force_ssl_controls_views.php` — inventoried_not_reviewed.
- `site/app/providers/forwarding_views.php` — inventoried_not_reviewed.
- `site/app/providers/inventory.php` — reviewed_scoped_clauses.
- `site/app/providers/package_types.php` — reviewed_source_module.
- `site/app/providers/package_types_storage.php` — reviewed_source_module.
- `site/app/providers/password_reset_template_views.php` — inventoried_not_reviewed.
- `site/app/providers/plan_catalog.php` — reviewed_scoped_clauses.
- `site/app/providers/service_observations_views.php` — inventoried_not_reviewed.
- `site/app/providers/settings.php` — parent_owned_identity_clause.
- `site/app/providers/stackcp_access_views.php` — inventoried_not_reviewed.
- `site/app/providers/stackcp_prerequisite_views.php` — inventoried_not_reviewed.
- `site/app/providers/stackcp_read_projection.php` — reviewed_scoped_clauses.
- `site/app/providers/workspace.php` — reviewed_source_module.
- `site/assets/css/hosting-comparison.css` — inventoried_not_reviewed.
- `site/database/migrations/hosting_catalog_migrate.php` — reviewed_schema_or_migration_delta.
- `site/database/migrations/hosting_checkout_migrate.php` — reviewed_schema_or_migration_delta.
- `site/database/migrations/provider_catalog_import_migrate.php` — reviewed_schema_or_migration_delta.
- `site/database/migrations/provider_catalog_variant_migrate.php` — reviewed_schema_or_migration_delta.
- `site/database/migrations/provider_package_types_migrate.php` — reviewed_schema_or_migration_delta.
- `site/database/migrations/recurring_billing_checkout_migrate.php` — reviewed_schema_or_migration_delta.
- `site/database/migrations/recurring_billing_migrate.php` — reviewed_schema_or_migration_delta.
- `site/database/migrations/service_plans_checkout_migrate.php` — reviewed_schema_or_migration_delta.
- `site/database/migrations/service_plans_migrate.php` — reviewed_schema_or_migration_delta.
- `site/database/schema/hosting_catalog_schema.php` — reviewed_schema_or_migration_delta.
- `site/database/schema/hosting_checkout_schema.php` — reviewed_schema_or_migration_delta.
- `site/database/schema/provider_catalog_variant_schema.php` — reviewed_schema_or_migration_delta.
- `site/database/schema/provider_package_types_schema.php` — reviewed_schema_or_migration_delta.
- `site/database/schema/recurring_billing_schema.php` — reviewed_schema_or_migration_delta.
- `site/database/schema/service_plans_schema.php` — reviewed_schema_or_migration_delta.
- `site/includes/foundation/recurring-billing/RecurringBillingSchedule.php` — byte_equivalent_packaged_mirror.
- `site/includes/foundation/recurring-billing/RecurringBillingTerms.php` — byte_equivalent_packaged_mirror.
- `site/includes/foundation/twentyi-inventory-reader/InventoryReader.php` — byte_equivalent_packaged_mirror.
- `site/includes/foundation/twentyi-inventory-reader/PackageTypes.php` — byte_equivalent_packaged_mirror.
- `site/includes/foundation/twentyi-inventory/InventoryPackageType.php` — byte_equivalent_packaged_mirror.
- `site/includes/foundation/twentyi-inventory/InventoryRegister.php` — byte_equivalent_packaged_mirror.
- `site/includes/foundation/twentyi-inventory/InventorySnapshot.php` — byte_equivalent_packaged_mirror.
- `site/includes/foundation/twentyi/FixedHttpTransport.php` — byte_equivalent_packaged_mirror.
- `site/includes/layout/catalog.php` — inventoried_not_reviewed.
- `site/includes/layout/hosting_catalog.php` — reviewed_scoped_clauses.
- `site/includes/layout/hosting_fulfillment.php` — inventoried_not_reviewed.
- `site/includes/layout/hosting_order_links.php` — inventoried_not_reviewed.
- `site/includes/layout/provider_plan_catalog.php` — inventoried_not_reviewed.
- `site/pages/services/hosting.php` — reviewed_scoped_clauses.
- `site/portal/controllers/hosting_catalog.php` — reviewed_source_module.
- `site/portal/controllers/hosting_checkout.php` — reviewed_source_module.
- `site/portal/controllers/hosting_purchases.php` — reviewed_source_module.
- `site/portal/views/hosting_catalog.php` — reviewed_source_module.
- `site/portal/views/hosting_checkout.php` — reviewed_source_module.
- `site/portal/views/hosting_purchases.php` — reviewed_source_module.
