## 2026-10-28 — preserve payments during initial and replacement enrollment

Independent `codex/finance-replacement-ledger-guard` from #300 `07fa7caf` prevents partial or nonconsecutive paid installments from being billed again by a newly created full ARB. Initial and replacement finalizers retain obsolete known candidates for verified cancellation; lifecycle/refund/void/settlement paths preserve pending retirement. Final PHP/database matrix passes 6,136 native executions and 306 focused executions. Initial cases include 30 actual separate-process payment commits per lane. Original replacement 37/230, pending-state 11/22, initial 6/44 and finalizer-rollback 4/22 failures remain retained. Hosted checks and independent review are pending; unknown replacement outcomes, established-ARB receipt audit, final combined acceptance, sandbox and main integration remain open. [Review and evidence](CODEX_FINANCE_ENROLLMENT_RECEIPTS.md).

## 2026-10-28 — retain and retire a replacement created during payoff

Independent `codex/finance-replacement-payoff-guard`, based on frozen #299 `fa404e59`, retains the known new gateway ID/history and verifies its retirement when payoff arrives during replacement creation or first readback. It also repairs the shared payoff reader's transaction requirement for ordinary unpaid owner reconciliation. Parent fails 40/138; the corrected PHP/database matrix passes 1,896 native and 272 focused executions. Earlier failures remain retained. Hosted exact-head and independent review are pending; final combined acceptance, unknown-create/partial-receipt audit, sandbox credentials and main integration remain gates. [Review and evidence](CODEX_FINANCE_REPLACEMENT_PAYOFF.md).

## 2026-10-28 — retire recurring billing after verified financing payoff

Independent `codex/finance-payoff-subscription-retirement`, based on frozen #298 `4405433d`, closes the post-reservation/manual-payoff billing gap. It retains the gateway identity before cancellation, requires exact contract and terminal readback, preserves uncertainty for owner/scheduler reconciliation, and never rolls back payment or permanent payoff. Parent fails 57/270; corrected native PHP/database matrix passes 3,132 total executions (1,344 new), plus 272 focused checks. Original failed probes and the isolated-owner test-harness correction are retained. Hosted exact-head and independent approval remain required; no main merge or release. [Review and evidence](CODEX_FINANCE_PAYOFF_RETIREMENT.md).

## 2026-10-28 — initial financing enrollment rechecks authoritative payoff

Independent review of #297 identified stale projected state after a committed payoff and an unchecked profile enrollment path. Separate `codex/finance-enrollment-payoff-guard`, based on frozen `89ecee07`, centralizes reservation-time signed agreement/customer/schedule/initial-payment and installment-ledger admission. Parent fails 138/240 native assertions; corrected source passes 240 new plus 207 existing assertions per PHP/database lane (1,788 executions across four lanes). Valid sandbox enrollment retains exact current charge/attempt proof without fulfillment. Permanent payoff, charge, replacement and production gates remain unchanged. Hosted and independent review gates remain open; no main merge or release. [Review and evidence](CODEX_FINANCE_ENROLLMENT_PAYOFF_GUARD.md).

# Software licenses, purchases and unified client portal — implementation contract

<<<<<<< HEAD
## Native financing reconciliation audit correction — 2026-10-28

This independent correction stacks on #287 (`4f368547`) and makes both direct owner reconciliation mutations atomic with their licensing audit event. Native trigger rejection reproduces two committed-state failures per PHP/database combination on the parent. The fix passes 75 assertions on PHP 8.2.35/8.4.25 × MariaDB 10.11.19/MySQL 8.4.13 (300 executions); the snapshot fences, real account mutex, owner reauthentication and provider-read boundary remain intact. A separate PHP writer verifies preservation of concurrent subscription state. The existing native workflow now runs and retains the regression. [Source, exact scope and retained first failures](CODEX_FINANCE_RECONCILE_AUDIT.md).

This branch contains #287 but not #285/#286/#288. Reserved branches and the current Application run remain untouched. The actual provider, installed HTTP, independent review, final combined/main acceptance and release gates remain pending.

## Delayed finance checkout result identity — 2026-10-28

