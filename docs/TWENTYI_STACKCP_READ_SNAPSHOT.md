# StackCP owner-read performance

The owner setup view now prepares one synchronous saved-data snapshot under the existing staff mutex and transaction. It verifies the complete authentication and assignment histories once, then checks the current account, key, approving owner, client and link for every selected package. The HTML is rendered after the snapshot releases its resources.

This changes repeated read computation only. Existing password/review-hash mutation APIs, history integrity, setup limits and rendered forms remain. No provider request, credential decryption, setup repair, reusable authorization token or cross-request permission cache is added. Every subsequent call validates current state again.

The original 9779 browser evidence showed provider-settings requests reaching 26–29 seconds, with about 17 seconds already present before StackCP assignment preparation. The bounded StackCP change does not establish that the entire page is fast. Other settings sections and actual database timing remain separate measurements.

## Verification status

Before integration, 68 actual extracted-code comparison/negative checks passed with synthetic database boundaries; original and changed HTML/refusals matched. In its retained-history 20-package example, modeled database calls fell from 1,629 to 236 while checking all 20 current package bindings. These are call counts, not native timings.

The prepared `tests/stackcp_read_snapshot_native.php` uses a separate database prefix and private storage. It compares original/new rendered HTML and actual query categories for 0, 1 and 20 packages; exercises out-of-page corruption and paired row/audit loss, missing original key without recreation, exact restoration, current-identity changes, captured prior forms and real separate-process lock contention/release. Its 49 expected named checks and query profiles require actual PHP/database execution. No timing threshold substitutes for authorization or history assertions.

The original comparison functions are source-bound in `tests/fixtures/stackcp_read_snapshot_original.json`. The new site-only class is covered by normal installer inventory generation and the private-path tests. Native report `stackcp-read-snapshot-native-results.json` is included in focused and full acceptance; actual browser and package acceptance remains required. Source review, modeled counts and syntax alone are not release or whole-page performance acceptance.
