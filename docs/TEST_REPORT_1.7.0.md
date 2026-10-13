# CajunWebPro 1.7.0 test report

Exact source/tag: `1fe0670142755f86aee235fabe1847e5d118ee52` / `v1.7.0`, [PR #20](https://github.com/afterburn25/Cajun_Web/pull/20). [Merged-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38385343738) passed exact-package, native database, actual HTTP/Chromium and unchanged-shipping-source gates.

## Results

- **358 native checks**: 50 original, 31 staff, 35 support, 49 catalog, 62 payment preparation, 69 attempts and 62 notifications. PHP 8.3 / 10.11.19-MariaDB-ubu2204; precise versions in workflow log.
- **451 HTTP/Chromium checks** against extracted packages and native MariaDB. Callback payloads/signatures and provider reporting are synthetic. Loopback proxy-header tests exercise HTTPS gates but do not establish actual TLS/shared-host or merchant delivery acceptance.
- Exact retained 1.6.0 COMPLETE (`b8c25c79f60616a30685490fd1185d2964759091efbb31dc83e57819b232b7ec`) updated with these UPDATE bytes. Additive migration preserves attempts/events, requests, invoices, encrypted gateway credentials, external master key, owner/staff/client/support/catalog records and config/guard/private files. Blank credential retention proves original ciphertext remains readable. All original settings are retained.
- Prior retained 1.0.8/1.1.0/1.2.0/1.3.0/1.4.0/1.5.0 upgrades and fresh one-page install remain passing. No real customer data is used.
- Native notification coverage includes schema/owner checks, independent RFC4231 HMAC vector, binary key/exact bytes, forged/malformed signatures and signed fields, wrong webhook, bounded payloads, durable receipt without network, duplicates and changed-body conflicts, concurrent PHP receivers, audit rollback, reporting amount/reference/type/status mismatches, atomic attempt/inbox rollback, recovery from unknown, credential rotation during reporting, out-of-order events/current reporting, terminal conflicts, unmatched/ignored messages, stale observations, merchant separation and support/staff denials. Real invoice records remain byte-for-byte unchanged.
- Actual HTTP verifies method/content/size/HTTPS/signature handling, generic no-cookie/no-redirect receipt, owner authorization and CSRF, replay protection, empty disabled fresh setup, unmatched queue handling, credentials/payload redaction and browser-controlled disabling. Desktop/320px screenshots and browser diagnostics retained. No unexpected PHP warnings or uncaught browser errors. Existing synthetic Accept.js tests remain labeled as intercepted tokenization, not actual provider success.
- Package checks: 64 shipping files / 40 PHP files, JavaScript syntax, canonical foundations, embedded installer hashes/version agreement, flat 64-file COMPLETE and 63-file UPDATE, ZIP CRC/member/hash checks. Exact-main local rebuild equals CI ZIP and manifest bytes. No private config, keys, guards or customer files ship.

## Package fingerprints

| Asset | Bytes | SHA-256 |
|---|---:|---|
| COMPLETE-UPLOAD | 1515788 | `cc6ef45967dc98ca9cdbd0cef1620bfda0a6d983428e782080ca1298a269bf9a` |
| UPDATE | 1421675 | `840a81166658440793b2bcaeb19fd3c763157637bf45c4d8f28f444e171d5cc5` |

## Scope and limits

Bounded M2 / partial HC-04 and HC-11: disabled-by-default signed sandbox inbox and explicit owner-triggered current reporting. Receipt itself cannot mutate attempts/invoices or call a provider. Only matching current reporting can update a sandbox observation. **No sandbox state is real revenue and no real invoice is paid.** Production/client checkout, scheduled processing, receipts/refunds, recurring billing and a real-money settlement ledger remain pending. Unknown attempts without a matching provider transaction remain unresolved; do not retry blindly.

No merchant credentials were supplied. Actual Authorize.net signature/key compatibility, delivery/retry/rotation, tokenization, transaction capture/settlement and end-to-end recovery have not been verified. Primary API/credential contracts informed implementation; synthetic checks are not provider or PCI acceptance. No live deployment, customer mail or real payment/provider operation occurred. Full 20i, webmail/templates/bells, website ZIP delivery and VPS SSH remain roadmap work.

Independent permission (251 checks) and invitation (46 checks) suites run on PHP 8.2/8.4; the complete application uses PHP 8.3/MariaDB 10.11 in CI. Permanent evidence includes named results, logs, screenshots and workflow metadata. Draft/public fresh download, byte/hash/extraction and tag verification is recorded separately after publication. Earlier versions remain immutable.
