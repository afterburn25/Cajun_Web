# Finance recovery: early source correctness review

**Disposition: correction required before freeze.** The writer confirmed three concrete admission gaps in this intermediate source. Root also authorized a narrow correction for the inherited FIN-AUTH-01 replacement-session gap. No current or frozen implementation is approved by this receipt.

Reference: `84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd`. Snapshot: `/workspace/scratch/8cc2b4f257ab/finance-recovery-correctness-review-early01` captured `2026-11-02T01:35:07.651461+00:00`.

The application snapshot contains 11 changed files and the unchanged gateway schema dependency, with all hashes retained in `manifest.json`. Current writer edits are outside this receipt's approval scope.

## Findings

### FIN-RECOVERY-EARLY-01 — A retained needs_accounting collection does not block a new installment charge or later automatic-contract admission.

**Status:** Writer confirmed; correction and final evidence pending.

**Classification:** New correction-layer missing admission fence.

**Source:** `finance_recovery.php:231–246`, `finance_recovery.php:249–274`, `finance_subscription.php:785–805`, `finance_installments.php:163–168`.

**Concrete trace:**

1. Owner partial receipt creates a retirement hold; verified cancellation moves the subscription to billing_suspended.
2. A late full ARB transaction is retained as needs_accounting without allocating it to the invoice. The partial ledger balance remains.
3. The balance-changed settlement branch retires the same provider identity and returns to billing_suspended.
4. The scheduler can create a catch-up request. charge_prepare calls charge_guard, which accepts billing_suspended and does not query collection_reviews. invoice_clear also omits that table.
5. A new charge may therefore be admitted before the already verified external collection is accounted for.

**Required invariant:** Every new charge and initial/replacement automatic contract must refuse an affected unresolved collection obligation under the same admission transaction. Keep real external receipts recordable and preserve the unallocated outcome; do not fabricate allocation or erase the review.

**Required cases:**

- Late full ARB collection after partial owner receipt, successful retirement, then fresh and previously prepared payment-link admission: no new charge attempt/provider call.
- Remaining-contract admission with affected needs_accounting period: no replacement reservation/create.
- Existing legitimate post-retirement payment positive control with no collection review remains admitted.

### FIN-RECOVERY-EARLY-02 — Supplying a captured gateway context bypasses current mode/enable/production gates in the real adapter.

**Status:** Writer confirmed; correction and final evidence pending.

**Classification:** New correction-layer current gateway permission regression.

**Source:** `finance_authorize_net.php:14–36`, `finance_subscription.php:497–509`, `finance_recovery.php:72–96`, `tests/finance_replacement_payoff_recovery_boundaries.php:24–25 (observed source)`.

**Concrete trace:**

1. Reenrollment reads current settings before predecessor retirement.
2. Predecessor I/O runs outside the admission lock; an owner can finish disabling or changing gateway mode before replacement dispatch.
3. recovery_dispatch compares the retained attempt to captured settings only.
4. The following create passes the captured context, and execute uses context directly instead of calling current settings.
5. The observed synthetic execute unconditionally called settings even with context, unlike the real adapter, so those synthetic disabled tests could not prove the real adapter gate.

**Required invariant:** Recheck current gateway dispatch permission and exact account/environment binding before admitting a new create and in the real adapter context path. Account pinning must not bypass current activation policy. Preserve unsent recovery evidence on refusal.

**Required cases:**

- Completed disable, mode change and production approval removal between predecessor retirement and dispatch: no create transport.
- Actual execute body with synthetic curl/transport: supplied stale context cannot bypass disabled/mode/approval checks.
- Same-account permitted credential rotation positive control and explicit context identity mismatch negatives.

### FIN-RECOVERY-EARLY-03 — A second replacement can send predecessor requests under an account different from the first replacement's retained account.

**Status:** Writer confirmed; correction and final evidence pending.

**Classification:** New recovery-backed predecessor account fence missing.

