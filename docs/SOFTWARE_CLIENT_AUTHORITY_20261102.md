# Software customer authority at the mutation boundary — 2026-11-02

## Source and delivery state

Requirement **SW-AUTH-01** corrects the four customer software entrypoints on exact published PR317 source `84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd`. The enclosing commit identifies this isolated correction on `review/software-client-authority-20261102`. Current observed main remains `1c44ca120e7768998fafa720b3e1c4c85990c330`; latest observed release remains v1.25.0.

Implemented and locally tested. Separate frozen application/native and additive-proof reviews are complete; their [exact receipts](evidence/software-client-authority-20261102/reviews/README.md) preserve both the initially blocked checkpoint and the later cleared source-admission result. Hosted acceptance remains pending. This is not main integration, a release, installed HTTP acceptance or live deployment. FIN-01/FIN-02 remain separate mature blockers under correction. Preserve PR317's active full acceptance on its frozen source.

## Source-admission follow-up

The application/native checkpoint is local `01cd1e25fc6458087a5259d1e4e760abfa5b0cb0`, tree `36424379fe767dd6e07dca65088cbe7d1d567c1b`. Its independent frozen review preserved all 466 native checks but found two source-admission failures: the historical ticket guard rejected the five software application paths, and the historical fixture guard rejected the additive software native fixture. Those original failed commands and their complete nonsecret outputs remain [retained](evidence/software-client-authority-20261102/source-admission/original/).

The additive [correction receipt](SOFTWARE_CLIENT_AUTHORITY_CORRECTION.json) and `tests/software_authority_provenance.py` now bind exact84 ancestry, precisely six shipping paths, the unchanged tested application/native bytes, unique reversible edits, exact added helpers, and the current canonical inventory. They also verify 877 unchanged historical test, tool, workflow and JSON receipt files. No historical receipt, original assertion or protected workflow was rewritten.

Only the ticket guard's historical revision/byte comparisons and the provider fixture guard's historical verifier/native comparisons use predecessors. Native and browser acceptance continue to execute current files. The provider guard keeps its original checks in order and also runs the new proof through the maintained helper suite. This standalone proof admits no finance or Account/Notifications combination; shared hooks and inventory will need a separately reviewed composition.

| Current-source command | Result |
| --- | --- |
| `python3 tests/software_authority_provenance.py` | 89 checks passed, including 70 rejection cases; exit 0, empty stderr. |
| `python3 tests/combined_runtime_repair.py` | Passed; exit 0, empty stderr. |
| `python3 tests/acceptance_artifacts_test.py --report …` | 628 checks passed; exit 0, empty stderr. |

[Exact source binding](evidence/software-client-authority-20261102/source-admission/source-binding.json), [corrected command records](evidence/software-client-authority-20261102/source-admission/corrected/execution.json), and [checksums](evidence/software-client-authority-20261102/source-admission/checksums.json) preserve this distinction. The native 466-check evidence is reused without rerunning it because all five application modules, inventory and four PHP fixtures are byte-identical to the reviewed checkpoint. The additive proof now awaits independent review; hosted, combined, main and release gates remain pending. The [primary exact84 review](evidence/primary-review-20261102/README.md) records why the separate corrections are required, without granting blanket approval.

## Problem and correction

The portal could load a valid customer, then an owner could change that customer's authority before a software order, one-time payment, enrollment or cancellation reserved its mutation. Those entrypoints trusted the earlier customer record. A fresh controlled actual-source probe reproduced seven of eight stale-version/deactivated admissions; the original deactivated order was already refused.

`software_current_client()` now runs first inside each owning transaction. It locks the same current `studio_clients` row used by account edits and invitations, checks active state, the selected session customer and current authorization version, and retains the existing 30-minute idle / 8-hour absolute limits. Raw support selectors, support access and contact-session provenance are refused. The shared helper has no service-plan readiness dependency.

The order reuses its existing client-row lock. Charge reservation, enrollment and cancellation acquire that row before their resource locks. These entrypoints do not add a staff mutex or invert an existing account lock order. A committed owner revocation is therefore observed at admission, including when the customer request waits for a held account row.

