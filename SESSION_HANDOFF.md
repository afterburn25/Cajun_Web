## Catalog withdrawal recovery correction — 2026-10-15

Starting41123e9b5b95f8bbe4244ee5db64a15eb3a85a58, PR141: source review found that losing a latest withdrawal record together with its matching audit could make an older enabled mapping appear current. Added an independently retained per-plan latest ID/digest pointer in settings, saved atomically with every review. Exact pointer/history checks and plan-family counts refuse missing/older review histories, including loss of both withdrawal and audit. Native fixtures cover paired loss, refusal of new approval, missing pointer and exact restoration. This strengthens recovery; it does not add provider execution. Local syntax and51 pure checks pass; current native/full acceptance remains pending. Preserve parent41123 full38819193594 and native38819193595; do not replace or cancel those runs.

# Catalog native evidence and review follow-up — 2026-10-15

PR141 parent24e75ca6 is verified at36catalog checks within501combined native checks in all4combinations; actual artifact hashes/CRCs/reports retained. Descendant repairs independent redisplay/recovered-format findings and adds actual browser coverage, pending current acceptance. [Exact scope and artifact evidence](docs/TWENTYI_PLAN_CATALOG.md). Root retains integration and release ownership.

# Catalog review correction — 2026-10-15

Starting24e75ca646f13852b54ea148966048ebdec66bec, PR141. Root independently found that rejected oversized/array catalog fields could throw again during redisplay, and malformed recovered association JSON could access format before validation. Corrected both: rendering uses a bounded nonthrowing UTF-8 projection, while association/catalog document validation normalizes malformed history. Added four native malformed-render cases and actual HTTP/Chromium setup, review, rejection, replay and catalog-backed existing fulfillment coverage. Parent native38818360818 has successful PHP8.2/MariaDB and PHP8.2/MySQL jobs; remaining jobs/artifact verification were pending when this entry was written. Local51 pure checks per PHP8.4 mode and changed PHP syntax pass; exact descendant native/browser verification remains required. All runs and previous releases are preserved.

# Parallel catalog increment — 2026-10-15

The owner requested parallel 20i agents. The catalog agent owns `codex/twentyi-catalog-controls`, parent `0ebc46237fce565005b581fe5a96a2691b9840b5`; root retains mailbox implementation and integration/releases. Main `b66062e4` was inspected and not merged into this isolated branch. [Catalog scope and continuation](docs/TWENTYI_PLAN_CATALOG.md).

Adds immutable owner-reviewed provider mappings on the existing retail plan screen, private exact decimal cost/currency/period evidence, current account/type/limits/defaults review, expiration/withdrawal, and catalog-backed existing hosting preparation. Original retail consent, commercial eligibility, fulfillment engine and histories are preserved. No automatic discovery or provider entitlement is claimed. Additive native storage, route/forms, historical association provenance and focused tests are included; installer inventory is regenerated.

Local PHP 8.4 default/no-INI each pass 51 named pure document checks; changed/new PHP syntax passes. Native catalog/order fixtures are written but await Linux CI; Windows vault protection was not relaxed. Current browser/package/independent review gates remain pending. No live provider operation, charge, customer mail, SSH, deployment or release occurred. Next: inspect exact branch native matrix and fix any failures, then root integrates with other parallel branches and performs combined acceptance/review. Published 1.24.0 remains unchanged.

# Current development — native mailbox creation boundary

Codex owns `codex/twentyi-mailbox-controls`, starting `c00874b1` above client-search PR139. Added exact documented mailbox creation request/response validation inside the existing provider adapter/gateway/ledger and fixed transport. Generic grants/default adapter cannot execute it. Raw mailbox passwords are resolved transiently and echoed response passwords are discarded; replay and unknown outcomes cannot resubmit creation. [Implementation, tests and required native continuation](docs/TWENTYI_MAILBOX_CONTROLS.md).

Local default/no-INI PHP8.4 passes95 mailbox cases and230 existing operation cases in each mode, plus fixed-transport regressions and changed syntax. Installer refresh49660 completed successfully and is closed. Native storage/authority/forms and browser/live acceptance remain unfinished. No live provider operation, customer mail, deployment or release occurred.

