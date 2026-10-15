# CajunWebPro current status — 2026-10-15

Published version: [v1.24.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.24.0), immutable source `7190b582a02be8a1daa501db14b5899cfc1eb5d0`. Release assets were downloaded and verified. The complete 20i integration remains the active P0; unrelated feature development is paused.

Current integration checkpoint normally merges catalog `8f7028dbbc23d9926d7d0ebde8d2f6b5e6861e98` into registrar merge `c1585199`, branch `codex/twentyi-mailbox-controls`, [PR140](https://github.com/afterburn25/Cajun_Web/pull/140). This normally preserves latest main `a1e64f81`, including licensing audits and the error-response guard. Domain replacement retains its transaction and actor-bound audit rollback; 40 isolated native licensing checks and independent conflict review pass. Combined descendant acceptance is pending.

| Workstream | Implemented increment | Verified evidence and remaining checks |
|---|---|---|
| Root / PR140 | Expiring owner permissions, client mailbox creation, safe history and shared operation ledger | Runtime334c390c: 87 native checks in each of four PHP/database combinations. Current main integration needs combined acceptance. |
| Catalog / [PR141](https://github.com/afterburn25/Cajun_Web/pull/141) | Owner-reviewed provider type mappings and cost terms separate from retail plans | Source8f7028: four verified native combinations, including43 catalog cases within508 combined; exact-source browser checkpoint passes1794 HTTP/Chromium checks; full recovery/Apache still pending. |
| Domains / [PR142](https://github.com/afterburn25/Cajun_Web/pull/142) | Registered-domain inventory and explicit client assignments | Source8b4a:63 native plus64/64 reader checks per combination, all four verified. Parent2fe browser:1810 checks; mobile owner capture timing correction pending combined inspection. |
| Cloud / [PR143](https://github.com/afterburn25/Cajun_Web/pull/143) | Website creation on an explicitly linked existing cloud server | Parent4aa:33 native checks per combination and1792 browser checks. Correction ce8d4e89 passes41 native checks in each of four verified combinations; its full browser/recovery acceptance continues. |

Three agents work in isolated checkouts. Root owns integration and release coordination. Registrar and catalog are now combined with mailbox and recent main; cloud remains separate, ready for sequential integration. Review also changes mailbox freshness ordering to the original intent sequence, with four new native cases for later failed/unfinished intents carrying earlier timestamps; their execution is pending. Test counts describe their named suites and must not be added into a platform-completion percentage.

Next: finish each branch's artifact/visual checks, merge verified increments sequentially, then run combined packaged native/browser/recovery/Apache acceptance and independent review before exact-main release verification. Preserve earlier in-flight runs.

Automatic capability/catalog discovery, full hosting lifecycle and controls, domain commerce/management, VPS lifecycle, provider support/SSH and commercial automation remain unfinished. See the [full requirements and evidence](docs/TWENTYI_REQUIREMENTS_EVIDENCE.md), [parallel evidence](docs/TWENTYI_PARALLEL_WORK.md) and [current handoff](SESSION_HANDOFF.md). No new release or live deployment is claimed.

[Archived prior status](docs/archive/2026-10-15-a894de58-PROJECT_STATUS.md) retains earlier checkpoints; its “current” statements are historical.
