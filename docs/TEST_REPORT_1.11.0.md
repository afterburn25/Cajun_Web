# CajunWebPro 1.11.0 — exact-main acceptance

Source/tag: `de70c5da2e8f7bd35fbb92b9b829d0ab3e880372` / `v1.11.0`. [Merged PR #38](https://github.com/afterburn25/Cajun_Web/pull/38). [Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38422422077). All three application packages come from this same clean tested commit; the Windows rebuild matches the Linux CI artifacts byte for byte. Download verification is recorded after publication.

## Results

- 618 native database/security/authorization checks; 782 HTTP/Chromium checks; 41 filesystem/package/interruption/recovery checks; 203 real Apache checks; 16 negative package-builder checks. Every named result is retained in TEST-EVIDENCE.
- PHP 8.3 / MariaDB 10.11 and Chromium; Apache 2.4 with mod_php under an unprivileged worker, rewrite both enabled and absent. Fresh root/subfolder setup, cookie scope, exact assets, internal denial, no directory listings, non-executable uploads and root-owned unused scaffolds are exercised. Actual create/read/write/rename/delete operations pass in the configured private store.
- All eleven immutable starting packages (1.0.8 and 1.1.0 through 1.10.0) pass real owner-authenticated staged updates. Existing config/guards/private bytes, credentials, accounts, business records, invoices, sandbox state and published client ZIPs retain their acceptance coverage. Legacy admin POSTs preserve authentication/body, and legacy signed callbacks preserve the exact signed body/query without redirects or duplicate processing.
- Missing staged bootstrap/payload, customization, interrupted journaled operations, repeated retry/recovery, denied writes, permanent lock conflicts and protected recovery copies pass. Apache maintenance blocks the old application while the owner recovery page remains reachable. Cached old PHP with timestamp validation disabled is invalidated before activation.
- One-page keyless fresh setup remains one submission; missing support produces a useful 503 message; successful setup removes the fixed setup-only PHP list and leaves internal denial in place.
- Public forms/estimator/portfolio, roles/support sessions, project messages/approvals/invoices, sandbox-only workflows, private resumable ZIP delivery and activity bells retain full application coverage. Provider and gateway observations are synthetic; no live charges, mail, hosting/VPS operations or SSH were performed.
- Desktop and mobile homepage screenshots are pixel-identical to published 1.10.0; administrator/client dimensions are retained with content/version differences. Original image bytes are checked against the pre-refactor inventory. Screenshots and numerical comparison are retained in TEST-EVIDENCE.
- Builder rejects untracked/dirty source, symlinks, unsafe or duplicate archive members, secrets/runtime data, altered protected scaffolds, root business modules, missing moved modules and stale code/CSS/URL mappings. PHP/JavaScript syntax and generated installer/foundation consistency pass.

## Packages and operation

FULL-PROJECT is the sanitized development bundle, including docs/tests/tools/brand/foundation/CI and organized site/. COMPLETE-UPLOAD contains 218 production files and extracts directly into the website directory with its internal folders. UPDATE contains 205 staged files and uses the authenticated migration; it is never a blind overlay and never invokes fresh installation. See START-HERE.txt and MIGRATION-INSTRUCTIONS.md.

Six upgrade-only direct-dispatch compatibility entries preserve prior URLs. Private data stays at its configured location; runtime upload staging is beneath that same store. Backup confirmation is required; bounded code rollback does not replace a recoverable matched website/database/private-storage backup.

## Limits

No live host or LiteSpeed instance was inspected or deployed. Apache-compatible rule acceptance does not certify nginx/IIS or an individual hosting account. Interruption tests stop after durable journaled operations; they do not claim arbitrary hardware/power-loss certification. Production payment/provider gates remain unchanged. Separate independent component checks run on PHP 8.2/8.4; full application acceptance is on PHP 8.3.

Counts by suite: `{"activity-native": 64, "apache": 203, "attempts-native": 69, "build-guards": 16, "catalog-native": 49, "delivery-archive": 55, "delivery-native": 57, "http": 782, "ledger-native": 69, "native": 65, "notifications-native": 62, "payments-native": 62, "staff-native": 31, "structure": 41, "support-native": 35}`.

## Published download verification

All 16 [release assets](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.11.0) were freshly downloaded after publication and exactly match the prepared files. Every SHA256SUMS entry passes; each ZIP member/hash/directory inventory and all common/per-package source commits match. The immutable tag resolves to `de70c5da2e8f7bd35fbb92b9b829d0ab3e880372`. Previous v1.10.0 asset fingerprints remain unchanged.

| Package | Members | SHA-256 |
| --- | ---: | --- |
| FULL-PROJECT | 384 | `4cf9985ae97143449d17c95dc9caf8ad0337b0b13ad14295a5c4aff1f2c9e83b` |
| COMPLETE-UPLOAD | 218 | `5bf60867bc47d14ac112bebcb30d02899a2ff13167d39bd01ca9e8dfb5c79f2c` |
| UPDATE | 205 | `927bb9f121076a0d627807a7809e099c551372a7d57acc71954980720462568d` |
