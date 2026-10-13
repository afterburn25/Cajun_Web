# Owner sandbox payment trials

1.6.0 adds a bounded M2 trial workflow to the existing 1.5.0 invoice requests. It is not production checkout. Only an owner can use **Sandbox trials**; ordinary payment staff, clients, visitors and support sessions cannot start or verify trials. Existing request permissions remain unchanged.

## Setup and use

Back up the database, website and protected private files. Merge the UPDATE package; have an owner enable **Sandbox trials** with their current password and backup confirmation. Existing payment preparation must already be enabled. Fresh one-page installation creates empty trial tables without requiring gateway credentials. Neither path enables production or alters invoices.

Save all four Sandbox credentials in Authorize.net settings and run the existing authentication check. Use private storage outside the document root and keep its protected master key with an appropriate separate backup. Sandbox trials require HTTPS, PHP cURL/TLS access, a USD-only sandbox merchant and the gateway's test mode off. Sandbox accounts still process test transactions when their test-mode flag is off. Do not enter production credentials or real customer card information into a trial.

Prepare an invoice request for an agreed amount. In **Sandbox trials**, open that request. Enter test card data, your current owner password and the explicit sandbox confirmation. Accept.js sends card data directly to Authorize.net and returns a nonce; the application submits only that nonce. API Login ID and Public Client Key are intentionally exposed on this authorized tokenization page. Transaction and Signature Keys remain protected. Custom tokenization alone does not establish PCI compliance or production readiness.

Each prepared request permits one trial. Refreshing or repeating its submission returns the same attempt, without another gateway send. A new trial needs a newly prepared request after the previous attempt is resolved. Amounts come only from the reviewed saved USD invoice. Receipt emails and partial authorizations are disabled; no customer email/address is sent. Sandbox states never mark the real invoice paid, adjust balances, send customer mail or provision services.

## Results and recovery

The application commits the attempt and audit before sending a transaction. It uses a fixed sandbox HTTPS endpoint with verified TLS, bounded responses, timeouts and no redirects or automatic retry. A create response supplies only a transaction locator. A separate transaction-details call must match the merchant, unique attempt reference, transaction ID, type and exact amount. A USD-only merchant preflight binds currency because transaction details do not provide a reliable currency field. Unknown or contradictory observations fail closed.

| State | Meaning and next action |
|---|---|
| submitting / unknown | A result is not confirmed. Do not resend. Locate the reference in the sandbox merchant portal and verify its transaction ID here. |
| held | Gateway review is pending. Resolve it in the sandbox merchant portal, then verify again. |
| captured | Matching test capture is awaiting sandbox settlement; this is not real revenue. |
| settled | Matching sandbox settlement; real invoice remains unpaid. |
| declined / voided / expired | Matching terminal sandbox result. No invoice payment is recorded. |

Submitting, unknown and held attempts block replacement/revocation of their request and manual paid/void transitions on the same invoice. Manual invoices, catalog invoices and trial intent creation share a database mutex and strict transactional audit. After a resolved test result, an administrator may independently verify a real external payment using the existing manual flow.

Enter an unknown attempt's candidate transaction ID and current owner password to **Verify transaction**. A wrong reference, amount, type, account, partial authorization or unavailable response does not attach the entered ID or resolve the attempt. Once attached, the transaction ID cannot be swapped. Credential rotation within the same sandbox account permits reconciliation after fresh authentication; switching accounts does not. A stale response cannot overwrite a newer recorded observation. Audit failures after dispatch leave the committed unresolved intent available for recovery.

There is no blind retry, manual “force successful” button or “not found means failed” shortcut. A timeout with no discoverable matching transaction remains unresolved; use a separate synthetic invoice for further development tests and investigate the original in the sandbox portal. Automatic transaction discovery, scheduled reconciliation, signed webhooks, real-money settlement, refunds, receipts, guest links and client checkout remain pending.

## Evidence and boundaries

Native MariaDB tests inject synthetic gateway responses for concurrency, timeout, mismatches, permission and audit failure scenarios. Chromium tests intercept Accept.js and capture the nonce submission boundary; they are not successful provider tokenization or charging. Real sandbox credentials were not supplied, so end-to-end merchant acceptance and actual HTTPS/shared-host behavior remain unverified. Do not describe this increment as a verified production payment processor.

No PAN, CVV, nonce, raw provider response or credential is persisted in trial/event/audit records. HTTP operators must keep body capture and payment-page third-party analytics disabled. Deployments still need their own operational and PCI assessment before production. There is no new background service or command-line installation requirement.

Official contracts reviewed 2026-10-13: [Accept.js](https://developer.authorize.net/api/reference/features/acceptjs.html), [transaction settings](https://developer.authorize.net/api/reference/features/payment-transactions.html), [transaction reporting](https://developer.authorize.net/api/reference/features/transaction-reporting.html), [API reference](https://developer.authorize.net/api/reference/index.html), and [Authorize.net schema](https://github.com/AuthorizeNet/sdk-php/blob/master/AnetApiSchema.xsd). The official reference exceeds the browsing tool's size limit; schema and feature documentation informed the implementation. Real provider acceptance remains a distinct required gate.