Follow-up to independent PR #290 on frozen `5e56c250`: `license_finance_charge_mark()` previously selected only the current invoice row, allowing a delayed result from an older attempt to overwrite a newer processing attempt. `license_finance_charge_record_paid()` also lacked the original request-key and retained transaction checks before reaching the ledger. This does not establish an unauthorized paid transition: existing ledger uniqueness can independently reject some stale receipts.

The separate `codex/finance-charge-attempt-results` correction passes the original attempt key through every checkout response, exception and owner reconciliation path. Both helpers recheck that key and any retained transaction under the account mutex/row lock; paid recording also checks immutable attempt history before ledger access. Owner reauthentication remains inside the paid transaction. PR #290's callback function is byte-for-byte unchanged.

Canonical charge/attempt PDO tests reproduce33 failures among180 assertions per supported PHP/database lane; all180 pass after correction (720 executions across PHP8.2.35/8.4.25 and MariaDB10.11.19/MySQL8.4.13). Twenty actual orchestration checks pass on each PHP version with isolated provider/persistence doubles. Companion10 routing,54 source and45 lifecycle checks, touched-file PHP syntax and workflow YAML/bash checks pass. The native fixture uses a synthetic owner and a ledger sentinel that rolls back eligible cases; it claims no completed ledger payment, license unlock, concurrent writer, installed HTTP or real gateway transaction. [Source hashes, original failures and scope](evidence/finance-charge-attempt-results.json).

The existing four-lane recurring workflow retains both new reports. No manual/duplicate dispatch, owned integration-branch push, production activation or release occurs. Independent review, hosted exact-head testing and final combined/main acceptance remain pending; real sandbox credentials remain unavailable. Disposable databases were gracefully stopped and their data retained.

## One-time finance capture history and lock correction — 2026-10-28

Independent review of frozen integration `3789bb50` reproduced a delayed-capture defect in `license_finance_charge_webhook_review()`. After reading an unsettled row, the callback could wait while another worker completed/refunded/voided the payment or began a new attempt, then overwrite the newer state with `review` and bind the old transaction to the reused charge row. A capture arriving after a replacement attempt already existed had the same problem even without a read/lock race. This could prevent safe catch-up checkout or misdirect owner reconciliation; the callback itself does not mark an invoice paid or unlock a license.

The callback now rechecks the eligible state and immutable attempt key under the existing account mutex/row transaction. It also checks retained attempt history for the verified transaction ID, refusing to associate an older attempt with the current row. Such events return the existing replay outcome; ordinary processing/uncertain/review callbacks still enter review. Exact invoice/amount/transaction verification, conflicting-transaction refusal and payment settlement gates remain unchanged. The shipping change is limited to this function and its canonically refreshed installer inventory.

Real PDO/InnoDB tests exercise canonical charge/attempt DDL, FKs/indexes, actual mutex/transaction helpers, and a second PHP process committing between the initial read and locked read. The 28 scenarios include all three eligible starting states, five completed outcomes, changed attempt keys, historical captures, current captures after replacement, amount mismatch, replay and transaction conflicts. Original code fails39 of140 assertions per PHP8.2.35/8.4.25 × MariaDB10.11.19/MySQL8.4.13 lane. An intermediate lock-only repair leaves six history assertions failing; the complete correction passes all140 per lane (560 executions), with empty PHP diagnostics, unchanged retained parent/attempt rows and released locks/transactions. [Exact source, original failures and bounded native evidence](evidence/finance-capture-snapshot-native.json).

The regression is added to the existing four-lane recurring workflow, with its own retained report and test path filter; no duplicate workflow or manual dispatch is introduced. Both PHP versions pass the10-case routing suite,54 finance source contracts and touched-file syntax. Synthetic gateway details and parent records are explicit: this is not real Authorize.net, webhook-signature, installed-browser or paid-off-license acceptance. Hosted current-head testing, independent approval and exact combined/main acceptance remain required. Work stays on the separate `codex/finance-capture-snapshot` branch; Devin/integration ownership and unavailable sandbox credentials remain unchanged.

||||||| 3789bb50
=======
## Customer domain lifecycle concurrency — 2026-10-28

