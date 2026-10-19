# CajunWebPro current status
## 2026-10-19 — nameserver-requests leaf merge preflighted (024b50dc)

On `devin/preflight-commercial` (`84fa341e`), Group B leaf `codex/twentyi-client-nameserver-requests` (`024b50dc`) is merged onto the renewal tip: registered nameservers, client nameserver requests, change/upgrade coverage and provider fixture extensions. Twenty conflicts resolved semantically (PHP union branches, workflow job union, doc unions, contract/report unions, regenerated installer inventory). Acceptance chain re-anchored to a 28-stage nameserver-aware model — nameserver sections at positions 3–6 with per-section pin stages, deep-chain states shifted +4, bridge repins translate deep-section pins across the stage boundary, `merged_stage_delta` projects verified nameserver-free stage bytes for the ten boundary-differing files, and served contract/report objects verify against `merged_stage_*_sha256` pins. Local acceptance harness: **1212 checks pass**. Hosted leaf/cumulative acceptance remains the merge gate.

## 2026-10-19 — renewal-invoicing leaf merge preflighted (632362f6)

On `devin/preflight-commercial` (`b3dbbd15`), Group B leaf `codex/twentyi-renewal-invoicing` (`632362f6`) is merged onto the commercial tip: atomic accepted-renewal invoice creation with retained manual allocation, native recovery and packaged browser evidence. Ten conflicts resolved semantically; acceptance provenance re-anchored — renewal's three native commands (195→198 recipe) and three report pairs persist through every predecessor stage while the deep recipe chain strips them for predecessor literals, all serialized pins/deltas/contract-predecessor hashes re-anchored on merged bytes. Local acceptance harness: **967 checks pass**. Hosted leaf acceptance plus cumulative integration acceptance remain the merge gates.

## 2026-10-19 — commercial Group B leaf merge preflighted (d2903bfb)

On `devin/preflight-commercial` (`1c7234da`), the largest Group B leaf `codex/twentyi-installed-billing-options` (`d2903bfb`) is merged into the integration tip `6832dde3`: installed monthly/annual hosting billing, catalog storefront, billing selector, retail variants, purchase inbox, imported plan setup, checkout and joined purchase, integrated alongside the non20i A-group work. All 22 merge conflicts resolved semantically; the acceptance-provenance chain was fully re-anchored onto merged bytes (22 source deltas rebuilt, all serialized pins/repins re-anchored via a 24-stage file-state model, nine-section contract-predecessor strip chain converged to fixpoint, commercial-stage recipe literals +5). Local acceptance harness: **964 checks pass**. Hosted application acceptance on `d2903bfb` (run `39297756510`, dispatched) is the remaining gate before merging to `devin/full-site-integration`.

## 2026-10-19 — renewal invoicing implementation

The [renewal invoicing increment](docs/TWENTYI_RENEWAL_INVOICING.md) adds an owner action to create and reserve the exact accepted renewal fee in a selected client project. Existing manual allocation and separate payment/provider approval remain. Branch `codex/twentyi-renewal-invoicing` starts at `a86f6f4f`; this is a candidate, not a published release. Source, portable, native and installed browser evidence must remain distinct. Full 20i, full product/image catalog import and 1.26 publication remain unfinished.


## 2026-10-19 — Group A non-20i integration merged (A1–A25)
## 2026-10-19 — installed monthly and annual purchase coverage

Branch `codex/twentyi-installed-billing-options` continues exact billing-selector826 after normal development main-sync0e813d21. Add the real packaged owner-to-client monthly/annual purchase journey after the existing purchase inbox, with separate read-only billing observations and original financial-history retention. [Scope, evidence and next action](docs/TWENTYI_INSTALLED_BILLING_OPTIONS.md). Shipping source and published1.25 are unchanged; installed runtime acceptance remains pending.

## 2026-10-19 — monthly and annual hosting plan browsing

