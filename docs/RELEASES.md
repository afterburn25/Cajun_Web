# Release register

## v1.3.0 — published 13 October 2026

[Release](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.3.0) published `2026-10-13T08:17:54Z`; immutable source/tag `7856cccf230a1646d02a4a92ad4bfb1c280efa19`. Application merge [PR #12](https://github.com/afterburn25/Cajun_Web/pull/12). Publication records are documentation only and do not alter release bytes.

| Asset | Bytes | SHA-256 |
|---|---:|---|
| [COMPLETE-UPLOAD](https://github.com/afterburn25/Cajun_Web/releases/download/v1.3.0/CajunWebPro-1.3.0-COMPLETE-UPLOAD.zip) | 1404661 | `bb9bdf041e8bb158d79edb5564276e061f9a0d5e11f79df839796b19f7e48dcb` |
| [UPDATE from 1.2.0 / 1.1.0 / 1.0.8](https://github.com/afterburn25/Cajun_Web/releases/download/v1.3.0/CajunWebPro-1.3.0-UPDATE.zip) | 1311999 | `67c49bbbefcbfc1a16e2e5737ce058dc1358b246c4c9ad99afe5bdad3f5fc2bb` |

Eight retained assets: complete/update ZIPs, manifest, SHA256SUMS, test report, installation guide, client support guide and permanent TEST-EVIDENCE ZIP. Complete has 52 flat members; update has 51 and excludes fresh setup. All assets were freshly downloaded both as draft and after publication; bytes/hashes/CRC/extraction/versions/tag match the tested exact-main build. Earlier releases remain immutable.

[Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38367798995): 50 original + 31 staff + 35 support native and 289 HTTP/Chromium checks, including actual 1.2.0 update, previous upgrades, fresh setup, audit-failure injection and stale-tab/client isolation. [Report](TEST_REPORT_1.3.0.md), [scope](RELEASE_1.3.0.md), [verification](RELEASE_VERIFICATION_1.3.0.json), [support setup](CLIENT_SUPPORT.md). No target-host deployment or provider/customer operations. SSH and provider SSO are not part of this portal-support release.

## v1.2.0 — published 13 October 2026

Release: [v1.2.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.2.0), published at `2026-10-13T07:28:07Z`. Immutable source/tag: `f85627e05b171e4ee8508231e2ed6ee7b9374274`; application merge [PR #10](https://github.com/afterburn25/Cajun_Web/pull/10). Later publication records are documentation-only; the tag, shipping source and prior releases remain unchanged.

| Deployment asset | Bytes | SHA-256 |
|---|---:|---|
| [COMPLETE-UPLOAD](https://github.com/afterburn25/Cajun_Web/releases/download/v1.2.0/CajunWebPro-1.2.0-COMPLETE-UPLOAD.zip) | 1377207 | `f3da85146c316da6f94cef5b2f0e7aba8b2526f0a683218da85d5215c6d1d32e` |
| [UPDATE from 1.1.0 / 1.0.8](https://github.com/afterburn25/Cajun_Web/releases/download/v1.2.0/CajunWebPro-1.2.0-UPDATE.zip) | 1284797 | `24ed8ae972c75b62a50d6145a3a8cb1bd5dfa91b29a03a96d4fad533e8cf2147` |

Eight permanent assets: complete/update ZIPs, manifest, SHA256SUMS, test report, installation instructions, administrator guide and retained TEST-EVIDENCE ZIP. Complete has 50 flat members; UPDATE has 49, excluding fresh setup. Core A-05 account lifecycle is delivered as described in the [administrator guide](ADMIN_ACCOUNTS.md) and [feature matrix](RELEASE_1.2.0.md); mailbox grants/MFA and later platform capabilities remain deferred.

[Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38362411560) passed 50 original native, 31 staff native and 216 HTTP/Chromium checks, including the actual published 1.1.0 package update, populated 1.0.8 upgrade, fresh setup, assigned/forged requests and concurrent owner changes. Independent access (176 each PHP 8.2/8.4), invitations (46 each) and documentation checks passed. [TEST_REPORT](TEST_REPORT_1.2.0.md) and the permanent evidence archive retain named cases, logs and screenshots.

Every asset was downloaded into a new directory before and after publication, matched byte-for-byte to the prepared release, and checked against SHA256SUMS. Both archives passed CRC, flat membership, safe extraction, every manifest hash and version checks; v1.2.0 resolves to the tested SHA. [Verification JSON](RELEASE_VERIFICATION_1.2.0.json). No live deployment, host HTTPS/SMTP, charges, customer mail/data or provider operations. Full application environment is PHP 8.3.6/MariaDB 10.11.19. Downgrade requires a complete validated host backup; old code does not enforce the new roles.


## v1.1.0 — published 13 October 2026

Release: [https://github.com/afterburn25/Cajun_Web/releases/tag/v1.1.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.1.0). Published at `2026-10-13T06:39:50Z`.
Immutable source/tag: `3703783e979abab38e8d72e9092c51dc835833b3`. Application merge: [PR #8](https://github.com/afterburn25/Cajun_Web/pull/8). Later documentation commits record publication and handoff only; `site/`, tests and build tooling remain the tested release tree.

| Deployment asset | Bytes | SHA-256 |
|---|---:|---|
| [COMPLETE-UPLOAD](https://github.com/afterburn25/Cajun_Web/releases/download/v1.1.0/CajunWebPro-1.1.0-COMPLETE-UPLOAD.zip) | 1337124 | `52da8a009ace9643cf65d678ca2de6d9612b01a24be4acd2634668d5de8423cd` |
| [UPDATE from 1.0.8](https://github.com/afterburn25/Cajun_Web/releases/download/v1.1.0/CajunWebPro-1.1.0-UPDATE.zip) | 1245967 | `884ec890f238803f2657ddb994272644926bd8b223cca1330de98149f0f9bec6` |

Seven permanent release assets: complete ZIP, tested update ZIP, manifest, SHA256SUMS, TEST_REPORT, installation/upgrade instructions and TEST-EVIDENCE ZIP. The full archive has 41 files; UPDATE has 40 and excludes fresh setup. Only `site/` contents are deployed, with flat root paths and original `app/`/`assets/` organization.

[Exact-main CI](https://github.com/afterburn25/Cajun_Web/actions/runs/38357271480): 50 native and 157 HTTP/browser checks passed, including actual fresh installation, unprivileged failures, populated historical 1.0.8 update, saved workflows and authorization. Independent foundation and documentation checks passed on the same main SHA. Read [TEST_REPORT](TEST_REPORT_1.1.0.md), [feature matrix](RELEASE_1.1.0.md), [installation guidance](INSTALLATION_AND_UPDATES.md), [Devin handoff](DEVIN_HANDOFF_1.1.0.md).

**Publication verified:** independently downloaded every asset into a new directory after publication. All seven are byte-identical to the prepared release. Verified SHA256SUMS, both ZIP CRCs, exact flat members, safely extracted per-file hashes, app/installer/manifest version agreement, and tag resolution to the tested SHA. [Machine-readable verification](RELEASE_VERIFICATION_1.1.0.json). Candidate draft downloads were also checked before publication.

No live deployment or provider acceptance is claimed. PHP 8.3.6/MariaDB 10.11.19 is the tested application environment. Earlier versions/custom schemas and code-only rollback are unverified; supported update origin is the retained 1.0.8 fixture. External mail, HTTPS host configuration, Authorize.net and 20i remain untested. Full future roadmap capabilities remain explicitly tracked.

## Historical 1.0.8

Historical complete ZIP retained only as the fingerprinted isolated upgrade fixture; see [baseline inventory](BASELINE_INVENTORY.md) and [fixture provenance](../tests/fixtures/README.md). It was not relabeled as 1.1.0 and no historical tag/release was fabricated. Preserve every published version; never move tags or silently replace release assets.