Customer registration, deactivation and replacement recheck active and expiry state after acquiring the license row lock. A request waiting behind suspension or expiry must reject the mutation while preserving domains and the committed lifecycle state. The native suite exercises all three operations with real concurrent SQL connections and positive recovery controls. [Independent review and runtime scope](CODEX_SOFTWARE_DOMAIN_LOCK_REVIEW.md).

>>>>>>> refs/c293
## Focused provider acceptance repair — 2026-10-28

Application run `40728300754` passed build, recovery, Apache and native phases and reached 2,997 installed-browser checks before the provider settings response exceeded Playwright's unchanged 30-second limit. A bounded StackCP page request now retains the provider account and hosting inventory but loads only StackCP setup and access workspaces, rather than recomputing unrelated provider histories. Both StackCP sections remain owner-only and preserve CSRF, password, immutable-history and exact-provider-read controls; the complete provider page remains available through an explicit return link. This clears an earlier integration-performance blocker without changing licensing, payment or financing state. Exact hosted browser evidence is still required. [Run review](CODEX_PR266_APPLICATION_40728300754.md).

## Installed-suite authentication isolation — 2026-10-28

Software-commerce and financed-license installed acceptance authenticate their own owner, buyer and foreign-client sessions through the real packaged forms. They do not inherit cookies created near the beginning of the complete browser matrix: that matrix can correctly exceed the production 30-minute idle timeout before reaching these late commercial suites. This test-only isolation keeps the timeout, CSRF, ownership and tenant controls intact while ensuring the commercial lifecycle itself is exercised. Frozen run `40705973907` supplied the first-cause evidence; exact corrected-head HTTP/Chromium execution remains required.

## Product-bound runtime validation — 2026-10-28

New software entitlements must send their exact catalog SKU as `product_id` to the public validation API. A valid key and registered domain are not authority for a different product: omitting the product is a definitive denial for any row in `software_entitlements`, and a supplied mismatch remains denied. Genuine legacy website licenses without an entitlement row retain the original `{license_key, domain}` contract. Master software keys remain any-domain internal test keys for their recorded product, not universal cross-product keys. [Independent finding and evidence](CODEX_SOFTWARE_PRODUCT_VALIDATION_REVIEW.md).

## Finance agreement sender upgrade correction — 2026-10-28

The owner finance-agreement screen reads eligible sender accounts through the maintained `mail_drafts_account()` service. A variable-function typo previously left the list empty and emitted PHP warnings when the populated published-v1.24 fixture, upgraded to the development v1.25 candidate, had both mail-account and draft storage prepared. The correction changes no authorization or send operation; it restores the intended read-only sender choice and is covered by the populated Apache upgrade plus a focused source contract. [Failure evidence](CODEX_FINANCE_MAIL_DRAFT_APACHE_REVIEW.md).

## Independent seat controls — 2026-10-28

Each owned eligible license retains its own installation controls. A full purchased license must not hide another trial or temporary license's spare-seat/redeployment form, and an invoice-null grant must not lose authorized recovery merely to satisfy a page-wide assertion. Installed acceptance now scopes seat, pending-deployment and domain-move forms to the exact displayed license, while service-level ownership, CSRF, cooldown and installation caps remain authoritative.

## Installed lifecycle acceptance contract — 2026-10-28

Software entitlements use the explicit `software_license_lifecycle` controls, not the legacy whole-website deactivate/reactivate forms. Installed acceptance selects the exact license plus `suspend` or `resume`, supplies a retained reason, verifies customer state and download denial/recovery, and confirms the legacy action is rejected without changing the license. This is test correction only; it does not broaden owner authority or alter payment/financing state.

## Current integration checkpoint — 2026-10-28

The independent integration lane now includes exact PR #266 head `cb916c07`. Exact-head installed run `40667109804` demonstrated the real owner setup, customer checkout, tenant isolation, sandbox CSP, trial upgrade and fulfillment paths before exposing a direct-dependency defect in the owner delivery retry. The feature now imports the software-commerce migration contract in `software_notices.php`, keeping a disabled/unaccepted mail attempt in the durable retryable state. Purchased-download ordering also prefers the verified invoice/unit entitlement when one customer has multiple eligible licenses for the same release; the installed suite binds the rendered link to the paid license and its purchase source.

