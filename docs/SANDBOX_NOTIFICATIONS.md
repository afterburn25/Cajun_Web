# Sandbox payment notifications

Implementation claim, 2026-10-13: Codex owns bounded M2 sandbox notification receipt and owner-triggered reconciliation on `codex/sandbox-webhooks`, starting at `3dd6d5e82e22ad4a2ab27dbf54d08bde45c50a9a` (published application 1.6.0). Owned files: payment adapters/views, additive schema, callback entry point, installer inventory, acceptance tests, workflow and continuity documentation. Devin retains PR #7 and portfolio component ownership.

Contract: disabled by default, sandbox only, explicit owner setup with current password, expected webhook ID and current sandbox credential revision. Verify HMAC-SHA512 over unmodified request bytes with the binary-decoded Signature Key. Bind receipt to the configured sandbox merchant and webhook. Save minimal identifiers and a body digest, never the raw body, signature, customer data or card data. A repeated notification is accepted only with the same digest. Durable receipt and audit must commit before HTTP 200. Receipt makes no network request and changes no invoice or attempt.

Owners explicitly verify queued notifications through the existing sandbox reporting adapter. Match transaction/reference/account, then require current independent merchant and transaction details. Apply an observation, notification processing state and strict audit together. Unknown, stale, mismatched or conflicting results leave the notification available for investigation/retry. Real invoice settlement, client checkout, production callbacks, external registration and scheduled processing are outside this increment.

Primary references checked 2026-10-13: [Authorize.net webhooks](https://developer.authorize.net/api/reference/features/webhooks.html) for signature header, raw-body HMAC, notification IDs, event types, merchantReferenceId/refId and prompt HTTP 200; [Authorize.net credential guidance](https://support.authorize.net/knowledgebase/article/000001271/en-us) for binary conversion of the 128-character hexadecimal Signature Key. Actual merchant delivery and key compatibility require sandbox acceptance; local signatures and reporting fixtures are synthetic evidence only.

## Setup and daily use

Back up website files, the database and private storage, retaining the original credential master key separately. Merge UPDATE into the existing site. Never rerun fresh installation. After payment preparation and sandbox trials are enabled, an owner opens **Sandbox notifications**, confirms the backup and enters their current password to add the inbox. The migration validates existing schema and never replaces retained records. Fresh installation includes an empty inbox, with receipt disabled.

In **Authorize.net settings**, save the four credentials for the sandbox account and check sandbox authentication. The Signature Key must be that same account's current key. The authentication check verifies only API Login ID and Transaction Key; it does not prove the Signature Key works.

Configure an inactive webhook in the Authorize.net sandbox account for your site's HTTPS `sandbox-notifications.php` endpoint. Use the actual installation path, including a subdirectory if applicable. Enter its webhook ID in this application's **Sandbox notifications** receiver form, check the receipt box and save with your current owner password. Enable the sandbox provider webhook only when separately authorized to perform provider acceptance. This application never registers, updates or activates it remotely. Real production credentials and events do not belong in this receiver.

Supported events are `net.authorize.payment.authcapture.created`, `capture.created`, `priorAuthCapture.created`, `void.created`, and `fraud.held`, `fraud.approved`, `fraud.declined` under the same `net.authorize.payment.` prefix. All undergo current transaction reporting checks; some transaction types have no matching application attempt and remain queued. Other authenticated event types are recorded as ignored, with no resource/customer payload. Refund processing is not implemented.

Open the inbox and use **Verify notification** with your current password. This requires HTTPS and a current authenticated sandbox gateway. Receipt itself performs no provider request. Verification calls the fixed sandbox merchant-details and transaction-details endpoints, and can recover a known application's ambiguous attempt when notification reference and reporting details match. Owner verification remains available for retained queued messages after disabling receipt; it does not re-enable the receiver. The inbox shows 50 messages per page with an Older notifications link.

| Inbox state | Meaning |
|---|---|
| queued | Signature/account/webhook matched and receipt was saved. This is not confirmation of payment. Owner verification is pending. |
| verified | Current sandbox reporting matched the attempt and its observation was stored with the notification and audit. No real invoice was paid. |
| ignored | Authenticated but unsupported event. No attempt was changed. |

A missing attempt, mismatched amount/reference/type/account, uncertain provider result, stale observation or conflicting terminal state leaves the message queued with an explanation. Investigate it in the sandbox portal; do not retry a charge just because a callback or reporting request failed. An old event is checked against current reporting, not applied as an old status. Manual transaction verification remains available under Sandbox trials.

## Delivery, rotation and recovery

Successful receipt (including an exact repeated delivery) returns HTTP 200 only after durable receipt and strict audit. A repeated notification ID with different bytes returns 409. Missing/invalid signatures or wrong webhook IDs return 401; malformed JSON/fields return 400; unsupported content types return 415; bodies above 32 KiB return 413. GET returns 405 and non-HTTPS POST returns 403, without a login redirect or session cookie. Disabled/unconfigured/stale credentials and storage failures return 503. Responses contain only a generic received boolean.

Authorize.net retries delivery when it does not receive HTTP 200 and can eventually inactivate a repeatedly failing webhook. Check delivery history and provider webhook status when investigating missed notifications. Application receipt must remain quick; queued verification is explicit and no scheduler/daemon is required. High-volume automated processing, retention policies and provider subscription management are deferred.

Changing or clearing sandbox credentials pauses receipt, even when blank fields retain their prior values. Recheck authentication, verify the correct Signature Key/webhook/account and save receiver settings again. This release accepts only the current key, with no previous-key overlap; coordinate rotation with the provider and inspect any missed deliveries. Existing queued messages remain bound to the original merchant account. A different merchant's credentials cannot reconcile them. Disabling the application receiver retains all history but does not disable the remote provider subscription.

Only minimal identifiers, event names/date, body digest, account fingerprint, receipt/verification metadata and strict audit are stored. Raw bodies, signatures and customer/card details are discarded. Do not configure host/proxy request-body logging for this route. Application audits use system actor 0 for receipt and the authenticated owner for verification.

## Evidence and limitations

Implementation is in PR #20; release acceptance and publication records are maintained in the session handoff. Local syntax/package checks pass. Native tests use synthetic signatures, an independent RFC 4231 cryptographic vector, concurrent receivers, injected reporting responses and failure/race cases. HTTP tests sign exact requests in Python; browser tests use actual PHP forms and the test-only trusted loopback proxy to exercise HTTPS gating. Retained 1.6.0 update tests preserve attempts/events, requests, invoices, credentials and external master key. These tests do not establish real TLS, shared-host deployment, Authorize.net delivery, registration or Signature Key compatibility with an actual merchant.

Before claiming actual sandbox acceptance, separately configure the sandbox merchant through secure owner controls; verify a real signed delivery, duplicate retry, matching transaction reporting, held/settled status recovery and coordinated key rotation on a permitted HTTPS test installation. No real provider operation, deployment, customer charge or mail has been performed by this increment. Client checkout, real-money ledger settlement, refunds/receipts, recurring billing and production enablement remain pending.
