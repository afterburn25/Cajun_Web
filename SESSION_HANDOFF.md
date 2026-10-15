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
