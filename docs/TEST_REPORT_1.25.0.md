# CajunWebPro 1.25.0 release acceptance

Status: release consolidation in progress. No 1.25.0 publication or production deployment is claimed. [Scope](RELEASE_SCOPE_1.25.0.md), [integration map](RELEASE_1.25_INTEGRATION_MAP.md) and [owner upgrade instructions](MIGRATION-INSTRUCTIONS-1.25.0.md) define this increment.

## Recorded source

The canonical branch is `codex/release-1.25.0`. It started from main `35d8fb7efc94f6f851399d0755fa93097f1fce66`, normally merged mature 20i source `3fa72d7b3b447fa3d281cc981ed861c508f850f2` in `b98b96eb2a33e4bdeb72e15217213431885aad67`, and normally merged the main documentation checkpoint `d7153b076e91b524ff2f99a9a7aa5c910551fc5e` in `85f0b489b30d4e9e6db839e2cf7da02f512176c5`.

The following acceptance correction commit has parent `85f0b489`. Its enclosing Git history identifies the exact source; it is not accepted merely because this report is present. New 20i capabilities and nonessential refactors remain frozen.

## Completed bounded checks

| Evidence | Actual scope and result |
|---|---|
| Integration source union | 2,886 shipping files checked against the staged Git union; both parents retained, six deliberate shared-file resolutions matched reviewed bytes, generated installer inventory current at the integration checkpoint. This is source evidence, not application acceptance. |
| Matrix helper regressions | 208 passing local temporary-file assertions after applying explicit PHP/database environment identity and runtime selection. Existing transfer and provenance checks retained. |
| Build guards | 69 passing local assertions, including root VERSION/runtime/installer agreement. |
| Licensing correction review | Independent source review of 16 exact files. Master keys excluded from client delivery; recognized populated historical schema upgraded additively; invoice cutover and explicit owner review preserve older licensing history. Native and HTTP execution remain separate gates. |
| Licensing portable probes | 55 corrected-control cases and 33 migration cases each executed in default and minimal PHP modes. These modeled checks do not establish native database behavior. |
| Missing-history recovery correction | Independent source review of the two-file audit-witness correction; both paired table/marker-loss cases refuse replacement, while exact interrupted empty first setup can resume. Native database regressions are included and await execution. |
| Actual-upgrade fixture guards | 28 local retention/form checks and 19 exact published-ZIP checks pass. Full real-HTTP upgrade execution is pending. |
| UI coverage audit | 20 meaningful flow groups mapped to actual fixture paths; added desktop/mobile admin-overview and client-project-list captures. Current layout retained. No visual acceptance is inferred from capture recipes. |

The final evidence archive must contain the retained reports and source/run bindings, not just these counts. Later fixture checks and actual CI results must be appended with their exact source and scope.

## Required candidate and exact-main gates

| Gate | Current state |
|---|---|
| Complete PHP 8.2 + MariaDB 10.11 | Pending exact candidate execution and independent evidence inspection |
| Complete PHP 8.2 + MySQL 8.4 | Pending exact candidate execution and independent evidence inspection |
| Complete PHP 8.4 + MariaDB 10.11 | Pending exact candidate execution and independent evidence inspection |
| Complete PHP 8.4 + MySQL 8.4 | Pending exact candidate execution and independent evidence inspection |
| Maintained distribution/minimal-PHP lane | Preserved; exact candidate execution pending |
| Actual published 1.24 COMPLETE → UPDATE | Additive populated upgrade fixtures prepared; root/subfolder, rewrite on/off, interruption, repeat, recovery and reapplication must execute |
| Extracted HTTP/Chromium and visual inspection | Pending exact candidate captures, named results and actual desktop/mobile inspection |
| Independent exact candidate review | Pending frozen final source; all P1/P2 blockers must be resolved |
| Windows/Linux package agreement | Pending clean candidate build and comparison to its CI packages |
| Normal merge and complete exact-main rerun | Pending accepted candidate |
| Immutable tag, release assets and fresh downloaded-byte verification | Pending exact-main acceptance |

Each complete environment uses its own same-attempt build, input receipt, native/browser, recovery, Apache and final aggregation. Actual CLI, database and Apache runtime versions are checked. A focused suite, green parent, successful partial job or browser-only checkpoint cannot substitute for the complete run. Preserve all existing tests and required reports.

## Data and historical evidence

The published 1.24 baseline is immutable source `7190b582a02be8a1daa501db14b5899cfc1eb5d0`. Its COMPLETE archive SHA-256 is `aa7a84b4c0d1201a4eddf2fe68340b90d4860117ceff4d959d165e6feafa731e` and contains 2,591 members. The new transfer allowance admits only this exact public archive and checks its extracted member bytes; it is not a general ZIP-input allowance.

Upgrade acceptance installs this archive and populates its original client, staff, project, message, upload, ZIP-delivery, invoice, receipt, mail, support and provider workflows. It retains original encryption keys and uncertain operation history. Recurring agreements and licensing did not exist in stock 1.24; tests create those only after upgrading and then verify recovery/reapplication preserves them. A separate populated historical licensing fixture covers older accepted development adoption.

Historical original full runs remain recorded: `38909577278` timed out; `38923745296` failed because its minimal runtime lacked tokenizer; `38927120383` failed because minimal PDOException support was missing. Their partial reports remain partial. Later focused compatibility and StackCP results establish only their named source/scope and do not certify this release union.

## Visual and external limits

The existing public and portal layout is preserved. The approved portal concept remains a design record; its exact reference PNG was unavailable locally during this consolidation, and the dedicated sidebar/dashboard concept is not implemented in the current runtime. This release does not claim implementation of, or visual equivalence to, that concept. Actual existing owner/client pages still require inspection for clipping, placeholder copy, errors and unauthorized information.

All automated fixtures use isolated credentials, clients, database and provider/mail/payment substitutes. They do not establish live 20i entitlement, real merchant acceptance, customer message delivery, target-host installation, remote StackCP login or SSH. Published 1.24 assets must remain unchanged. Publication is complete only after every 1.25 asset is downloaded from GitHub and checked against the prepared bytes, tag, manifests, CRC, paths, directories, versions and expected members.
