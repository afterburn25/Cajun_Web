# Project status — 20i P0

Updated 2026-10-15.

Codex owns the complete 20i P0 integration. Unrelated work stays preserved and paused. Published v1.23.0 remains immutable; 1.24 is withheld for the fresh-owner warning repair until corrected candidate/main and release-download gates pass. No production operation or deployment is claimed.

Cloud inventory and explicit client assignments are combined in [PR #127](https://github.com/afterburn25/Cajun_Web/pull/127), branch `codex/twentyi-cloud-inventory`. Normal merge `235f98e137e838401da26687dd750ac7315b78e0` retains #128 and has the same tree as independently reviewed `c377174cc3072c2ae2aee815c1ccbfd542b8780a`. [Native matrix](https://github.com/afterburn25/Cajun_Web/actions/runs/38703797745): 848 downloaded named executions across PHP 8.2/8.4 and MariaDB 10.11/MySQL 8.4. [Packaged checkpoint](https://github.com/afterburn25/Cajun_Web/actions/runs/38703794283): 7,585 executions in 42 reports, including 1,406 HTTP checks, no PHP diagnostics. Three archive manifests/members/hashes and six actual owner/client screenshots were inspected. [Review](https://github.com/afterburn25/Cajun_Web/pull/128#issuecomment-6187625318) is clear. Extended recovery/Apache were skipped; this is not release acceptance.

The combined implementation reuses the reader/refresh engine, explicit current observation and provider context, existing clients, owner authorization, audit and locks. Schema 5 preserves populated v1–v4 data and immutable approval events. Client cards expose assigned server specifications with original units; hosted package IDs and other clients are excluded. Reapproval/removal rejects stale forms. No assignment creates an order, establishes health or authorizes a provider write.

This continuation starts at the normal merge above. Only the cloud screenshot method changes: full-page captures start at the top, keeping real sticky navigation in its natural location instead of scrolling an oversized element under it. No UI is hidden or rewritten for evidence. Syntax/docs checks cover this commit; its new screenshots remain to be produced by the next release candidate. Prior failed fresh-installer, setup-message and missing-profile runs remain preserved.

## Next work

1. Finish stable 1.24: exact fresh-profile candidate `5fa5998d`, [PR #129](https://github.com/afterburn25/Cajun_Web/pull/129), full run [38703134813](https://github.com/afterburn25/Cajun_Web/actions/runs/38703134813). Native 544 and extracted browser 7,476 / 1,373 HTTP are verified; independent review is clear. Full recovery/Apache, normal merge, corrected exact main and downloaded draft/public assets remain required. Never publish old main `5dd2a3e1` despite its historical passing suite: the shared fresh-owner path was uncovered afterward.
2. After immutable 1.24 publication, prepare the cloud increment as 1.25 with that actual baseline. Run full exact candidate, inspect full-page desktop/mobile captures, obtain combined review, merge normally, verify exact main and downloaded release assets. Current cloud development archives retain inherited 1.24 labels and are not release files.
3. Continue the entire platform: account/catalog discovery, native controls, necessary plan/order/payment consent and provisioning, domain commerce, VPS/cloud lifecycle, support and interactive SSH. Reuse preserved #90–#92 service-plan foundations for necessary commercial dependencies; do not restart unrelated finance or webmail work.
4. The complete original API upload still needs its accessible path/link; an input request is pending. Use documented official guides and isolated fixtures for independent implementation. Existing live authorization covers only GET /package. Missing implementation is not an external approval gate.

[Prior dated evidence](docs/archive/2026-10-15-cloud-before-integration-PROJECT_STATUS.md) · [Requirements](docs/TWENTYI_REQUIREMENTS_EVIDENCE.md) · [Cloud owner guide](docs/TWENTYI_CLOUD_INVENTORY.md)
