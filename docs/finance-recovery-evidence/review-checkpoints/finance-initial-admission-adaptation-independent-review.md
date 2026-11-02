# Independent review: initial admission correction and historical result fixture

**Bounded result: the proposed adaptation has no source-review blocker, and the unchanged independent native witness passes 11/11 on the corrected application file.** This is not approval of the full FIN tree, PR317, a merge or a release. Writer acceptance on its final frozen tree remains separate.

## Exact source

Parent is `84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd`. The reviewed four-file proposal is `finance-recovery-local-evidence/initial-admission-adaptation01`, manifest SHA-256 `c787288a6250fc064323048254100f5dfe80d23745a4011f9ab46c3faf92fc1e`. All four source hashes and their three existing predecessors were independently verified.

| Path | SHA-256 after adaptation |
|---|---|
| `site/app/billing/finance_subscription.php` | `3b7c779fdcf4a1e3a9f6d67756cbaa10b20c7b924f4191ebd0edadf12a3a483b` |
| `tests/finance_initial_receipts_native.php` | `6d05dc5f3a09b2e8dcd7afcfbd47f805533d4b576cc52543c1cf7fb2581140d4` |
| `tests/finance_replacement_payoff_recovery_cases.php` | `5f8b4b1299b0088fdc98b7a2e2b52d79533115cbb8bd85d730676b4de9e0e740` |
| `tests/finance_replacement_payoff_initial_admission.php` | `087a779de88e3b6a763bf8b4a2f6fcad14ccc1905f667958cf64f040012e9a07` |

## Application behavior

The added `license_finance_recovery_invoice_clear` call runs for each issued covered period inside `license_finance_subscription_enrollment_offer(..., lock=true)`. `license_finance_subscription_reserve` invokes it under the existing staff mutex and SQL transaction. Processing, uncertain and review one-time attempts therefore refuse the reservation before any ARB create. The existing generic unresolved-attempt and separate accounting-review guards are reused.

The current finalizer also invokes the locked enrollment check. A candidate already created before admission became unsafe is retained, marked for retirement, and processed by the current retirement path. The correction does not turn a late provider result into permission for another create, nor discard the earlier payment outcome.

## Four historical-result fixture entries

Only the opaque/profile × before/during-readback `prepared_gateway` entries are adapted. Actual charge preparation and the separate native charge-commit writer remain current. The fixture explicitly inserts the same canonical historical `creating` reservation shape and an already-existing synthetic provider candidate, then calls current owner reconciliation and current finalization. No old application body or weakened guard is substituted. Other current initial callers and fault cases retain their original entry path.

The old timing labels are preserved for continuity, while `entry_scope` and the report scope identify these four as historical admitted-result recoveries. All 13 existing assertion call sites plus the assertion helper signature are byte-identical; a broad regex count of 14 includes the declaration and must not be described as 14 distinct assertion sites. The runtime suite's retained 358 checks require its separate execution result.

The proposed new module has 26 checks covering both actual opaque and profile callers against processing, uncertain and review attempts, declined controls, and recovery of an explicit historical candidate while the separate charge remains uncertain. Those source assertions were inspected, but their runtime result was pending when this receipt was written.

## Independent native verification

The original valid witness is preserved at `finance-initial-unresolved-native03`: 11 checks, 2 failures. Its frozen04 application admitted a new recurring contract over an unresolved period charge. Invalid setup runs `native01` and `native02` remain separate and are not counted as reproduced application failures.

The unchanged witness is rerun at `finance-initial-unresolved-native04`. Exactly one source file differs between its captured source map and native03: `site/app/billing/finance_subscription.php`, containing the two-line admission correction. The probe and original fixture boundary are byte-identical. All captured source hashes were verified.

- **11/11 passed**, PHP 8.3.6 / MariaDB 10.11.14, 6.173 seconds.
- The uncertain attempt causes a specific unresolved-outcome exception, zero provider calls, no subscription reservation, identical financial snapshots, and an unchanged uncertain attempt.
- The explicitly declined control creates exactly one subscription, retains its declined attempt, and invents no receipt.
- SQL transaction and native mutex are released. The two expected seed diagnostics at `financing_offers.php:261` account for all 134 stderr bytes.
- Only the reviewer-owned loopback database on port 33317 was restarted; it was then shut down cleanly. Its lifecycle receipt and server log are retained. No other runtime or provider was accessed.

## Remaining gate

The writer's final frozen application/fixture source and the relevant main/native compatibility outcomes must still be bound and reviewed. Root owns the independent provenance proof and overall integration decision. This receipt establishes the narrow source adaptation and independently confirms closure of the reproduced initial-admission defect on the stated application bytes.

The JSON companion contains full evidence paths and SHA-256 hashes.