These are application/test results, not Authorize.Net sandbox transactions. Fresh combined-head hosted acceptance is required. Real sandbox execution still depends on approved default-branch registration of the manual-only workflows, separately stored sandbox credentials and an explicit sandbox-only dispatch; production charging remains disabled and unauthorized.

## Owner administration dispatcher correction — 2026-10-28

Every `software_*` owner form is now routed through the existing studio administration controller instead of falling through to the generic unknown-action response. The central staff-action boundary classifies the full prefix as owner-only `staff.manage`; individual password reauthentication and transaction checks remain in place. Whole-license lifecycle, deactivate and reactivate branches also load the software-commerce readiness definition before calling it. The fast routing contract complements the existing installed tests that submit the real owner setup and paid-off deactivate/reactivate forms.

## Customer domain cooldown ordering — 2026-10-28

Customer domain changes are serialized under the license row lock. When replacement-internal deactivation and a later standalone deactivation have the same second-resolution timestamp, their audit IDs determine order: only a replacement event recorded after its internal deactivation is exempt. Timestamp age still determines cooldown expiry. This closes the same-second replace/deactivate/add bypass without changing same-domain recovery, owner overrides, disabled/expired cooldowns or installation limits. Twelve native service cases and a supported four-lane PHP/database workflow accompany the correction.

## Current integration refresh — 2026-10-28

The independent integration lane now includes exact PR #266 feature head `9a1e9451`. It retains the unavailable-agreement private HTTP404 repair, the software admin dispatcher correction and generated inventory, and adds the canonical client-workspace dependency to the installed software-commerce fixture. Installed tenant-isolation acceptance still requires HTTP404 together with `Cache-Control: no-store, private` and `Referrer-Policy: no-referrer`. This refresh requires exact-head installed and package acceptance.

## Interrupted financing setup recovery — 2026-10-28

Financed-license, customer finance-agreement and finance-gateway setup each record an audited pending marker before issuing nontransactional DDL. A retry resumes only its matching recognized preparation and only if every already-created table remains empty. The completed schema and indexes are validated before the version is committed and the pending marker is cleared. Unmarked tables or partial storage containing records are preserved and rejected for owner review rather than adopted.

## Independent review correction — 2026-10-28

The combined `codex/licensing-integration-review` lane includes current feature `9a1e9451`, prior review evidence `25e2d34f` and both focused corrections below. Its support-session regression separately verifies the private finance view's HTTP403 and posts a forged finance action through the real dispatcher, requiring an attributed denied audit event and no agreement or payment-request mutation. The single validation-rate-window delay precedes all combined post-payoff probes. Exact combined-head installed execution remains pending.

Payoff is now correctly scoped: it permanently eliminates financing-related suspension but cannot erase an explicit owner deactivation. Validation and financing synchronization no longer silently reactivate a paid-off key; the owner may deliberately reactivate the same key and retained domain history. Domain binding and the default prohibition on resale/distribution continue after payoff.

Customer software ZIP publication now accepts only canonical portable member names. Drive-qualified, dot/empty-segment, control-character, Windows-reserved/trailing-dot, nested-ZIP, special-file and case-colliding entries are refused before publication; stored ZIP bytes remain private and checksum-bound.

Native follow-up: 40 actual PDO/InnoDB cases now exercise optional-finance capture/refund/void routing, all delayed protected-state events, normal transitions, audit-failure rollback, a second process waiting for the account mutex, and unchanged license/agreement/invoice/payment records. They pass locally across PHP 8.2/8.4 with MariaDB 10.11 and MySQL 8.4 and run inside the existing recurring native matrix. They do not perform real gateway transactions. Complete Linux installed acceptance remains separate; Windows correctly refuses the later private payment-key write because it cannot retain 0600 permissions.

The follow-up `codex/licensing-lifecycle-ordering` correction preserves financing `cancelled`, `reversal_recovery` and `replacing` states against delayed lifecycle events. A delayed failure also cannot downgrade `billing_suspended`; ordinary active/failure transitions still work. This retains manual catch-up eligibility and existing settlement/replacement fences without changing payment verification, ledger math or payoff policy. Forty-five actual-service cases pass with isolated database doubles; native gateway concurrency remains a separate gate.

