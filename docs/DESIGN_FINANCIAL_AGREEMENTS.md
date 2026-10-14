# Financial agreements on design projects

Unreleased implementation in [draft PR114](https://github.com/afterburn25/Cajun_Web/pull/114), continuing from contract/documentation head `b7a2d0ca4e3dff1747029053c54e933f04162317`. The [interface contract](DESIGN_PAYMENT_FORMS_CONTRACT.md), [native consent](DESIGN_PAYMENT_STORAGE_CONTRACT.md) and [invoice obligations](DESIGN_PAYMENT_INVOICES_CONTRACT.md) remain authoritative. Published1.23 is unchanged. Complete20i implementation belongs to Devin.

## Prepare and publish

Open an existing client project in the admin and find **Financial agreement**. An owner first enables the existing payment preparation and invoice ledger, then deliberately prepares financial agreements with a current password and backup acknowledgement. Existing records are retained. Setup failures do not echo passwords.

Staff need both client management and payment management permissions. The client must approve the latest proposal before financial terms can be published. **Prepare financial terms** contains labeled agreement, item and stage fields. Prices, discounts and tax are exact USD amounts; discounts and tax apply to the whole item. Quantities are positive whole numbers. Decimal inputs are never rounded into a different consent amount.

A single full payment uses the remaining total with value0.00. Split payments use an optional first deposit, milestone installments linked to actual project milestones, and a final remainder. Fixed installments use USD; percentage installments use percent of the complete quote. **Preview exact amounts** calculates the result without saving. Add, remove and reorder controls preserve row identities and existing values and work without JavaScript. Publishing creates an offer, not a payment or project-progress change.

## Client decisions and invoices

The client opens the same project in their portal, reviews the exact quote, source proposal version, terms and allocations, then explicitly acknowledges acceptance or decline. Declines require meaningful feedback. Proposal approval alone is not financial consent. Support sessions can inspect authorized financial information but cannot decide for the client.

An accepted stage has its own **Issue invoice** action. It uses the exact accepted amount and opens the existing invoice management page. Repeating the original request returns the same invoice. After the first invoice, the entire agreement is frozen. Remaining accepted stages can still be invoiced once each. Dates and milestone changes never issue invoices automatically.

Issued stages show recorded payments and remaining balances from the existing invoice ledger. Clients follow the existing authorized statement/receipt links; private receipt notes stay private. Unissued allocations are not described as paid. Historical offers display their original allocation without claiming a current invoice status. The current view and bounded history remain distinct.

Invalid and stale forms preserve the original request, reviewed version and repairable entered text. A new current offer requires explicit reload and review. If another staff member issues an invoice while a draft is open, the failed draft remains visible as read-only reference while issued terms stay frozen. Exact original publication and client-decision retries remain valid after issuance.

Online card checkout is still unavailable. This increment retains studio payment instructions and verified external receipts. It does not activate a merchant, send customer mail, use20i or deploy to the live host.

## Evidence and remaining acceptance

Local PHP8.4/MariaDB checks: **209 named native financial assertions under128MiB**, including the original178 and31 actual form-context/projection/authority checks. **84 form/render checks in each default/no-ini mode** cover exact decimal conversion, malicious/malformed values, retained requests, server-side rows, escaping, accessible draft context and support projection. Actual PHP HTTP/Edge acceptance passes **52 feature assertions plus one no-runtime-diagnostics assertion**, including real setup/password/CSRF, full/split offers, stale client decisions, decline/withdrawal, invoice/replay/freeze, concurrent retained drafts, partial receipts and client/foreign/support separation. Six actual desktop and320px staff/client/error images were inspected and retained; keyboard and no-JavaScript row editing are exercised. The first attempt to launch the browser harness found no requests package in the default Python; the existing browser-test environment was used successfully without weakening tests.

The earlier consent-only source26e1008 now has completed [full run38645906931](https://github.com/afterburn25/Cajun_Web/actions/runs/38645906931): **11,024 named final assertions**, including1,470 HTTP/browser and1,697 Apache. Downloaded Linux ZIP CRC/member hashes and COMPLETE source bytes match26e1008. This is evidence for that earlier source, not these newer invoice/forms changes; no new Windows/Linux archive comparison or screenshot approval is inferred from it.

All2,632 installer dependency hashes match2,633 shipping files, and18 changed PHP files pass syntax checks. Focused PHP8.2/8.4 CI, the new combined application run, latest-published1.23 populated financial update/recovery, exact current packages/Apache acceptance and independent parent/combined review remain required. The upgrade cases now explicitly preserve accepted terms, unique invoices, partial receipts, original replay requests, earlier project/mail/provider records and private keys through recovery and reapplication. Their source has been syntax-checked; execution is pending. Keep the draft unmerged until applicable acceptance and independent review pass. HC-11/P-05 and production checkout remain incomplete.
