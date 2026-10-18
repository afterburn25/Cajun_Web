# 20i catalog and shared hosting storefront

Owner request, 2026-10-18: show **Import 20i Catalog** after a successful connection test; import the catalog, support product artwork replacement, and connect products to ordering and side-by-side Shared Hosting/VPS pages.

## Current source scope

Branch `codex/twentyi-catalog-storefront` starts at capture-corrected `b7c97953f323004e1fdd527bdef0b2da63f01c71` and normally merges the preserved first-invoice checkout source `1db3235286f915935e4ab621e2be5a2f14288997`. The enclosing commit, **Connect 20i catalog import and shared hosting storefront**, includes the following additions. Source integration does not transfer either parent's runtime acceptance to this candidate.

- The current successful saved-connection check exposes Import 20i Catalog. A newer failed or unfinished check, changed settings, disabled connection or missing credentials hides it. Opening the link reads saved local state only.
- The existing explicit direct hosting-catalog refresh is followed by batch import. Each reviewed batch covers a fixed window of at most 50 saved types across all preview pages. Repeating the same window never advances to unseen types. Current owner/password, CSRF, exact observation hash, original import provenance and deterministic SKU collision checks remain required. Inserts, revisions, audit and counts share one transaction. Existing edits and artwork survive reimport.
- Prices, service periods, descriptions and features remain owner choices. A successful connection does not establish complete account-wide product coverage, pricing or entitlement.
- The normal merge retains dedicated owner hosting publication, exact client offer review, service agreement, existing/new project choice and one first unpaid invoice. An invoice is not a verified payment or activated service. Current authority, consent and fulfillment policy stay enforced.
- The public Hosting page and `shared-hosting` alias compare fresh published offers in groups of up to four. Rows align prices, inclusions, exclusions, named limits and support. Each checkout link carries its exact offer identity and fingerprint; login preserves only that closed destination. The existing invitation-based account access remains in place.
- Existing local image uploads/replacements appear in comparisons after the changed offer is reviewed and published again. Historical publication images remain retained. Only local validated media identifiers enter the comparison; private provider details and arbitrary image URLs are excluded.

## Remaining provider scope

Direct `packageTypes` evidence supports hosting IDs and names. The retained cloud catalog response was JSON null; it establishes neither an empty product list nor a VPS schema. Automatic provider artwork fields and download contracts remain unverified. This increment therefore does not supply VPS catalog ordering or automatic 20i image downloads. The complete owner API reference is already retained and must not be requested again.

## Verification and next action

Local checks pass: 932 named portable PHP executions across 16 runs, 40 focused PHP syntax checks, 473 artifact-helper outcomes, 48 synthetic Chromium layout/keyboard checks, canonical shipping/dependency refresh and documentation validation. The real Linux database matrix and installed HTTP/browser/upgrade acceptance remain pending.

Focused portable checks exercise the actual connection helper/view, fixed-window batch service with modeled storage, login controller, import forms and comparison renderer. The new native fixture requires a disposable Linux MySQL/MariaDB database and tests real batch transactions and audit-failure rollback. Synthetic Chromium checks use actual renderer/CSS bytes with local fixture artwork; they are not installed application acceptance.

Initial synthetic-browser attempts retained a missing bundled-browser failure and a test-only Unicode-literal mismatch. The final run uses installed Chrome and the corrected fixture; earlier evidence remains preserved. No application assertion was removed.

The focused `catalog-storefront.yml` matrix preserves the existing import/catalog native fixtures and adds the new cases. Existing full application and hosting acceptance workflows remain intact. Run the first focused and full acceptance on this exact successor, inspect actual packaged admin/client journeys and captures, complete upgrade/recovery and independent review, then integrate through a normal PR. No release or live deployment is claimed. Published1.25.0 remains immutable.

The new nameserver-change lane remains separate at `5654649dc62095298ce703b39b54d12d56c49f09`, draft [PR222](https://github.com/afterburn25/Cajun_Web/pull/222). Devin retains non-20i work. Whole-project completion cannot be inferred from branch counts, portable checks or this catalog milestone; complete VPS lifecycle, provider backups/databases, SSH and broader business workflows remain open.


## Installed storefront continuations

Starting from `9b450c4b8daafd79f4242c39ddd65e2bdb56b624`, the **test: cover installed catalog import and selected-plan checkout** successor adds two case modules and two bounded caller changes. The original main browser driver, full/focused workflows, native recipe and required report inventory stay unchanged. New checks flow into the already required `http-results.json` and `hosting-checkout-http-results.json`; the latter also retains the scoped storefront result. Exact source inverses preserve both complete preceding producers and the entire9b acceptance contract.

The dedicated `ptici_` continuation uses the installed package and original uploaded image. Real owner forms make three explicit connection checks and two catalog refreshes against the existing synthetic loopback TLS server, respecting actual cooldowns and the20-per-hour limit. A125-type catalog yields49,50 and25 new drafts around the existing type. CSRF, password, confirmation, source/hash and offset refusals, fixed-window replay and original row/image preservation are checked without resetting history. Connection and batch forms each require desktop/mobile captures.

The `hcc_` checkout continuation uses the original packaged checkout fixture after its existing cases. Actual forms upload a fixed local PNG, review/publish a new current offer, select it anonymously, sign an already invited client in, refuse a stale fingerprint, and create one first unpaid invoice. The real invoice page and exact retry are checked. Four captures cover desktop/mobile comparison, selected review and unpaid invoice. This fixture has one published plan; it does not replace the earlier synthetic two-to-four-column layout checks or claim open account signup, card collection or provider provisioning.

Local validation:492 artifact-helper outcomes and52 original checkout guards pass. Final local syntax and evidence-preservation checks pass15. Independent source review found a missing sticky-header capture assertion; the corrected helper retains original viewport checks, then measures and verifies the complete target with unobscured heading/action points, recording any capture-only height growth. Actual pixels remain pending. Agent source/wrapper review is portable evidence only; the installed Linux application/browser journeys and their screenshots have not executed here. Original focused [run39148262109](https://github.com/afterburn25/Cajun_Web/actions/runs/39148262109) and full [run39148336514](https://github.com/afterburn25/Cajun_Web/actions/runs/39148336514) are preserved at9b and remain queued. First successor execution, actual capture review, all required runtime/upgrade/recovery checks and release verification remain necessary. No shipping file changes require a new package refresh for this test-only increment.

### Provider contract follow-up

The retained owner reference documents `cloudProviders` GET provider discovery and POST provider-specific specification/zone discovery, without a complete response schema. Neither that text nor existing-instance OS/rebuild fields establish a saleable VPS catalog. The actual `cloudServerProductData` null response remains unexplained. HostShop documents [separate hosting products](https://docs.20i.com/products-services/adding-hosting-products) and [uploaded product images](https://docs.20i.com/hostshop/hostshop-product-image); this does not establish whether a separate artwork API exists. Next investigation targets exact discovery response fields and provider clarification, using the reference already supplied. No provider calls were made for this documentation follow-up.

External investigation receipt: `work/twentyi-full-catalog-provider-contract-review/review.json`, SHA256 `b388147d92f279d21034ee932c602088744c6168b359a15e9a4442663b64c5d9`. Root validation receipts are under `work/catalog-storefront-http-validation/`. These are local continuity records, not released assets or claims of installed acceptance.
