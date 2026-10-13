# Service catalog and invoice snapshots

Candidate 1.4.0 delivers a bounded M1 / HC-01 and HC-06 foundation. It reuses existing clients, projects and manual invoices. It does not yet implement checkout, tax/discount calculation, recurring enrollment or provider provisioning. Publication status is in the [release register](RELEASES.md).

## Setup and permissions

After backing up the installed site, merge the verified UPDATE package. Owners enable **Service catalog** with their current password and backup confirmation. From older versions, enable the existing workspace and administrator tables first. The additive setup creates three catalog tables without replacing existing records; incompatible schemas or unknown unmarked records are rejected. Fresh installation creates the empty catalog automatically. No sample products or prices are published.

Owners have catalog access. Other administrators need the explicit **Service catalog, internal costs and catalog invoices** permission. Creating, paying or voiding a catalog invoice additionally requires client-workspace access. Ordinary website/client/support permissions do not grant catalog access. Support portal actions cannot create or change invoices.

## Products and public prices

Create a unique SKU, service name, family, service period, USD unit price, one-time setup fee, summary, features and service terms. One SKU describes one unit and one period: one-time, one month or one year. Internal cost and provider product reference are optional and visible only in catalog administration. Never put credentials in a product reference. Pricing uses integer cents, not floating-point arithmetic.

Products are draft, published or retired. Publishing requires a configured positive price, summary and terms. Only design, care and additional services can publish in this milestone. Hosting, domain and VPS records can be prepared as drafts but cannot publish or issue invoices until their provider integration is verified. There is no destructive product deletion; retire products to stop new issuance while retaining history.

Published catalog products replace the editorial cards on the existing Packages page. When none are published, the existing scoped-service presentation remains. Estimator ranges stay indicative and do not become invoice amounts. Internal cost and provider references are excluded from public queries and invoice snapshots. Catalog saves append a revision; stale edit forms are rejected instead of overwriting a newer revision.

## Review and issue an invoice

From a client project, choose **Create invoice from catalog**, select a current published price and 1–100 units, and add manual payment instructions. **Review invoice** calculates the total on the server. The setup fee is applied once per invoice: unit price × quantity + setup. Confirm the exact reviewed price and service period to issue it. This version issues one catalog item per invoice and supports USD totals from 0.01 through 999999.99. Tax and discount calculations are not implemented; confirm the configured amount is appropriate for the agreed work before issuing.

An issued invoice stores the SKU, service text, period, price revision, unit/setup amounts, quantity and total. Later product edits or retirement cannot change that record. The snapshot excludes internal costs. The client sees it on their own project invoice, using existing client isolation. No new customer database or separate invoice ledger is introduced.

Changing a price between review and issue requires a fresh review. Repeated identical submissions with the same request key return the original invoice; a changed payload is rejected. The database lock and unique key also prevent duplicate invoices from simultaneous identical submissions. This is invoice issuance idempotency, not a payment-processing guarantee.

## Manual receipt and cancellation

Record **received payment** only after independently verifying an external payment and entering its reference. Catalog-linked receipts require both catalog and client access and strict audit. This does not contact a payment provider. Unpaid issued invoices may be voided with a reason and confirmation; their snapshots remain. Paid or void invoices cannot be silently reversed or marked paid again through these controls. Refunds and gateway reconciliation remain future work.

Product revisions, invoice creation and paid/void transitions are transactional with strict audit. Audit failure rolls back the mutation. Existing free-form manual invoices keep their previous behavior; they are not retroactively converted into catalog invoices. Recovery requires a complete validated host backup; a code-only downgrade is not supported.

## Acceptance

`tests/catalog_native.php` covers migrations, permissions, cent validation/overflow, stale revisions, provider publication restrictions, private-field isolation, immutable snapshots, duplicate/concurrent issuance and strict audit rollback. HTTP/browser and actual published 1.3.0 package-update results will be recorded in the release report before publication. Live hosting, tax configuration, gateway and provider operations are not exercised by this increment.
