# Initial-payment integrity during license synchronization

Date: 2026-10-28 (America/Chicago). Branch `codex/finance-initial-sync-binding`, based on [PR #296](https://github.com/afterburn25/Cajun_Web/pull/296) at `17357a9d8baea2bad1799e154ccc94800d9ea1c7`. Neither Devin's branch nor the integration writer's branch is changed.

## Verified failure

Core `license_financing_sync()` accepted the invoice selected by `activation_invoice_id` whenever that invoice was fully paid. It did not bind its customer, amount, currency or retained agreement identity back to the signed offer, and `ledger_balance()` has a legacy status-based fallback before ledger preparation. PR #296 repaired enrollment admission but did not change this independent access-state projection.

The native reproducer starts with a target receipt fully reversed: 0 cents paid and 500 cents still due. After a deliberately misbound pointer selects another agreement's valid paid invoice, the original synchronization changes `pending_initial` to `active`. PR #296's enrollment reader rejects the same invoice. This is a retained-data integrity defect; no customer/portal ability to edit the pointer is claimed. The original license/domain records remain unchanged, but the financing access state advances without payment for the target agreement. The probe and its audit are preserved in the disposable database.

## Correction and permanent payoff

The existing initial-payment verifier moves unchanged, apart from its name, into `financing_offers.php` as `license_finance_offer_initial_paid()`. Enrollment and portal call that shared verifier. Synchronization now reads the full signed offer, binds its client to the license owner, and applies the same exact agreement/invoice/ledger checks before considering activation. Zero-down agreements still require intact signed terms and do not require an initial invoice. Unpaid valid invoices keep or restore `pending_initial`; inconsistent obligations refuse without changing retained state.

The permanent payoff prefix is byte-identical and returns before any new invoice/offer validation. Later reversals, missing invoices, another invoice pointer or unavailable ledger cannot reopen a paid-off plan. Independent license deactivation, domains, rights and billing history remain unchanged. Subscription replacement, provider calls, payment settlement and production activation gates are not modified.

## Regression evidence

- Parent source: 22 failed assertions out of 207 on PHP 8.2.35/MariaDB 10.11.19. Failures cover another agreement's invoice, another customer's invoice, wrong amount/currency, corrupted signed fingerprint/scalar, inactive client and unavailable ledger. Original native admission tests remain present.
- Corrected source: 207/207 per PHP 8.2.35/8.4.25 × MariaDB 10.11.19/MySQL 8.4.13 lane, 828 assertion executions. This includes the existing 119 enrollment assertions plus 88 synchronization assertions across 22 new scenarios per lane. It is not 828 newly added assertions.
- Native synchronization cases retain full reversals on target invoices before substitution. Separate valid ledger receipts for foreign/mispriced invoices prove the repair binds the obligation rather than relying only on ledger self-consistency. Actual canonical schemas, signed journals, ledger hashing/balance calculation, transactions, license synchronization and the permanent payoff branch run against native SQL.
- Positive paid and zero-down controls activate; partial/full reversal and underpaid recovery remain payment-locked; ordinary reads preserve suspension. Five permanent-payoff variants retain paid-off status and exact financial/license/domain/rights rows, including owner-deactivated licenses. Rejected inconsistent obligations roll back without record changes.
- Existing source, 45 lifecycle, replacement-retirement and 22 handler contracts pass. Touched PHP syntax, canonical installer refresh, documentation and staged-source checks are verified before publication. The existing recurring native workflow already invokes the expanded fixture and retains its report; no new workflow or duplicate manual dispatch is needed.

Fixture customers, agreements and external receipts are synthetic. Payment-link setup is deliberately unavailable after signature, producing 54 expected sanitized setup diagnostics per lane; no private tokens or provider requests are created. Test counts, source hashes, original failures, case results and complete report hashes are in the [evidence JSON](evidence/finance-initial-sync-review.json). A local edit briefly misdecoded Unicode using the Windows default code page; review caught and restored those bytes before tests. The preceding review document's multiplication symbol is also corrected; shipping customer text is preserved.

## Pending gates

Implemented on a separate correction branch; hosted checks and independent review remain required. Full application 40767087342 remains failed at the cloud navigation timeout; PR #294 has separate hosted component evidence and still needs installed browser/full acceptance. Existing populated published-v1.25 upgrade evidence and provisional packages belong to their earlier exact sources. Final combined/current-main acceptance, approved version, sandbox validation and release approvals remain open. Production charging, deployment and release publication remain disabled.