**Source:** `finance_subscription.php:341–356`, `finance_subscription.php:497–503`, `finance_recovery.php:59–69`, `finance_recovery.php:132–139`.

**Concrete trace:**

1. First replacement is bound under account A; its closed recovery attempt retains A.
2. After later billing suspension, current settings change to B.
3. reenroll obtains B and recovery_reserve writes a new B reservation without validating the closed attempt for the old gateway subscription.
4. Predecessor retirement passes B to execute; the helper that enforces retained subscription settings is not used on this path.

**Required invariant:** Consult an existing recovery-backed predecessor binding before any new reservation or provider request. Same-account credential rotation can remain supported. Do not infer a merchant identity for legacy subscriptions that lack one.

**Required cases:**

- Two successive replacements with a completed different-account or environment change: deny before reservation and before transport.
- Same-account revision change retains original evidence and allows legitimate recovery.

**Bounded claim:** Source proves request credentials can differ from retained original account. It does not establish cross-merchant ID collision or actual wrong-customer cancellation; no provider semantics or outcomes were tested.

### FIN-AUTH-01 — Replacement reservation and pre-create admission use a prior client ID without rechecking the current client session/account under the shared lock.

**Status:** Root authorized narrow correction; exact84 origin verified; final fix/evidence pending.

**Classification:** Inherited exact84 replacement admission current-client authority gap.

**Source:** `exact84 finance_subscription.php:328 replacement_reserve`, `exact84 finance_subscription.php:452 reenroll_opaque`, `exact84 portal/controllers/router.php:62–63`, `exact84 portal/controllers/actions.php:50–54`, `exact84 app/plans/records.php:38–43`.

**Concrete trace:**

1. Portal router obtains client=studio_client(), then dispatches the action using its ID.
2. A completed studio_client_save between that read and financial admission changes auth_version (and may deactivate the account) under staff_locked.
3. replacement_reserve checks the offer's client ID and current financial contract, but neither it nor reenroll rechecks current session auth_version/age.
4. Initial enrollment already calls service_plans_current_client under its reservation transaction; replacement omits that boundary.
5. For a valid otherwise eligible signed agreement, current auth-version revocation therefore is not an admission condition for replacement provider I/O.

**Required invariant:** Use existing service_plans_current_client inside replacement reservation and pre-create authority admission. Refuse revoked/deactivated/expired sessions before new provider effects; do not require continued client authority merely to retain or reconcile an external outcome that was already admitted.

**Required cases:**

- Real shared helper with stale auth_version, deactivated client, idle timeout and maximum session age before reservation: no reservation/provider call.
- Completed revocation or disable after reservation but before create dispatch: no new create; retain recoverable unsent predecessor state.
- Valid current client control; already dispatched lost/late results remain durably observable despite subsequent revocation.

**Bounded claim:** Read-only source finding; no native reproduction executed by reviewer. Root separately authorized the bounded writer correction. This finding is appended here and does not rewrite the original finance review.

## FIN-AUTH-01 exact84 origin

The target file at84 has Git blob `74cae87cc76aa8f6f9663f4159786832d8a02229` and SHA256 `eae98b777fb8da6325c9ba92a5ca54c7bbe0eac01a575f01094eb5c3c05c19a9`. Its replacement entry/reservation functions omit the fresh-client call present in initial enrollment. The current-client helper checks active state, auth version, idle age and maximum age; the account mutation rotates auth version while holding the same staff lock.

| Exact84 function | Source start | SHA256 of raw function range |
| --- | ---: | --- |
| `license_finance_subscription_enrollment_offer` | 206 | `765217900b9aa4f658e107882ef2b01a2c4206fbd2f2c44dce76585b96f61406` |
| `license_finance_subscription_reserve` | 247 | `d7b25ef74ade4b0cef8f98c37d7866e8f20d47efcd4808fa7fb7b7e10dd81fb9` |
| `license_finance_subscription_replacement_reserve` | 328 | `c9ed52c826621ec12a93756cc06d042cd7714038e53ea2517b12b1a897c08cab` |
| `license_finance_subscription_reenroll_opaque` | 452 | `d46505e5e6ac0b1a03de6a5a54f98fd6b9b1b3febd38798fa56a0dd46a741cd2` |

