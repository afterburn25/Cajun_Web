# CajunWebPro 1.9.0 test report

Exact source/tag: `080f14aea8fe5c35e04e4eba62976609aef7cd67` / `v1.9.0`, [PR #24](https://github.com/afterburn25/Cajun_Web/pull/24). [Merged-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38396372080) passed exact-package, native database, actual HTTP/Chromium and unchanged-source gates.

## Results

- **539 native checks**: 50 original, 31 staff, 35 support, 49 catalog, 62 payment preparation, 69 sandbox attempts, 62 notifications, 69 invoice ledger, 55 archive and 57 delivery persistence/permissions checks. PHP 8.3 / 10.11.19-MariaDB-ubu2204; exact runtime versions in workflow logs.
- **569 actual HTTP/Chromium checks** against extracted packages and native MariaDB. Client packages and identities are synthetic. Prior gateway, signature and payment records remain synthetic; no actual merchant acceptance, customer mail, real charge/refund or provider operation.
- Retained 1.8.0 COMPLETE SHA-256 `a3aa9d335e5cf39904c757aa94631d9bae80f47896be695819523849d8f2f190` updated using the exact UPDATE package. Additive setup preserves client/staff/support/catalog data, invoices and external receipts, requests, unresolved attempts/queued notifications, encrypted credentials/master key, original settings/configuration/guards/private media and unrelated files. A retained client downloads a newly published ZIP; previous balances render and protected credentials still decrypt.
- All earlier 1.0.8 through 1.7.0 update paths and the one-page keyless fresh installation pass.
- Native archive tests cover traversal/absolute/ambiguous/case-colliding paths, overlaps, Windows reserved names, links/special files, nested/database/config/credential files, encryption, corruption, executable prefixes, missing/empty handover documents, static/files-only distinctions, credential boundaries and entry/file/compression limits. Uploaded code is inspected without execution or extraction.
- Native database tests cover explicit schema/migration compatibility, strict audit rollback, owner reauthentication, staff/client/support isolation, guarded outside-root files, immutable/idempotent versions, stale review rejection, inactive/revoked/moved ownership, altered/missing/symlink file rejection, quota enforcement, concurrent same-version uploads and byte-range validation.
- HTTP tests cover CSRF and publication confirmations, escaped metadata, draft privacy, client isolation, exact ZIP headers/bytes, original version retention, range reconstruction/416/If-Range/HEAD, audit failure before streaming, corrupt-file refusal, attributed support downloads, missing grants, expired support and revoked client access. Chromium inspects desktop/320px screens, downloads exact bytes and opens the extracted synthetic static site locally. No uncaught browser errors or unexpected PHP diagnostics.
- COMPLETE has 69 shipping files / 45 PHP files; UPDATE has 68 files. Syntax, embedded installer bootstrap/version/dependency hashes, canonical foundations, ZIP CRC/members and file hashes pass. Exact-main local rebuild equals CI packages and manifest. Configuration, keys and customer files are excluded.

## Package fingerprints

| Asset | Bytes | SHA-256 |
|---|---:|---|
| COMPLETE-UPLOAD | 1585725 | `9a7b11f36cb3ca502637b59356c8163d274ad5e0a53fc42d88d1c0b0b9d11c23` |
| UPDATE | 1490996 | `99278fb1008e230ee51aa54c91ed4d10d8ec6a914f6030438ea0c4f952a26640` |

## Scope and limits

Bounded P-06 prepared website ZIP handover. Owner review remains necessary; automated scanning is not proof of absence of all secrets or transfer rights. Static sites need no database; files-only packages explicitly exclude databases/content and external services. No automatic builds, database export/import, larger background jobs, complete dynamic restore orchestration, notifications or automatic deletion/retention. Full P-06 is partial. No paid-in-full gate.

No live customer site or shared-host deployment occurred. Production card checkout, actual merchant acceptance, refunds/credits/tax/subscriptions, broader 20i, multi-account mail/templates/bells and VPS SSH remain roadmap work. Devin PR #7 remains separate. Synthetic provider evidence is not actual provider or PCI acceptance.

Independent permission (251 checks) and invitation (46 checks) suites run on PHP 8.2/8.4; the full application suite runs on PHP 8.3/MariaDB 10.11. Retained evidence includes named results, workflow metadata/logs, screenshots and a synthetic client ZIP. Draft/public fresh download, byte/hash/extraction and tag verification are recorded after publication. Prior releases remain immutable.
