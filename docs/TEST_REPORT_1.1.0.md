# CajunWebPro 1.1.0 test report

Release source: `3703783e979abab38e8d72e9092c51dc835833b3`. Intended immutable tag: `v1.1.0`.
Exact-main application run: https://github.com/afterburn25/Cajun_Web/actions/runs/38357271480 (all required steps executed successfully).

## Results

- 50 named native PDO MySQL checks passed against **10.11.19-MariaDB-ubu2204**, using PHP 8.3.6 on Ubuntu 24.04. The retained workflow log records the exact installed PHP/Chromium versions.
- 157 named real HTTP/Chromium checks passed against extracted deployment packages. No request stubs or SQLite SQL translation.
- Non-root installer filesystem tests; one-page fresh installation through both HTTP and Chromium; physical setup removal; missing files/extensions, unwritable storage, installed-site guards and preserved form values.
- Actual UPDATE ZIP over the fingerprinted populated 1.0.8 fixture; preserved configuration, guards, media, unrelated files, original settings, owner identity, portfolio and inquiries. Additive migration, repeat/interrupted migration and incompatible-schema rejection passed.
- Saved browser/admin content, pricing, pipeline, clients, milestones and GD uploads; public estimator/review/consultation/contact persistence; published/unpublished sitemap and all four favicon heads.
- CSRF, consent, spam/rate/duplicate controls; forged totals ignored; invitation expiry/single use; real client activation/session revocation; cross-client records/files/invoices/approvals denied; stale/superseded/duplicate decisions rejected; revision concurrency and deliberate restore; executable/path-traversal rejection; truthful manual invoice transitions.
- Desktop, 390px and 320px mobile checks, keyboard/touch, reduced motion, no-JavaScript estimator, root/subfolder paths, implementation screenshots. PHP runtime diagnostics empty; browser diagnostics contain only the explicitly asserted invalid-estimator HTTP 422; no unexpected errors or failed requests.
- Clean-checkout build validated 41 shipping files, PHP/JavaScript/embedded installer syntax, version labels, regenerated dependency inventory, flat archive membership/CRC and per-file SHA-256. Locally rebuilt archives and manifest were byte-identical to the exact-main CI outputs.

Independent components on the same main SHA also passed: [146 access checks](https://github.com/afterburn25/Cajun_Web/actions/runs/38357271523) and [46 native invitation checks](https://github.com/afterburn25/Cajun_Web/actions/runs/38357271475) on each of PHP 8.2.34 and 8.4.26 (MariaDB 11.4.13 for invitations). [Documentation checks](https://github.com/afterburn25/Cajun_Web/actions/runs/38357271521) passed. Their logs and workflow metadata are retained in the evidence ZIP. These foundations remain outside the deployment package.

## Package fingerprints

| Asset | Bytes | SHA-256 |
|---|---:|---|
| COMPLETE-UPLOAD ZIP | 1337124 | `52da8a009ace9643cf65d678ca2de6d9612b01a24be4acd2634668d5de8423cd` |
| UPDATE ZIP | 1245967 | `884ec890f238803f2657ddb994272644926bd8b223cca1330de98149f0f9bec6` |

The complete archive has 41 flat shipping members; UPDATE excludes `install.php` and includes no config, permanent locks or storage. `manifest.json` identifies every complete-package file. SHA256SUMS covers all distributed supporting assets too.

## Evidence and limits

`CajunWebPro-1.1.0-TEST-EVIDENCE.zip` permanently retains named JSON results, build output, real implementation screenshots, server/CI logs and the exact workflow metadata. Every account, lead, invoice, preview capture and message used by acceptance is a synthetic fixture. Screenshots do not represent real commissioned client work. Local mail capture proves local PHP mail acceptance, not external SMTP inbox receipt.

No live hosting, HTTPS proxy/certificate configuration, Apache/LiteSpeed enforcement, production SMTP, customer data, Authorize.net, 20i, domains or VPS were exercised or changed. Full application certification is limited to the recorded PHP 8.3 / MariaDB 10.11 environment; separate foundation PHP 8.2/8.4 tests are not full-application compatibility evidence. No real charges, customer mail or provisioning occurred.

Later roadmap: card payments/subscriptions, complete 20i portal, full webmail/templates/notifications, integrated additional-staff UI, support impersonation/SSH, website ZIP handover, backup/restore and a full file updater. 1.1.0 supplies manual invoices and explicit database upgrades only. Publication/download verification is recorded in the repository release register after the public assets are read back.