Parent b09b combined38812924437/job116690370817 is in browser; f86 full38811407918/job116685268807 passed recovery and is in Apache. Both are confirmed live and must be preserved. Reviews138/139 remain empty. Freshly fetched main is now `b66062e4cc3e2e670ba1c37d4ae902de2b630c30` (licensing audit tests), after merged16f07e49; preserve and inspect this later normal merge without interrupting current acceptance. Published1.24 remains unchanged.

# Current checkpoint — verified client-search browser and merged Windows packages

Starting source `b09b79278d1a459e4235f9a9431031f53abf6334`, draft PR139. Parent f86 actual browser artifact12009795846 verifies1,774 named checks, empty unexpected diagnostics and inspected desktop/mobile client screens. Current b09b four native artifacts verify106 combined checks including50 client cases each. Exact b09b Windows build86750 is complete and independently verified:11 checksums, FULL5417/COMPLETE2659/UPDATE2646 exact source/member bytes. [Detailed evidence](docs/TWENTYI_CLIENT_DOMAIN_SEARCH.md).

Current combined application38812924437/job116690370817 remains live in native integration; parent f86 application38811407918/job116685268807 remains live in recovery. Preserve both. Independent review remains required and earlier write rejections must not be bypassed. Published1.24 is unchanged. Continue native hosting controls using the existing provider gateway/ledger and documented mailbox creation contract; no live provider operation is authorized by this checkpoint.

# Current checkpoint: normal main merge and replacement rollback repair

Enclosing merge commit: **Merge current main and preserve domain replacement on failure**. Parents: client-searchf86c745c982ae1daedc1a7210bcf91052e4f9c49 and main16f07e493bd21b5b0570880cfa4faa5782db4c5e. Same branch and draftPR139 abovePR138; no force-push or published artifact change. Full20i goal remains active.

Incoming licensing work is preserved. A source-inspection finding was reproduced on actual local MariaDB InnoDB temporary tables: replacing with invalid input or a database-rejected new domain left the old domain inactive. Fix is a bounded transaction around row-lock/current-old-domain check, deactivate and register; failures roll back and an existing caller transaction is refused untouched. Local probe passes4 corrected cases. Actual merged `tests/license_native.php` passes35 named cases with only its fixture prefix/config/output paths adapted for local isolation (`licm_af9522_`, retained). No Website_Core execution. Shipping/native regression reporting now writes `qa/license-native-results.json` so CI artifacts retain these outcomes. Owner-only administration and optional licensing storage guards remain.

Refresh session46888 completed successfully and is closed. Changed/merged PHP and Python syntax pass; shipping bytes are LF. Resolve generated inventory through this refresh, stage/hash-verify and finish the merge, then push and discover its own current-head runs. Parent f86 full38811407918/job116685268807 passed package/native and is in browser; parent1fbeb38810400216/job116681882922 is in browser. Preserve both. Independent review remains required; no current reviews exist onPR138/139 as of this checkpoint. Prior comment/review/metadata write rejection must not be bypassed. PR139 attachment still exceeds the chat100-attachment cap; use its ordinary link.

Full e37538805429786/job116664920044 is completed successfully. Artifact12008047599 SHA256275ef98dbe59118e7f1b3df45efba35b1f9bb36eb404ac6e9dea703752167a7b is downloaded, CRC/hash/named reports verified:61 reports including1737 HTTP,289 recovery,1787 Apache. Testmerge15746f7f has the same treea561f58b as e375. Linux packages verify11 checksums, exact Git/member/directory/update bytes; Windows/Linux FULL and COMPLETE are identical. UPDATE only changes the source commit in its manifest. Proofs: `work/20i-e375-full-verification.json`, `work/20i-e375-linux-verification.json`, `work/20i-e375-windows-linux-comparison.json`. These remain parent evidence, not proof of this merged head.

Local defect evidence: `work/license-replace-reproduction.json`, `work/license-replace-fixed.json`, `work/20i-main-merge-license-native.json`; temporary-table repro is repeatable, the full native fixture prefix is already populated and must not be reused. Incoming main's own handoff is preserved in [its archive](docs/archive/2026-10-15-main-16f07e49-SESSION_HANDOFF.md), and both session-log histories remain. This integration fix is not a new licensing feature workstream. Continue20i after current combined acceptance.