The focused `codex/licensing-webhook-isolation` correction keeps optional financing from interrupting software capture/refund/void notification routing. Finance storage readiness is checked before capture lookup; refund/void ownership is resolved before finance production settings are required. Matched financing transactions retain the existing payment and activation gates. Ten isolated actual-service cases pass locally. [Source, findings, matrix and remaining release gates](CODEX_LICENSING_REVIEW_2026_10_28.md). This is proposed correction evidence, not merged application or real gateway certification.

**Owner request:** October 25, 2026. This branch is an independently staged feature request; it is **not merged, shipped or deployed**. `main` is the authoritative production source. Devin remains integration/release lead. Avoid editing Devin's integration branch and require review of shared billing/auth/schema/portal changes.

## Existing functionality to retain (verified in `main` source)

- `site/app/licenses/service.php`: perpetual customer keys, internal master keys, up to two active domains per license, serialized domain replacement, audits.
- `site/database/schema/license_schema.php` and `site/database/migrations/license_migrate.php`: existing v3 license storage, invoice linking, explicit owner-gated preparation and legacy history recovery.
- `site/api/handlers/license.php`: rate-limited validation API; only HTTP 200 with `authorized:false` is a definitive denial. Malformed requests, outages and unprepared storage must remain inconclusive, non-200.
- `site/app/licenses/fulfill.php`: idempotent license fulfillment following a verified paid catalog invoice; attempts private bundle creation and sends a best-effort email with a client-portal URL.
- `site/app/licenses/bundle.php`, `site/api/bundle-download.php`: licensed ZIP creation, private stored copy, ownership and integrity validation. Master keys cannot be shipped.
- `site/portal/controllers/actions.php` + `site/portal/views/workspace.php`: existing logged-in customer license list, registered domains, self-service replacement/deactivation and downloads.
- The same client portal already surfaces domains, hosting, agreements, invoices, projects and website releases. Extend it; **do not build a separate license/payment portal**.

## Requested end-to-end experience

### Staff/software catalog
1. Define software products with SKU, public/private download visibility, versioned releases, price, optional module/dependency, update entitlement, and allowed concurrent registered installations. Any supported software product may be licensed; do not limit the registry to hardcoded restaurant/salon SKUs.
2. Issue **standard** (paid, perpetual), **trial** (free, fixed expiry), **temporary** (staff issued, expiry) and **master** (privileged internal use only) licenses. Require licensee name and installation domain for non-master types. Tie the license to a real customer account/product, never only an email/domain string.
3. Master licenses permit any domain but are strictly staff-only; require owner reauthentication, explicit purpose, recorded issuer and event audit. They are never exposed to customers, emails, bundles, downloadable archives, or API responses.
4. Keep a complete owner-side license register: license type, product/version, licensee/client, creation source/invoice, expiry, distinct reversible suspension vs owner revocation, registered active and retired domains, recent validation/heartbeat, current activation count, and immutable issuer/transfer history. Recurring payment failures use separate `payment_failed` / `billing_suspended` states: paid-through access is preserved until entitlement expiry, and an exactly verified later payment returns billing to active without changing the license key; manual suspension/revocation remains sticky. Financed website licenses use the separate [financing contract](LICENSE_FINANCING.md): only an actually issued unpaid installment beyond its agreed grace period can payment-suspend a site, and full payoff permanently removes the payment lock without granting resale/distribution rights.
5. Trial and temporary grants expire reliably **server-side** without a nightly job. Upgrades to paid grants must preserve history and not allow replay or accidental double fulfillment.

### Purchase -> verified payment -> delivery
1. Software is listed alongside hosting, domains and web-design services in the **existing customer portal** and storefront.
2. Checkout creates an invoice/order pending verified payment. Never mark an invoice paid because of a browser redirect, unsigned callback, amount mismatch, sandbox event or unverified provider status.
3. On the verified paid transition, allocate the purchased product grant **idempotently by paid order line and entitlement unit**; the existing one-license-per-invoice link is insufficient for multiple products/quantities. Replays and concurrent webhook processing may not mint duplicates.
4. Release only customer-eligible downloadable versions, from server-private storage. Tie each download to the exact purchaser, order, product, version and authorized entitlement. Keep checksums, content length, filename and immutable asset history. No user-controlled paths or publicly enumerable direct archive URLs.
5. Queue durable delivery notice for sending after the DB transaction commits. Customer receives email with a link to the **same authenticated portal** where the approved downloads, license keys, approved domains and receipts appear. Email must not embed master keys or permanently reusable download tokens. Failed mail is retryable without granting a duplicate key or reversing payment.
6. Ensure purchases, invoices, subscription renewals and downloads all appear in the same customer's unified account, but with permission checks on each service; support/impersonation cannot change domains or download restricted products without explicit authority.

