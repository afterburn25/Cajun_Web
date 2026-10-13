# CajunWebPro 1.10.0 test report

Exact source/tag: `4649a913d40166b9874f9550e57b4186edfc43ab` / `v1.10.0`, [PR #26](https://github.com/afterburn25/Cajun_Web/pull/26). [Merged-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38402918742) passed exact-package, native database, real HTTP/Chromium and unchanged-source gates.

## Results

- **603 native checks**, including 64 new activity checks; named counts: `{"activity-native": 64, "attempts-native": 69, "catalog-native": 49, "delivery-archive": 55, "delivery-native": 57, "http": 630, "ledger-native": 69, "native": 50, "notifications-native": 62, "payments-native": 62, "staff-native": 31, "support-native": 35}`. Full application: PHP 8.3 / 10.11.19-MariaDB-ubu2204; runtime versions retained in workflow logs.
- **630 HTTP/Chromium checks** on extracted packages and native MariaDB. Synthetic identities and records; no customer/provider operations.
- Real retained 1.9.0 COMPLETE SHA-256 `9a7b11f36cb3ca502637b59356c8163d274ad5e0a53fc42d88d1c0b0b9d11c23` upgraded with the exact UPDATE. Setup preserves accounts, credentials/master key, configuration/guards/private media, original settings, projects/reviews/messages, invoices/receipts, requests/attempts/notification inbox, catalog/support and published/draft client ZIPs/manifests/events. Retained published ZIP downloads byte-identically; prior balance renders and encrypted credentials still decrypt.
- Earlier retained 1.0.8 through 1.8.0 updates and the one-page keyless fresh installation pass.
- Native activity: owner setup/schema compatibility and unknown data refusal, strict audit rollback, authorized source kinds, payload minimization, explicit idempotent read state, per-client/per-staff isolation, current grants/auth/ownership, support denial, passive expiry, source removal, capped counts, stable history pagination, new arrivals versus reviewed batches and concurrent read marks. No activity hook is added to business writes.
- HTTP activity: setup/read CSRF, current-password validation, anonymous/client/staff separation, explicit dual identities, private cache/CORS policy, escaped metadata, no implicit reads, stale batch/new arrivals, audit/database failure recovery, uninterrupted business message saving, exact old-message links, pagination, count caps, support denial and role/client revocation.
- Real Chromium: desktop/320px admin and client screens, authorized public-site staff bell, separate client bell, anonymous absence, one-minute/focus/hidden polling with a controlled browser clock, stale-error indicator, recovery and revocation. No uncaught browser errors or unexpected PHP diagnostics. Screenshots reviewed before release.
- All earlier archive, private delivery, support, ledger, sandbox and authorization acceptance remains passing. Gateway/signature observations are synthetic, not actual merchant acceptance.
- COMPLETE: 73 shipping files / 48 PHP files. UPDATE: 72 files. PHP/JavaScript syntax, installer embedded bootstrap/version/inventory hashes, ZIP CRC/membership and content hashes pass. Local exact-main rebuild equals CI packages/manifest. Configuration, keys and client files are excluded.

## Package fingerprints

| Asset | Bytes | SHA-256 |
|---|---:|---|
| COMPLETE-UPLOAD | 1608483 | `a9003b041552c6759ade64a471fbfb940e192dcb5ad6d8bcb4d610902f154885` |
| UPDATE | 1513299 | `5095fea4de7240c1d1d47b4c04e18e3197c56863b0f1e138b8be566cf2cbfb2c` |

## Scope and limits

Bounded P-07 workspace activity only. Historical retained facts initially appear unread; original source retention controls their visibility. Review decisions and their generated conversation messages are distinct records. Source bodies/payment references/draft ZIPs are never included in bell responses. Background polls preserve idle expiry and check at most once per active minute; no push or email delivery claim.

Full P-07, SMTP dispatch, preferences, stage timeline, hosting/renewal/ticket events, A-06–A-08 mail/templates/email bells and broader 20i/VPS SSH remain pending. Real card checkout and actual merchant acceptance are incomplete. No live customer deployment or target-host verification occurred. No customer mail, payment/refund or provider operation.

Independent permission (251) and invitation (46) suites run on PHP 8.2/8.4; full application on PHP 8.3/MariaDB 10.11. Evidence retains named results, workflow metadata/logs and screenshots. Draft/public fresh download, byte/hash/extraction and tag verification are recorded after publication. Previous releases and Devin PR #7 remain intact.
