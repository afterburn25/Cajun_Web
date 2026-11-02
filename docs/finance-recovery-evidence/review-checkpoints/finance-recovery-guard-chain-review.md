# Finance recovery correction: exact84 source/provenance guard-chain inventory

**Recommendation:** attach FIN-01/FIN-02 as a dedicated, strictly scoped reversible correction layer anchored to exact84. Keep historical receipts and assertions intact; project predecessor bytes only into historical source comparisons. Run corrected runtime fixtures against actual corrected application bytes.

This is a read-only guard-chain analysis for the writer before source freeze. It does not approve the implementation or supersede the independent finance review.

- Exact reference: `84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd`.
- Source checkout: `/workspace/scratch/8cc2b4f257ab/Cajun_Web_primary_review` (clean and unchanged).
- Writer checkout: `Cajun_Web_finance_recovery_fix`; its mutable application bytes were not reviewed for correctness here.

## Active guard chain

1. `tests/combined_runtime_repair.py` calls the proposals layer when its receipt exists. The proposals layer calls `tests/tickets_integration_contract.py`. The ticket guard enforces the exact BASE→HEAD shipping path set and exact output/predecessor hashes; paths not in its 42-path receipt are implicitly unchanged. None of the requested nine finance paths is in that receipt. A new finance shipping delta therefore requires a separate validated outer layer. Do not append finance paths to the old ticket/proposal receipts.

2. `tests/acceptance_artifacts_test.py` runs strict provider fixture validation, then `provider_browser_fixture_provenance.projected_validation()` for the unchanged combined and standalone receipts. The provider verifier pins its own bytes and a fixed fixture PATHS set, checks exact before/after hashes, and requires a unique complete inverse/forward round trip. This is the correct kind of boundary for a narrowly reviewed fixture correction; it is not a runtime fixture loader.

3. The old `unchanged_test_sha256` map contains no finance/financing fixture entry. The payoff fixtures inspected here are also outside provider PATHS and the old combined/standalone repair file sets. Their source changes still need the new finance receipt, original assertion retention and current runtime evidence, but there is no reason to widen those old lists merely in anticipation.

4. `tests/license_finance_source_contract.php` is a current behavioral/source-shape gate, not a historical whole-file hash. It explicitly expects gateway v3, additive v1/v2→v3 upgrades, pending-preparation markers, finite ARB, current production gates, signed terms, owner reconciliation and scheduler wiring. New recovery v1 can remain separate while preserving these original assertions.

5. `tools/build.py` recomputes current installation digests. `tools/legacy-release-files.json` records old releases: its deployed1.26.0 entry matches exact84 for the eight requested finance application/schema files, while the admin license view has later edits. Refresh the current generated inventory after freeze; preserve the old release inventory.

## Requested application identities and retained pins

| Exact84 path | SHA256 | Relevant retained source evidence |
| --- | --- | --- |
| `site/app/billing/finance_subscription.php` | `eae98b777fb8da6325c9ba92a5ca54c7bbe0eac01a575f01094eb5c3c05c19a9` | Seven older finance/reauth receipts pin predecessor versions, not the current whole file. Original finance review supplies function equivalence. Deployed1.26.0 inventory and fresh original01 copies match84. |
| `site/app/billing/finance_authorize_net.php` | `75cd7b5d120084da911ab9810e095e10e1737bdaa99aece87dbba695fe11d00f` | finance-payoff-retirement.json matches exact84; older owner-reauth receipt differs. Deployed1.26.0 and original01 match84. |
| `site/app/billing/ledger.php` | `d2e144b11747ad2ea51e3a6c425b75dd3133f54f58e14a210110dc1ff0f1714a` | No direct docs path/hash entry found in bounded scan. Deployed1.26.0 and original01 pin exact84; native boundaries copy ledger_receive. |
| `site/app/licenses/financing.php` | `c384d0303f59fe6c8351af0bc4f72d69f4b5b3065e1e3cc85103bb33e3ce95bb` | finance-payoff-retirement.json matches exact84; initial-sync predecessor differs. Deployed1.26.0 and original01 match84. |
| `site/app/licenses/finance_installments.php` | `75c34c7c0806d3fd0435c711fa310151667d4510216702c3aad3e69cb98178ae` | finance-enrollment-receipts.json matches exact84; payoff-retirement predecessor differs. Deployed1.26.0 and original01 match84. |
| `site/app/billing/finance_charge.php` | `538b57715bd909d3a06910e22ed4d94eb16bb9b45926bb3e67edc71079b3c003` | finance-sandbox-profile-review.json matches84. Older charge/capture receipts in original finance map differ. Deployed1.26.0 matches84; not in the early original01/combined-first copied subset. |
| `site/database/migrations/license_finance_gateway_migrate.php` | `c3661c16c4c6e25fa567c236820764409a610c08be4ac4b671e87646d85520d2` | Deployed1.26.0 and original01 match84; explicit current v3 source/native migration assertions are the behavioral guard. No direct docs path/hash entry found. |
| `site/database/schema/license_finance_gateway_schema.php` | `9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25` | Deployed1.26.0 and original01 match84; canonical DDL consumed by native fixtures. No direct docs path/hash entry found. |
| `site/admin/views/studio/licenses.php` | `ecb1ed274ab43b72a442f288d107e4def377b7f3cdf87db8010d112bf17e055a` | LICENSING_WORKSPACE_SOURCE.json after blob 9bd0b6bb05933e1306ccfd2004e2f988e7df6455 matches84; ADMIN_RELEASE_FIXES_SOURCE is the earlier layer. Older finance receipt hash differs. |

