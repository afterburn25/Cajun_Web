# Payment settings and request preparation

## 2026-10-13 — Verified 1.8.0 invoice payment increment

External USD receipts, partial/full balances, retained owner corrections, legacy balance import and client-owned statements/printable receipts are delivered in [v1.8.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.8.0) / `c36a36e80c40485c9e3e7e6cdd4884ced25db961`. Existing 1.7.0 invoices, attempts/notifications, credentials/keys and earlier data survive UPDATE. New requests/trials use remaining balances; sandbox evidence never becomes revenue. 427 native / 503 HTTP/Chromium checks pass. Actual merchant acceptance, production/client checkout, refunds/tax/credits/subscriptions remain pending. [Guide](INVOICE_LEDGER.md) · [report](TEST_REPORT_1.8.0.md).

## 2026-10-13 — Verified 1.7.0 sandbox notification increment

Signed sandbox receipt, durable minimal inbox and explicit owner-triggered current reporting are delivered in [v1.7.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.7.0) / `1fe0670142755f86aee235fabe1847e5d118ee52`. Receiver defaults off; schema is additive, invoice settlement remains disabled and no runtime daemon is needed. Original 1.6.0 UPDATE preserves attempts/events and all earlier records/keys. 358 native / 451 HTTP/Chromium checks pass; signatures/reporting remain synthetic. Actual merchant acceptance, production/client checkout and automatic processing are pending. [Guide](SANDBOX_NOTIFICATIONS.md) · [report](TEST_REPORT_1.7.0.md).

## 2026-10-13 — Verified 1.6.0 sandbox increment

Owner-only tokenized sandbox trials, durable intent/events, transaction-detail reconciliation and shared manual/catalog/request locking are delivered in [v1.6.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.6.0) / `e5d4c17456faff0522429ab3f58093a06a523bd5`. Sandbox never pays real invoices. Production/client checkout, webhooks, receipts/refunds and recurring billing remain pending. Original 1.5.0 UPDATE preserves credentials/key, requests and existing records. 296 native / 421 HTTP/Chromium checks pass; gateway and browser tokenization fixtures remain synthetic. Back up and enable Sandbox trials after UPDATE. [Guide](SANDBOX_PAYMENTS.md) · [report](TEST_REPORT_1.6.0.md).


Released 1.5.0 implements a bounded part of M2 / HC-04 and HC-11. **Card collection and production processing are disabled.** This is not completed checkout, a payment receipt, or evidence of a real Authorize.net sandbox transaction. Current published versions and acceptance reports are in [Releases](RELEASES.md).

## Setup and access

After a full file/database backup and merging the UPDATE package, an owner opens **Payment requests**, confirms the backup and enters their current password to enable preparation. Complete existing client-workspace/administrator setup first on older sites. The additive setup creates `payment_gateways` and `payment_requests`, checks existing column types, nullability, unique keys, identifier collations and InnoDB, rejects incompatible/unrecognized records and preserves original data. Fresh one-page installation creates empty tables without requiring merchant credentials. No products, prices or payments are seeded.

**Authorize.net settings** is owner-only. Saving, removing and checking credentials requires the current owner password and CSRF. Requests require both the new explicit `payments.manage` grant and `clients.manage`; existing nonowner grants do not expand automatically. Support contexts cannot mutate requests or credentials. Existing staff/client identities and invoices are reused.

## Credentials

Sandbox and Production have separate editable **API Login ID**, **Transaction Key**, **Signature Key**, and **Public Client Key** fields. All saved fields remain blank on reads and after validation errors. Blank edits retain existing values; initial setup requires all four. Replacement uses a revision check to reject stale forms and resets connection validation. Explicitly confirmed removal clears only the selected environment. Strict audit and credential writes share an InnoDB transaction. Audit records contain environment/revision and outcome, never values.

All four values are authenticated/encrypted with AES-256-GCM, with environment and table-prefix binding. A separate random 32-byte `payment-master.key` is created with 0600 permissions in the installation's configured private storage. Real resolved storage must be outside both the application root and the PHP web document root. In-document-root fallback storage may continue to serve existing features, but cannot save gateway credentials. Ask the hosting owner to configure a genuinely private storage path if needed; this release does not move files automatically. No key is created during fresh installation or database-only setup.

