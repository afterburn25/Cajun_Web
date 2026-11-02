# Account and Notifications: current-source integration acceptance

## Candidate and delivery state

This candidate combines the published PR #317 acceptance correction with the reviewed Account/Notifications implementation and browser setup correction. The intended isolated publication branch is `chatgpt/account-notifications-acceptance-20261101`. The existing PR #317 head remains frozen while its full acceptance runs; this candidate does not update main, publish a release or deploy the website.

The ordinary merge has these two actual published parents. The local recovery commits are recorded for traceability, but no new test requires those local-only commit objects at runtime.

| Parent | Published commit | Exact tree | Equivalent reviewed local snapshot |
| --- | --- | --- | --- |
| PR #317 correction and recovery evidence | [`84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd`](https://github.com/afterburn25/Cajun_Web/commit/84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd) | `a206e95562ab751cd9461db3a8ed32c4305ecbec` | `bfbb9794df8440b917275b0f69e559e0775497e0` |
| Account/Notifications and explicit provider preparation fixture | [`ebcbbb56336249fee2073c8f02e9d6998740b64e`](https://github.com/afterburn25/Cajun_Web/commit/ebcbbb56336249fee2073c8f02e9d6998740b64e) | `845ced19cff2e22561faa47770706cdc028d84f0` | `1bbb4041b79194085c2ff379ec993cc018e70088` |

Their common ancestor is `366d53b1ed6b367b32664f2b91ee073fc6813544`. The published commit objects were independently reconstructed from read-only GitHub Git-commit metadata and accepted locally only after their complete object bytes reproduced the exact published SHA. Their trees match the retained local snapshots. The enclosing merge identifies the final candidate.

The [parent comparison](evidence/account-notification-current-source-2026-11-01/parent-comparison.json) at preserved source checkpoint `7feb1c5f` proves that all **92** unchanged root-side paths and **107** unchanged feature-side paths retain their exact published bytes. Four existing fixture/proof files require intentional composition; all shipping and build-entry bytes are identical to the published feature parent. That checkpoint retains the root's new recovery documents, all historical failures, the feature's three legacy workflows and source builders, and the exact published account browser correction. The original account browser's **42** check-call expressions remain unchanged and in order.

The final documentation amendment prepends concise current-state entries to eight status/handoff documents. The [handoff preservation receipt](evidence/account-notification-current-source-2026-11-01/handoff-preservation.json) proves that every complete prior document remains verbatim below its new entry. No non-document source changes after the preserved checkpoint.

## Preserve both fixture proof chains

`tests/release_cases.py` now executes the feature's shared `core_admin_destinations` assertion while retaining the root correction's separate exact-route assertion and request-failure diagnostic callbacks. The shared helper covers all five root destinations: Overview, Service catalog, 20i workspace, Administrators, and Licenses & software. Its negative cases continue to reject absent or duplicated links, foreign origins, incorrect paths, extra or duplicated query selectors, and fragments.

The other composed files are `tests/owner_navigation_contract.py`, `tests/provider_browser_fixture_provenance.py`, and `tests/core_browser_fixture_contract.py`. The added [combination receipt](ACCOUNT_NOTIFICATION_FIXTURE_COMBINATION.json) binds the four current files to both published parent versions through exact, reversible byte edits. The added verifier pins each predecessor hash, verifies the current bytes and forward/inverse replay, and rejects source, receipt, path, parent or inverse drift.

The historical provider receipt, core receipt and original core verifier remain byte-identical. Source-comparison adapters reconstruct each verifier's original input at the comparison boundary; actual installed/browser drivers always execute the composed current files. The root's diagnostic helper and exact-route checks remain live. No old test implementation is executed in place of the current browser source.

## Close the focused CI admission gap

The legacy [combined workflow](../.github/workflows/account-notifications-combined.yml) runs only on its original isolated source-building branch. Reusing that job on an integrated head would invoke builders pinned to historical files and could reject or reconstruct the new fixture composition. It remains entirely unchanged, along with the standalone Account/Security and notification source-building workflows.

The new [current-source workflow](../.github/workflows/account-notifications-acceptance.yml) provides an independent read-only admission path:

- Pushes on the new acceptance branch and main, pull requests targeting main, and explicit manual invocation are admitted. Automatic triggers use bounded paths for shipping code, foundation code, tests, tools, receipt JSON, version/install instructions, the two focused workflow files and the protected application workflow pinned by the proof. A normal initial push to the separate candidate branch starts this lane once; no manual duplicate dispatch is needed.
- The checkout uses the immutable event head: the PR head SHA for a pull request, otherwise the push/manual SHA. It fetches complete history without retaining credentials and verifies both published merge parents are ancestors.
- All prior source policies and proof commands remain present. The current-source lane additionally verifies the composed fixtures, workflow contract, notification/ticket layers, canonical build and an entirely clean tracked/untracked source state before emitting its actual checked-out SHA.
- Repository permission is `contents: read`. The admission job has no GitHub write token, source-building command, commit, push, or ref mutation. It preserves source archives, commit/tree identity, dirty-state reports and failure artifacts with unconditional upload.
- The complete existing acceptance and checkpoint job bodies are byte-identical. The four native combinations remain PHP **8.2/8.4** with MySQL **8.4** and MariaDB **10.11**. Both PHP 8.4 database combinations retain the actual installed account/notification browser journey. Native report minimums, every original SQL/policy/browser command, strict stderr checks, source-SHA binding, artifact names/paths and 14-day retention remain intact.
- Development concurrency is scoped to the pull request or branch. A newer change supersedes obsolete work for that ref; main pushes keep every run. The protected full `application-tests.yml` workflow is byte-identical and remains a separate acceptance gate.

The [workflow regression proof](evidence/account-notification-current-source-2026-11-01/account_notification_workflow_contract.json) compares those complete retained job bytes against the pinned historical workflow and checks the admission graph and immutable source binding. Twenty deliberately altered workflows are refused, including removed SQL/browser coverage, reduced matrices, weakened report gates, moving checkout refs, write permissions, historical reconstruction, omitted parent/policy/dependency checks, per-commit concurrency, cancelled main evidence, dirty admission and lost failure artifacts.

## Verification and remaining gates

These results were produced from the composed local working tree before the enclosing merge was committed. The reports identify source proof and strict locator-double checks separately from installed acceptance; overlapping checks are not added together.

| Verification | Result | Evidence |
| --- | --- | --- |
| Maintained acceptance-helper suite | 575 passed | [Report](evidence/account-notification-current-source-2026-11-01/acceptance-helpers.json) |
| Exact fixture composition and negative cases | 36 passed | [Report](evidence/account-notification-current-source-2026-11-01/account_notification_fixture_provenance.json) |
| Provider provenance including composition checks | 241 passed | [Report](evidence/account-notification-current-source-2026-11-01/provider_browser_fixture_provenance.json) |
| Original core fixture contract | 50 passed | [Report](evidence/account-notification-current-source-2026-11-01/core_browser_fixture_contract.json) |
| Shared navigation and strict negative locator cases | 54 passed | [Report](evidence/account-notification-current-source-2026-11-01/owner_navigation_contract_test.json) |
| Workflow equivalence/admission regression | 56 passed | [Report](evidence/account-notification-current-source-2026-11-01/account_notification_workflow_contract.json) |

All six commands exited successfully with empty stderr. The canonical rebuild, documentation validation, source identity manifest and independent team review are recorded in the final review checkpoint below.

The feature parent's [real local account browser](ACCOUNT_NOTIFICATION_SETUP_FIXTURE_20261101.md) passed **54/54** on its documented PHP 8.3.6/MariaDB/Chromium runtime, with original and intermediate failures retained. That evidence is carried unchanged; it does not certify this combined candidate, the hosted matrix, or the complete installed application journey. The primary PR #317 run is also a different source. Current-source hosted focused acceptance, the complete combined application/update acceptance, required review, main integration, exact-main verification and release publication remain separate gates.

Do not move PR #317 merely to start this candidate's focused run. If the separate candidate branch is absent, publish the reviewed source and use its ordinary push event; otherwise verify that branch's tree and continue its existing acceptance run. Preserve the current PR #317 evidence, then reconcile its eventual acceptance result and the combined candidate before advancing the integration lane. Do not trigger live email, provider operations, payments or deployment during these isolated checks.

## Final source review checkpoint

The [source/review manifest](evidence/account-notification-current-source-2026-11-01/source-review.json) binds all eight integration files, the retained workflows/builders/receipts, the actual account browser and every reported proof to SHA-256 identities. The [canonical rebuild](evidence/account-notification-current-source-2026-11-01/canonical-build.log) passed with empty stderr and no tracked source changes. It refreshed and checked the canonical installer; it explicitly did not build a release from uncommitted files.

Root review checked the actual historical projection callers and the new immutable source admission. A separate review agent independently reconstructed all six available fixture-parent projections and compared them with actual published Git blobs; it also verified every retained matrix/checkpoint byte, the original builders, the 41 root recovery document/evidence files and 27 feature fixture/workflow/document/evidence files in its focused carryover sample.

That reviewer identified one real dependency gap: the guard pins the protected application workflow, but its first draft omitted that path from automatic triggers. The corrected push and PR filters include it, and a dedicated negative mutation proves the omission is rejected. The reviewer independently reproduced the final **56** workflow checks, including **20** negative cases, and confirmed that the retained report is identical. No blocking issue remains within this source/workflow review scope. This is automated team review, not external approval or a hosted result.

Both actual admission shell blocks from the new workflow were then executed verbatim against the clean ordinary merge `7feb1c5f25f44dff9ddd91b7db07a85f96a12883`, tree `e275126f3907f620ae45d6cf1b0c80df68b2240c`. They passed with empty stderr, including both published ancestry checks, all original/current source proofs, account/notification/ticket/runtime shipping contracts and canonical refresh. The record step emitted that exact checked-out SHA and verified a clean source tree. The [execution record](evidence/account-notification-current-source-2026-11-01/admission-execution.json) and [actual output](evidence/account-notification-current-source-2026-11-01/admitted-source.txt) preserve the observed result. These local admission commands do not execute the separate hosted SQL/browser matrix.

That checkpoint is retained locally as `review/account-notifications-source-admission-20261101`. The enclosing final merge amends only documentation/evidence and retains exact parents `84e1f4c6` and `ebcbbb56`; the integration-file hashes and every non-document byte remain unchanged. Final documentation/link validation and source binding follow that amendment. Hosted focused/full combined acceptance, main verification and release delivery remain pending.