The following pre-merge handoff retains exact client/parent artifact identity. Its push-next instruction is historical once the enclosing merge is pushed.

# Continue20i: finish client-search browser acceptance

Current branch `codex/twentyi-client-domain-search`, [draft PR139](https://github.com/afterburn25/Cajun_Web/pull/139), base `codex/twentyi-service-orders` / PR138 at5bec4fb2. Implementation head `1fbebcac0992cc7f3d71c91b33e4c97d047f4a4b`, tree `f325b8c1345fedb791a3b5a2655573a9c59646c3`. Enclosing follow-up commit: **Prepare permitted support fixture for client domain acceptance**. Published1.24 is unchanged; full20i goal remains active.

## Verified evidence

Native domain run38810375887: all four combinations successful. Downloaded artifacts12008521772/12008227522/12007977896/12007059361 have106 combined checks each, including50 client-specific checks; hash/CRC/named reports verified. [Exact hashes and scope](docs/TWENTYI_CLIENT_DOMAIN_SEARCH.md). Local `work/20i-1fbeb-client-native-verification.json`. PR native38810400244 also succeeded by workflow status.

Windows build session95816 completed successfully and is closed. `work/verify_1fbeb_windows_build.py` already copied/verified the exact artifacts and is not idempotent: do not rerun. Retained `work/20i-client-1fbeb-windows-dist` and verification JSON:11 checksums; FULL5416/COMPLETE2659/UPDATE2646; exact source/member hashes/directories/update payload. Development1.25 labels only. Refresh session76236 is also complete/closed.

Parent5bec four recurring artifacts are downloaded/verified as recorded in hosting evidence. Browser artifact12007782227, SHA256793db721ef6a79f048b5b8d8793f5b361c363298c53e2eb77487b22b3d47bd7e, passes1738 actual HTTP/Chromium checks; unexpected browser/PHP diagnostics are empty. Testmergefe1f8684 and source5bec share tree6af747dc5b9857453de8176e881b656e1b484c98. Actual owner desktop/client mobile active screenshots inspected; no contradictory pending wording. Files/proof: `work/20i-5bec-browser*`.

## Current correction and live handles

Browser fixture code originally tried support_start for a newly invited client without first enabling its support policy. Production correctly restricts such clients. This test-only follow-up uses actual owner support_policy, verifies the persisted permission and real scoped session, then retains the403/no-provider-call assertion. No shipping runtime changes; native/Windows evidence remains scoped to1fbeb. Python AST and docs checks pass. This correction is not yet browser-tested.

Preserve full1fbeb run38810400216/job116681882922, last observed in native database/application integration.22 of25 workflows completed successfully at the last full listing; hosting operations38810400532 and recurring38810400385 were in progress. Parent5bec full38807153582/job116670835668 is in recovery; earlier e375 full38805429786/job116664920044 was in Apache. Inspect these exact handles; do not restart on observation timeout. After pushing this follow-up, discover its own runs and inspect/download named reports and screenshots.

PR139 creation succeeded. attach_artifact failed because this chat exceeds100 attachment identities; do not unlink preserved PR history to force attachment. The ordinary GitHub link is usable. Prior comment/review-request/PR-metadata writes were rejected by automatic approval review; do not bypass via CLI. Independent review remains required before release.

## Next

Push the fixture/evidence correction, fix any real current acceptance failures, inspect client desktop/mobile screenshots and verify current packaged/recovery/Apache evidence. Obtain independent review through permitted mechanisms. Preserve main6ec7a749 licensing history for a normal merge and combined regression, including existing optional storage/owner-only guards. Do not modify Website_Core.

Continue the complete [20i requirements](docs/TWENTYI_REQUIREMENTS_EVIDENCE.md), especially automatic catalog/configuration and full fulfillment. The full API upload was already requested once; do not ask again or invent fields. No live provider operation, payment, customer mail, SSH or deployment occurred. Do not weaken Windows private-key permission checks; native tests run in Linux. Preserve exact shipping LF bytes and regenerate installer hashes after any runtime change.