The key must remain protected and outside Git, deployment ZIPs and ordinary database/website backups. Keep a separately encrypted, access-controlled recovery copy. Restore the matching key and database together. A missing/damaged key fails closed; saving over existing ciphertext never silently generates a replacement. If recovery is impossible, an owner can explicitly remove all saved environment credentials and reconfigure them. Credential replacement is supported; automated master-key rotation and backup management remain pending. Host aliases/symlink publishing and backup exclusions also require host-specific verification.

## Sandbox connection check

After saving sandbox credentials, an owner can choose **Check sandbox authentication**. The only provider operation in this release is `authenticateTestRequest` to the fixed HTTPS sandbox API endpoint. It uses only API Login ID and Transaction Key, validates TLS, follows no redirects, limits response size and has connection/total timeouts. There is no production endpoint or charge operation. Limit: three owner check attempts per 15-minute bucket. The result is one of authenticated, rejected, or unavailable. Only fixed status text is saved/displayed; provider response text is not logged or echoed. Credential changes during a pending check prevent a stale result from being applied.

Authenticated means **only** the Login ID and Transaction Key passed authentication. It does not validate the Public Client Key, Signature Key, tokenization, webhook signatures, payment success, settlement or production readiness. PHP cURL/TLS and outbound HTTPS are needed for this optional check; failure leaves payments disabled. Merchant credentials were not supplied for release acceptance: response parsing/failure/race tests use clearly labeled CLI fixtures, and no real provider-success claim is made.

## Requests on existing invoices

Issue an invoice for the agreed amount first, using existing manual invoices or the [service catalog](SERVICE_CATALOG.md). Open **Payment requests**, choose **Review request**, review the exact invoice total, select full service/deposit/milestone/final balance, and set expiry from 1–30 days. These purpose labels do not derive a project balance or create partial payments. Every request covers its invoice's full saved USD amount. No posted amount/currency is trusted.

Confirmation rechecks the active client, issued/unpaid invoice and invoice fingerprint, including saved amount, currency, title, instructions and any catalog snapshot. A changed invoice requires fresh review. Duplicate identical submissions, including concurrent processes, return one record. A deliberate new request supersedes earlier prepared requests for that invoice. Requests can be revoked, expire, and become unavailable when their invoice is paid/void or client is inactive. No request changes the invoice's financial status. Strict audit failure rolls back both new records and replacement/revocation.

Administrators see the latest 100 invoices/requests and can copy the current shareable link for a prepared request. The owning signed-in client can open the same link from the project invoice, with a clear preparation-only notice. There is still no card form or payment action. Client ownership is checked again for request display; a support view remains read-only. No automatic mail is sent.

## Shareable payment links

Every prepared request mints a 256-bit bearer token. The database stores only its SHA-256 hash plus the token sealed with the payment master key, so a database copy alone cannot redeem links. The public `index.php?page=pay&t=` page resolves the token without any portal session, is `noindex`/`no-referrer`/`no-store`, rate-limits lookups per IP, and shows the invoice reference, purpose, authoritative amount, expiry and the invoice's own instructions. Revoked, superseded, expired, already-paid, invoice-changed and unknown links each render an honest terminal state; the link records no payment. Requests prepared before schema 2 have no link — revoke and prepare a new request to issue one.

## Remaining M2 acceptance

Next: tokenized sandbox checkout, payment attempts serialized across manual and gateway transitions, uncertain-response reconciliation, verified webhook signatures and replay protection, accurate receipts/refunds/balances, and explicit subscription consent. Shareable expiring/revocable request links are delivered on `devin/non20i-payment-links`. HTTPS, merchant-specific PCI review and authorized provider tests are required before live activation. Hosted-form tradeoffs remain a merchant decision. No 20i provisioning, webmail, ZIP handover or SSH work is implied by this foundation.

## Provider references

Reviewed 13 October 2026: [Authorize.net Accept.js](https://developer.authorize.net/api/reference/features/acceptjs.html), [API reference](https://developer.authorize.net/api/reference/index.html), [sandbox testing guide](https://developer.authorize.net/hello_world/testing_guide.html). Accept.js tokenizes card data in the browser; a later server transaction call is separate. The selected sandbox endpoint and authentication message follow the provider API contract. CI fixtures test our handling, not provider availability.