### Client self-service
- **Dashboard:** My Services (domains, hosting, web projects, software), Billing & Payments, My Software (licenses/activations/versioned downloads), Support, Account & Team.
- Customers may register the purchased product's initial domain and **replace an approved domain** on a license already owned, using the existing atomic replacement path. Require CSRF/session, active ownership, operation cooldown, clear warning that the old domain loses authority at next validation, and audit of prior/new domain. No changes to master licenses.
- Show license status (trial days remaining, expiration, active, suspended, pending deployment), assigned product, current domain(s), usage summaries and purchase details. Avoid falsely showing "in use" merely because a domain is registered.
- Domain change should not require repurchase; policy may restrict abusive switching with a configurable cooldown while allowing support review.

### Validation and usage
- Preserve `POST /api/validate.php` semantics for existing products; introduce optional versioned client request fields only with backward-compatible parsing. Validate server-side type, expiry, product, client-owned entitlement, revocation, registered domain and seat limit.
- Record bounded validation outcomes and last-seen timestamps per activation using a well-defined verified signal. Rate-limit and batch writes; keep separate immutable security audit, short operational retention and no raw visitor analytics. A successful validation is evidence of a check, not proof the software is currently running.
- Validation heartbeats do not create permanent per-request audit rows containing IP/domain data. Successful software checks use one minute-bucketed observation row per license/domain; observations for domains that are no longer active are automatically purged after 180 days. Owner/admin license mutations remain in the immutable audit trail.
- For unavailable license storage or transient failures return non-200 (inconclusive) instead of forcing installed customer websites offline. Expiry/revocation of a verified grant is a definitive denial only when entitlements and history are trustworthy.

## Incremental implementation gates

**A — Entitlement policy and contract (this initial branch slice).**
`site/app/licenses/software_license_policy.php` defines strict pure rules for four types, name/domain binding, UTC expiry, staff-only master and authorization; `tests/software_license_policy.php` exercises them without DB/network. No production endpoint, schema or portal behavior is changed in this slice.

**B — Durable schema and guarded migration.**
Add additive grants/activation history and immutable entitlement-to-order-line rows without changing existing v3 records or creating master keys in customer data. Use explicit owner preparation, preserve interrupted-migration journal, and establish schema readiness for all new entry points. Test migrations from published 1.25 and populated current `main`, repeat/interruption/recovery and missing-history refusal.

**C — Issuance and validation.**
Wire policy through staff actions and public API with a single checked read path and type/expiry/seat tests; retain existing perpetual license behavior. Add bounded activity/verification dashboard, no leak of full keys in audit.

**D — Catalog/verified checkout fulfillment.**
Replace hardcoded industry-only licensed SKUs with registered products, tie entitlements to verified payment/order lines, block sandbox as payment, make fulfillment exactly-once and deliver approved archives privately.

**E — Unified portal and transactional delivery notices.**
Add My Software section to existing portal (not a new app), line items/downloads/invoices/status; safe self-service domain transfers; outbox-backed email containing only a login-bound portal link. Existing project/hosting/domain/mail navigation must remain intact.

**F — Release acceptance.**
Native PHP/MySQL + installed root/subfolder HTTP/Chromium + published-v1.25 UPDATE + CSRF, unauthorized customer access, support-session restrictions, expired trial, payment replay, refund/reversal review, email retry, oversized ZIP, missing source, checksum/archival integrity and cross-tenant isolation. Independent review, exact merged-`main` acceptance, package asset audit and new tagged release **before any live deployment**.

## Key non-negotiables

