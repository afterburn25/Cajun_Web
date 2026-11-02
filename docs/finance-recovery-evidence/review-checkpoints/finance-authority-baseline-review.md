# FIN-AUTH-01: independent review of the retained exact84 baseline

**Disposition:** the retained source identity and failure witness support FIN-AUTH-01 within the stated native/synthetic boundary. This is separate evidence for an inherited replacement-authority defect; it does not approve the writer's corrected implementation.

- Exact source: `84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd`.
- Retained witness: `/workspace/scratch/8cc2b4f257ab/finance-recovery-local-evidence/original-auth01`.
- Recorded runtime: PHP8.3.6 / MariaDB10.11.14; exit1; **22 assertions, 9 failures**.
- Reviewer activity: read source/reports and recompute identities; no runtime or database rerun.

## Source identity

All11 source copies match `execution.json`. Seven application/schema copies plus the original native fixture and original boundary helper match Git at exact84 byte-for-byte. The only two additions in this manifest are the authority entry and case helper.

The retained run tree was also compared to exact84: **6009 present tracked blobs match**, with no changed tracked file and no missing `site/`, `foundation/` or `tests/` path. Its only additional files are the two authority helpers. Nonruntime repository documents/tools are omitted from that run-tree subset; this was not presented as a full repository clone.

The fixture loads original setup and helper definitions without executing the old main assertion loop. The original file is unchanged, so the old assertions are preserved, but the22 authority checks must not be reported as a rerun of the original138.

## What the22 assertions show

| Scenario | Checks | Failed | Provider-boundary calls | Replacement creates | Observed result |
| --- | ---: | ---: | ---: | ---: | --- |
| `healthy` | 2 | 0 | 5 | 1 | pending_first_payment |
| `stale_version` | 3 | 2 | 5 | 1 | pending_first_payment |
| `deactivated` | 3 | 0 | 0 | 0 | Exception before I/O |
| `idle_expired` | 3 | 2 | 5 | 1 | pending_first_payment |
| `login_expired` | 3 | 2 | 5 | 1 | pending_first_payment |
| `foreign_session` | 3 | 2 | 5 | 1 | pending_first_payment |
| `before_dispatch_revoked` | 3 | 1 | 5 | 1 | pending_first_payment |
| `after_dispatch_revoked` | 2 | 0 | 5 | 1 | pending_first_payment |

The four invalid-session entry cases each fail two assertions: refusal before provider I/O and preservation of the financial snapshot. Each retains a successor with replacement_count=1. Revocation during predecessor retirement adds the ninth failure: the old contract is correctly retired, but the successor create still occurs. All eight lock/transaction-release checks pass.

The healthy and already-admitted-result controls pass. The deactivated-client case also passes: the seeded nonzero initial invoice triggers an existing active-client check before I/O. That result must not be described as a reproduced deactivation bypass. The final suite should additionally cover deactivation on a zero-down agreement so it exercises the shared current-client gate independently of the initial-invoice check.

## Assertion and boundary semantics

### Actual application bodies

Original boundary helper copies named exact application function bodies with namespace/import declarations only. Replacement reserve/reenroll/finalize and retirement bodies are unmodified; non-overridden helpers resolve to canonical globally loaded application functions.

### Native state and revocation

The authority helper starts another PHP process against the same disposable prefix. The worker performs current staff_owner admission and commits the version/deactivation change under staff_locked + staff_transaction. Parent code checks distinct process/SQL connection and completed commit before proceeding.

### Synthetic boundaries

Gateway mode and ARB execution are synthetic, with matching signed future schedules and seeded initial receipts. Transport log records requests, IDs, transaction state and retained-identity presence. No real network or card operation is represented.

### Before-create revocation

afterOldRetirement runs at terminal predecessor readback before the application calls replacement create. Its committed revocation should stop a new create; exact84 instead reaches one create and binds the successor.

### Already-admitted outcome

postReservation callback runs inside the synthetic create adapter after the call is recorded and before its synthetic result returns. The passing control requires retaining the resulting successor after subsequent revocation, preserving the admitted-outcome boundary.

### Denial assertions

Stale version, idle/max age and foreign session assert both no request with an exception and exact financial snapshot equality. Each fails both assertions; retained successor IDs and replacement_count=1 corroborate actual mutation. Lock-release assertions all pass.

### Expected diagnostics

Eight preserved stderr lines identify financing_offers.php:261. Each of the eight seed cases deliberately disables payment-link readiness while signing a nonzero-initial agreement; that line throws the expected preparation diagnostic. No fixture-exception assertion is present.

## Retained evidence identities

