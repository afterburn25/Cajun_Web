# Hosting purchases and setup handoff

Development branch: `codex/twentyi-hosting-purchase-inbox`, starting from `0ddbd23d6489e2b9445304123e214d0a2e188f31` on draft [PR228](https://github.com/afterburn25/Cajun_Web/pull/228). Version 1.26.0 remains an unpublished candidate; published 1.25.0 is unchanged.

## Owner and client workflow

Completed invoice checkouts now have a durable **Hosting purchases** entry. Owners can find the original offer, client, invoice, project, service agreement and local setup phase together. **Review setup** opens that exact agreement's existing hosting-order controls. The owner's existing password, confirmation, invoice binding and payment/provisioning requirements continue to govern setup.

Primary clients can revisit their own purchases from Hosting and the checkout result. Each row links the original invoice, project and agreement. An offered order links to **Add website address**; an accepted order links to **View hosting progress**. Invoice status, agreement state and local order phase are shown separately. An accepted order is not proof of provider activation. Actual hosting-service progress remains on the existing agreement page.

Lists contain completed invoice checkouts only. Unconfirmed reviews and the earlier request-only flow stay in their existing locations. Pages contain at most 20 purchases with a bounded next-page check and deterministic newest-first ordering. This increment adds no billing, provider, order or schema writer and no new POST action.

## Original invoice selection

For checkout-created agreements, the existing owner order form now selects the retained original checkout invoice by its exact identifier. Previously, it searched only the latest 50 same-priced invoices; enough newer invoices could hide the correct invoice. The selected invoice still passes the existing agreement, client, project, amount, currency, status and billing eligibility checks. Missing origin never falls back to an unrelated invoice. Legacy agreement selection keeps its original bounded behavior.

## Authority and history

Every public reader acquires the existing mutex and transaction, refreshes settings and checks present authority. Owners need current owner and financial permissions. Clients need the current primary-client session; foreign identities, contact provenance, support contexts, expired/revoked sessions and inactive accounts are refused through the existing authority helpers. The client query includes its identity and the row projection rechecks ownership.

The reader validates the retained checkout graph, original invoice and current agreement/project ownership. Existing order records pass their original journal and audit validation and must bind the same agreement, client and first invoice. Client projections expose only the documented public fields; provider configuration, private cost, credentials and receipt internals are omitted. Pagination bounds the displayed rows, but existing checkout validation still scans retained history; this increment does not establish large-history performance.

Never-prepared checkout is explicitly unavailable. Never-prepared order storage can show an unstarted setup only when no existing order rows or order audit history contradict it. Missing established markers, tables, rows, events or audit witnesses require recovery; a GET does not reconstruct history. Withdrawing an offer or disabling its provider connection does not by itself remove legitimate historical purchase visibility.

## Verification and release gates

The actual reader and invoice-selector functions pass 44 portable checks with modeled database and authority boundaries in both default and minimal PHP 8.4.25. The separate UI suite passes 76 actual-renderer/controller checks in each mode. Together with the unchanged checkout 58 and imported-plan 68 checks per mode, the final applied run passes 492 named executions. All 17 touched feature/test PHP files pass syntax checks. These are source/model checks, not native SQL or installed HTTP acceptance.

Independent review found that the initial owner invoice link opened general payments with the wrong query key. Its original 75 UI checks passed because the expected URL repeated that mistake. The corrected link uses `invoice-payments` with `invoice_id`; the added regression follows the generated URL through the actual payment and ledger controllers to the selected invoice reader. The original proposal and results remain preserved. Separately, the first local test driver attempted to load Windows built-in PDO/tokenizer modules dynamically; that harness failure is retained and corrected to plain minimal PHP. Distro CI retains its required explicit extension flags.

The new isolated Linux fixture uses real imported products, publication, client confirmations, invoices and hosting-order actions. It creates 51 newer invoices through the real catalog writer to exercise the original-invoice regression. It covers current identity, client isolation, history loss/corruption, exact restoration and unchanged database/private-file/provider counters during reads. Its Windows refusal and unused disposable database/private-path guards remain intact. Native execution remains pending until the source-bound Linux run produces evidence.

Full and focused acceptance add five required commands/reports while retaining all predecessor commands, reports and captures: 181 native recipes, 202 required browser-lane reports and 253 final reports. The final applied artifact-helper suite passes 584 named outcomes and preserves all 570 predecessor result objects and their relative order. Twenty-three current source pins are checked before finite predecessor projections. There is no new installed-browser producer in this increment. Local Chromium checks of synthetic-data rendering must be labeled accordingly; they do not establish real authenticated navigation or submission.

Applied-render/CSS Chromium checks pass 66 cases across eight desktop/mobile layouts, including 320-pixel cards, long hostnames, button visibility, exact navigation destinations and overflow. All eight resulting captures were inspected by the UI agent and root. The initial browser-path failures and scrolled-header captures remain retained; final captures begin at the top of the page. This is synthetic file rendering with background scripts disabled, not an installed login or application result.

Independent integration review passes 135 finite source/hash/inverse checks, binding all 23 current pins, 13 exact Git predecessor inverses, 15 updated pin locations, workflow/report inventories and the original 570 outcomes within the new 584. This review found no additional blocker after the invoice-link correction; it does not replace native, HTTP or upgrade execution.

The clean committed package, actual Linux database outcomes, installed navigation, supported upgrades and complete candidate/release acceptance remain separate gates. Preserve original queued runs and their failures; do not cancel or rerun them solely because they are waiting. No live deployment, provider write, charge, customer mail or SSH action is performed here.

## Installed purchase navigation

Navigation correction and acceptance successor `codex/twentyi-purchase-inbox-browser` starts from `7535f861c2c3accf37482f5af94099780c7806a6`. The original packaged checkout, storefront, plan-setup fixtures and their captures execute before the new inbox continuation. Its boundary is real owner/client login, exact purchase-to-invoice/project/agreement navigation, honest paid/unpaid and setup labels, client isolation and contact/support refusal, with unchanged existing commerce state. No fresh payment or provisioning action is part of this continuation.

The reviewed continuation and portable verification are implemented in this successor. Installed Linux SQL/HTTP/Chromium execution, actual new capture review, combined upgrades, final candidate acceptance and publication remain pending. The original7535 source and queued runs remain preserved; published1.25 is immutable.

The source review found a separate broken link inside the inherited setup card. Its owner **Review invoice** destination used the general payment-request page and an unrecognized `invoice` query key. The one-line correction selects `invoice-payments` with `invoice_id`; the client link, authorization and payment behavior are unchanged. Canonical installer refresh changes only the corresponding shipping hash.

The additive actual-renderer/controller regression reproduces the old failure after all 76 predecessor checks pass, in default and minimal PHP 8.4.25. Corrected source passes all 80 checks in each mode, preserves every original result object and follows the generated link through the actual payments and ledger controller bodies. Pure plan/order documents are real; native storage/context and later progress rendering remain modeled. This is not SQL or HTTP acceptance.

Applied local checks pass 631 artifact-helper outcomes, preserving all 584 predecessor objects and their order, 52 original checkout guards, and 80 actual renderer/controller checks in each default/minimal PHP 8.4.25 mode. The browser proposal also retains 49 helper/synthetic-layout checks and 26 final source/predicate checks; these are not installed application evidence. Canonical refresh, touched PHP/Python syntax and documentation checks pass. The 181 native commands, 202 browser reports and 253 final reports are unchanged; four new inbox captures are required through the existing checkout report. Inspect the first automatic successor full/focused Linux runs and their original outputs next. Native SQL, installed HTTP/Chromium, actual capture review, supported upgrades, final integration and release acceptance remain pending. Do not cancel or rerun the preserved parent jobs merely because they are queued.

Independent source review caught three browser-assertion errors before integration: a wrong session-cookie name, an incorrect foreign-invoice HTTP status, and an owner project title assertion that ignored its editable input. Corrected predicates use the actual application session name, safe 404 response and selected project ID/title input. The initial drafts and failure findings remain outside Git. This source review does not claim those prepared HTTP journeys have executed.
