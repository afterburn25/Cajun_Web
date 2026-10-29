# Application 40820683270: preserve native acceptance command order

PR304 `2e31a040e3ccf0e58919142c4ac287aabe2e89b7` repairs the duplicate software-suite invocation exposed by full run40802174745. Independent review confirms its entire shipping tree and original software/financing assertions are unchanged. Its six orchestration checks and a separately authored five-test executable dispatch regression pass against the exact source. Duplicate PR305 was closed; its independent evidence remains retained and no full run was dispatched for it.

The single corrected [full run40820683270](https://github.com/afterburn25/Cajun_Web/actions/runs/40820683270) failed before native/browser execution. Build job123272195324 reached acceptance-helper check218, `Software commerce native acceptance command is additive and ordered`. PR304 placed its new guard between the established license-native and software-native commands; the sealed contract intentionally requires the software command immediately after the license command.

## Initial order correction

Isolated `codex/software-acceptance-guard-order` starts at exact PR304 head. It moves the unchanged six-check guard to the existing build step, after the build guards, and uses that step's established `python3` interpreter. The existing native command block is restored byte-for-byte. Full workflow comparison proves every other byte is unchanged after removing the moved command. No test assertion, production source, permission, timeout, serialization, artifact contract or release approval is modified.

## Local verification and gates

The exact parent reproduces the same failure locally after218 checks (217 pass, one fails). The initial corrected workflow passes all283 existing artifact/helper checks, the six orchestration checks, YAML parsing and all24 embedded Bash steps. The original before/after report hashes and workflow SHA are retained in [machine-readable evidence](evidence/pr266-software-guard-order.json). This reuses an existing failing acceptance regression; it does not change its expected sequence.

Exact corrected full installed acceptance is pending. Original failed runs and artifacts remain preserved. PR266, PR268 and main are untouched; independent review, the separate financing corrections, combined upgrade/package verification, merchant sandbox credentials, normal integration and exact-main acceptance remain open. No release, deployment or payment activation occurred.

## Follow-up: retain the report inside the build partition

The single full run [40821246468](https://github.com/afterburn25/Cajun_Web/actions/runs/40821246468), frozen at `5a5389ac343e943b8cfd8b1aef0bf606593c5daf`, passed all283 helpers,69 build guards, the six orchestration checks, package generation and historical fixture preparation through1.25. Build job123273983150 then failed the actual partition seal: `Evidence crosses partition/private boundary`. The standalone orchestration JSON was outside the existing build report allowlist. Native/browser, Apache and recovery jobs were skipped; this was not application acceptance.

The guard now appends its exact output to the already permitted `qa/build-results.txt`, preserving the existing transcript and `set -o pipefail`. The partition allowlist, native command block, shipping code and every original283 assertion remain unchanged. Nine additional helper checks run the actual workflow reporting command in Bash against copied public source and use the real sealer, ZIP and envelope verification. They prove that successful and deliberately failing guard output survives byte-for-byte, failure remains a failing step, and the standalone JSON remains forbidden. The new regression reproduces the original seal failure before the one-line correction; all292 helpers pass afterward. YAML and all24 Bash steps parse. Exact-source evidence is appended to the existing machine-readable record above.

The next full acceptance must run once on the enclosing corrected commit. Earlier failed evidence remains preserved; this local result is not installed, combined, main or release acceptance.
