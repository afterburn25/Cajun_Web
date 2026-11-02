# Independent finance application and native review — frozen05

**Decision: approve the bounded finance application correction on the exact frozen05 bytes below. No blocking finding remains in this reviewed correction scope.** This approval covers FIN-01, FIN-02, FIN-AUTH-01, FIN-INIT-01 and the three confirmed early correction findings. It does not approve all of PR317, a combined merge, a release or production activation.

The review is independent of implementation. I read the application/schema/view delta and relevant actual fixture bodies, verified immutable source copies and retained assertions, inspected the writer's native results, and independently reproduced and retested the initial-admission defect. The writer ran the 413- and 358-check suites; those are independently reviewed evidence, not tests I claim to have executed.

## Source identity

Parent: `84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd`.

Manifest: `finance-recovery-local-evidence/implementation-frozen05/manifest.json`, SHA-256 `2c50b9c02296d0ea220b049fcc79ea705ab7ca3c3e34d6a5c1f9c2f7e4fa76ea`.

All 31 changed PHP copies match that manifest. The current main and initial compatibility executions each retain those 31 files plus the unchanged gateway-v3 schema dependency. Their 32 captured hashes were checked. The source review records 53 exact named function ranges in `finance-final-independent-review/reviewed-functions.json`; the UI clauses and the two-line comment-only service change are separately identified there. Generated inventory identity is recorded, with its proof owned by root.

| Application/schema/view path | SHA-256 |
|---|---|
| `site/admin/views/studio/licenses.php` | `897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc` |
| `site/app/billing/finance_authorize_net.php` | `484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba` |
| `site/app/billing/finance_charge.php` | `b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8` |
| `site/app/billing/finance_recovery.php` | `4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2` |
| `site/app/billing/finance_subscription.php` | `3b7c779fdcf4a1e3a9f6d67756cbaa10b20c7b924f4191ebd0edadf12a3a483b` |
| `site/app/billing/ledger.php` | `fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989` |
| `site/app/licenses/finance_installments.php` | `fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c` |
| `site/app/licenses/financing.php` | `2d2857ef961963044c641123899041bf728247ac555bb22a2599ea4c04664bc6` |
| `site/app/licenses/service.php` | `04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443` |
| `site/database/migrations/license_finance_gateway_migrate.php` | `df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9` |
| `site/database/migrations/license_finance_recovery_migrate.php` | `4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e` |
| `site/database/schema/license_finance_recovery_schema.php` | `ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f` |

## Findings and correction invariants

### FIN-01: unknown replacement outcome during payoff

The replacement reservation now writes an independent durable attempt in the same transaction as changing the existing subscription to `replacing`. It preserves the signed remaining contract, the predecessor snapshot, signed agreement identity, environment, account fingerprint and original settings revision. Dispatch is committed before the create boundary. A missing ID or lost response keeps that attempt open even if independent receipts permanently pay off the financing obligation.

Recovery verifies an existing successor against the retained original contract. It does not derive a new contract from the now-paid ledger or dispatch another create. A known candidate is observed in its own committed step before later readback/finalization. Binding the successor, writing terminal predecessor history and closing the attempt are atomic. A failure before binding preserves the open obligation; a failure after binding preserves the exact candidate for retirement. Owner and scheduler recovery use the same durable facts, and unknown outcomes remain visible after payoff.

The original native witness had eight failed unknown/payoff assertions within its 217 checks. Those assertions are unchanged and now pass. Additional native cases cover reservation-before-I/O, separate committed payoff, observed/binding audit rollback, wrong account, same-account credential rotation, unsent reservation recovery, and scheduler visibility.

### FIN-02: receipts after enrollment and late external collections

`ledger_receive` and `license_finance_charge_record_paid` now attach the retirement obligation inside the receipt transaction. A covered partial or later receipt either marks the retained current subscription `retirement_pending` or sets a sticky obligation on the open replacement attempt. An audit failure rolls back both the receipt and the hold. Provider retirement runs after the transaction and mutex have been released.

