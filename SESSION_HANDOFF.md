# Continue 20i: test private client domain search

Current branch `codex/twentyi-client-domain-search` starts at `5bec4fb2cd0826386617098972ae3eb3a6fff0e1` from `codex/twentyi-service-orders` / PR 138. Enclosing implementation commit: **Add private client domain availability search**. Create its draft PR with base `codex/twentyi-service-orders`; do not overwrite PR 138. Published 1.24.0 remains unchanged. Full goal remains active.

## Implemented, awaiting runtime acceptance

See [client-search guide](docs/TWENTYI_CLIENT_DOMAIN_SEARCH.md). Additive policy/search/result storage records real clients separately from staff; owner approval binds current profile/configuration/key context and approving owner authentication. Shared provider budget counts both staff/client attempts, including incomplete results. The existing fixed authenticated reader/transport performs the lookup. Current authorization is checked before credential access, send and persistence; revoked results discard availability. Private client forms/history exclude support impersonation and other clients. Request nonces prevent repeat reads. Preparation distinguishes fresh empty interruption from lost established history, and owner migration preflights before DDL.

New tests: `tests/provider_domain_client_cases.php` extends the existing native fixture and writes a named subset report; `tests/provider_domain_client_cases.py` extends the actual extracted-package Chromium flow with real invitation/login, owner enablement, support refusal, two-client isolation, replay, shared limits/429 pause, missing history and restoration, desktop/mobile screenshots and internal-file denial. Existing owner/reader checks remain. Current local PHP lint/Python AST checks pass. Native/browser cases are written but not yet executed. The local Windows private-key permission guard remains intact; use the Linux CI fixture for native tests.

## Next actions

1. Installer refresh session 76236 completed successfully and is closed; documentation checks pass. Finish staged Git-archive/hash equivalence. Commit all intended feature files and documentation after syntax checks. Do not claim this refresh is a clean exact-source distribution build.
2. Push this branch and create/attach its draft PR. The domain-search workflow now includes this push branch; the full application workflow permits PRs targeting the hosting branch. Preserve prior run handles. Download native artifacts and verify named results; correct failures, then inspect actual browser screens and package evidence.
3. Obtain independent review through available approved mechanisms before integration/release. Prior GitHub comment/review-request/metadata writes were rejected by automatic approval review; do not bypass through CLI. Source push and PR creation remain available.
4. Preserve and normally integrate new main licensing history after this checkpoint and repeat combined acceptance as needed. Main inspected at `6ec7a7499e8e582b2c164e762601a42cee1f9ea2`; its changes include license API/service, portal actions/workspace and native licensing tests. No Website_Core work is authorized here.
5. Continue automatic catalog/configuration verification, complete hosting commerce and the remaining [20i requirements](docs/TWENTYI_REQUIREMENTS_EVIDENCE.md). The complete API upload was already requested once; do not ask again or invent response fields.

## Retained parent evidence and live handles

Parent5bec native recurring `38807146247`: all four jobs successful; downloaded artifacts pass SHA256, CRC and named-report verification. IDs12007570714/12006976999/12006693688/12005934484; proof `work/20i-5bec-native-verification.json`, hashes in the hosting evidence guide. Full `38807153582`, job `116670835668`, is still in HTTP/Chromium. Merge e375 full `38805429786`, job `116664920044`, is in Apache. Preserve these handles and inspect their results instead of restarting. Source e375 native/Windows and parent4d059 browser artifacts are already downloaded/verified as recorded in [hosting evidence](docs/TWENTYI_ORDER_FULFILLMENT.md). The [prior handoff](docs/archive/2026-10-15-SESSION_HANDOFF-5bec4fb2.md) retains artifact hashes and closed build handles. Those parent reports do not certify this new client-search branch.

No real provider read/write, purchase, charge, customer mail, SSH or deployment occurred. Source edits must preserve LF bytes identical to Git; regenerate shipping inventory after changes. Preserve optional licensing storage/owner-only guards and all existing histories.
