# Imported hosting billing options

This increment lets the owner sell one imported 20i hosting type through separate monthly and annual local products. Each option has its own SKU, price, image, service plan and publication. It continues the catalog and checkout work from `3b96f48f69e1a32370509dc123fee03d5e338cad` on `codex/twentyi-catalog-retail-variants`.

Published 1.25.0 remains unchanged. This source increment does not publish 1.26.0, deploy the website, import an unverified VPS catalog or establish automatic provider artwork support.

## Owner workflow

1. Import a supported hosting type through the existing saved 20i connection and select its product in Catalog.
2. In **Billing options**, enable the additive storage with the current owner password and confirmation. Existing installations do not run the installer again.
3. Open **Add a billing option**, enter a new SKU and choose a monthly or annual service period. The new product starts as an unpriced draft without an image or commercial terms.
4. Configure that draft's price, description and optional local image. Complete its matching hosting plan and review its publication through the existing Hosting offers workflow.
5. Published options appear separately on Shared Hosting. The selected offer determines the unpaid invoice's service period and price.

Changing one option does not edit another option's product, image or existing invoice. The family list includes the original product and links to every additional option. Creating from a child option resolves the same original import. Failed submissions retain their original nonsecret identity, SKU and period; passwords and confirmation are cleared. A stale review stays disabled until the owner explicitly reloads it.

## Data and authority

The existing `catalog_provider_imports` table and its unique provider/type association remain intact. Reimport still returns the original product. The additive `catalog_provider_variants` table binds each new local product to that canonical import, its immutable source and the exact creation request. The existing publication document formats remain unchanged; the canonical import ID and retail product ID are authenticated together.

Creation uses the existing staff mutex and transaction, current owner authority, a fresh product/source review, a unique request identity and an audit witness. Exact completed retries return the original product; changed inputs and revoked authority are refused. A new option does not inherit prices, images or promises from another product.

Missing, partial or altered established variant history stops affected catalog reads, publication and media cleanup. It cannot become an ordinary unbound product or an empty store. The original import and each variant retain independent image revisions; publication and checkout continue using their existing evidence and authorization rules. No provider write, charge, customer message or remote session is introduced.

Guarded page reads validate variant history once for a group of origins, then authenticate every canonical/retail pair. This avoids repeating a full history scan for each option. The internal composition requires the existing owning transaction and does not retain a cache between requests.

## Verification and remaining gates

Portable document and modeled service checks, the existing plan-editor regression, and a loopback HTTP/Chromium fixture exercise the implementation. The browser fixture uses actual dispatch, parsing, rendering and CSRF with explicitly modeled SQL and business services. It is not installed-site acceptance.

The guarded native fixture requires disposable Linux SQL, a fresh `hcvn_` prefix and isolated private storage. It is designed to exercise real canonical import, an original publication and unpaid invoice before additive setup, independent annual publication and checkout, exact retries, audit rollback, media protection and observed mutex contenders. Local Windows syntax and refusal checks do not establish those database outcomes.

The [source-bound local evidence](evidence/catalog-billing-options-local.json) records 57 document and 59 modeled service outcomes in each default/minimal PHP mode, plus the preserved 48 import, 59 batch and 58 setup outcomes per mode. The existing plan editor retains all 68 named predecessor outcomes in both modes. Final local HTTP/Chromium execution passes 69 checks; seven local syntax/platform-guard checks explicitly do not credit native SQL. Independent source review found no material blocker in the recorded bytes.

The canonical installer refresh and shipping PHP/JavaScript checks pass. All 2,941 inventoried dependency hashes match current shipping bytes; this did not build or publish a release from uncommitted source.

The additive acceptance helper passes 758 outcomes, retaining all 642 predecessor outcome objects in their original order. It preserves 181 prior native recipes and adds five, and preserves the original 202 browser / 253 final reports while adding six required reports. It adds no screenshot credit. Both earlier helper-composition failures remain preserved; independent review and exact source checks bind the final configuration.

Native Linux execution, installed application journeys, combined upgrade and release acceptance remain required before integration or publication. Preserve predecessor runs and their failures; queued work is not a passing result.

## Customer billing-period selection

The successor [hosting billing selector](TWENTYI_HOSTING_BILLING_SELECTOR.md) lets customers browse All plans, Monthly or Annual in Shared Hosting and their portal. Selection preserves independently published offer identities, images and full charges. Native/installed acceptance and integration remain pending.