The synchronization path retries partial-receipt retirement while preserving the established permanent-payoff path. A verified terminal result is required before the obsolete contract is treated as retired. Repeated receipt tests distinguish a true cancellation timeout from a lost reply followed by truthful terminal readback. They preserve all receipts, exact IDs and permanent payoff, and make no extra create request.

A verified overlapping provider collection is retained separately in `license_finance_collection_reviews` with transaction, subscription, period, amount, contract and provider context. It is not allocated as a second invoice receipt. Current/retired/unbound identities are rechecked under serialization, repeated events are idempotent, and unresolved accounting obligations block later collection admission. The original twelve post-enrollment receipt failures now pass; the suite also includes unchanged legitimate settlement controls.

### FIN-AUTH-01 and the early admission findings

Replacement entry now checks the current client before preflight synchronization, again under the reservation transaction, and again before dispatch. The checks use the existing current-client helper: ownership, active state, authority version and session age are read against current state. If authority changes after predecessor retirement but before create, the attempt stays prepared and unsent with terminal predecessor evidence. If revocation happens after admission, the external outcome remains durably recordable.

The exact84 authority baseline is separately preserved: original-auth01 has 22 checks/9 failures; original-auth02 retains those 22 and adds the zero-down deactivation case, producing 25 checks/11 failures. Its nine existing captured files equal exact84, and its two new helpers are the same helpers used by frozen05. All 25 assertions now pass.

The three implementation-stage findings are also closed. Whole-offer `needs_accounting` fences now precede further charge/initial/replacement admission. The actual gateway execute body refreshes current settings and refuses disabled, changed-mode, unapproved-production or changed-account operation even when supplied an older context; it permits current credentials for the same account. Before a second replacement reservation, the prior bound attempt's account is checked. Actual adapter bodies were exercised with a synthetic curl boundary and real separate native settings mutations, rather than relying only on the higher-level recording transport.

### FIN-INIT-01: unresolved one-time attempt before initial enrollment

The independent frozen04 witness confirmed a new full recurring contract could be created while a covered-period one-time charge remained uncertain: 11 assertions, 2 failures. The restored `invoice_clear` check runs inside the existing locked initial reservation loop and now refuses processing, uncertain and review attempts before reservation or provider I/O.

The unchanged independent witness passes 11/11 with exactly one captured source difference: the two-line subscription correction. It comprises two scenarios, uncertain refusal and a declined positive control. The uncertain case has zero provider calls, no reservation, identical financial snapshots and an unchanged uncertain attempt. The declined case creates once and invents no receipt. Reviewer-owned port 33317 was shut down cleanly; no writer runtime was accessed.

The final main suite adds 26 assertions for both opaque and profile callers plus historical uncertain-result recovery. Current finalization continues to retain and retire already-admitted candidates. The four original `prepared_gateway` fixture entries now explicitly represent historical creating reservations and existing synthetic candidates; current owner reconciliation/current finalization and the real separate charge-commit worker process them. No historical application body or weakened assertion is substituted. All 13 original assertion call sites remain byte-identical; the broad count of 14 ranges includes the assertion function declaration.

## Migration, UI and retained behavior

The new recovery-v1 storage is additive to gateway v3. Setup requires current owner authority and the existing financial mutex, refuses an inherited transaction, and validates schema/index identity. Only an explicitly marked empty interrupted setup is resumable. Unknown versions, unmarked pre-existing tables, populated interruption and missing established tables are refused while existing financial history is preserved. No original contract is fabricated for legacy ambiguous rows.

The owner licensing view adds escaped replacement/accounting queues and uses the existing CSRF-protected setup/reconciliation actions. Those services retain current owner checks, including serialized rechecks. This is source review of the view and destination behavior, not browser or visual acceptance.

