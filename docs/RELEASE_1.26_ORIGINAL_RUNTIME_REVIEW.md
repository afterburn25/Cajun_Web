# Original 1.26 runtime evidence review

Recorded 2026-10-18. This is evidence for catalog candidate `f67b1631683b29096dd8ec1daee405e104dc0b83`, tree `a24876428ada7d4f7c764d28650d208b8eddf8f2`, in [PR216](https://github.com/afterburn25/Cajun_Web/pull/216). It is not publication, deployment or acceptance of a later source. Published [1.25.0](RELEASE_1.25.0_PUBLICATION.md) remains immutable.

## What has finished

The four original supported-runtime runs have successful build, native/database/browser, Apache and recovery jobs. Their final hosted acceptance jobs remain queued at the latest observation. Each remains on attempt 1; none was retried, cancelled or replaced.

| Original run | Selected environment | Producer jobs | Hosted final acceptance |
| --- | --- | --- | --- |
| [39098578401](https://github.com/afterburn25/Cajun_Web/actions/runs/39098578401) | PHP 8.2 / MariaDB 10.11 | All four successful | Queued |
| [39098583508](https://github.com/afterburn25/Cajun_Web/actions/runs/39098583508) | PHP 8.2 / MySQL 8.4 | All four successful | Queued |
| [39098588864](https://github.com/afterburn25/Cajun_Web/actions/runs/39098588864) | PHP 8.4 / MariaDB 10.11 | All four successful | Queued |
| [39098593805](https://github.com/afterburn25/Cajun_Web/actions/runs/39098593805) | PHP 8.4 / MySQL 8.4 | All four successful | Queued |

The separate default PHP 8.3/MariaDB run [39098482550](https://github.com/afterburn25/Cajun_Web/actions/runs/39098482550) uses CI merge source `ee131c1f65ecf2fe3ac8faaf55d04485d8edec71` and the same f67 application source. Its previously reviewed evidence is not reused as another runtime's result. Its final hosted job is also still queued.

## Retained evidence and method

All four original partition envelopes and the build-input envelope for each runtime are freshly downloaded. API artifact identities, sizes and SHA256 digests bind the outer ZIPs. Inner manifests bind the source/tree, run/attempt, runtime, exact acceptance code and every report/package member. The original f67 aggregation function is executed locally against each isolated set, with job bindings reconstructed from authenticated artifacts and actual job results. This local reconciliation does not run or replace the queued hosted job; its `needs.*` output values were not directly fetched.

The [source-bound receipt](evidence/catalog126-original-runtime-review.json), SHA256 `bb81afeb131100c2e8a24dd7f779f427fd4def57c43436f3eeea236f69e36336`, records local reconciliation outcomes, all 216 report names and hashes for each run, package comparisons, review receipts and limitations. Root independently compared every assembled report with its recorded hash, checked the exact Git helper/workflow sources and verified the frozen reviews and all 20 downloaded envelope identities against retained API metadata.

All four unchanged local aggregations pass: each has 216 required reports, 28,873 named executions, no failed named outcomes and zero PHP diagnostics. The 28,287 value is the sum of unique names within each report, not a globally unique test count. Each runtime's 324 browser and 16 Apache image files are authenticated inventory; this does not imply every runtime's images were visually reviewed.

Native checkpoint upload identity/digest metadata is retained separately; its outer checkpoint archive is not downloaded or compared. The exact f67 hosted workflow itself downloads only the four partition artifacts and passes the checkpoint ID/digest through job outputs. Its helper checks that metadata prerequisite, not checkpoint bytes. The local reconstruction preserves that requirement but does not claim access to actual hosted `needs.*` outputs.

## Packages and populated upgrades

The FULL-PROJECT, COMPLETE-UPLOAD and UPDATE archives are byte-identical across all four original runtimes. Independent review compares every FULL-PROJECT member to exact f67 Git source, COMPLETE to the deployment slice, and UPDATE to its allowed payload/support files and manifests. Their hashes and member counts are recorded in the receipt.

Each runtime passes 2,827 Apache checks, 998 latest 1.25-to-1.26 upgrade checks, 666 retained earlier-upgrade checks and 303 structure/recovery checks. The 16 populated upgrade scopes preserve each scope's original 88 tables, 272 rows and 13 private files, allowing only the documented catalog import counter change from string `1` to `2`; the snapshot contract excludes login bookkeeping and permits additive data. These are reviewed original outcomes, not fresh application executions.

Retained local analysis failures are also identified in the receipt: the reviewers corrected assumptions about intermediate Apache progress, random per-run IDs, expected PHP version messages and a Python module-name collision. No original application result was edited or rerun to address those analysis-tool errors.

## Catalog and visual findings

The PHP 8.2/MariaDB reports include passing direct-catalog reads/imports, retained inventory imports, real multipart image replacement and 3,149 installed HTTP checks. The catalog flow imports types absent from installed inventory while preserving existing local products, prices, artwork and original import identity. It does not add automatic provider prices or images, cloud catalog import, hosting publication or ordering at this source.

Catalog reviewed ten original images from that runtime. The two direct-catalog cards are readable and show refresh/import actions, blank owner-password fields and the stated feature limits. Root additionally inspected the complete desktop direct-catalog card and the mobile imported-image card. The latter repeats the already known capture limitation: its bottom 47 pixels obscure part of Save image. The successful multipart workflow and later positioning correction do not grant visibility to those obscured pixels. The narrow catalog table's right columns are outside the captured scroll position; scrolling interaction is not established by that image.

The capture-only successor `b7c97953f323004e1fdd527bdef0b2da63f01c71` in [PR220](https://github.com/afterburn25/Cajun_Web/pull/220) addresses this and other previously documented capture gaps. Its [original full run39137551561](https://github.com/afterburn25/Cajun_Web/actions/runs/39137551561) remains queued. No result from f67 certifies those changed capture fixtures.

## Remaining release work

Inspect the original hosted final jobs when they finish. Complete the corrected capture candidate's required runtime acceptance, inspect every changed actual image, and reuse earlier visual review only where exact image/region identity establishes it. Then perform normal candidate integration, exact-main acceptance, package comparison and fresh draft/public release-download verification. This review changes documentation only and performs no provider request, payment, customer mail, SSH or live deployment.

The C: drive's free-space preflight stopped additional large downloads at about 2.5 GiB free. New artifacts and reconstructed assemblies are stored under `D:/CodexArtifacts/Cajun_Web/catalog126-2026-10-18`; the original first-run envelopes and compact receipts remain in the workspace. No original evidence was deleted or moved. The receipt records the split locations so subsequent agents can find the verified files without downloading them again.
