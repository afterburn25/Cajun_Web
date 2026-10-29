# Application 40820683270: preserve native acceptance command order

PR304 `2e31a040e3ccf0e58919142c4ac287aabe2e89b7` repairs the duplicate software-suite invocation exposed by full run40802174745. Independent review confirms its entire shipping tree and original software/financing assertions are unchanged. Its six orchestration checks and a separately authored five-test executable dispatch regression pass against the exact source. Duplicate PR305 was closed; its independent evidence remains retained and no full run was dispatched for it.

The single corrected [full run40820683270](https://github.com/afterburn25/Cajun_Web/actions/runs/40820683270) failed before native/browser execution. Build job123272195324 reached acceptance-helper check218, `Software commerce native acceptance command is additive and ordered`. PR304 placed its new guard between the established license-native and software-native commands; the sealed contract intentionally requires the software command immediately after the license command.

## Narrow correction

Isolated `codex/software-acceptance-guard-order` starts at exact PR304 head. It moves the unchanged six-check guard to the existing build step, after the build guards, and uses that step's established `python3` interpreter. The existing native command block is restored byte-for-byte. Full workflow comparison proves every other byte is unchanged after removing the moved command. No test assertion, production source, permission, timeout, serialization, artifact contract or release approval is modified.

## Local verification and gates

The exact parent reproduces the same failure locally after218 checks (217 pass, one fails). The corrected workflow passes all283 existing artifact/helper checks, the six orchestration checks, YAML parsing and all24 embedded Bash steps. The original before/after report hashes and workflow SHA are retained in [machine-readable evidence](evidence/pr266-software-guard-order.json). This reuses an existing failing acceptance regression; it does not change its expected sequence.

Exact corrected full installed acceptance is pending. Original failed runs and artifacts remain preserved. PR266, PR268 and main are untouched; independent review, the separate financing corrections, combined upgrade/package verification, merchant sandbox credentials, normal integration and exact-main acceptance remain open. No release, deployment or payment activation occurred.
