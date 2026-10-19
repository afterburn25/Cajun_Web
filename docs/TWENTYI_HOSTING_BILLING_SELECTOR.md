# Choose a hosting billing period

Development branch: `codex/twentyi-hosting-billing-selector`, from exact `e7deced05e0b625994ee2facf628ebe337030081` (draft [PR245](https://github.com/afterburn25/Cajun_Web/pull/245)). The enclosing implementation commit is **Add monthly and annual hosting plan browsing**. This increment continues the owner's catalog, comparison and ordering request. Published 1.25.0 remains unchanged; this is not a live deployment or completed 20i integration.

## Customer behavior

Shared Hosting and the client portal's **Available plans** have **All plans**, **Monthly** and **Annual** choices. All plans is the default. Changing the period starts at the first matching page; Previous plans and More plans retain that period. The filter runs before the twenty-plan page limit, so annual offers remain reachable even when more than twenty monthly offers precede them.

Each card keeps its independently published price, image, services, support and terms. An annual price is the full annual charge. Choosing a period does not convert prices, imply a discount, pair different plans, or change an existing agreement. The owner still creates and publishes each [billing option](TWENTYI_CATALOG_BILLING_OPTIONS.md) independently.

**Choose this plan** retains the exact publication and fingerprint for the existing checkout review. Request-only offers lead to the matching portal list, where each request form retains its original publication identity. Checkout still requires the primary client and existing commercial checks. Changing a browsing filter cannot authorize a purchase. Request and invoice history are not filtered or repriced.

Unavailable plans and empty matching pages are distinct. The controls use ordinary links, work without JavaScript, wrap on narrow screens and have visible keyboard focus. The table remains a labeled scroll region on small screens.

## Implementation boundaries

`billing` accepts only `all`, `monthly` or `annually`. Public pagination uses `hosting_page`; portal pagination uses `p`. Only decimal strings from 1 through 100000 are accepted; arrays, unknown billing values and malformed page values are refused. URLs are built from fixed local routes.

The existing read service adds a bound `c.term` predicate before `LIMIT` and `OFFSET`, preserves publication, revision, withdrawal and ordering conditions, and rechecks the projected cadence. All plans retains the original query and result shape. The current provider source and complete import/variant history still govern availability. This change adds no schema, business writer, provider request, credential read, payment or email operation.

## Verification

The [local evidence record](evidence/hosting-billing-selector-local.json) distinguishes actual-source portable checks, a modeled local HTTP/Chromium journey, syntax/platform guards and pending native application execution. The original comparison and checkout-view checks retain their named outcomes. The checkout-view test needed the new pure helper loaded because that fixture extracts controller functions without their normal includes; the initial failure is retained.

Applied-source checks pass 71 billing-query/read outcomes, 52 existing comparison outcomes and 58 existing checkout-view outcomes in each default/minimal PHP mode: 362 executions. Final modeled HTTP/Chromium execution passes 62 checks. Root inspected desktop and mobile captures; the final captures are byte-identical to the inspected versions. The mobile table is deliberately scrolled horizontally, so that capture does not show every column at once. The earlier browser bootstrap and URL-wait harness failures remain retained.

Canonical installer refresh and shipping PHP/JavaScript checks pass. All 2,942 dependency hashes match shipping bytes. This refresh does not publish a release or establish installed-site acceptance.

The new native fixture is restricted to disposable Linux SQL, database `cwp_test`, loopback, a fresh `hcbn_` namespace and private test storage. It creates real published monthly/annual options and exercises filtering before pagination, exact checkout/request identities and preservation of original invoices and private records. Its initial provider catalog observation is synthetic. Windows refusal and syntax checks do not establish native database results.

The additive acceptance helper passes 871 named outcomes, retaining all 758 predecessor outcome objects and their order. All 186 prior native recipes remain, with three added; all 208 browser / 259 final reports remain, with four added. The four workflow/contract/helper files were independently reviewed and copied byte-for-byte from the tested proposal. No workflow trigger, job or capture requirement changed. Existing pending runs are preserved. Native Linux execution, installed application and upgrade journeys, combined integration and release/download verification remain required before publication.

## Coordination

Root owns public/portal presentation, documentation, packaging and GitHub delivery. Catalog owns the query/read service and portable checks. Domains owns native SQL coverage and additive acceptance binding. VPS owns the modeled browser journey and independent review. Devin retains the separate non-20i and licensing assignment.

The separate frozen client directory-listing increment [PR246](https://github.com/afterburn25/Cajun_Web/pull/246), source `b0fe332f391f5586d1a3978e9ae8f21a0605bfba`, is not part of this branch. Preserve it and its first full run [39224585904](https://github.com/afterburn25/Cajun_Web/actions/runs/39224585904). The catalog parent and original catalog/capture runs remain separately pending; no queued result is credited as acceptance.

Next: inspect the first exact-source full run, resolve actual failures without replacing the original evidence, integrate accepted increments sequentially, and complete combined release gates. Full cloud catalog discovery, automatic provider artwork, remote support access and the broader project remain open.
