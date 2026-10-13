# 1.5.0 release scope and acceptance

| Roadmap | Delivered subset | Evidence |
|---|---|---|
| HC-04 | Owner-only separate four-field Sandbox/Production settings, masked/blank retention, revision-safe replacement/removal, external authenticated-encryption key | Native filesystem/crypto/tamper/lost-key/stale-write and actual HTTP/browser secret handling |
| HC-04 | Fixed sandbox authentication-only adapter, TLS/timeout/size limits, safe status, rotation race/rate control | Synthetic CLI response/transport fixtures; no real merchant success claimed |
| HC-11 / M2 | Reviewed prepared requests against existing USD invoices, full/deposit/milestone/final labels, expiry/revoke/replace | HTTP/Chromium review/prepare, stale amount/currency and client isolation tests |
| HC-06 reliability | Serialized duplicates, strict audit rollback, unchanged invoice financial state | Separate concurrent PHP processes and native/HTTP injected audit failures |
| A-04 / F-04 | Additive payment setup, actual retained 1.4.0 upgrade, one-page setup and retained packages | Exact-main acceptance and draft/public release readback |

M2 remains partial. No card entry, charge, production connection, guest payment capability, verified webhook, receipt, refund, uncertain-transaction reconciliation or recurring subscription exists yet. Authentication alone does not verify all four keys or a card payment. Existing manual billing remains unchanged. No live deployment/customer/provider operation. [Guide](PAYMENT_PREPARATION.md), [report](TEST_REPORT_1.5.0.md), [releases](RELEASES.md).
