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
