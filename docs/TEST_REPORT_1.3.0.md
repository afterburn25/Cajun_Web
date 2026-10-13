# CajunWebPro 1.3.0 test report

Exact tested release source: `7856cccf230a1646d02a4a92ad4bfb1c280efa19`. Immutable tag: `v1.3.0`. Application merge: PR #12.
Exact-main acceptance: https://github.com/afterburn25/Cajun_Web/actions/runs/38367798995. All required steps completed successfully.

## Results

- 50 original native checks, 31 staff adapter checks and 35 support adapter checks passed on PHP 8.3 / **10.11.19-MariaDB-ubu2204**. The workflow log records exact PHP/browser versions.
- 289 named HTTP/Chromium checks passed against the extracted deployment packages. Tests use a native MariaDB database and real loopback requests, without request stubs or SQLite translation.
- The actual published 1.2.0 complete package was downloaded and pinned to SHA-256 `f3da85146c316da6f94cef5b2f0e7aba8b2526f0a683218da85d5215c6d1d32e`. The new UPDATE ZIP was overlaid on its populated original schema/files. Configuration, permanent guards, private media, unrelated files, original settings, user passwords/auth versions, staff roles/grants, pending invitation records, inquiries, portfolio and client projects survived. Support setup required explicit owner confirmation; no customer was silently opted in.
- Existing actual 1.1.0 and retained 1.0.8 updates, fresh one-page keyless installation, administrator lifecycle and ordinary customer/project workflows remain passing. Fresh installation includes support tables but requires explicit support policy confirmation.
- Support native tests cover incompatible/interrupted/repeated schema setup, owner reauthentication, default-denied client policy, immutable staff/client identities, unmodified actual-customer login, hashed browser binding, fixed/idle expiry, nested-switch rejection, scoped messages, separate CSRF, copied-link denial, client/staff credential or permission revocation and logout. An injected database audit failure rolls back the write.
- Support HTTP tests cover one-click entry on the client record, coexisting real customer login, stripped-context and ordinary-token rejection, stale-tab isolation, cross-client project/message/download denial, blocked client approvals/security/payment mutations, actual staff-attributed messages/uploads/downloads, strict audit failure rollback/cleanup and no-byte download failure, view-only controls and forged requests, explicit write/download grants and revocation/logout.
- Actual Chromium checks exercise narrow/desktop administration and portal rendering, client-record entry, context-preserving navigation, staff message submission, persistent banner on scrolling and Return to admin. Reviewed actual screenshots. No PHP runtime warnings or uncaught browser errors. Expected injected audit failures have sanitized diagnostic references and do not leak database messages.
- Exact-commit packaging verifies 52 shipping files, 29 PHP files, local/embedded JavaScript syntax, installer/core/version equality, dependency hashes and canonical access/invitation copies. Complete/update ZIP members, CRC and SHA-256 are validated. The local rebuild and manifest match the exact-main CI artifacts byte for byte.

## Package fingerprints

| Asset | Bytes | SHA-256 |
|---|---:|---|
| COMPLETE-UPLOAD | 1404661 | `bb9bdf041e8bb158d79edb5564276e061f9a0d5e11f79df839796b19f7e48dcb` |
| UPDATE | 1311999 | `67c49bbbefcbfc1a16e2e5737ce058dc1358b246c4c9ad99afe5bdad3f5fc2bb` |

Complete contains 52 flat files; UPDATE contains 51 and excludes only fresh `install.php`. Neither includes installed configuration, permanent locks, private storage or customer data. SHA256SUMS covers the packages, manifest, guides, this report and permanent evidence archive. Previous released versions remain immutable.

## Scope and limits

This release delivers A-09 for the current website-project portal: separate support permissions, explicit client restrictions, browser-bound expiring context, staff attribution and strict audit. It does not implement provider SSO, VPS SSH, client payment collection, recurring billing, webmail/templates/bells, MFA, per-staff client assignment lists, website ZIP delivery or a full application updater. Those remain roadmap work.

The TEST-EVIDENCE ZIP preserves named results, exact run metadata/logs, real application screenshots and diagnostics. Component PHP 8.2/8.4 policy/invitation results are separate from full application acceptance on PHP 8.3/MariaDB 10.11. Local captured mail is not external inbox delivery. Live hosting, HTTPS/proxy/certificate setup, Apache/LiteSpeed enforcement, customer records, Authorize.net, 20i and VPS were not exercised or changed. No live deployment, charge, provisioning or customer mail occurred. Recover using a complete validated host backup; a code-only downgrade is unsupported. Release upload/download verification is recorded separately after fresh readback from GitHub.