These pins have different roles. A matching historical report is bounded evidence about its recorded execution. A matching deployed inventory recognizes historical files during upgrade. Neither is proof that a new correction behaves correctly. The JSON companion records each discovered document/hash match or mismatch.

## Native fixture boundaries that must survive

| Fixture group | Retained boundary |
| --- | --- | --- |
| Replacement/payoff native | The main fixture loads canonical application source and native schema. Its namespace helper extracts named functions unchanged from subscription, adapter retirement, invoice sync, scheduler and ledger. Transport/mode are synthetic. New recovery helpers must be explicitly loaded or copied so dependency resolution does not silently fall back to an unintended implementation. |
| Initial receipts and payoff retirement | Keep separate-process receipt commits, rollback/audit failures, current owner/role checks, cross-customer refusal and delayed snapshot checks. The revoked-owner exit remains isolated so it cannot erase other reports. |
| One-time charge | Charge-attempt bindings are synthetic orchestration; charge-response/capture fixtures use canonical native charge/attempt storage with selected synthetic owner/ledger/provider boundaries. They must continue testing actual current function bodies when finance_charge.php changes. |
| Reconcile native | Actual row/reconcile and audit/staff function bodies plus canonical schema, native mutex/transactions and separate competing commits; parent/owner/gateway boundaries are synthetic. Keep exact scope disclosure. |
| Core migration/release | license_financing_native_cases.php retains gateway v1→v3 and v2→v3 with original subscription identity and interrupted setup assertions. release_finance_native.php executes the full cases and hashes populated retained tables across repeated preparation. Repeated preparation is not installed file-upgrade acceptance. |

### Fresh reproduction evidence inspected, without execution

| Retained run | Checks | Failed | Original first138 | Runtime recorded by writer |
| --- | ---: | ---: | --- | --- |
| `original01` | 217 | 20 | All passing | PHP 8.3.6; 10.11.14-MariaDB-0ubuntu0.24.04.1 |
| `combined-first` | 217 | 0 | All passing | PHP 8.3.6; 10.11.14-MariaDB-0ubuntu0.24.04.1 |

Every source copy named in each execution manifest hashes exactly to that manifest. All original01 application copies equal exact84. All217 check names and their order are identical between original01 and combined-first. The original01 main fixture differs from84 only by an additive recovery-case require and call; its original boundary helper is byte-identical. These observations establish retained evidence identity, not final application approval.

The reports are retained at:

- `/workspace/scratch/8cc2b4f257ab/finance-recovery-local-evidence/original01/native.json` and `execution.json`.
- `/workspace/scratch/8cc2b4f257ab/finance-recovery-local-evidence/combined-first/native.json` and `execution.json`.

combined-first is an intermediate217/217 witness. Later safety/migration additions and any changed finance_charge/admin view bytes require fresh final source-bound evidence.

## Exact reversible correction attachment points

### A1 — New dedicated FIN proof and receipt, proposed tests/finance_recovery_provenance.py + docs/FINANCE_RECOVERY_SOURCE.json

