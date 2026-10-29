# CajunWebPro catalog and product worksheet — 2026-10-28

Owner-review worksheet. This is not a catalog import file. No products, prices, licenses or subscriptions have been created in the live application.

## Existing 1.25.0 service catalog

Open **Billing & Products → Service catalog**. Inspect existing products before creating anything; reuse an existing suitable SKU rather than creating a duplicate.

Proposed SKUs below pass the current SKU character/length rules. Their availability in the live catalog is unverified. Keep proposed entries as drafts until scope, prices and terms are approved.

| Proposed SKU | Title | Family | Period | Unit price | Setup fee |
|---|---|---|---|---|---|
| CWP-WEB-DESIGN | Business Website Design | Web design | One-time service | Owner to supply | Owner to supply |
| CWP-WEBSITE-CARE | Website Care | Website care | One month of service | Owner to supply | Owner to supply |
| CWP-ADDITIONAL-WORK | Additional Website Work | Additional service | One-time service | Owner to supply | Owner to supply |

### Business Website Design

**Summary:** A business website organized around an agreed project scope, content and customer journey.

**Confirm before publication:** Page/content limits, included functionality, responsive design scope, content responsibilities, review rounds, milestones, handover materials, exclusions, agreed price and treatment of additional work.

For bespoke pricing, use a project-specific approved quote/invoice. A generic catalog card must not imply unlimited work at a guessed fixed price.

### Website Care

**Summary:** A defined period of ongoing website work with agreed inclusions and request procedures.

**Confirm before publication:** Work allowance, eligible tasks, exclusions, request channel, response expectations, rollover policy if any, service period, renewal/cancellation terms and price.

A monthly catalog period is a price/service description; creating it does not itself create an automatic payment subscription.

### Additional Website Work

**Summary:** Additional work for an existing website, defined and approved before it begins.

**Confirm before publication:** Exact unit of work, deliverable, exclusions, dependencies, review process, timing and price. Choose a unit customers can understand; do not leave the scope open-ended.

## Publication boundaries in 1.25.0

Current public catalog families are **Web design**, **Website care** and **Additional service**. A published entry requires a positive configured price, summary and terms.

Hosting, domain and VPS records remain draft-only under the reviewed catalog policy. Do not change a product family to bypass those restrictions. The public Hosting page can continue to accept consultations.

An issued catalog invoice retains its reviewed price/scope snapshot. Later product edits must not be represented as changing an already issued invoice.

## Software-product preparation for the upcoming release

Prepare one worksheet per actual software product. These fields do not create a software catalog entry or grant any license in the installed 1.25.0 release.

| Field | Owner input |
|---|---|
| Product name and proposed SKU | Pending |
| Current release version | Pending |
| Approved package/source and SHA-256 | Pending |
| Product description and supported uses | Pending |
| Supported server/Core/module dependencies | Pending |
| Price, billing period and renewal terms | Pending |
| Standard / trial / temporary availability | Pending |
| Trial/temporary duration and conversion terms | Pending |
| Allowed concurrent installations | Pending |
| Update-entitlement duration | Pending |
| Client domain-change policy/cooldown | Pending |
| Support scope | Pending |
| Third-party licenses and distribution permissions | Pending |
| Customer-facing download/handover instructions | Pending |

Internal master licenses remain internal. Trial, temporary, domain-change, paid-delivery and recurring-payment behavior must use the accepted licensing release and its required verification.

## Financing decisions to prepare separately

Record the agreed business inputs for each customer without generating or sending a live agreement yet: cash price, initial payment, financed amount, charge/APR disclosures, installment count/cadence, dates, grace policy, optional maintenance, cancellation/refund treatment and the exact reviewed agreement text.

No rate, fee, financing schedule or legal term is invented by this worksheet. The licensing/financing release, gateway acceptance and applicable owner/legal review remain separate requirements.

## Source and handoff

- [Current service catalog guide](../SERVICE_CATALOG.md)
- [Reviewed v1.25.0 catalog service](https://github.com/afterburn25/Cajun_Web/blob/v1.25.0/site/app/billing/catalog.php)
- [Owner launch workbook](OWNER_LAUNCH_WORKBOOK_2026_10_28.md)

Only documentation changes are proposed. Active implementation branches and production settings remain untouched.