| Artifact | SHA256 |
| --- | --- |
| `execution.json` | `dee478552a6b3f680e5d514ce99ed8105c0b5d5fa5a9557537b692f34e0c3511` |
| `native.json` | `579828778d0b5836d334d254c3528a6a860f729405b936863057f9ba7a55a5d4` |
| `native.stdout.log` | `579828778d0b5836d334d254c3528a6a860f729405b936863057f9ba7a55a5d4` |
| `native.stderr.log` | `a7a80ae887c0f7f9da1624764bd4b08a0654c5e3a70e9aefa7921f852781567d` |

`native.stdout.log` is byte-identical to `native.json`. The failure names and counts match `execution.json`, and the nonzero exit is consistent with the failed assertions. All stderr diagnostics remain preserved.

## Source-copy identities

| Path | SHA256 | Relation to84 |
| --- | --- | --- |
| `site/app/billing/finance_subscription.php` | `eae98b777fb8da6325c9ba92a5ca54c7bbe0eac01a575f01094eb5c3c05c19a9` | Exact match |
| `site/app/billing/finance_authorize_net.php` | `75cd7b5d120084da911ab9810e095e10e1737bdaa99aece87dbba695fe11d00f` | Exact match |
| `site/app/plans/records.php` | `2d9df2440c7ef3d21cea9b6d640006fa5a6545783ef90659c24555f74d04531c` | Exact match |
| `site/app/licenses/finance_installments.php` | `75c34c7c0806d3fd0435c711fa310151667d4510216702c3aad3e69cb98178ae` | Exact match |
| `site/app/licenses/financing.php` | `c384d0303f59fe6c8351af0bc4f72d69f4b5b3065e1e3cc85103bb33e3ce95bb` | Exact match |
| `site/app/clients/accounts.php` | `03ca51e11d3ce125f2eb203685a82c109dbe3638f37c84cad953da171c665c1e` | Exact match |
| `site/database/schema/license_finance_gateway_schema.php` | `9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25` | Exact match |
| `tests/finance_replacement_payoff_native.php` | `eaae47960f586f6b9aef01d744e56e05bfe4b5ac78350d620a361e597d928464` | Exact match |
| `tests/finance_replacement_payoff_boundaries.php` | `5cc87de087a46a43d6acbd8525ed667b4b4ae68d643be7e45b7c70f573c30514` | Exact match |
| `tests/finance_replacement_payoff_authority_native.php` | `044359f856226e5a31df3891d3f0029d73a4c7e21e1ef3af817112ec47cdcacb` | New authority fixture |
| `tests/finance_replacement_payoff_authority_cases.php` | `2844174dadd4b7488acca4e8eedc337291e0f09af10f103a0f1a84eadb33f9d7` | New authority fixture |

## Next independent-review boundary

The writer reports adding the shared current-client check before preflight projection, inside reservation and again before replacement create. Those corrected moving bytes were not inspected here. The next review must bind frozen application/schema/native/provenance source and its evidence, including refusal without financial mutation before admission and durable handling of already-admitted outcomes.

The original finance review and early correctness snapshot remain unchanged. This is a distinct FIN-AUTH-01 baseline receipt.

## Limitations

- This validates retained baseline identity and assertion/evidence semantics, not corrected implementation behavior. Moving corrected files were not inspected.
- No application, PHP/native test, database, provider, service, browser, network, remote-write or Actions operation was executed by this reviewer.
- Only PHP8.3.6 / MariaDB10.11.14 is recorded by this baseline. It is not the maintained full runtime matrix, installed/browser acceptance or live-provider evidence.
- The 22 assertions are a separate authority run. It reuses original setup/helper source but does not execute the original 138 assertion suite; those assertions remain intact in the exact84 fixture.
- The owner-authorized writer uses actual staff_owner, mutex and transaction with direct SQL auth changes. It does not invoke studio_client_save or the browser/HTTP owner flow.
- Foreign-session and age cases directly exercise internal admission using deliberately invalid session state; they do not independently demonstrate a cross-tenant HTTP route exploit.
- Complete financial before/after snapshots are compared inside the fixture but not emitted in native.json. The recorded retained ID/replacement_count and transport trace independently expose the unsafe replacements; other preservation claims remain source-inspected producer assertions.
- Deactivation is denied in this baseline through the nonzero initial-payment invoice active-client guard. It is a passing control, not a reproduced deactivation bypass. A zero-down deactivation case was requested for the final corrected suite.
- Source identity in the retained full runtime tree was verified at review time. The execution manifest independently pins 11 retained source copies; no claim of a separately sealed execution attestation is made.
- Original primary-finance-review.md/json and its three-blocker snapshot were not modified. FIN-AUTH-01 remains a separate subsequent finding and correction scope.
