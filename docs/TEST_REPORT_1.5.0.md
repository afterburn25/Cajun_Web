# CajunWebPro 1.5.0 test report

Tested source/tag: `3e95bdf81ca0126d99a69c41f2ff6eb0c08da1e1`, `v1.5.0`; application [PR #16](https://github.com/afterburn25/Cajun_Web/pull/16). Exact-main acceptance: https://github.com/afterburn25/Cajun_Web/actions/runs/38375934366. Build, native database, actual HTTP/browser and unchanged-shipping-source steps all completed successfully.

## Results

- **227 native checks**: 50 original, 31 staff, 35 support, 49 catalog, 62 payment preparation. PHP 8.3 / **10.11.19-MariaDB-ubu2204**; exact runtime versions in retained workflow log.
- **401 named HTTP/Chromium checks** against extracted packages and native MariaDB. No SQLite translation or browser request stubs.
- Actual published 1.4.0 COMPLETE-UPLOAD pinned to `5d67d3349cf2e48be5a693f9662a2b6f4bbb80c829f03d5ccb5de84a2e678bef` was updated using this UPDATE package. Preserved configuration, guards, private files, owner passwords/identities, staff grants/invitations, support policies/audit, clients/projects, manual/catalog invoices, product revisions/snapshots and prior settings. Additive payment setup creates no credentials or requests.
- Earlier 1.0.8/1.1.0/1.2.0/1.3.0 upgrade, one-page fresh install, client/staff/support/catalog and manual-invoice regressions remain passing.
- New native tests cover owner reauthentication, incompatible/repeated migration, secret format/encryption/environment binding, authenticated tamper denial, external owner-only key storage, lost-key fail-closed behavior, stale saves, blank retention, credential removal, sanitized authentication responses, verification/rotation race and rate limits. Gateway results use explicitly synthetic CLI transports; no Authorize.net network operation was performed.
- Request tests cover saved invoice amounts, ownership/status, stale review, purpose/expiry boundaries, duplicate retry and simultaneous separate PHP processes, supersession/revocation, inactive clients, paid/void invoices, explicit permission combinations, support-context denials, and audit failure rollback. Preparing requests never changes financial status.
- Actual HTTP tests exercise masked forms/errors, CSRF, credential separation/removal, forged production checks, stale writes, forged amounts/currency, client isolation, staff grants, request lifecycle and transactional audit rollback. Real Chromium saves credentials, confirms blank reads, reviews/prepares requests, and checks desktop/320px layouts. No unexpected PHP warnings or uncaught browser errors. Screenshots show synthetic fixtures only.
- Exact packaging validates 58 flat shipping files, 35 PHP files, JavaScript syntax, embedded bootstrap/inventory/version agreement, canonical foundation copies, ZIP members/CRC/hashes. Locally rebuilt complete/update/manifest bytes equal exact-main CI artifacts. No private configuration, master key, installed lock or customer data is packaged.

## Package fingerprints

| Asset | Bytes | SHA-256 |
|---|---:|---|
| COMPLETE-UPLOAD | 1463955 | `99c4c76a369154d60c142d3af07fb35d57393ee9205fb259dee5a05851854e98` |
| UPDATE | 1370603 | `be704aee759d8920e7dac9a8e137c9878732d04b0a28021c5db1eb847cf7477f` |

Complete has 58 files; UPDATE omits only fresh install.php. SHA256SUMS covers deployment ZIPs, manifest, report, guides and permanent test evidence. Older tags/releases remain unchanged.

## Scope and limitations

Bounded M2 / partial HC-04 and HC-11: owner-only protected separate gateway credentials, sandbox authentication diagnostics, and invoice-bound prepared requests. **Card collection, charges, production connections, public payment links, verified webhooks, receipts/refunds/reconciliation and recurring billing remain disabled or unimplemented.** Authenticate success verifies only API Login ID and Transaction Key; other keys and actual transactions need independent provider tests. An issued manual invoice or prepared request is not a processed payment.

Independent 251-check permission policy and 46-check staff-invitation suites run separately on PHP 8.2/8.4; they do not certify full application behavior on those runtimes. Full application acceptance uses PHP 8.3/MariaDB 10.11 and isolated loopback HTTP. No merchant credentials were supplied. Real sandbox authentication, live HTTPS/shared hosting, host aliases/private-storage backup exclusions, Authorize.net transaction processing, 20i and customer/provider operations were not tested or changed. No live deployment or mail was sent. Recover using a complete verified host backup; code-only downgrade is unsupported.

The permanent TEST-EVIDENCE ZIP retains named results, logs, exact workflow metadata, screenshots and diagnostics. Fresh draft/public download verification and tag resolution are recorded separately after publication.