Gateway result recorders and finalizers are unchanged. Once an operation was admitted and the reservation committed, a later account revocation cannot erase the actual gateway outcome. This is a reservation-time authorization rule, not a promise to cancel a payment already admitted. The regression explicitly covers the latter distinction.

Shipping scope is five PHP application files plus the canonical installer inventory. No schema, merchant activation, route, payment contract, license policy or customer-facing design change is included.

## Native evidence

The original 272 software assertions remain intact and in the same order. Four additive fixture insertions establish genuine current buyer sessions, preserve the other customer's original missing-dependency refusal, and append the new regression. The inverse restores the parent file exactly.

| Trial | Original checks | New checks | Result |
| --- | ---: | ---: | --- |
| `original01` | 272 passed | Incomplete | Preserved harness failure: a child reused a synthetic gateway ID. No application baseline credit. |
| `original02`, exact84 application | 272 passed | 83 passed / 111 failed | Clean reproduction after disjoint child-ID allocation. All 194 new cases evaluated before the parent assertion helper reported failure. |
| `corrected01` | 272 passed | 194 passed | 466 passing stdout assertions; exit 0 and empty stderr. |

The corrected run uses PHP8.3.6 and MariaDB10.11.14 in a newly created, loopback-only disposable database on port33312. The original application came from an immutable Git archive. Original and corrected runs use byte-identical test files. The independent review compared all original source-manifest entries to exact84 and all corrected entries to the working source, allowing only the subsequently regenerated inventory.

The new 194 checks cover:

- Eight checks for four healthy order, charge, enrollment and cancellation controls.
- Thirty-two checks after actual `studio_client_save()` commits a version rotation or deactivation from an independent PHP/database connection.
- One hundred twenty-eight checks across wrong customer, missing version, idle expiry, absolute expiry, raw support selector, resolved support context and both contact provenance keys.
- Twenty checks for four actual held-row-lock races, including observed waits and refusal after commit.
- Six checks that admitted charge, enrollment and cancellation outcomes remain recorded after later revocation.

There are eleven successful independent owner-worker receipts. Denied mutations must preserve the complete database/schema/private-file snapshot, make no synthetic gateway call and leave no transaction open. The held-lock race uses an explicit SQL version change; the separate committed-change cases use the actual owner API.

Only gateway configuration and transport are synthetic. The three transport-bearing entrypoint declarations are token-extracted verbatim into a test namespace; their actual PDO, reservation, session and finalization helpers remain in use. The order function is called directly. Tests do not store opaque tokens or merchant credentials. They establish native authority behavior, not real merchant, installed HTTP or live provider acceptance.

All ten touched PHP files pass lint. The existing software commerce contract passes 39 assertions and production-activation contract passes six. Canonical `tools/build.py --refresh` succeeds. The unchanged native evidence partition accepts the new report and reads its 194 uniquely named passing checks.

## CI and preserved gates

Only three filter lines change in `software-commerce-native-fast.yml`: add `chatgpt/**` to eligible PR bases and watch `tests/software_commerce_authority*.php` in both existing path lists. The push branch list, permissions, concurrency, timeout, job commands and ordinary/protected workflows are byte-identical to the parent. A stacked correction PR can therefore run the existing full native command without an extra push-triggered duplicate.

The fast job is one local-runtime-equivalent focused gate. Required combined full application, installed browser, maintained PHP/database matrix, independent integration review, exact-main verification and release asset verification remain mandatory. No queued or old-parent result is credited to this correction.

## Retained evidence and continuation

Nonsecret original failure, baseline, corrected native output, per-check reports, lint/contract logs, source hashes and independent preservation checks are retained under [`docs/evidence/software-client-authority-20261102`](evidence/software-client-authority-20261102/). Configurations, keys, SQL dumps and live identities are excluded. `checksums.json` binds the retained evidence.

Complete final frozen-tree review, commit this isolated correction and open a draft stacked PR against the unchanged PR317 branch. Let its normal PR event run native acceptance. After both finance corrections and this correction clear their gates, combine accepted source deliberately, regenerate inventories and validate the exact combined result. Do not move the protected full-run head early, force-push another worker, weaken a failed assertion, or merge with known mature blockers. Merge and verify current main before any separately verified release; live deployment remains a distinct authorization.
