# CajunWebPro 1.25.0 acceptance job allowance

The correction starts from `88f116f0caf4a9bd8ea6f7faafd1e0eadb432039`, tree `4ef3e12906ed23674b331c5ea1512cc1a8fe03b4`. The enclosing Git commit identifies its successor. Frozen release scope and shipping application code are unchanged.

## Original evidence

Supplemental PHP8.3/MariaDB [run39024070818](https://github.com/afterburn25/Cajun_Web/actions/runs/39024070818), attempt1, used PR preview `6fd1d234` with the exact candidate tree and verified ordered parents. Native/database integration passed in40m59s. HTTP/Chromium ran138m21s before GitHub explicitly reported that the combined job exceeded3h0m0s. The native/browser job and workflow concluded cancelled; final aggregation failed. All seven archives and five completed job logs remain retained, with GitHub size/digest and outer ZIP checks. Preservation receipt SHA256: `232e6527d11c1846b47a295be92caeefad3901cc71619ae533322c3436e64550`.

The actual browser checkpoint contains316 of320 expected PNGs. The four scheduler captures, final HTTP result and scheduler privacy diagnostic are absent. The driver writes its cumulative HTTP result at completion, so missing final output is not a zero-test or successful-test count. Source order and retained requests place interruption in the final scheduler preparation/initial approval-refusal phase, before its first capture. The final accepted request's payload was not logged.

The last completed provider-settings response closed at13:43:26UTC. A new connection was accepted that second and cancellation occurred at13:43:29, three seconds later. Settings responses continued completing in24–27seconds; no stalled request or application assertion failure is demonstrated at the cutoff. The cancelled run cannot establish that its remaining checks would pass. Some existing Requests calls do not specify a timeout; this diagnosis does not claim that every individual request is bounded.

Retained settings traffic comprises789 GETs and435 POSTs, totaling6,690seconds of coarse accepted-to-closed connection intervals. This is not SQL profiling, CPU attribution or a live-site performance guarantee. Successful PHP8.2 MariaDB and MySQL full runs took95m49s and75m13s in HTTP respectively, with810 settings GETs and447 POSTs each. Their native phases took35m12s and30m08s. The per-check PASS text was printed only at driver completion and cannot time individual flows. Timing receipt SHA256s: successful runs `b889c60df7693997afb731f48fd6cff32458044b2c144b57e48271d5409fc56f`; cancelled run `7cb39a4df295ea4fe3266eabd07cf4a6c22b36df0b1e5ccf8599d5586cf4527d`. Independent diagnosis `6f2b02a72a0fc1b0b5da339a684e44631637687318f13f06bb2fdefdeb9f62b0` agrees with the bounded correction and its limits.

## Narrow correction

Only `jobs.native-and-browser.timeout-minutes` changes semantically in the workflow:180→240. This gives the complete accumulated suite one additional hour, preserving its native-to-browser state in one job. Every other workflow value and step stays intact. All149 native commands in their exact order,161 browser/205 final report requirements,124 source pins and320+8 images remain. All assertions and application/request/browser/provider timeout behavior, including the existing30-second navigation limit where applied, remain unchanged. No request is retried or an original outcome relabeled by this change.

The earlier105→180 adjustment was likewise an aggregate runner allowance, recorded in commit `cccf85bf`. Neither historical adjustment nor this diagnosis is acceptance. The finite240-minute budget can still expire, and existing application timeout checks can still fail. The long settings responses remain a measured observation; this change does not claim an application performance improvement.

## Parent evidence and remaining gates

At13:51UTC, original88f full proofs passed for PHP8.2/MariaDB `39024074510` (SHA256 `d4205d5d927ea5744bb61ef5631b5b03b285e85699602ccaffbc0041e163ad78`), PHP8.2/MySQL `39024079957` (`06543e297703348c94792dc61e9aaf9c8e993cc72b113923277d7018f13e36ec`) and PHP8.4/MySQL `39024095149` (`4b0ec224aa8ed419be0a726f7b39788cbb4fddc6cf5833cd6913e93c9bd77bdb`). PHP8.4/MariaDB `39024087120` was still running. All originals remain intact; do not cancel them to hide evidence.

Parent maintained/minimal compatibility and all four recovery/Apache/published1.24-upgrade partitions passed their separate verification. All328 original MySQL-run images were actually inspected, with exact names/hashes and reviewed regions. Final visual receipt `70c41ee7fcfd83fd5f88b0124ddb4f4018a4aea545ceec5f965b9c6ad4750cab` and independent coverage receipt `c928bf2328eb8bb1888d573fd684270e522a88aef3124a25b937d9d5cd8f4145` retain P3 nested-card spacing and capture/scroll/transition limitations. No claim is made that obscured, offscreen or unviewed repeated pixels were visibly approved.

Two external-verifier failures also remain preserved: the stale twelve/thirteen operations label and Windows default decoding of an exact UTF8 refusal. A reviewed one-literal expectation correction and explicit Python UTF8 execution produced the actual passed v3 proofs without changing the14 verifier modules, original artifacts or CI attempts.

Require independent review of this exact workflow-only change and fresh complete corrected-candidate Windows/four-environment/maintained/default PHP8.3 evidence and actual images. Parent passes do not certify the successor. Then normally merge the accepted candidate, run fresh complete exact-main acceptance/images, prepare18 assets, download-verify the draft, publish, separately download-verify every public asset, and provide the verified UPDATE instructions. Main and published1.24 remain unchanged; no production deployment is authorized or performed. Full20i remains unfinished.

## Subsequent observation and local validation

Later observation, **2026-10-17 13:58 UTC**: required PHP 8.4/MariaDB run `39024087120` also concluded cancelled after its combined native/browser job reached the three-hour cap; final aggregation failed. Its original build, native database, recovery and Apache phases passed, while browser acceptance remains incomplete. The original job annotation and five logs are retained; archive collection is in progress. This does not change the earlier cutoff or certify a passing candidate. Local validation of the correction passed all 215 acceptance-helper checks and the documentation check (265 Markdown files, 1,781 local links). Root and independent source review confirmed that the complete workflow differs semantically only in the aggregate 180-to-240-minute allowance. Shipping code and tests are unchanged.

