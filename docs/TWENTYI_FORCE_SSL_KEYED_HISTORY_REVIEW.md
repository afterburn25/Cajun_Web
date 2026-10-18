# Force SSL retained-history authentication review

Updated 2026-10-16. This correction is included in the normal556+b221 integration; the enclosing **Integrate paid renewal and authenticate hosting change history** commit identifies its source. It does not claim native or browser acceptance before those tests actually run.

## Finding and scope

Readiness previously reconciled counts, relationships and recomputable unkeyed row/audit digests before pending/latest lookups selected indexed package and disposition fields. A stored-row alteration with a matching recomputed audit could move an unresolved request to another package or change a nonmatching readback to observed, hiding the fence without changing the retained keyed canonical document. This requires alteration or inconsistent restoration of retained storage, not ordinary public input.

Source556 proof `b428f69159955f3303ac6fb2bea8d94eabb05c1eb2dfca45965f82f7b3c75118` verifies14 extracted cryptographic/query-model checks. Those checks do not execute native SQL or a provider gateway. The first external proposal's lower bound also skipped empty or space-trimmed CHAR IDs; that proposal remains preserved and was not applied.

## Reviewed correction

External v2 manifest `d17d09f3bc8770a0c2c0ce79a503708054ce8f9706eec1116694d3f269bd8e11`, patch `d4df54fd59a975e8abdc8db1d7095790f3c8df09fe3e1a97ed773c5b8680b4cd`, adds validation of every retained request, terminal and check through the existing keyed document decoders before indexed admission. The first page has no lower bound; later pages use binary keysets with64 rows and exact retained-counter reconciliation. The read never repairs or caches history. Current approval and retained historical binding keep their existing distinct checks. There is no schema, workflow, provider response, retry or grant-authority change.

An independent Domains source review `614cf09ab2906c3126d31b0d717e44286c0eb51ca937c497e456e699a8f3d9f5` confirms exact556 bases and resolved hashes, only additive edits, original-line preservation, full first-page coverage and four real outside-transaction tamper fixtures. Root copied the three exact hash-verified resolved files after a guarded patch check failed on the working copy; no partial patch applied. Every changed PHP file passes syntax checking.

| File | Reviewed resolved SHA256 |
|---|---|
| site/app/providers/force_ssl_controls.php | 73c83ebb2667b5c48188cdb6f8e5ca12429ba58e61683c868623a8940f848172 |
| site/app/providers/force_ssl_controls_storage.php | 6788d25e82df4678cedb530dac98a9653843950ee54567120d392a6fbeb1ba2b |
| tests/provider_force_ssl_controls_native.php | 1058984c98e9116e5ef79cd7c9d40e4f639e393777a57af310f3874c382cbe64 |

The four new native cases mutate the indexed request package, check disposition, empty check ID and space-trimmed check ID plus their recomputable audit. Each checks the actual intended mutation, unchanged keyed proof and counters, exact proof refusal on new approval/current view, no grant/audit/DNS/GET/POST effects, and exact restoration. Native PHP8.2/8.4 and MariaDB10.11/MySQL8.4 execution is pending at commit time. Combined browser, package and release gates remain required. There were no live provider calls or customer effects.
