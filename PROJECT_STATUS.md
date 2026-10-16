# CajunWebPro current implementation status

Updated 2026-10-16. Published [1.24.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.24.0) remains unchanged. Full 20i, combined acceptance and release 1.25.0 remain unfinished.

## Current StackCP performance candidate

Branch `codex/twentyi-stackcp-read-snapshot` continues exact parent [5bfd9c69](https://github.com/afterburn25/Cajun_Web/commit/5bfd9c695d35103c3bf27e8eaf66b054c59741cf). Enclosing commit description: **Validate StackCP histories once per protected owner view**. Git history identifies this commit; it is not a release.

The owner StackCP view now computes one saved-data snapshot under the existing staff mutex and its own transaction. Complete authentication/assignment history checks run once within that call; each selected package still checks current account, owner, client, credentials and link authority. Existing mutation APIs, review hashes and rendered forms remain. Every later call validates again. No provider request, setup repair or cross-request authorization cache is added. [Implementation and evidence](docs/TWENTYI_STACKCP_READ_SNAPSHOT.md).

Local integrated evidence passes 68 extracted actual-code comparisons/negative cases, 1,004 handoff/prerequisite/view executions across normal and minimal PHP, all 173 acceptance-helper checks and syntax for the six changed/new PHP files. Canonical installer refresh, all shipping PHP/JavaScript syntax and documentation validation pass. The modeled 20-package comparison reduces calls from 1,629 to 236; this is not measured database or whole-page latency. Source review and the strengthened paired-loss fixture are preserved (review `3c599a33`, runtime `069bf0ab`, fixture `b6001dbf`, root application `4e52ac41`). Actual native SQL/HTML comparison, contention and recovery remain pending.

The new native fixture is included in the focused PHP 8.2/8.4 × MySQL 8.4/MariaDB 10.11 matrix and full acceptance. The full workflow now requires 148 native commands, 158 browser reports and 197 final reports. All 147 predecessor commands retain their exact order/arguments; the explicit PDO correction and separate distro job are unchanged. One new module is added to protected-path coverage. Browser assertions and screenshots remain unchanged.

## Verified predecessor and retained failures

Parent 5bfd original focused [38931463106](https://github.com/afterburn25/Cajun_Web/actions/runs/38931463106), attempt 1, passed all five jobs. Each native environment has 12 reports and 755 passing named executions (3,020 total); Ubuntu PHP 8.3 has six reports and 260 executions, with actual no-INI PDO/tokenizer and no-driver evidence. All five exact-source archives were verified (proof `ab93db13`). This establishes focused parent acceptance, not this descendant's full application acceptance.

Original cccf full [38927120383](https://github.com/afterburn25/Cajun_Web/actions/runs/38927120383) failed at the missing minimal-PHP PDO exception class. Its 48/157 checkpoint and 87/196 final reports leave 109 required reports missing; HTTP was skipped. Linux/Windows packages, 289 recovery checks and 38 Apache reports/2,707 primary executions passed their own partitions. Original 2a full [38923745296](https://github.com/afterburn25/Cajun_Web/actions/runs/38923745296) failed at missing tokenizer; original 9779 full [38909577278](https://github.com/afterburn25/Cajun_Web/actions/runs/38909577278) exceeded the old 105-minute native/browser limit. Preserve these original runs and all artifacts; no passing partition clears their failures.

## Ownership and next action

Codex and its three agents own complete 20i, provider support/SSH, integration and releases. Devin owns remaining non-20i implementation under [assignment 157](https://github.com/afterburn25/Cajun_Web/issues/157). Actionable reviews remain on licensing [159](https://github.com/afterburn25/Cajun_Web/pull/159#issuecomment-6218434878), mail [161](https://github.com/afterburn25/Cajun_Web/pull/161#issuecomment-6219105635), and ticket layouts [158](https://github.com/afterburn25/Cajun_Web/pull/158#issuecomment-6219612932). Proposed or foundation-only PRs are not accepted application features.

Next: publish this candidate, run its first focused native matrix, inspect exact reports/query profiles, then proceed with remaining provider-settings performance work and combined browser/package acceptance. Main integration, exact-main acceptance and retained incremental release delivery remain required. No live deployment, provider mutation, charge, customer mail or SSH is performed here.

Previous immutable status: https://github.com/afterburn25/Cajun_Web/blob/5bfd9c695d35103c3bf27e8eaf66b054c59741cf/PROJECT_STATUS.md