Branch `codex/twentyi-hosting-billing-selector` continues exact `e7deced05e0b625994ee2facf628ebe337030081` (draft [PR245](https://github.com/afterburn25/Cajun_Web/pull/245)). The enclosing commit is **Add monthly and annual hosting plan browsing**. Shared Hosting and portal Available plans gain All plans / Monthly / Annual links and matching pagination. Filtering happens before the page limit; each offer retains its own full price, image, terms and exact checkout identity. Existing requests and invoices remain unchanged. [Behavior and evidence](docs/TWENTYI_HOSTING_BILLING_SELECTOR.md).

Root owns UI, documentation, packaging and integration; Catalog owns the read service, Domains native coverage and acceptance binding, and VPS the modeled browser journey and independent review. Local portable/model checks and packaging are recorded separately from pending Linux SQL, installed application, upgrade and release gates. The initial extracted-fixture dependency failure and browser harness failures remain retained. Published 1.25.0 and the live site are unchanged. Devin retains non-20i/licensing.

Preserve the separate frozen directory-listing source `b0fe332f391f5586d1a3978e9ae8f21a0605bfba` ([PR246](https://github.com/afterburn25/Cajun_Web/pull/246)) and original queued runs. Next: inspect this increment's first exact-source full run, resolve actual failures, integrate accepted branches sequentially and complete combined release verification. Queued work is not acceptance; no queue-driven restart or cancellation.

## 2026-10-19 — separate monthly and annual hosting offers

Branch `codex/twentyi-catalog-retail-variants` continues exact parent `3b96f48f69e1a32370509dc123fee03d5e338cad` (PR235). The enclosing implementation commit is **Add independent billing options for imported hosting**. Owners can create separate unpriced monthly/annual drafts for one retained 20i import, configure independent prices/images, and publish through the existing plan and checkout workflow. Canonical reimport identity and existing invoices remain unchanged. Missing established variant history fails closed. [Behavior and verification](docs/TWENTYI_CATALOG_BILLING_OPTIONS.md).

Root owns UI, catalog selection, documentation, packaging and integration. Catalog owns additive variant records and service checks; Domains owns the guarded native fixture and additive acceptance binding; VPS owns actual-source modeled HTTP/browser checks and independent review. Local portable/model evidence is distinct from pending Linux SQL, installed application, upgrade and release acceptance. The first plan-editor model failure and browser-fixture styling failures remain preserved; corrections do not erase them. Published1.25.0, existing release assets and the live site are unchanged.

## 2026-10-18 — align checkout fulfillment controls

The successor from `3042f24eddc3587d67ac5d10e28df3b3294a3310` on `codex/twentyi-checkout-fulfillment-controls` removes an unsupported manual-fulfillment choice from checkout-origin hosting agreements. The existing server already requires a verified receipt for those purchases. The owner forms and provider-setup guidance now follow that rule; eligible legacy orders retain their manual controls. [Correction and evidence](docs/TWENTYI_HOSTING_PURCHASE_INBOX.md#checkout-fulfillment-controls).

Actual renderer regressions pass 94 checks per PHP mode and preserve all 80 previous outcomes. Installed owner navigation and full candidate acceptance remain pending; a portable render is not an installed-site result. Release scope now describes the already-implemented storefront, guided plan setup, checkout and purchase inbox instead of the earlier catalog-only scope. Published 1.25.0 and production remain unchanged.

## 2026-10-19 — installed hosting purchase navigation

Branch `codex/twentyi-purchase-inbox-browser`, parent `7535f861c2c3accf37482f5af94099780c7806a6` (draft [PR229](https://github.com/afterburn25/Cajun_Web/pull/229)); enclosing commit **fix: verify hosting purchase navigation and invoice links**. This bounded acceptance increment continues the existing packaged checkout, storefront and plan-setup journeys through the owner and primary-client purchase inbox. It follows the original invoice, project and agreement links, distinguishes the inherited paid/accepted order from unpaid purchases, and checks unchanged commercial history across navigation and access refusals. The inherited setup card's owner **Review invoice** link now selects the original invoice through `invoice-payments` / `invoice_id`; its previous `payments` / `invoice` URL opened the general payment-request page. The client destination is unchanged. Published releases are unchanged. [Scope and actual verification](docs/TWENTYI_HOSTING_PURCHASE_INBOX.md#installed-purchase-navigation).

Applied local checks pass 631 artifact-helper outcomes, preserving all 584 predecessor objects and their order, 52 original checkout guards, and 80 actual renderer/controller checks in each default/minimal PHP 8.4.25 mode. The browser proposal also retains 49 helper/synthetic-layout checks and 26 final source/predicate checks; these are not installed application evidence. Canonical refresh, touched PHP/Python syntax and documentation checks pass. The 181 native commands, 202 browser reports and 253 final reports are unchanged; four new inbox captures are required through the existing checkout report. Inspect the first automatic successor full/focused Linux runs and their original outputs next. Native SQL, installed HTTP/Chromium, actual capture review, supported upgrades, final integration and release acceptance remain pending. Do not cancel or rerun the preserved parent jobs merely because they are queued.

## 2026-10-18 — hosting purchase history and setup handoff

Continue on `codex/twentyi-hosting-purchase-inbox` from exact `0ddbd23d6489e2b9445304123e214d0a2e188f31` (draft PR228). The enclosing commit, **Connect hosting purchases to existing setup**, adds owner and primary-client purchase lists with original invoice/project/agreement links and separate local setup status. The existing owner order form selects the original checkout invoice even after 51 newer same-price invoices; legacy invoice selection is unchanged. No new schema, POST action, provider request or payment writer is added. Actual-source modeled checks and syntax pass locally; native Linux SQL, installed navigation, combined upgrade and final release acceptance remain pending. The additive workflows preserve predecessor commands, reports and captures. Published 1.25.0 and original queued runs remain unchanged. The first local test harness incorrectly tried loading built-in Windows modules dynamically; its failed output is preserved and the harness now uses plain minimal PHP. [Behavior, evidence and remaining gates](docs/TWENTYI_HOSTING_PURCHASE_INBOX.md).

## 2026-10-18 — guided imported hosting plan setup

Packaging follow-up from `787c7f3225dd21757d5fd3a6ec9febb86230d905`, draft [PR228](https://github.com/afterburn25/Cajun_Web/pull/228): the first clean local build refused two shipping files whose Windows working copies had mixed line endings. Their application content already matched Git exactly after LF normalization. Restored only those two local copies to their committed bytes and refreshed the two installer hashes; no feature logic or acceptance source changed. The enclosing commit is **Align installer inventory with committed storefront bytes**. Original source/runs and the failed build receipt remain preserved. Byte comparison verifies all 2,928 non-inventory shipping files against Git and every generated hash; all 21 feature/test pins are unchanged and documentation checks pass. Recheck the exact committed package and first successor CI results before integration or release.

Continue from exact catalog storefront source `460eebfb5d8d87c3816576aeb7289bfa90478668` on `codex/twentyi-imported-plan-setup`. The enclosing commit, **Connect imported hosting products to plan setup**, adds the owner form and atomic matching-plan/activation/provider-mapping composition. Saved product pricing and imported type/profile remain authoritative; exact retries preserve the original records and expiry. The existing offer preview, separate publication and invoice checkout remain in place. [Behavior, verification and remaining gates](docs/TWENTYI_IMPORTED_PLAN_SETUP.md).

Applied-source portable/model checks and local synthetic-data Chromium layout checks pass. The independent review found and corrected a form-fallback database re-read; the original proposal remains preserved externally. Linux native transactions, installed HTTP/browser execution, supported upgrades and final release acceptance remain pending. Published1.25.0 and original queued runs are unchanged. Complete VPS catalog discovery, automatic provider artwork, remote SSH and whole-project completion remain open.

## 2026-10-18 — installed catalog storefront coverage

Continue `codex/twentyi-catalog-storefront` from `9b450c4b8daafd79f4242c39ddd65e2bdb56b624`, draft [PR223](https://github.com/afterburn25/Cajun_Web/pull/223). The enclosing commit, **test: cover installed catalog import and selected-plan checkout**, adds two actual packaged HTTP/Chromium continuations. Existing main browser, full/focused workflows, native commands and report inventory are unchanged; the new outcomes are required through the existing HTTP reports. Shipping files and published1.25.0 are unchanged.

The prepared journeys cover current-success import navigation, settings/failed-check invalidation, 125-type fixed-window import and retries with retained artwork, then actual image upload/publication, anonymous plan selection, invited-client login, stale-offer refusal and one unpaid invoice. Eight new captures are required. This is test implementation, not completed installed-browser acceptance. [Details and next steps](docs/TWENTYI_CATALOG_STOREFRONT.md#installed-storefront-continuations).

Local artifact preservation checks pass492 named outcomes and the original checkout input/output guards pass52. Final Python syntax and evidence-collision checks pass15, including all four additional checkout image paths. The original9b first focused run39148262109 and full run39148336514 remain queued. Preserve both; the changed test source needs its own first execution, not cancellation or rerun of either original. Next: review the successor's actual Linux native/HTTP outcomes and captures, complete upgrade/recovery and remaining runtime gates, then integrate/release. Complete VPS catalog, automatic provider artwork, SSH and whole-project completion remain open.

## 2026-10-18 — catalog import and shared hosting storefront

The owner's latest catalog flow is implemented on `codex/twentyi-catalog-storefront`, starting at `b7c97953f323004e1fdd527bdef0b2da63f01c71` and normally joining checkout source `1db3235286f915935e4ab621e2be5a2f14288997`. The enclosing commit is **Connect 20i catalog import and shared hosting storefront**. Current-success import navigation, bounded fixed-window draft batches, safe public comparisons, replaceable local artwork and exact-offer login handoff are connected to the dedicated hosting checkout. [Scope and remaining gates](docs/TWENTYI_CATALOG_STOREFRONT.md).

Local checks pass: 932 named portable PHP executions across 16 runs, 40 focused PHP syntax checks, 473 artifact-helper outcomes, 48 synthetic Chromium layout/keyboard checks, canonical shipping/dependency refresh and documentation validation. The real Linux database matrix and installed HTTP/browser/upgrade acceptance remain pending. Complete VPS catalog mapping, automatic provider artwork, remote SSH and whole-platform completion are not claimed. Published1.25.0 stays unchanged. Preserve first original runs; do not cancel or rerun them merely because they are queued. Next: finish local checks, push a draft PR, run first focused/full successor acceptance, and review actual results before release.

## 2026-10-18 — complete captured-image inventory and StackCP correction

All 340 original captured extents now have finite visual receipts: 324 browser images and 16 Apache images, with no duplicate or unassigned browser images. This closes the captured-image inventory, not the content hidden by overlays or outside scrolling regions. The [review and correction record](docs/RELEASE_CAPTURE_READINESS_1.26.0.md#complete-captured-image-inventory-and-stackcp-correction) retains those limits.

Two StackCP test fixtures now check the original desktop/mobile layouts first, then capture five tall mobile cards below the header and focused skip link. Five new checks verify complete card bounds and unobscured hit points. Twelve synthetic local Chromium cases and all 262 artifact-helper checks pass; actual corrected application images remain pending. No shipping code or workflow changes. Published 1.25.0 remains the latest verified release.

The owner reference is retained; no resend is needed. Automatic provider artwork and complete cloud catalog mapping remain unresolved. A bounded [Devin PR198 source follow-up](https://github.com/afterburn25/Cajun_Web/pull/198#issuecomment-6251229265) confirms its two previous defects are addressed in source; queued native/HTTP/upgrade acceptance still prevents integration clearance.

## 2026-10-18 — further candidate image review

The direct hosting catalog candidate remains under release verification. First full capture-corrected [run39134909145](https://github.com/afterburn25/Cajun_Web/actions/runs/39134909145) is queued at immutable `78f45bd8cda7df89494c4ecaaa762ea14170227f`; the original f67 final aggregation is also queued. Neither was rerun or replaced. Published 1.25.0 remains the latest verified release.

Another 115 original browser captures have finite visual receipts: StackCP14, mail23, website delivery/licensing12, client/activity/support14 and hosting commerce52. Five StackCP mobile section captures obscure upper content beneath the sticky header; those areas remain unverified. Minor spacing/inner-card overruns and companion-covered section gaps are recorded separately. This is visual progress, not full release acceptance. [Evidence and remaining work](docs/RELEASE_CAPTURE_READINESS_1.26.0.md#further-image-review--2026-10-18).

The complete owner API reference is retained and its hash was rechecked. Official documentation confirms separate HostShop product and image setup without supplying the missing reseller catalog image contract. No resend is needed; automatic provider images and cloud catalog imports remain unfinished. No application, test, workflow, released asset or production setting changes in this checkpoint.

## 2026-10-18 — capture readiness and actual candidate image review

Original candidate `f67b1631683b29096dd8ec1daee405e104dc0b83` has successful build, Apache, native/browser and recovery jobs in [run39098482550](https://github.com/afterburn25/Cajun_Web/actions/runs/39098482550); final acceptance remains queued. The retained browser partition contains 167 reports and 20,749 passing named executions, including 3,149 HTTP checks. These results do not certify all remaining runtime or release gates.

On separate `codex/release126-capture-readiness`, actual image inspection led to test-only corrections: reveal motion content and decode images before the fresh-root capture, prove the image-save button reachable at the original viewport before capturing its complete card, and wait for the mobile domain-search drawer to close. Shipping files are unchanged. Local Chromium root probes pass 25 named checks; a separate domain capture probe preserves ten original viewport outcomes. The existing artifact helper suite passes 262. Corrected full-application captures remain pending. [Evidence, limits and next steps](docs/RELEASE_CAPTURE_READINESS_1.26.0.md). Published 1.25.0 and original candidate runs remain intact; no deployment occurred.

## 2026-10-18 — recovery test startup correction

The hosting first-invoice branch corrects the test server's loopback preflight and retains a bounded startup stage/errno when startup fails. The original application actions and cleanup remain unchanged. Six portable checks and all 466 existing artifact-helper checks pass; two real Linux socket regressions are now mandatory in the focused workflow. Corrected native/application/browser execution is still pending. [Evidence and scope](docs/TWENTYI_HOSTING_FULL_ACCEPTANCE.md#recovery-startup-correction-and-original-outcomes).

The historical 512 run remains failed overall. Its four native matrix lanes have 28 verified reports with 3,168 exact named outcomes; its packaged recovery passed the 66-check absent phase before an OSError in the next phase. The original errno and exact startup stage were not retained, so TIME_WAIT is a hypothesis, not an established cause. Published 1.25, production and all original runs remain unchanged.

## 2026-10-18 — packaged checkout tests and truthful history labels

The client checkout result links now show the actual confirming client and distinguish the application's automatic agreement activation. The old schedule view assumed a staff creator and would emit a PHP warning for checkout schedules; its correction preserves legacy staff history. Actual renderer checks pass 58 cases in each PHP mode, including all 46 preceding cases. [Current checkout scope](docs/TWENTYI_HOSTING_FIRST_INVOICE.md).

Packaged owner/client HTTP and Chromium tests are now wired into the focused and full application workflows. They cover publication, owned/new project choice, first invoice now, exact retry, result links, CSRF and other-client/contact-context/support refusals, plus 12 desktop/mobile captures. These are prepared tests, not completed browser outcomes. The native continuation honors the real 30-second refresh cooldown; no clocks or production safeguards are bypassed. Full recipe/report preservation guards retain every preceding test. First source 7703 run 39120172123 and all earlier CI evidence remain unchanged; Linux checkout, visual inspection, upgrade and release acceptance remain pending.

## 2026-10-18 — integrated hosting checkout, runtime acceptance pending

The development checkout now connects explicit owner opt-in, the client project/review/confirmation screens, and one guarded transaction for client consent, a recurring schedule and the first unpaid invoice. Exact completed retries return the original records; inconsistent or missing financial history is refused. Checkout-origin fulfillment requires verified payment and the original provider mapping. Existing request-only offers retain their behavior. [Scope and remaining acceptance](docs/TWENTYI_HOSTING_FIRST_INVOICE.md).

Actual integrated-source PHP8.4.25 checks pass130 commercial-document,65 subscription,92 billing,46 portal-render/form and119 legacy-publication cases in each default/minimal mode. These are pure/model/renderer checks, not a native checkout or browser run. The first native Linux checkout test is prepared for the focused PHP8.2/8.4 × MariaDB10.11/MySQL8.4 workflow. Its protected provider-key setup cannot run faithfully on this Windows host. Packaged same-clock browser flow, complete-suite wiring, supported upgrade/recovery and release acceptance remain pending. Published1.25 and every original CI source/run remain unchanged.

## 2026-10-18 — recurring checkout storage verified locally

The hosting first-invoice branch now preserves the actual client on checkout schedules and their first invoice. Explicit owner migration retains legacy records, while stored-history validation binds the exact consent, schedule and independent invoice origin. The applied source passes66 native PHP8.4.25/MariaDB10.11.16 checks in a fresh isolated database. [Scope and remaining work](docs/TWENTYI_HOSTING_FIRST_INVOICE.md).

The original test stopped after34 checks at a fixture audit lookup; its output and database remain preserved. The corrected fixture keeps all assertions. These checks cover migration and synthetic retained history, not a completed client purchase. Atomic checkout creation, owner/client pages, verified-payment fulfillment integration and full release acceptance remain pending. Published1.25 is unchanged.

## 2026-10-18 — subscription migration verified locally

The hosting first-invoice branch adds an explicit owner migration for actual client/system attribution while preserving legacy subscriptions and audits. The applied source passes58 native PHP8.4.25/MariaDB10.11.16 checks, including interrupted DDL recovery, rollback of the final marker/audit, original-record preservation and actor/history corruption refusals. [Exact scope](docs/TWENTYI_HOSTING_FIRST_INVOICE.md).

The first test run exposed a fixture identifier bug after12 checks; its output and database are preserved. The corrected fixture passed in a fresh isolated database. This is bounded migration/history acceptance, using synthetic format2 retained rows. Authenticated checkout composition, first invoice creation, screens, other runtime combinations and release acceptance remain pending. Published1.25 is unchanged.

## 2026-10-18 — hosting first-period billing groundwork

The hosting first-invoice branch now combines client-attributed subscription documents with recurring terms derived from the exact retained consent. Its separate checkout path starts period0 on the accepted local date; legacy activation and later future-period rules remain unchanged. [Implementation and remaining work](docs/TWENTYI_HOSTING_FIRST_INVOICE.md).

Parent `e6b97ec3bf6dc7298a63971d371367f98524edec` already records the subscription increment. Applied recurring tests pass92 new,372 existing terms and321 existing schedule cases per PHP mode; legacy outputs match the baseline. This checkpoint does not create an invoice or expose checkout. Native storage, the atomic writer, customer screens and release acceptance remain pending; published1.25 is unchanged.

## 2026-10-18 — client-attributed hosting subscription documents

Starting from `9f4b374682919ff065d5127de914e87fe04eb2c2`, the hosting first-invoice branch now retains the actual client as the checkout subscription creator and separates explicit client consent from automatic local activation. Existing subscription format1 behavior remains unchanged. [Scope and remaining integration](docs/TWENTYI_HOSTING_FIRST_INVOICE.md).

Applied-source checks pass65 new subscription and67 existing cases in both PHP modes, plus the related130 checkout and119 publication/request cases. The legacy subscription output matches the baseline exactly. This is pure document groundwork: the native schema, atomic invoice writer and customer checkout page are not yet implemented in this checkpoint. Published1.25, original CI sources and live services remain unchanged.

## 2026-10-18 — hosting checkout consent groundwork

Branch `codex/twentyi-hosting-first-invoice` starts at `0c7cbae34220717c72e2f3dbeed1b41ca3545dbf` and adds pure documents for an explicit owner checkout policy and one client service/billing confirmation. Existing request-only publications retain format1 behavior; format2 checkout offers cannot enter the legacy request/conversion path. [Implementation and remaining work](docs/TWENTYI_HOSTING_FIRST_INVOICE.md).

The new review fixes local-midnight and skipped-date boundaries, binds the exact plan/price/client/project/date and keeps private provider facts out of the display projection. Applied-source PHP8.4.25 checks pass130 new and119 existing cases in both default and minimal modes. This stage adds no checkout page, database migration, invoice creation, charge or provisioning. Native atomic composition, actual actor storage, same-clock browser acceptance and release gates remain pending. Original full-acceptance0c7, hosting512/10142/f67 runs and published1.25 remain preserved.

## 2026-10-18 — main CI triggers and hosting development remain separate from release approval

This integration combines hosting development source `4e35059b5a3966be33e80fc7e7bd2115a19ce11a` with main `e397d9a6e293850e8dff61a593e3ba39ee178400`, which normally merged PR218. The ten focused workflow path filters are retained with their original jobs; all hosting shipping files, tests, acceptance contracts and full application workflow remain exact to the development parent. Both histories are preserved by a normal main-to-development merge.

The separate f67 1.26 release candidate, original 512/run39104617939 and 10142/run39108671173 remain their original sources and runs. Dated queue observations below keep their original cutoff; this merge supplies no new runtime result. Published 1.25 assets remain immutable. This is not the f67-to-main release merge, hosting acceptance or publication. Continue the existing original-result reviews and [hosting acceptance gates](docs/TWENTYI_HOSTING_FULL_ACCEPTANCE.md); Devin correction work remains as recorded below.

## 2026-10-18 — Devin integration review and correction handoff

Starting at hosting acceptance source `fc5a3fc6391a5d85dfee01060b5806d04dae192a`, reviewed current Devin contacts/activity, payment-link and mail restacks. [Concrete findings and invoice restoration proposal](docs/DEVIN_RESTACK_REVIEW_2026-10-18.md) identify missing contact billing authority, dropped main invoice guards/navigation, payment-upgrade prerequisites, overly broad retained-setting exceptions and stale fixture bindings. Previously reported mail defects remain unresolved. No Devin branch is merged or certified by this review.

Source/model and PHP syntax checks pass within their documented scope; native database, HTTP/browser and combined release acceptance remain pending. Original f67 full runs have successful builds and queued remaining jobs; original512/run39104617939 and10142/run39108671173 remain queued at the latest 2026-10-18 check. The hosting source and immutable published1.25 assets are unchanged. Next: Devin corrects owning branches and propagates shared fixes; Codex retains original hosting results and continues exact-source acceptance before integration.

## 2026-10-18 — full hosting acceptance integration in development

Branch `codex/twentyi-hosting-full-acceptance` starts at `10142c7e97c8ba9986e0c18d9ab0190ccc432727` and connects the reviewed hosting offers, recovery, eventual purchase and owner/client handoff tests to the existing complete acceptance workflow. It also extends the real published 1.25 upgrade in the same four Apache contexts. [Exact scope and remaining gates](docs/TWENTYI_HOSTING_FULL_ACCEPTANCE.md).

The 23 test/CI/contract files pass independent source review and local portable checks: 385 artifact-helper outcomes retain all previous 342, 43 new upgrade guards pass, and touched Python/PHP syntax passes. These results do not establish database, HTTP, Chromium, screenshot or release acceptance. No shipping file, version, schema or live service changes.

Original focused hosting source `28351f71` is separately verified from run39100808137: 18 reports, 2,043 named outcomes and ten reviewed captures. Later original512/run39104617939 and10142/run39108671173 remain queued at the 08:22 UTC observation. Their source branches and runs are preserved. The f67 1.26 release candidate and published1.25 assets remain separate. Next: retain original focused outcomes, fix concrete failures, then run complete acceptance for the final combined source. Full catalog coverage, automatic provider images and immediate first-payment checkout remain unfinished.

## 2026-10-18 — joined hosting purchase and client-access tests

The portal-handoff branch adds a disposable purchase test using actual application wrappers, followed by packaged owner/client browser navigation. The native sequence covers imported offer, client consent, due invoice, verified synthetic receipt, fulfillment, confirmation and explicit package assignment. The browser continuation uses the real owner Unlink form and guided re-link, then checks correct-client visibility and other-client exclusion. [Scope and limitations](docs/TWENTYI_HOSTING_CLIENT_ACCESS.md#joined-purchase-and-browser-continuation).

Starting source `26b57f4412db12117729901280372e2fd58f6cde`. The preparation child alone uses a two-day process clock offset; remaining processes and the database keep real time. This tests eventual recurring purchase, not immediate checkout. Existing focused native/recovery/browser assertions remain intact. Private runtime state is excluded from uploaded evidence.

Source review and local portable/syntax checks pass. Actual Linux clock/database and packaged HTTP/Chromium execution, ten new screenshots, full-suite integration and release gates remain pending. Original 1.26 and hosting512 runs are preserved; published 1.25.0 remains unchanged. Next: retain the first combined run and diagnose its actual outcomes without replacing original evidence.

## 2026-10-18 — preserve the hosting package a client requested

The separate `codex/twentyi-hosting-portal-handoff` branch now binds catalog-origin order preparation and saved-link validation to the provider mapping retained in the original published offer. A later mapping for the same retail plan, or a manual preparation submission, cannot silently replace it. Owners see the original mapping or guidance to create a new offer and client agreement; historical validation does not require that old mapping to remain currently available. Ordinary non-catalog orders and explicit billing project consent retain their existing behavior.

Starting source `bde6cc1c4fa8a4230085ed3e4f99f05d6df0f5da`. Five production files pass syntax and independent source review; 37 modeled checks pass in each PHP mode, and six real fixture dependency loads pass. The canonical installer refresh/full syntax check and 1,912 local documentation links also pass. These are not SQL, browser or complete purchase acceptance. Original release and focused hosting sources/runs are preserved. The joined purchase test is being prepared separately and must measure the repeated historical-read cost. [Behavior and remaining checks](docs/TWENTYI_HOSTING_CLIENT_ACCESS.md).

The separate 1.26 candidate now has verified maintained compatibility: 54 reports, 3,280 named checks, all five original jobs passing. Full application/browser, recovery and release acceptance remain pending in [PR216](https://github.com/afterburn25/Cajun_Web/pull/216). Published 1.25.0 remains unchanged.

## 2026-10-18 — client hosting access handoff in development

The separate `codex/twentyi-hosting-portal-handoff` branch adds a saved-assignment card to confirmed hosting orders. It identifies the expected client, shows whether the package is linked or needs review, and opens the existing provider inventory at that package. Existing owner linking and provider refresh actions remain responsible for access changes. [Scope and validation](docs/TWENTYI_HOSTING_CLIENT_ACCESS.md).

This starts from hosting successor `512656850e1d9855b921ab4e3b9b4a891b636b16`; it is separate from both the unchanged 1.26 release candidate and the original focused hosting run. Local projection/renderer checks pass in both PHP modes; native database, browser navigation and the joined purchase-through-client-access flow remain pending. Published 1.25.0 remains unchanged.

## 2026-10-18 — packaged owner recovery checks prepared

The hosting successor adds an explicit HTTP-test opt-in after its exact-source COMPLETE and UPDATE packages are built. Four isolated installed copies exercise the real staged owner page with absent, empty, published and withdrawn hosting history. Tests cover package validation, CSRF, current owner/password, backup confirmation, permitted recovery and retained-history refusal. They verify business rows, table definitions, files and private media while separately checking expected authentication/rate/audit bookkeeping. [Scope and limits](docs/TWENTYI_HOSTING_OFFERS.md#file-recovery).

All 88 original hosting assertions and the separate 44-case native recovery continuation are preserved. Source/syntax review, 57 portable checks and 18 modeled bookkeeping checks per PHP mode pass; actual packaged SQL/HTTP results remain pending. This uses complete synthetic journals with real same-source packages, not a published-baseline or interrupted-upgrade acceptance result. The 1.26 release candidate remains separate. CI-only [PR218](https://github.com/afterburn25/Cajun_Web/pull/218) is merged on main; its trigger changes do not alter these preserved original runs.
## 2026-10-19 — component filters and verified queue cleanup

All 25 reviewed Devin non-20i slices are sequentially merged into `devin/full-site-integration` (tip `9896b1f0`, based on main `1067757d`). Families: mail (replies/flags/attachments/copies/HTML compose), contacts and account permissions, protected previews, stage history, payment links + isolation, backups runtime/archive/restore-drill/database-export, project progress and planning, design-payment agreements, recurring billing, notifications (announcements/events/outbox), support tickets + notifications, service plans, pipeline, proposals, revisions, comparison registry + sources, billing calendar, homepage device depth, and the support-access contract foundation.

Each merge preserved HEAD's contact-aware permission gates (`contact_can`, `client_id` scoping), `author_type`/`sort_sequence` UNION column parity, read-locked activity records, support-session refusals and the complete 152-command predecessor native recipe plus five additions in order (157 commands). Installer inventory regenerated per merge; `tools/acceptance-contract.json` rebuilt semantically and pinned to actual file hashes — 229/229 artifact checks on the final tree. Local suites: mail 35/49/71, recurring forms 76/76, design-payments 85, service-plans 83, pipeline 31, proposals 35, revisions 30, comparison-facts 30, support-access 37.

Two real defects fixed during integration: a closure missing the `$user` capture in the merged stage insert (`studio.php`), and recurring-forms validation placed in the POST path instead of the GET query parser. The A16 contract was initially committed with conflict markers; amended to `1f217bdd` (force-with-lease on the Devin-owned branch).

Hosted combined acceptance is triggered by the integration-branch push filter. The run on pre-fix `9896b1f0` (39297205239) was cancelled as stale; the runs on `ceb06c05`-era tips are authoritative. Nothing is merged to main, released or deployed yet.

Follow-on corrections after Group A: `cb8d2117` fixed notification dispatch shadowing in the admin action dispatcher (`payments_notification_*` before the generic `notification*` handler); `ceb06c05` removed a fatal duplicate `require` of the notification layout in the portal router.

Unported Group C components were extracted as component-only ports on the integration tip — `devin/vps-uncertainty-port` (PR #257, 311 checks), `devin/ssh-access-port` (#258, 70), `devin/provider-catalog-port` (#259, 50 catalog + 171 provider), `devin/domains-port` (#260, 81) — all green locally, awaiting hosted runs. Verified-superseded PRs closed in bulk: #102–#125, #32/#36/#37, #43–#46, #60/#61, #109/#111, and all 25 Group D ancestor PRs. ~101 open PRs remain, nearly all Group B.

Known remaining: Group B Codex 20i drafts still pending observed CI (runs dispatched; GitHub-hosted concurrency starvation serializes the queue); normal merge into `main` follows combined acceptance; the non-20i/20i gap review follows after the combined run.

## 2026-10-19 — contacts native fixture correction
## 2026-10-19 — payment-link view correction

A separate companion to Devin's [PR202](https://github.com/afterburn25/Cajun_Web/pull/202) prevents revoked, superseded, expired or changed payment links from exposing current invoice metadata or a stale amount due. Prepared links display the same invoice snapshot used to validate their original fingerprint and client/project binding. [Scope, reproduction and remaining acceptance](docs/PAYMENT_LINK_VIEW_REVIEW.md). This branch starts at `750f1ab8`; it is not the main branch or a release. Native and installed-browser acceptance remain pending.

## 2026-10-18 — client nameserver request candidate

The first clean candidate build at63444bcd exposed an inherited package failure: the four tracked catalog diagnostic scripts were outside the builder's development-source allowlist. A bounded correction admits those four exact paths to FULL-PROJECT, retains every private-file/symlink/source-byte guard, and adds22 passing portable path-policy checks. Site files and installer inventory are unchanged. Corrected package and exact-source GitHub acceptance remain due.

Primary-client nameserver requests now connect submission/withdrawal, owner review, the existing one-send approval and separate readback to client-safe status. This is source on `codex/twentyi-client-nameserver-requests`, based on af78abd8 / [PR222](https://github.com/afterburn25/Cajun_Web/pull/222). [Exact behavior and evidence](docs/TWENTYI_CLIENT_NAMESERVER_REQUESTS.md).

Applied local checks pass 1,034 named portable/model executions, 91 loopback TLS checks, 39 upgrade guards and 473 acceptance-helper checks, plus canonical installer/dependency verification. The native, packaged browser and installed update/recovery producers are wired into GitHub acceptance. Synthetic layout checks pass; actual SQL, authenticated browser, installed update/recovery and exact-source GitHub acceptance remain pending. Published1.25 is unchanged; this is not a release or deployment. Hosting purchase inbox PR229 at7535f861 remains separate, with both original acceptance runs queued at the latest check. Complete20i remains unfinished.

## 2026-10-18 — installed nameserver change acceptance prepared

The separate nameserver-change branch now includes packaged owner-form/Chromium tests, eight scoped desktop/mobile captures, and a published-1.25 UPDATE/recovery producer for original request, ledger, readback and pending-fence history. This is acceptance preparation for source `5654649dc62095298ce703b39b54d12d56c49f09`, recorded by the enclosing **test: cover installed nameserver change recovery** commit. Application shipping files and published 1.25 are unchanged.

Actual root checks pass 80 isolated TLS fixture outcomes, 31 portable upgrade guards, 334 acceptance helpers and 19 syntax/source/inventory checks. Installed SQL, actual application forms, resulting browser images and real update/recovery execution remain pending. [Scope and remaining gates](docs/TWENTYI_REGISTRAR_NAMESERVER_CHANGES.md#installed-acceptance-producers--2026-10-18).

## 2026-10-18 — nameserver change candidate

The separate `codex/twentyi-nameserver-changes` branch adds owner-approved complete-set nameserver changes, durable request/ledger history, no automatic resend, explicit follow-up reads and narrowly scoped interrupted-history recovery. Controls are grouped inside existing domain cards; global history survives unlinking. [Scope, actual evidence and remaining gates](docs/TWENTYI_REGISTRAR_NAMESERVER_CHANGES.md).

Local checks pass1,350 portable PHP executions,30 changed-file syntax executions,18 synthetic responsive-layout checks and283 acceptance helpers. Native SQL, packaged owner write forms/browser, installed request-history recovery and release acceptance remain pending. Canonical installer refresh and documentation checks pass. Published1.25 and the full retained API reference are unchanged; no live provider call or deployment occurred.

## 2026-10-18 — nameserver packaged acceptance wired

The nameserver candidate now includes actual owner-form/Chromium checks, eight responsive captures, and a separate published-1.25 update/retry/recovery producer. The full workflow preserves its previous commands and adds five native commands and two Apache commands. Local checks pass 271 acceptance-helper outcomes, 20 upgrade guards, 19 actual published-archive guards and four PHP syntax executions; canonical refresh passes. These are local/source results, not Linux application acceptance. [Scope, corrections and remaining gates](docs/TWENTYI_REGISTRAR_NAMESERVERS.md#packaged-acceptance-producers--2026-10-18).

The full owner API documentation remains retained; no resend is needed. Published 1.25, the separate catalog/hosting candidates and Devin's non-20i work are unchanged. No new release or live deployment occurred.

## 2026-10-18 — owner nameserver observations candidate

The separate `codex/twentyi-registrar-nameservers` branch now includes explicit owner setup, saved registered-domain nameserver observations, latest-attempt status and dated history in the 20i connection screen. Failed or unfinished newer reads never fall back to an older current result. Previous assignments are labeled in history. [Feature and remaining acceptance](docs/TWENTYI_REGISTRAR_NAMESERVERS.md).

Applied PHP 8.4.25 checks pass 144 reader, 230 operation, 64 registrar and 42 renderer outcomes in each default/minimal mode (960 named executions), plus 22 touched-file syntax executions. These are pure/synthetic checks. The isolated Linux native fixture and four-environment CI extension are present but have not executed at this source checkpoint. Packaged HTTP/browser, upgrade/recovery, full acceptance and publication remain pending. No nameserver setter or live deployment is enabled.

The full owner API reference is retained and needs no resend. Hosting catalog imports are in the separate 1.26 candidate; cloud catalog mapping and automatic provider images remain unresolved. Original f67 runtime run39098588864 has four successful producer jobs and queued aggregation. Its bounded native/browser archive review validates 167 reports, including 3,149 HTTP outcomes on PHP 8.4.26 / MariaDB 10.11.19, without final-acceptance or new pixel-review credit. Corrected b7 run39137551561 remains separate. Published 1.25 stays unchanged.

## 2026-10-18 — registrar nameserver boundary

The separate main-based nameserver branch adds a typed registrar read and restricted request builder using the supplied SDK example. It keeps registrar IDs separate from hosting package-domain IDs and refuses unsupported partial changes. Generic write approval remains denied; admin forms, saved observation history and native authority are still pending. [Scope and next application slice](docs/TWENTYI_REGISTRAR_NAMESERVERS.md).

Actual PHP 8.4.25 checks pass 144 focused and 381 existing cases in each default/minimal mode, plus 221 unchanged acceptance-helper outcomes. These are synthetic/pure checks, not provider or native application results. Focused PHP 8.2/8.4 CI is prepared. The original route-contract finding and patch-encoding failure are preserved. Published 1.25 and the separate 1.26/hosting/PHP-control branches remain unchanged.

## 2026-10-18 — reduce unrelated CI work

Parent `cefb08d6c29f4faca104089bb5941b322418d2ba`, current [PR203](https://github.com/afterburn25/Cajun_Web/pull/203); branch `codex/contacts-native-fixture`; enclosing commit **Fix duplicate contacts setup in service-plan fixture**. This addresses the observed domain-renewal workflow39130068128 failure inherited by PR210: renewal checks passed, then the service-plan fixture attempted to create `spln_studio_contacts` twice. The canonical base schema already creates that table. Remove only the redundant second DDL call, retaining the schema marker and all contact, consent, worker and authority assertions.

[Named evidence](docs/evidence/service-plan-contact-fixture.json) retains three original artifact digests and the87-pass/1-failure service-plan reports. Nine actual extracted schema/setup guards pass in each default/minimal PHP mode; both syntax modes and independent source review pass. These are not corrected native SQL results. Main, published1.25 and the separate20i candidates do not contain this unintegrated contact extension. Devin retains feature ownership; root supplies this bounded test correction at the earliest affected branch so the downstream stack can inherit it through normal integration.

Next: first corrected-source native/full GitHub acceptance, then sequential integration with PR203 and descendants. Preserve original failed runs; no source or result is replaced and no production action occurred.

## 2026-10-18 — protect hosting history during file recovery

The hosting-commerce successor now guards the staged owner updater before restoring files. It rechecks current owner access under the shared account/publication lock and permits absent or validated empty hosting history. Any publication, including a withdrawn offer, or incomplete/unrecognized history blocks automatic file recovery. Historical updater copies and manual file replacement remain outside this safeguard. [Recovery behavior and limits](docs/TWENTYI_HOSTING_OFFERS.md#file-recovery).

The 142-case regression passes in normal and minimal PHP with modeled database and recovery seams. The focused Linux matrix now includes 44 separate native recovery checks across four isolated phases while preserving the original 88 hosting assertions. Their actual SQL/filesystem results and staged HTTP acceptance remain pending. Tracking [issue #217](https://github.com/afterburn25/Cajun_Web/issues/217) retains that distinction. This successor is separate from the unchanged 1.26 candidate and published 1.25 release.

## 2026-10-18 — hosting offer screens in development

The separate `codex/twentyi-hosting-commerce` branch now connects owner publication/review, public hosting cards and authenticated client requests to the backend committed at `47fc69f6`. These screens are development work awaiting Linux database and packaged browser acceptance. [Feature scope and verification](docs/TWENTYI_HOSTING_OFFERS.md). The focused workflow now also contains an isolated packaged PHP 8.3/MariaDB HTTP and Chromium job covering image upload, publication, client requests, owner decisions and existing agreement consent. Its ten desktop/mobile captures still require actual pixel review. All runtime results remain pending. The original four-environment run [39098575981](https://github.com/afterburn25/Cajun_Web/actions/runs/39098575981) remains preserved.

Local Windows PHP 8.4.25 with MariaDB 10.11.18 passes all 138 existing service-plan SQL checks. The new hosting fixture stops before its assertions because Windows does not retain the existing required POSIX 0600 permissions. No filesystem safeguard was changed or bypassed. The full supplied 20i reference is already retained; automatic provider images and the cloud catalog still lack a verified response contract.

Hosting UI checkpoint `dfb0ec18` now normally incorporates the browser callback correction from `f67b1631`. The separate 1.26 release candidate remains `f67b1631`, with corrected browser-test ordering and verified Windows packages. Its original full Linux runs remain pending. Published 1.25 assets and the live site are unchanged.

## 2026-10-18 — separate hosting-order connection in progress

`codex/twentyi-hosting-commerce` starts at frozen 1.26 source `6e3dbeff6dc6359a418837d0c95baa43e4c92533`. It prepares [hosting publications and client requests](docs/TWENTYI_HOSTING_OFFERS.md) connected to the existing agreement writer. Pure documents pass 119 checks in normal and minimal PHP; source/syntax review is separate from pending SQL, route, browser and full ordering acceptance. No customer route is enabled. The 1.26 release candidate remains unchanged on its own branch, and published 1.25 assets remain immutable.

## 2026-10-18 — browser fixture ordering correction

The original browser checkpoint39093140366 failed after 1,225 successful HTTP checks: the new catalog fixture made six simulated reads before an unchanged provider-setup test required zero prior calls. All 31 catalog HTTP checks passed. The same ordering exists in frozen `6e3dbeff`. Move only the catalog callback after the existing scheduler callback; retain every assertion and original result. Shipping files are unchanged. The corrected descendant still requires complete candidate/main/browser/release acceptance. See [test record](docs/TEST_REPORT_1.26.0.md). Published 1.25.0 remains unchanged; hosting-order backend work stays on a separate branch.

## 2026-10-18 — assembled 1.26.0 candidate

The [1.26.0 candidate](docs/RELEASE_SCOPE_1.26.0.md) adds direct hosting catalog imports. Version markers, installer inventory, the real published 1.25 archive, historical baselines and separate populated 1.25-to-1.26 upgrade cases are assembled. Combined local checks pass: 262 transfer/evidence guards and 28 retained plus 42 new portable upgrade guards. These are not installed upgrade or browser acceptance. [Current test record](docs/TEST_REPORT_1.26.0.md).

Focused catalog native acceptance passed at `9f0b83fd`; combined browser checkpoint [39093140366](https://github.com/afterburn25/Cajun_Web/actions/runs/39093140366) uses test-corrected `12da59d1` and remains pending. Next: review final integration wiring, commit the assembled candidate and run full supported-runtime acceptance. Published 1.25.0 stays unchanged. No 1.26.0 release or live deployment is claimed. Earlier blocks below preserve their original status and are superseded by this entry.

## 2026-10-18 — direct hosting catalog candidate

Corrected source `9f0b83fd` passes the four-way hosting catalog native matrix39092587358: 109 default, 109 minimal and 51 database checks per environment. The combined checkpoint39092587539 passes packaging but exposes an older fixture that rewrites original import provenance to model changed credentials. That fixture correction and actual browser verification remain pending. All original outcomes are retained.

First CI source `9aa5c22e` is not accepted: native matrix39092279466 failed on invalid synthetic legacy inventory input; browser checkpoint39092299171 failed on the full-project `ops/` source allowlist before browser execution. The [feature guide](docs/TWENTYI_HOSTING_CATALOG.md#verification-status) preserves the outcomes and correction scope. No published release changed.

The `codex/twentyi-direct-hosting-catalog` branch starts from `6caf654c99cb5befb610f405e40df69b0c174cb6` and implements a [direct hosting catalog draft-import candidate](docs/TWENTYI_HOSTING_CATALOG.md). Owners can explicitly refresh confirmed package-type IDs/names and import types absent from installed inventory. Existing imports, edited prices and local images retain their original identity and provenance across both sources. Additive observation storage binds current owner/connection context and refuses stale, failed or inconsistent sources. No provider pricing, automatic images, cloud import or hosting publication is added.

Source/syntax and focused offline checks have passed; native database, packaged HTTP/browser and release acceptance remain pending. Root integrates shared admin/build/test wiring; the three agents own projection/importer, persistence and focused test proposals. Published1.25.0 remains immutable and no live deployment occurred. Next: run the combined focused database matrix and browser acceptance, fix any concrete failures, then complete the next version's upgrade and release gates.

## 2026-10-18 — cloud endpoint supplied no catalog details

## 2026-10-19 - owner transfers the complete project to Devin

**Devin now owns all remaining 20i and non-20i implementation, integration, testing, review coordination and releases. The immediate priority is to review and merge completed work from both workers into main, then finish the remaining full roadmap independently.** [Full directive](docs/DEVIN_FULL_SITE_TAKEOVER.md) · [Devin start here](DEVIN_START_HERE.md) · [Current handoff](SESSION_HANDOFF.md).

Earlier instructions assigning 20i or releases exclusively to Codex and limiting Devin to non-20i are superseded. Existing quality, data-preservation, repository approval and live-operation gates remain. This is a documentation/assignment change only: it does not merge any application candidate, publish a new software release, deploy the site, stop an external worker or start a Devin session.

## Verified baseline at this handoff

- Main before this documentation change: `a86f6f4fa592e6f715a9420aec8de4622258642e`.
- Latest published release read from GitHub: [v1.25.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.25.0), source `b79c67110829126916e3a65e616191542448bccc`. Prior source/tag/assets remain unchanged.
- [PR216](https://github.com/afterburn25/Cajun_Web/pull/216) remains an open direct-hosting-catalog candidate at `f67b1631683b29096dd8ec1daee405e104dc0b83`. Its capture successor and numerous hosting, nameserver, runtime, StackCP, client and non-20i branches require current-source review and combined integration.
- No new completion percentage is established by this instruction update. Prior chat estimates are not audited project counters.

The [takeover inventory](docs/DEVIN_FULL_SITE_TAKEOVER.md) identifies initial PR families, concrete contacts/payment-link/VPS caveats, required acceptance and the complete remaining scope. Devin must refresh all source/CI/release states, build the dependency-based integration queue and merge only accepted useful slices. An old PR body may lag current job results; a passed parent cannot certify a successor.
Previous checkpoint: https://github.com/afterburn25/Cajun_Web/blob/35d8fb7efc94f6f851399d0755fa93097f1fce66/PROJECT_STATUS.md

## Devin non-20i lane — 2026-10-17

Devin PRs verified green: #198, #202, #203 on main (48/48 each) and stacked #200, #204, #205 on their parents (long lanes run after retarget). New tip `devin/non20i-staff-notes` @ `b6d20382` stacked on #205: staff-only internal project notes via `studio_messages.author_type='internal'` — invisible to clients, contacts, support sessions and activity feeds; no schema change. Syntax/test-compile/canonical inventory pass; PR pending GitHub API rate limit reset.

Nothing merged, deployed or contacted externally. 20i/provider/hosting/SSH work remains Codex-owned.


## Devin lane update — 2026-10-17 (later)

Current tips (stacked chain on devin/non20i-contact-activity):
- #209 `devin/non20i-staff-notes` @ `3e6db257` — staff-only internal project notes via `studio_messages.author_type='internal'` (no schema change); portal conversation allow-lists visible author types. `3e6db257` corrects `d203b5ea`'s flash assertion ('Project updated' is the shared `studio_*` group flash; 'Project saved.' is only for `studio_job`).
- #210 `devin/non20i-milestones` @ `868e967c` — additive `studio_milestones` (state + optional YYYY-MM-DD target date) via existing `studio_migrate`; admin manage forms, portal visibility, milestone activity events.
- #211 `devin/non20i-activity-prefs` @ `537c2c98` — additive `activity_prefs`; per-actor hiding of update types from feed/unread/open/mark, re-run `activity_setup` provisions on existing installs.

php -l + test-compile + canonical inventory pass on all. Component lanes queued (GitHub runner starvation); long lanes run after Codex retargets to main.

Previous checkpoint: https://github.com/afterburn25/Cajun_Web/blob/35d8fb7efc94f6f851399d0755fa93097f1fce66/PROJECT_STATUS.md
Original [run 39090394774](https://github.com/afterburn25/Cajun_Web/actions/runs/39090394774), attempt 1, checked out `1159648f968d46439f7e88a9cd0f6dbee3758643`. Its 39 offline transport/disclosure tests passed; the live step and overall run failed, and the bounded shape artifact was uploaded. `package_types` observed an array of objects with `id` integer, `label` string and `platform` string. `cloud_server_product_data` returned the diagnostic outcome `unexpected_root`; the actual root kind was not retained, so an empty catalog or missing entitlement cannot be inferred.

## Immediate next actions

Create or safely reuse a Devin-owned main-based integration branch, classify all pending Codex/Devin work, resolve mature blockers and merge ready slices sequentially. Complete a verified incremental release with install/update ZIPs and actual screenshots before allowing another indefinite branch backlog. Then continue all remaining 20i and non-20i capabilities without waiting for Codex or a new continue instruction.

CI cleanup is not feature completion. Preserve the completed narrow queue-cleanup records and original required tests; do not replay old cancellation batches or redispatch queued work. Review and publication follow [the existing release workflow](docs/RELEASE_WORKFLOW.md).

## Preserved detailed history

The complete pre-takeover status and every dated source/test/queue checkpoint remain at the [exact previous main version](https://github.com/afterburn25/Cajun_Web/blob/a86f6f4fa592e6f715a9420aec8de4622258642e/PROJECT_STATUS.md). Feature guides, [continuation evidence](docs/ROADMAP_CONTINUATION_2026_10_19.md), [1.25 publication evidence](docs/RELEASE_1.25.0_PUBLICATION.md), roadmaps, original failures and release assets remain intact. The current ownership above supersedes old allocation prose; it does not reinterpret historical results.
