# CajunWebPro session handoff

Current acceptance source: [cccf85bf](https://github.com/afterburn25/Cajun_Web/commit/cccf85bf6c3523bca8a0659b5c660915fd67e248), tree `7ee27c085caaa5de08e3ec1daca4adf198c750a3`, on `codex/twentyi-cloud-minimal-runtime`, parent2a86704f. The reviewed correction explicitly loads the test-only tokenizer and increases only the stateful native/browser job's finite budget to180 minutes after the original105-minute timeout. Application shipping files, foundation classes and fixtures are byte-identical to2a; no assertions were removed. Local171 artifact-helper checks and documentation validation pass. Its first [full run38927120383](https://github.com/afterburn25/Cajun_Web/actions/runs/38927120383), attempt1 with checkpoint_only=false, is active; exact-source Windows packaging and corrected Linux/full acceptance remain pending.

Updated 2026-10-16. **Published 1.24.0 remains unchanged. No 1.25.0 or complete-20i release is accepted.**

Codex and its three agents own 20i, provider support/SSH, shared integration and releases. Devin owns remaining non-20i work under [assignment #157](https://github.com/afterburn25/Cajun_Web/issues/157).

Preserve frozen candidate `2a86704fa0c7775206cf9ac93e27d0457be307fa`, tree `32f1058c4f13006ba6977216fea20dce7c815db9`, on `codex/twentyi-cloud-assignment-integration`. Its four focused archives pass3,020 named checks; exact Windows/Linux packages agree at5886/2884/2871 members. [Current evidence](docs/TWENTYI_CURRENT_CHECKPOINT.md).

Next actions:

1. Preserve original full38923745296/attempt1. Its native-and-browser job117051173466 failed because Ubuntu PHP8.3 `-n` lacked the test extractor's tokenizer; HTTP was skipped. The correction is now pushed atcccf85bf; follow its first full38927120383 and exact-source packages. Keep the normal34 checks and all no-INI assertions; do not weaken or restart the failed source.
2. Retain separately verified build66/helper171/recovery289 and exact Linux/Windows package equality. Finish the original Apache evidence, still active at15:39UTC, without treating it as full acceptance. Inspect actual combined browser/screenshots only after they execute on a corrected source.
3. Preserve terminal819c failure/99missing reports and investigate original9779's canceled native-and-browser job117002783253 plus failed aggregate117051509888. Root did not request cancellation. Older passing partitions remain source-bound.
4. The current integration priority is to fix [licensing #159](https://github.com/afterburn25/Cajun_Web/pull/159) and [mail replies #161](https://github.com/afterburn25/Cajun_Web/pull/161), then finish existing end-to-end work. [Assignment priority](https://github.com/afterburn25/Cajun_Web/issues/157#issuecomment-6219110174). New foundations and open PRs are unaccepted proposals, not complete features. Retain the PR159 and161 review findings below. PRs158 and160–170 include unfinished foundations; coordinate auth/billing/schema/portal/installer boundaries before normal integration.
5. After exact combined acceptance and independent review, integrate accepted histories with current main, verify exact-main packages and downloaded release assets, and publish only the tested increment. Continue remaining20i work afterward.

Licensing #159 remains at `e3d62770`. [Review 6218434878](https://github.com/afterburn25/Cajun_Web/pull/159#issuecomment-6218434878) still identifies an unrelated/admin-created/revoked NULL-invoice license being claimed by a new purchase, refusal to migrate genuine populated unmarked pre-master databases, and stale installer hashes that stopped original CI.

Mail replies #161 remains at `1d45798b`. [Review 6219105635](https://github.com/afterburn25/Cajun_Web/pull/161#issuecomment-6219105635) identifies a P1 copied saved-submission read-revocation gap, a P2 mixed-pagination case skipping 20 of 42 items, and a P2 `smtp.example.invalid` fixture failure (15 pass / 1 fail in both jobs). No correction or acceptance is claimed here.

This is a documentation-only handoff, with no live provider/customer action or release. [Previous immutable handoff](https://github.com/afterburn25/Cajun_Web/blob/82667afb963f394b73caa2a9477a07eebad1590b/SESSION_HANDOFF.md) preserves prior context.