- No direct edits to `main`, Devin-owned branches or live site from this feature branch.
- Do not replace existing billing, licensing, installer, client identities or bundled key contract.
- No master license download, no plaintext master keys in customer email, no public unauthenticated file URLs.
- No paid fulfillment before trusted payment confirmation. Do not assume a sandbox receipt is real payment.
- Existing license domains and historical purchases survive upgrade. Use one portal and one customer identity.
- Build UPDATE and full/complete packages **without a ZIP inside another ZIP**.

## 2026-10-25/26 feature branch implementation status

Branch PR #266 implements owner-gated software database preparation, standard/trial/temporary/master issuance, expiration-aware license validation, observed checks, private versioned ZIP publishing, on-site Accept.js tokenization, payment attempt fencing, initial charge readback, ARB enrollment and cancellation, signed webhook reconciliation of initial/renewal transactions, subscription receipt idempotency, customer domain changes, license downloads and delivery email retry.

The current branch also adds a first-class `software` catalog family with arbitrary valid SKUs, owner-purpose records for master keys, explicit owner suspend/resume and revoke/restore lifecycle controls, recoverable `payment_failed` / `billing_suspended` recurring states that return to active only after verified payment, and a financed-website license layer that binds finite installment obligations to retained recurring-billing schedules with grace-period suspension, permanent removal of financing suspension after payoff, customer/domain binding, and explicit resale/distribution rights. It also retains a configurable serialized customer domain-move cooldown, tenant-isolated asset authorization, visible renewal invoices, durable renewal notices, strict decimal payment parsing, sandbox response-shape verification without fulfillment, refund/void receipts keyed by the follow-on gateway transaction, sticky dispute/reversal review states and cancellation-race readback. Software-commerce storage is now **v3**: owner policy records support an optional module key, dependency-SKU metadata, 0–120 month update entitlements and 1–25 concurrent installations; issued entitlements snapshot their update cutoff, installation limit and policy revision, and validation/domain registration/download authorization enforce those snapshotted rights. Eligible unlinked trial/temporary grants on the exact customer/SKU/domain are converted in place after verified paid fulfillment, preserving the original key, issuer, creation timestamp and domain history; multiple eligible matches stop for owner review instead of guessing. Customer software ZIP links now carry a specific license entitlement, and the download endpoint re-verifies that entitlement against its customer, SKU, update window and originating purchase invoice/unit before streaming bytes. Version eligibility is measured from an immutable `released_at` timestamp set the first time a ZIP becomes customer-visible; private staging/record time does not extend update rights, and hiding/restoring a release never resets that first-release date. The owner register shows issuance source, update cutoff, installation limit, domain history and bounded successful-validation observations without pretending those observations prove the software is currently running. Owner and customer observation views now share the same retention-aware reader, so inactive-domain observations older than 180 days are pruned before display while still-active installation history remains available. Credit-card chargebacks are not invented as Authorize.net webhook events: an owner can record independently verified merchant-service-provider dispute evidence against the original transaction, which places the payment/subscription into review without silently revoking paid-through access. Installed-browser acceptance now includes real owner preparation, client ordering, CSRF/tamper refusal, tenant-isolated portal/download behavior, domain replacement, sandbox-page CSP/nonce rendering and private ZIP verification without contacting a gateway. Sensitive owner mutations are authenticated at the service boundary, not only in controllers: master-key issuance, exhausted delivery-retry recovery, merchant-dispute evidence, one-time paid reconciliation and ARB enrollment reconciliation re-check owner credentials inside their serialized mutations. Customer software domain add/deactivate/replace re-check customer ownership inside the service, and recurring cancellation re-checks customer/invoice ownership plus an independent ARB cancellation readback before local cancellation is finalized. The generated installer inventory includes the v3 software-product policy service and all current changed shipping files; this is source preparation, not a verified release package.

**Shipping/activation gates still open:** No production merchant key was used, no real payment was charged, no external webhook was provisioned, and the repo has not passed a complete hosted database/HTTP/Chromium acceptance or published an updated installer. The software payment selector permits disabled/sandbox modes only. A separate owner production-review control records or clears review but does not expose a production checkout option; approval itself is refused unless code explicitly defines `CWP_SOFTWARE_PRODUCTION_PAYMENTS === true`. Even if database settings are manually changed, production remains fail-closed unless that code gate **and** the owner-reviewed production flag are both present; database flags alone cannot enable live charging. Production remains disabled until independently reviewed gateway setup, actual sandbox and production-shape testing, retention/privacy/PCI review, owner approval, explicit production-mode release work and a new release. Do not treat the existence of these sources as authorization to process live payments.