Anchor exact84 commit and tree; fixed explicit changed shipping and fixture/new-file scope; exact before/after SHA256 and byte counts; pinned verifier; exact reversible edits and new-file absence; reject missing, extra, overlapping, unchanged and unreviewed edits. These names are proposed, not existing reviewed files.

### A2 — tests/tickets_integration_contract.py validate(root), before BASE→HEAD comparison/current file reads

After validating the whole current FIN layer, supply source_revision=exact84 and predecessor_bytes only for historical comparison. Keep the original ticket/proposal/combined receipt sets, hashes, BASE IDs and VERSION policy intact. Verify current corrected source first; merely returning84 would bypass the required check.

### A3 — tests/provider_browser_fixture_provenance.py receipt/validate/projected_validation historical boundaries, only if actual overlapping pinned files change

An adapted verifier must self-invert through a separately pinned correction before its old verifier hash is checked. In projected_validation, inverse current correction bytes before old provider/combined/standalone inverses. Never supply predecessor fixtures to actual PHP/runtime tests. Existing FIN payoff helpers are not in provider PATHS or the old three repair receipts, so do not enlarge those sets preemptively.

### A4 — tests/finance_replacement_payoff_native.php and dedicated new recovery cases/boundaries

Keep original assertion statements and order; attach new cases through explicit require/call hooks and record inverses. Extract real changed functions and new dependencies without editing their bodies; record all function/module/schema hashes and exact synthetic boundaries. Retain original01 failure report and source copies unchanged.

### A5 — tests/license_financing_native_cases.php; tests/license_finance_source_contract.php; new recovery migration/safety suite

Preserve gateway version3 and original v1/v2→v3/readiness/interrupted-setup assertions. Add recovery version1 and its own marker/owner checks; add tests for empty/resumable setup, unmarked/damaged/populated history, retention and ambiguous legacy rows. Do not fabricate pre-create contracts for old ambiguous subscriptions.

### A6 — site/install/inventory.generated.php and final source/evidence manifest

After source freeze, regenerate current inventory using retained builder and prove it exactly matches current files. Preserve tools/legacy-release-files.json and old deployed1.26.0 hashes. Runtime evidence must bind the frozen commit/tree, full delta and helper/schema/source files; combined-first is an intermediate witness only.

### A7 — Future combined software+finance candidate

Keep each standalone correction anchored independently to84. Shared ticket/provider hooks and installer inventory need an explicit reviewed outer composition with exact constituent identities and conflict resolution. Neither proof may accept the other by widening a path allowlist or guessing a compatible hash.

## Coordination with the software correction

The parent reviewer reported an independent software_authority_provenance.py design anchored to exact84, with strict shipping/native scope, pinned verifier, `source_revision`/`predecessor_bytes` ticket hooks, and provider-verifier/native inverses only for historical checks. The finance standalone correction should keep the same reference independently. Shared hooks and generated inventory must be resolved in an explicit later composition; a verifier must not accept another correction merely because it is also based on84.

Useful negative proof cases are: altered current bytes, altered predecessor hash, missing or extra path, omitted/ambiguous edit, changed base/verifier, preexisting supposed new file, stale untracked/uncommitted change, unknown overlap and attempted use of predecessor fixtures as current runtime evidence.

## Evidence inventory

The JSON companion records complete Git blob/SHA256/byte identities for all9 requested application files,32 guard/receipt/build/workflow sources and22 native fixture boundaries, plus exact retained runtime-copy hashes. No source, test or historical receipt was edited.

## Limitations

- This is source/provenance guard-chain inventory, not FIN-01/FIN-02 implementation approval; writer application bytes remain mutable.
- No tests, PHP programs, database, service, provider, payment, mail, browser, network, remote write or Actions polling executed.
- JSON receipt hashes establish source identity or historical observations only. No historical pass is promoted to complete independent current review.
- The report scan identifies explicit path-to-hash and path-record entries under docs, plus the original finance review map; it is bounded and does not decode every archived artifact or claim exhaustive remote evidence discovery.
- Current combined-first evidence does not cover later safety/migration/source edits automatically; a fresh frozen-source manifest and required regression evidence are still needed.
- Software-layer hook names and design are coordination from parent, not independently reviewed final software proof bytes.

No current or future merge/release approval is granted by this inventory. The independent FIN-01/FIN-02 source review starts only after the writer identifies frozen application and fixture bytes.
