# Financed website licensing

This feature binds an existing **website license** to an accepted CajunWebPro recurring-billing schedule. It is a payment-enforcement and commercial-rights layer; it does not create a second client identity, a second invoice ledger, or a transferable license marketplace.

## Payment-dependent access

- The recurring-billing schedule remains the authoritative source for the agreed installment amount, cadence, customer, project, and retained terms.
- Financing is finite: the owner records the agreed installment count (1–360) and a grace period (0–90 days).
- A site is never suspended merely because a scheduled period exists. Suspension requires an actually issued financing invoice with a real unpaid ledger balance that is beyond the retained due date plus the agreed grace period.
- During the grace period the license stays authorized.
- Once an issued installment is beyond grace, license validation returns a definitive payment suspension for that financed license. The key, customer, domains, invoice history, and agreement are preserved.
- After a verified catch-up payment is committed, financing is recomputed and the same key resumes. No replacement license is minted.
- When every required installment has been issued and fully paid, the financing record receives a one-way `paid_off_at` timestamp and state `paid_off`. From that point forward payment history is no longer consulted for authorization, so ordinary later billing failures or corrections cannot payment-lock the site again.
- Paid-off does **not** bypass manual license revocation, domain binding, security enforcement, or commercial-rights restrictions. It means only that the financing obligation can no longer disable the site.

## Domain and customer binding

The license remains owned by the same CajunWebPro client account. The customer may use the existing authenticated portal to replace an approved domain without repurchasing, but that does not transfer `customer_id`, create a new licensee, or authorize resale. The old domain loses authority at its next successful validation after replacement.

## Resale and distribution rights

Financing payoff does not grant resale, redistribution, sublicensing, white-label distribution, or source-code distribution.

The default rights state is **site-use only**:
- resale: not granted
- distribution: not granted
- exclusive resale: not granted
- exclusive distribution: not granted

An owner may positively grant resale/distribution rights only from retained agreement/payment evidence and with owner password reauthentication. Rights grants are monotonic in this workflow: once a paid commercial right is recorded it is not silently removed later. Exclusive resale requires resale rights; exclusive distribution requires distribution rights.

These technical flags enforce CajunWebPro entitlement behavior and provide auditable product state. Contract wording and legal enforceability remain subject to the actual signed agreement and legal review.

## Payment collection integration

This licensing increment binds to the existing recurring-billing invoices and invoice ledger. It therefore preserves the exact agreed installment amount rather than accepting an arbitrary amount from a license form.

The current generic recurring-billing subsystem does not yet perform automatic card collection itself. Existing verified payment paths and future integrated Authorize.Net/card-on-file collection must settle the same invoice ledger and then call financed-license synchronization after commit. License validation also recomputes the financing state, so a committed payment can restore access even if a payment-notification hook was temporarily unavailable.

Production charging remains separately gated by merchant configuration, customer authorization, PCI/security review, and integrated acceptance.


## Client-specific finance offers and signature flow

Finance offers are customer-specific and immutable once published. The owner selects an existing website license and an offered recurring-billing schedule for the same customer, then records the cash price, initial payment, amount financed, finance charge, stated APR, installment count, grace period, payment-link lifetime, commercial-rights scope, and custom agreement language.

CajunWebPro validates the monetary relationship before saving an offer:

- cash price minus initial payment must equal amount financed;
- the exact retained installment amount multiplied by installment count must equal amount financed plus finance charge;
- the installment amount/cadence/anchor/due interval come from the retained recurring-billing schedule, not free-form browser values.

If any term changes, withdraw the unsigned offer and issue a new one. The old offer remains retained.

The client receives an authenticated portal deep link such as the financing-agreement route in their client workspace. The link alone grants no authority. If the client is signed out, login returns them to that exact agreement; another client account cannot open or sign it.

Electronic acceptance records the authenticated client id, typed signer name, client-account email, UTC timestamp, exact finance-term SHA-256, and an evidence hash. The same action accepts the pinned recurring schedule and applies the owner-preauthorized activation. A support session cannot sign or decline.

When an initial payment is due, signing creates one idempotent invoice and one private payment-request link, then redirects the client to that payment page. If no initial payment is due, the signed installment schedule becomes active without creating a fake zero-dollar invoice.

The owner may prepare an agreement email from the finance-offer record. CajunWebPro creates a real local email draft addressed to the client with the authenticated portal deep link and opens the existing review/send workflow; it does not silently send mail.

The shareable payment-request page uses the preserved hardened bearer-link design: a random 256-bit token, hash-at-rest lookup, encrypted token recovery, no-store/no-referrer/noindex headers, and metadata-free terminal states. On this branch that page is still a payment-request handoff and does not by itself certify production card collection. Unified Authorize.Net checkout remains an integration/release gate.