**Real sandbox acceptance:** `.github/workflows/software-authorize-sandbox.yml` is manual-only and never points at production. It requires explicit `confirm_sandbox_only` plus repository/environment secrets `ANET_SANDBOX_API_LOGIN_ID`, `ANET_SANDBOX_TRANSACTION_KEY`, and `ANET_SANDBOX_PUBLIC_CLIENT_KEY`. The test loads `https://jstest.authorize.net/v1/Accept.js`, tokenizes Authorize.net's documented sandbox-only Visa, submits a small sandbox-only auth/capture to `https://apitest.authorize.net/xml/v1/request.api`, verifies transaction-details readback, then creates/reads/cancels a sandbox ARB subscription with a fresh one-use nonce. It never prints or artifacts credentials, card data, or opaque tokens. A successful manual run is still not production authorization; production remains separately gated. Sandbox credentials are scoped only to the credential-check and execution steps, amounts are compared with exact decimal arithmetic, and ARB cancellation is independently read back after the cancel request before the sandbox run is considered successful.

**PCI note:** A merchant-hosted card form that uses Accept.js is a PCI SAQ A-EP integration, not automatically SAQ A. To reduce scope in a later independent hardening pass, consider an Authorize.net hosted payment surface while keeping CajunWebPro's branded order/receipt flow.

**Payment-page CSP:** The normal site CSP remains self-only for scripts and browser connections. The authenticated software checkout replaces that header before output only when an enabled Authorize.net environment is successfully loaded, allowing the exact sandbox or production Authorize.net script/tokenization hosts and retaining the per-request script nonce. The checkout scripts themselves carry that nonce. Accept.js remains provider-hosted as required by Authorize.net's integration guide: https://developer.authorize.net/api/reference/features/acceptjs.html. No static SRI hash is pinned to the provider-managed `/v1/Accept.js` URL because Authorize.net does not publish an immutable integrity value for that mutable CDN path and its own error contract expects the current provider-hosted script. Reassess this if Authorize.net publishes an immutable integrity artifact. Actual browser acceptance must confirm the production tokenization endpoint; the CSP currently permits only Authorize.net's documented API host plus the `api2.authorize.net` host used by Accept.js implementations, never a wildcard domain.


**Outstanding acceptance scope:** the focused pure contract suites and installed-browser syntax checks were green on recent feature checkpoints before the CI-queue cleanup. The new financed-license policy and manual/payment lifecycle changes still require committed PHP lint/native/database/browser coverage and a regenerated installer inventory. The expanded MariaDB workflow and full installed application workflow must complete successfully; the installed browser source now covers expired-trial denial, in-place paid trial upgrade, exact entitlement downloads/audit, disabled-mail retry, refund/dispute review UI and support-session blocking, but those runtime assertions are not counted as passed until the full workflow executes; exercise real Authorize.net sandbox tokenization, transaction-detail and webhook shapes; complete the queued installed root/subfolder HTTP/Chromium execution of the new software-commerce cases; verify refund/void/dispute review UI and delivery-email retry; run an actual populated published-v1.25 UPDATE/recovery after the next release version is assigned; complete the queued FULL-PROJECT / COMPLETE-UPLOAD / UPDATE package build and verify ZIP/unzip, checksums, inventory and no-nested-ZIP packaging; complete independent security review and exact merged-`main` acceptance. Do not merge into main, enable production charging or ship while those gates remain unresolved.
# License-key entropy compatibility

New licenses retain all 128 random bits produced at issuance and render them as eight four-hex groups. Historical four-group keys continue to validate and are never rewritten, so upgrades preserve installed customer identities.

### Owner reconciliation and concurrent state changes

Owner readback happens outside the database lock. Before persisting an old identity or lifecycle response, reconciliation compares the locked subscription's row ID, gateway ID and state with its pre-readback snapshot. A concurrent replacement or lifecycle transition requires a fresh reconciliation attempt and preserves the newer state. See [independent snapshot review](CODEX_FINANCE_RECONCILE_SNAPSHOT_REVIEW.md).
