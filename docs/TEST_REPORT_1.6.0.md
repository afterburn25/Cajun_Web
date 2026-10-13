# CajunWebPro 1.6.0 test report

Tested source/tag: `e5d4c17456faff0522429ab3f58093a06a523bd5` / `v1.6.0`, [PR #18](https://github.com/afterburn25/Cajun_Web/pull/18). [Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38380935411) passed package, native DB, real HTTP/Chromium and unchanged-source gates.

## Results

- **296 native checks**: 50 original, 31 staff, 35 support, 49 catalog, 62 preparation and 69 sandbox attempts. PHP 8.3 / 10.11.19-MariaDB-ubu2204; precise runtime details in retained workflow log.
- **421 HTTP/Chromium checks** exercise extracted deployment packages and native MariaDB. Sandbox browser tokenization alone uses explicitly intercepted synthetic Accept.js and a captured POST boundary. This is not real merchant or live HTTPS acceptance.
- Original retained 1.5.0 COMPLETE package pinned to `99c4c76a369154d60c142d3af07fb35d57393ee9205fb259dee5a05851854e98` updated with these UPDATE bytes. Additive trial setup preserves encrypted credentials, master key, requests, manual/catalog invoices, product snapshots, accounts, invitations, support records, private files, config and installed guard. Blank credential retention confirms old ciphertext remains readable.
- Existing 1.0.8/1.1.0/1.2.0/1.3.0/1.4.0 upgrades and fresh one-page installation remain passing. No real customer data is used.
- Native trial tests cover owner/support/staff boundaries, incompatible/repeated setup, USD merchant preflight, stale revisions, durable intent before send, concurrent process duplicate submission, saved amount/token-only request, timeout/no retry, mismatched reporting ID/reference/amount/type/currency/partial/status, held results, credential/account changes, transaction-ID collisions, stale/conflicting observations, strict audit rollback, recovery after post-send persistence failure, and manual/catalog/request locking including a preflight/manual-payment race.
- Actual HTTP tests enforce authentication, CSRF, HTTPS gating and unexpected raw-card field rejection. Chromium checks the owner form, public tokenization fields versus protected secrets, scoped CSP, field placement, single token-only submission, and desktop/320px layout. Browser provider script and POST capture are simulated; no actual provider traffic. No unexpected PHP warnings or uncaught browser errors.
- Shipping acceptance covers 61 flat files / 37 PHP files, JavaScript syntax, canonical bundled foundations, embedded installer/inventory/version agreement and ZIP CRC/member/hash checks. Local exact-main rebuilt package/manifest bytes equal CI. Complete/update contain 61/60 files. No private runtime configuration, key, guard or client files are shipped.

## Package fingerprints

| Asset | Bytes | SHA-256 |
|---|---:|---|
| COMPLETE-UPLOAD | 1494827 | `b8c25c79f60616a30685490fd1185d2964759091efbb31dc83e57819b232b7ec` |
| UPDATE | 1401104 | `97ffbad970d173de6958ef8ddb0c281a535a233fc50bed44d81664f211fd7ce1` |

## Scope and limitations

Bounded M2 / partial HC-04 and HC-11: owner-only sandbox trials, minimal attempt/events, explicit transaction-detail reconciliation, and shared invoice/request locks. **Sandbox outcomes never settle real invoices.** Production/client checkout, guest links, signed webhooks, automatic transaction discovery/reconciliation, receipts/refunds and recurring billing remain pending. A missing transaction after timeout stays unresolved; there is no forced-success or blind retry escape.

No sandbox merchant credentials were supplied, so actual tokenization, authentication, charge/capture/settlement and recovery with Authorize.net have not been verified. Synthetic tests do not establish PCI compliance or real provider acceptance. Live deployment, customer/provider operations, mail and charges were not performed. Full 20i, webmail/templates/bells, website ZIP delivery and VPS SSH remain roadmap work.

Independent 251-check permission and 46-check invitation suites run on PHP 8.2/8.4; full application CI uses PHP 8.3/MariaDB 10.11. Loopback HTTP with a test-only trusted-proxy header exercises the HTTPS gate for simulated browser rendering; it is not a live TLS/shared-host test. Permanent evidence includes named results, logs, screenshots and exact workflow metadata. Downloaded-back draft/public asset and tag verification is recorded after publication. Earlier released assets/tags remain immutable.