`service.php` differs from exact84 only by the two authorized explanatory comments. Removing those precise bytes reproduces the baseline file. No executable licensing behavior changes there. Existing schema-v3 assertions and the original payoff, receipt and pending test bodies are retained. The separate payoff fixture timing adaptation was reviewed by root in `finance-payoff-compatibility-independent-review.json`; its first two manual receipts deliberately pause only the synthetic gateway while the final payoff fault remains active. Twelve added enabled-transport assertions cover immediate/repeated retirement separately.

## Exact-source evidence reviewed

| Evidence | Result | Source/interpretation |
|---|---:|---|
| `final-native05` | **413/413** | Current frozen05; PHP 8.3.6 / MariaDB 10.11.14; 357.384 seconds. First 387 assertions equal the passing frozen04 report, and the final 26 cover initial admission/recovery. |
| `initial-compatibility03` | **358/358** | Current frozen05; 32 current-caller scenarios and 4 explicitly historical recoveries. Each historical case has a separately committed charge, exact retained candidate and no create request. |
| Reviewer `finance-initial-unresolved-native04` | **11/11** | Unchanged independent two-scenario witness; only corrected subscription source differs from the preserved 11/2 failure. |
| `original01` | 217 checks / 20 failures | Exact84 application baseline; original 138 pass, all 79 added reproduction assertions retained in current source. |
| `original-auth02` | 25 checks / 11 failures | Separate exact84 authority baseline; original22 retained; all25 now pass. |
| Frozen04 compatibility | 292/292, 232/232, 25/25 | Prior-source evidence retained in adjacent-compatibility02 and adjacent-resumed03; not relabeled as current whole-tree execution. |
| Adjacent capture/response/owner fences | 140/140, 220/220, 75/75 | Retained native evidence with its own source hashes and empty stderr. |
| Pure/source checkpoint | 381 checks; 29 PHP lints | Retained frozen04 checkpoint with empty stderr. Full current source/provenance/matrix admission remains root's separate gate. |

Counts in this report are assertions, not independent scenarios. Overlapping suites are not added into a misleading total.

The current 413-check run's 5,036 stderr bytes contain exactly 73 expected lines: 67 intentionally unprepared seed payment-link diagnostics and six original readback/cancellation negative controls. I matched the lines to the actual source and fixture sites. Additional captured case exceptions, including the deliberate SQLSTATE45000/1644 audit trigger, remain in the JSON and are part of the rollback evidence. The current initial suite's 4,162 bytes similarly map to 36 deliberate seeds plus named readback/cancellation/audit/lost-response cases. No unexpected diagnostic was found in those completed current runs.

## Preserved failures and limits

The original failures, invalid initial-diagnostic harness runs01/02, invalid aborted final-native01, intermediate compatibility failures and later successful corrections are separate retained artifacts. The original generic HY000 from adjacent-full01 remains unclassified and nonreproduced. The later 33313 interruption is independently evidenced by a MariaDB signal11 and metadata-query stack, followed by numeric2006/2002 failures; it is not called an application assertion failure or an OOM. Only the unfinished232/25 suites were replayed after that owned restart. The older HY000 is not attributed to that later crash or to DDL.

OBS-03 remains a limit: historical operation IDs lacking original merchant/environment evidence are not retroactively bound by this correction. No cross-merchant collision or actual wrong-customer cancellation is claimed. Legacy ambiguous replacements require explicit merchant-account reconciliation. `needs_accounting` rows intentionally remain visible and block further collections; no automatic allocation, refund or accounting-clearance workflow is added.

All provider boundaries here are synthetic. This review does not establish live merchant behavior, real charges, browser acceptance, the full maintained PHP/database matrix, generated/provenance proof acceptance, or a combined integration result. Root owns those separate gates and the normal-merge decision. The shipping-overlap review requested afterward is a separate source receipt, not an extension of this isolated approval.

The JSON companion records complete current hashes, findings closure, exact evidence paths, limitations and evidence hashes. Earlier finance review receipts remain unchanged historical snapshots.
