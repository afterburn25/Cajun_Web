# CajunWebPro 1.4.0 test report

Tested immutable source/tag: `0cdea7fbd17e887d3f4bc0574a411b157431b7df`, `v1.4.0`, application PR #14. Exact-main acceptance: https://github.com/afterburn25/Cajun_Web/actions/runs/38371380103. Every required build/native/browser/source-integrity step completed successfully.

## Results

- 50 original, 31 staff, 35 support and 49 catalog native checks passed on PHP 8.3 / **10.11.19-MariaDB-ubu2204**. The retained workflow log records exact runtime versions.
- 345 named real HTTP/Chromium checks passed against the extracted release packages and native MariaDB, without request stubs or SQLite translation.
- The actual published 1.3.0 complete ZIP was downloaded and pinned to SHA-256 `bb9bdf041e8bb158d79edb5564276e061f9a0d5e11f79df839796b19f7e48dcb`. The current UPDATE was applied to its original populated schema/files. Configuration, installed guards, private media, unrelated files, staff passwords/roles/pending invitations, support policy/audit, client/project records, manual invoices and original settings survived explicit catalog setup. No products/prices were silently published.
- Existing actual 1.1.0/1.2.0 and retained 1.0.8 upgrades, one-page keyless fresh installation, ordinary client/staff/support workflows remain passing. Fresh setup initializes the empty catalog.
- Catalog native cases cover interrupted/incompatible/repeated migration, money format/bounds/overflow, duplicate SKU, stale revision, required publication data, blocked unverified provider publication, private cost/reference exclusion, server-side totals with setup once, immutable issued snapshots, same/different request-key replay, simultaneous duplicate issuance in separate PHP processes, permission combinations, support-context denial, strict audit rollback and terminal paid/void transitions.
- Catalog HTTP checks cover public draft/published/retired behavior, configured price changes, private-field isolation, CSRF/client and staff boundaries, server-side amount/currency authority, price changes between preview/issue, unchanged original invoices, duplicate issuance, audit failure rollback, manual receipt and unpaid cancellation. Client invoices retain the old service/price after catalog edits.
- Real Chromium creates/publishes a care product, views configured public cards, reviews an exact calculated invoice, confirms issuance and views it as its owning customer. Desktop/narrow catalog/public/review/client screenshots were reviewed. No unexpected PHP runtime warnings or uncaught browser page errors. Deliberately injected audit failures produce safe diagnostics.
- Exact-commit packaging validates 55 shipping files, 32 PHP files, JavaScript/embedded installer syntax, version/core/inventory equality, canonical foundation copies and flat ZIP members/CRC/per-file hashes. Local complete/update archives and manifest are byte-identical to tested exact-main CI artifacts.

## Package fingerprints

| Asset | Bytes | SHA-256 |
|---|---:|---|
| COMPLETE-UPLOAD | 1433801 | `5d67d3349cf2e48be5a693f9662a2b6f4bbb80c829f03d5ccb5de84a2e678bef` |
| UPDATE | 1340794 | `7858b70141889f9cf4db98280ae4e889bce2378c1b22c0ef4a7cc988595cbeec` |

Complete has 55 flat shipping members; UPDATE has 54 and omits only fresh install.php. No installed config, permanent locks, private storage or customer data is packaged. SHA256SUMS covers the two packages, manifest, report, guides and retained test evidence. Previous releases remain unchanged.

## Scope and limits

This is a bounded M1 / partial HC-01 and HC-06 release: configured service SKUs/prices, private internal costs, public approved service cards, revision history and immutable catalog snapshots on existing manual invoices. Each SKU has one service period and one USD price; one catalog item per invoice, 1–100 units, setup once. Published provider-dependent products, provider quotas, cart/orders, tax/discount calculation, multi-currency, gateway checkout, recurring enrollment/renewals, refunds and reconciliation remain incomplete. Existing manual invoices are not retroactively converted.

Independent access-policy/invitation checks run separately on PHP 8.2/8.4; they do not certify full application behavior on those runtimes. Full application acceptance uses isolated PHP 8.3/MariaDB 10.11 and loopback HTTP. Live shared hosting/HTTPS/Apache or LiteSpeed/SMTP, Authorize.net, 20i, customer data, real payments and provider operations were not exercised or changed. No live deployment occurred. No raw card fields or gateway credentials were introduced. Recover from a complete validated host backup; a code-only downgrade is unsupported.

The permanent TEST-EVIDENCE ZIP contains named results, exact run metadata/logs, synthetic-fixture application screenshots and diagnostics. Fresh draft/public download verification and immutable tag resolution are recorded separately in the release register after readback.
