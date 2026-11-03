## 2026-10-18 — nameserver additive acceptance

The nameserver branch preserves all152 prior native commands and appends five reader/view/native commands at the domain-contract boundary. The Apache partition preserves its four commands and appends portable guards plus the actual published1.25 nameserver update/recovery producer. Required report sets are169 browser/checkpoint and215 final. Eight new scoped PNG names are fixed in `tools/acceptance-contract.json`; they still require actual capture review.

The shared input bundle now contains independently verified public1.24 and1.25 archives/member trees. Existing1.24 validators and producers remain. `tests/acceptance_artifacts_test.py` checks both baseline guards and finite inverses to the preceding source, including the last browser callback and synthetic GET addition. Local271 helper outcomes pass. No new required runtime report has yet been accepted for this source; source preparation does not satisfy partition or final gates. [Feature details](TWENTYI_REGISTRAR_NAMESERVERS.md#packaged-acceptance-producers--2026-10-18).

# Parallel application acceptance

## Current renewal integration — 2026-10-16

The normal556+b221 union preserves the complete105-command predecessor sequence and adds six renewal invocations immediately after renewal-agreement checks. Reviewed browser/Apache driver hashes are updated; recovery/preparation drivers and transfer helper remain exact556. Reports now total121 browser/160 final. Root external execution passes111 offline helper cases, including predecessor-order and exact-addition guards. New combined full acceptance remains pending. Original556 run38889625340 continues separately with115/154 expectations; build/recovery/Apache jobs have succeeded, native/browser and final evidence remain pending. Historical implementation details below describe the original split and preserved first failure.

This isolated workflow change starts at `e1cd8e44e4b8f748140618cf152521bb4215bc27`, tree `c67b71baf3ed159cc725576f7e1991f99da86f9a`, on `codex/parallel-application-acceptance`. That base preserves the accepted Force SSL recovery and verified package-builder changes. Application behavior, shipping files, installer inventory and every existing test assertion remain unchanged. The original faef run and its external evidence helpers remain frozen.

The new dependency order is one build and pristine fixture preparation, followed by three independent jobs: native then browser, filesystem recovery, and Apache. Final aggregation waits for all required jobs. There is no measured speedup claim; runner availability, environment setup and artifact transfer affect the result.

## One build, separate test state

The build job runs the original exact-commit builder and build guards once. It runs the unchanged historical preparation before any application tests, producing 27 public fixture trees and two public schema/shim documents. The input helper transfers only those explicit trees, the exact distribution files, and four build evidence files. Hidden scaffolds such as `.htaccess` remain ordinary checked members.

The manifest binds repository, run ID, attempt, exact CI commit, source-head identifier, Git tree, workflow/helper/expectation hashes, file sizes and SHA256 values. Every child downloads the explicit artifact ID supplied by that build job, verifies the expected manifest digest and all ZIP members, then materializes only safe regular paths without overwriting existing files. Checksums and the three archive manifests bind the distribution to the exact CI commit. The source-head identifier is recorded separately from the tested CI merge commit; the later independent package review still decides equivalent-tree versus exact-main acceptance.

No database dump, application configuration, private vault, session, credential, browser profile or post-test runtime tree crosses into another job. The helper rejects private filenames and unapproved paths. Arbitrary `test-runtime/`, including the builder's temporary negative fixtures, is excluded. Only native/browser receives the build QA files; recovery and Apache create their own evidence directory before any diagnostic output or Apache startup.

Native and browser stay together. All 105 existing native commands retain their order and arguments; the only moved command is public legacy preparation, now in the build job. Native's `ci_` data/configuration is consumed by its following browser process in that same runner. Apache has its own fresh MariaDB service, both rewrite modes and every retained baseline. Each database uses its assigned host port. Filesystem recovery retains its guard environment and existing unprivileged PHP worker; it does not need a database service. All existing drivers, assertions, cooldowns, fixtures and screenshot calls are unchanged.

## Evidence and failure handling

Each job seals its QA evidence into a separately owned partition, with exact run/source/attempt identity, input receipt, file digests and finite step outcomes. Build evidence also contains the one original distribution. `git diff` plus the untracked shipping-file check runs after each test job even on failure; this is an explicit strengthening over the old success-only check. A successful upload does not establish successful testing.

Native/browser seals an immutable QA snapshot before uploading its browser checkpoint. Final assembly uses that same sealed content, so subsequent writable logs cannot change the accepted snapshot. If sealing fails, the checkpoint may still preserve raw partial QA, but that job cannot satisfy complete acceptance. The final manifest records the checkpoint artifact ID and transport digest for independent verification.

The aggregate validates immutable artifact IDs and manifest digests from this workflow's job outputs, checks source/run/attempt correspondence again, and verifies file ownership before assembly. Only byte-identical build evidence may appear in both build and native/browser partitions. Other duplicate or case-colliding paths fail. It preserves each trustworthy partial partition and records failed, cancelled, skipped, missing or corrupt inputs explicitly. All required named reports and every available named outcome must pass; missing or unexpected reports fail the aggregate.

The base has 114 browser and 153 final named reports after the two recovery portable reports. The new offline transfer regression report makes **115 browser / 154 final reports**. Original reports and screenshot identities are retained. `qa/parallel-acceptance.json` adds provenance and job outcomes without presenting itself as another named test report. Final artifacts retain the existing `qa/` and `dist/` layout. Browser checkpoints remain separate from recovery/Apache outputs. Independent visual, diagnostic and same-source Windows/package comparison remain required; the aggregate's `full_partition_checks_passed` is not release clearance.

Intermediate artifact names include run ID, attempt and CI commit. The first attempt retains the original browser/final artifact names. Later attempts add an explicit attempt suffix instead of overwriting earlier evidence. Re-running only failed jobs while reusing an earlier attempt's build or successful partitions fails identity validation. A complete new attempt requires all jobs from that attempt, or a separately reviewed future reuse policy; there is no implicit cross-attempt fallback. This implementation does not rerun any existing workflow.

`checkpoint_only` intentionally skips recovery/Apache and produces a limited checkpoint with no full-acceptance claim. A cancellation is not an intentional skip. An always-conditioned aggregate cannot guarantee execution after an entire workflow is cancelled or timed out; a missing aggregate remains incomplete and existing partial artifacts remain evidence. No verifier may infer success from absence of a failure file.

## Review and validation

The external dependency review verified the original faef sources and evidence helpers without altering them. It required explicit `qa/` creation before Apache ErrorLog startup, strict same-attempt partitions and truthful missing/cancellation handling; those requirements are represented here.

`tests/acceptance_artifacts_test.py` executes the actual helper against synthetic temporary files. Its original100 named checks cover a complete four-partition assembly, limited checkpoints, failures and missing reports/partitions, corruption, duplicate JSON/member/path identities, cross-attempt/source mismatch, private input refusal, exact screenshot/package retention, and unchanged native command order. This is local offline evidence only. Those checks and source review preceded first run38887580893. The actual run exposed the output bug below; corrected transfer and application acceptance remain pending.

The artifact-ID download interface is documented by the official [download-artifact action](https://github.com/actions/download-artifact/tree/v4); dependency, condition and output behavior follows [GitHub workflow syntax](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax). The reviewed action documentation is retained locally with its digest. No application/provider request is made by the transfer helper.

## Bounded review corrections

Independent review found that GitHub does not expose job.services inside a job-level env block. Both database jobs now read the assigned port in their setup step and export CWP_TEST_PORT through GITHUB_ENV. The offline regression accepts that supported placement and rejects the original placement for each job.

Review also found that per-archive case checks did not prevent two different partitions from supplying case aliases. The helper now rejects global casefold collisions before extraction. Its negative regression requires the exact collision reason, so an incidental Windows overwrite refusal cannot falsely pass it. The original source-supported Linux-path projection was a bounded synthetic investigation, not Linux execution. Initial source/proof and the independent reproduction are preserved; actual corrected GitHub execution remains pending.

## First actual execution and output correction

Original5a402a35 run38887580893 passed helper100, exact build, public fixtures and shipping guard, then failed because the output-name pattern excluded digits in its own sha256 label. Both pack-inputs and seal refused before writing GitHub outputs; dependent jobs skipped. The downloaded final aggregate truthfully lists154 missing reports. Preserve that original attempt.

The bounded correction accepts a lowercase/underscore-leading name with numeric suffixes and rejects CR/LF value injection. Nine additional temporary-file checks call the actual pack-inputs and seal entry points, retain their real artifact functions, verify exact appended manifest digests, and reject malformed names/values before writing. All109 local cases pass; independent review reproduces the old failure and passes10 focused output boundaries. Application/workflow/driver/native-command bytes remain unchanged. The corrected source requires its own first full run; there is no measured speedup or application acceptance claim.
