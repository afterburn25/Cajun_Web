# Recurring finance fixture correction — 2026-11-02

The current finance component at `3ccd7c29e629525a9c586d1710bcae06bb2ac5bc`
adds required gateway and durable replacement-admission checks. Its inherited
recurring fixture still attempted replacement with a disabled gateway and then
called finalization without recorded dispatch or a known successor.

## Correction and retained behavior

Only `tests/license_financing_native_cases.php` changes its runtime behavior.
Three insertions add 51 lines. Every original line and assertion remains in
its original order. The fixture saves synthetic sealed sandbox credentials
through the real owner service, executes the real reservation, dispatch and
observation boundaries, and retains the original finalization and assertions.
It restores the disabled gateway state and removes only its own synthetic
sandbox credential row before the existing payoff scenarios.

Eight additional checks cover the initial disabled state, disabled-admission
refusal with no financial/audit mutation, refusal before dispatch and before
observation with complete retention, positive dispatch, and restored gateway
state. No production configuration, application function, workflow, timeout,
test entry, or original assertion changes.

## Original hosted failures

The following current-head workflows failed on the same inherited recurring
fixture. All four PHP/database jobs failed in each family. Logs inspected from
all three MySQL families and the MariaDB recurring job report the disabled
gateway exception at `license_financing_native_cases.php:401`.

- [Recurring run 41382653990](https://github.com/afterburn25/Cajun_Web/actions/runs/41382653990), jobs 125076823662 and 125076823712.
- [Renewal agreement run 41382653792](https://github.com/afterburn25/Cajun_Web/actions/runs/41382653792), job 125076596019.
- [Paid renewal dispatch run 41382653874](https://github.com/afterburn25/Cajun_Web/actions/runs/41382653874), job 125076597565.

Their reported 238 total entries comprise 237 passes and one unexpected-failure
entry. The local unchanged reproduction below retains the same result.

## Local execution and limits

| Stage | Recorded result |
| --- | --- |
| [Unchanged component](finance-recovery-evidence/recurring-fixture-20261102/baseline/execution.json) | Exit 1; 237 passing entries and the disabled-gateway failure. |
| [First corrected harness attempt](finance-recovery-evidence/recurring-fixture-20261102/corrected01/execution.json) | Exit 1 before meaningful assertions: the runner reused retained baseline tables. This failure is preserved. |
| [Fresh namespace, current fixture](finance-recovery-evidence/recurring-fixture-20261102/corrected02/execution.json) | Exit 0; all 648 checks passed, comprising the original 640 plus eight additions. |

This is PHP 8.3.6 / MariaDB 10.11.14 local native SQL evidence in a disposable
database. `curl_exec` was disabled for the parent process; the fixture requires
no adapter calls. This does not replace hosted PHP 8.2/8.4 × MySQL 8.4/MariaDB
10.11 acceptance, combined-source acceptance, browser checks, or release gates.
Each execution preserves its exact source hashes, report, stdout and stderr.
The shipping tree has no differences from the published finance component.

## Finite source proof

The source receipt adds exactly one reversible fixture path and a finite list
of these evidence files. Applying the three inverses reconstructs the complete
original fixture byte-for-byte. The earlier receipt and verifier are retained
losslessly as `parent-source.json` and `parent-verifier.py`. Every earlier
application, test and evidence identity remains unchanged. The existing proof
still rejects unrelated changed paths, altered assertions, missing evidence,
unsafe modes and noncanonical shipping inventory. Its added fixture checks
bind the 648-check report to the actual current fixture and application files.

The implementation agent's checks are self-review. Independent review and
current hosted results are separate required evidence before integration.
