# Financing enrollment after authoritative payoff

Date: 2026-10-28 (America/Chicago). Branch `codex/finance-enrollment-payoff-guard`, based on frozen [PR #297](https://github.com/afterburn25/Cajun_Web/pull/297) `89ecee07f60239e2cb98c5015807923607bee5e2`. This addresses [independent review 6485640935](https://github.com/afterburn25/Cajun_Web/pull/297#issuecomment-6485640935); correction verification below is producer self-review, not independent approval.

## Verified problem

Payments commit before best-effort financing synchronization, and synchronization errors are deliberately caught so verified financial history is retained. A paid installment ledger can therefore coexist with a stale active/grace projection. Initial enrollment previously checked the projection only. Its profile-based caller also omitted the new current agreement/payment admission entirely.

The native reproducer creates actual canonical signed schedules and installment invoices. A separate PDO connection commits synthetic receipts after preflight and before reservation, deliberately leaving the projection unchanged. On the parent, both exact caller bodies reach the synthetic ARB provider and retain a subscription after complete payoff. Unpaid/reversed initial invoices, suspended/paid-off projections and changed ownership/schedule also reach profile enrollment. The original source fails 138 of 240 assertions.

## Correction

Every initial reservation now uses the shared guard under the existing account mutex and transaction. Opaque enrollment retains current client-session verification. The internal profile path rechecks the current active customer, signed offer, license/project ownership, schedule terms, initial invoice and running journal without requiring a customer session during an authenticated owner reconciliation.

Locked admission validates authoritative installment invoices and ledger balances using the existing financing policy. Complete payoff and overdue suspension reject enrollment even when the projection is stale. Initial enrollment creates a full signed-period contract, so any existing partial/full installment payment or voided installment requires review rather than duplicate collection. No payment, license, domain, commercial right or payoff record is rewritten by admission.

Sandbox profile enrollment keeps a no-fulfillment path only after the shared signed-offer/invoice checks: the initial invoice must be issued with zero payment-history entries and the current charge/attempt must match its agreement, invoice, amount, transaction and sandbox-test state. It cannot bypass payoff, suspension, ownership, support or schedule checks. Production mode is expressly excluded from this exception; actual gateway activation code is unchanged.

Only three existing subscription functions change and one sandbox proof helper is added. The other 17 existing function bodies are identical, including opaque orchestration, replacement, finalization and reconciliation. Core permanent payoff, initial-payment verification, charge handling, portal and production activation sources remain byte-identical to #297.

## Evidence and limits

- Parent: 138/240 failures on PHP 8.4.25/MariaDB 10.11.19. Original 94/178 reproduction and the first expanded-fixture signature error remain retained separately.
- Corrected: 240/240 new admission assertions in 44 scenarios per PHP 8.2.35/8.4.25 × MariaDB 10.11.19/MySQL 8.4.13 lane (960 executions). Each lane includes 20 separately committed writers. Both caller paths and direct reservation are exercised; positive paid, zero-down, owner-session and valid sandbox controls remain.
- The existing initial-payment/synchronization fixture passes 207/207 in all four lanes (828 executions), including permanent-payoff variants. Combined native execution count is 1,788, not 1,788 newly added assertions.
- Handler 22, lifecycle 45, source 54, retirement 9 and sandbox-exception mode/readiness 6 checks pass on each PHP version. Touched PHP syntax, workflow YAML/bash, canonical installer refresh and exact staged-source hashes are verified separately.

Native storage, journal validation, invoice hashes/balances, locks, SQL reservation/finalization and commit boundaries are real. Gateway settings, transport and readback are isolated synthetic namespace functions; application admission/caller function bodies are copied exactly from the selected source. Synthetic receipts do not certify Authorize.Net behavior. Deliberately unprepared payment-link setup emits 38 expected sanitized diagnostics in each new lane and 54 in each existing lane. No real gateway, HTTP/browser, combined upgrade or main acceptance is claimed. [Named assertions, exact source hashes and original failures](evidence/finance-enrollment-payoff-guard.json).

The existing four-lane recurring workflow runs and retains the new regression, with dependency paths for initial/enrollment test files. No additional workflow or manual duplicate dispatch is introduced. Hosted exact-head checks, independent approval, final combined acceptance, unavailable real sandbox credentials, approved integration and post-merge/release gates remain pending. Devin's primary branch and the integration writer's branch remain untouched.