These are source identities and a control-flow finding, not a claim that a reviewer executed a reproduction. Final correction binding and native negative/positive evidence remain pending.

## Other reviewed invariants

- Durable replacement reservation stores a signed remaining contract, predecessor identity, agreement hash and nonsecret provider context in the same transaction as replacing status.
- Dispatch state commits before ARB create; unknown outcomes stay open and block ordinary second replacement reservation. A prepared unsent attempt can be closed only after predecessor retirement/readback and locked identity recheck.
- Owner receipt and exact retirement hold/audit share the ledger transaction. A failure of that hold rolls back the receipt; later retirement I/O is outside the receipt transaction.
- Known replacement IDs are recorded in a separate transaction before later readback/finalization; finalization binds successor and predecessor history atomically and retains obsolete candidates for retirement.
- Retained collection review has unique external transaction identity, exact amount/period/account/contract consistency checks and audit, without creating a duplicate invoice receipt.
- Scheduler includes open replacement attempts after payoff and retries known successor/retirement paths; unresolved unknown successors remain visible as partial health.
- Recovery migration uses a separate v1 marker beside gateway v3, explicit owner/setup gates, pending marker before DDL, strict schema/index validation and refusal of unmarked/populated or incomplete storage. It does not invent legacy replacement contracts.
- Admin view exposes unresolved attempts and unallocated collections with existing action/CSRF and owner reconciliation route; this was source review only.

## Application source identities

| Path | SHA256 |
| --- | --- |
| `site/app/billing/finance_recovery.php` | `07fc039980eda8cd190f61467f98f03b184096344700945d283b8c55eaf496fb` |
| `site/database/schema/license_finance_recovery_schema.php` | `ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f` |
| `site/database/migrations/license_finance_recovery_migrate.php` | `4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e` |
| `site/app/billing/finance_subscription.php` | `d0a130a57b7edef2f7c5782cf19389b727bbc2912d506eba9ae77b0966b279f6` |
| `site/app/billing/finance_authorize_net.php` | `27091dfeb30fbe7ef2f4c4292b388370747afeaa282583b1057ae90f37a33d17` |
| `site/app/billing/ledger.php` | `fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989` |
| `site/app/billing/finance_charge.php` | `b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8` |
| `site/app/licenses/financing.php` | `964f62a8146552555fbd194ed45a71ab988fdbc71ae00f5044460cf05dce2f99` |
| `site/app/licenses/finance_installments.php` | `fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c` |
| `site/database/migrations/license_finance_gateway_migrate.php` | `df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9` |
| `site/database/schema/license_finance_gateway_schema.php` | `9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25` |
| `site/admin/views/studio/licenses.php` | `897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc` |

## Evidence boundaries

The JSON companion records selected unchanged dependency contexts and captured mutable fixture contexts. Three existing late-collection cases inspected in the writer fixture stopped after retention, replay and retirement; they did not attempt another charge after that point. The observed synthetic execute also refreshed settings regardless of supplied context, unlike the real adapter snapshot. The writer has acknowledged both coverage gaps and will add source-faithful cases.

No historical review receipt has been changed. This early review and its frozen snapshot should remain available when the eventual corrected source is reviewed.

## Limitations

- Intermediate application snapshot only; writer files remain mutable and subsequent fixes have not been reviewed here.
- No tests, PHP/runtime, database, services, providers, network, Actions or remote writes were performed by this reviewer.
- No source/test/Git state mutation; only outside-Git snapshot/review artifacts were written.
- Selected fixture clauses expose boundaries and missing cases; no assertion count or writer pass is treated as final correctness approval.
- Historical finance-account provenance for legacy subscriptions remains separate OBS-03; no account binding is fabricated.
- This is not whole PR317, integration, release, live-provider or browser approval.
