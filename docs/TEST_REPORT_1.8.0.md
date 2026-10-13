# CajunWebPro 1.8.0 test report

Exact source/tag: `c36a36e80c40485c9e3e7e6cdd4884ced25db961` / `v1.8.0`, [PR #22](https://github.com/afterburn25/Cajun_Web/pull/22). [Merged-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38390645499) passed exact-package, native database, actual HTTP/Chromium and unchanged-source gates.

## Results

- **427 native checks**: 50 original, 31 staff, 35 support, 49 catalog, 62 payment preparation, 69 sandbox attempts, 62 notifications and 69 invoice ledger. PHP 8.3 / 10.11.19-MariaDB-ubu2204; exact versions in workflow logs.
- **503 actual HTTP/Chromium checks** against extracted packages with native MariaDB. Payment records are synthetic external-payment fixtures; gateway/HMAC/Accept.js evidence remains synthetic, not actual merchant acceptance. No real payment, refund, mail or provider operation.
- Retained 1.7.0 COMPLETE SHA-256 `cc6ef45967dc98ca9cdbd0cef1620bfda0a6d983428e782080ca1298a269bf9a` updated with these exact UPDATE bytes. Migration preserves existing invoices, requests, encrypted credentials/external master key, unresolved trials, queued notifications, staff/client/support/catalog data, original settings and files. Legacy paid balances retain original amount/date without new receipts. Blank credential retention confirms saved credentials still decrypt.
- Earlier 1.0.8 through 1.6.0 update paths and one-page keyless fresh installation remain passing. No customer data used.
- Native ledger checks exercise exact cents, partial/full payments, invalid amounts/dates/methods, overpayments, stale reviews, idempotent retries/changed payloads/actors, normalized duplicate references, permission combinations, owner-only corrections and reauthentication, concurrent receipt processes, strict audit rollback, immutable originals, legacy import and incompatible/unmarked schema rejection, sandbox separation/unresolved guards, remaining requests/trials, invoice tampering/inactive clients, catalog paid/void bypass denial, cross-client receipts and support denial.
- HTTP checks exercise additive migration/CSRF, private reference escaping/redaction, precise amount validation, duplicate/stale submissions, corrected receipts, denied receipt audit, staff/client/anonymous isolation, remaining request amounts and strict correction rollback. Browser records an external receipt, displays client statements and prints a receipt PDF; desktop/320px/print screenshots retained. No unexpected PHP diagnostics, uncaught browser errors or ledger gateway requests.
- Packages contain 66 shipping files / 42 PHP files. COMPLETE has 66 flat files; UPDATE has 65. JavaScript, embedded installer bootstrap/version/dependency hashes, canonical foundations, ZIP CRC/members and file hashes are checked. The exact-main local rebuild equals CI packages and manifest. Configuration, keys and customer files are excluded.

## Package fingerprints

| Asset | Bytes | SHA-256 |
|---|---:|---|
| COMPLETE-UPLOAD | 1547093 | `a3aa9d335e5cf39904c757aa94631d9bae80f47896be695819523849d8f2f190` |
| UPDATE | 1452717 | `357984c623810e6f760ee54d15275c678c558e596019ec822e305e81161db472` |

## Scope and limits

Bounded M2 / HC-06 / HC-11 external-payment bookkeeping and client documents. No production card checkout, card gateway settlement, refunds, credits/tax, subscriptions or recurring billing. Recording corrections do not move money. Legacy paid records are imported facts and cannot create new verified receipts. Client printing is browser-generated HTML/PDF, not an automatic PDF/email system.

Actual merchant credentials were not supplied. Real Authorize.net delivery, tokenization, capture/settlement and recovery remain unverified; synthetic provider tests are not provider or PCI acceptance. No shared-host deployment or live customer operation occurred. Full 20i, webmail/templates/bells, client website ZIP delivery and VPS SSH remain roadmap work. Devin PR #7 remains separate.

Independent permission (251 checks) and invitation (46 checks) suites run on PHP 8.2/8.4. The complete application suite runs on PHP 8.3/MariaDB 10.11. Retained evidence contains named results, workflow metadata/logs, screenshots and the printed sample PDF. Draft/public fresh download, byte/hash/extraction and tag verification are recorded after publication. Prior releases remain immutable.
