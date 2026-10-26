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
