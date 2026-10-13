# Release register

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
