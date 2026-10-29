# Stale sandbox checkout subscription continuation

Date: 2026-10-28 (America/Chicago).

## Finding and source

Independent follow-up to [PR #291 review comment 6483700465](https://github.com/afterburn25/Cajun_Web/pull/291#issuecomment-6483700465), based on frozen head `110c30da71ac16fef5194dc95390980bab85504b`. Branch: `codex/finance-sandbox-stale-profile`. Neither Devin's implementation branch nor the integration owner's branch is modified.

The locked `license_finance_charge_mark()` correctly refuses an older attempt or a completed charge state. Its void return did not tell `license_finance_charge_run()` that the sandbox mark was rejected. Checkout consequently continued to `license_finance_subscription_create_profile()` using profiles from the superseded response. Persisted charge rows remained protected, but the later recurring-subscription side effect was still reachable.

The mark now returns whether it applied. Sandbox checkout returns review immediately when admission fails, before subscription creation. Successful current sandbox results retain their existing behavior. Production payment recording, owner reconciliation, webhook review and payoff synchronization functions are byte-identical to #291. This does not change provider mode, credentials, approval gates or subscription replacement policy.

## Regression evidence

The existing native response fixture now includes the actual checkout function alongside the actual mark and paid helpers, canonical charge/attempt SQL, and real staff mutex/transaction. A separate SQL connection commits either a replacement attempt or a refunded, voided or paid state between the synthetic provider response and mark. The fixture records whether the subscription-creation boundary was reached. The positive control leaves the original attempt current and confirms exactly one correctly parameterized subscription call.

- Before correction on PHP 8.2.35 / MariaDB 10.11.19: 28 of 220 assertions fail. Eight directly demonstrate four stale responses returning sandbox success and reaching subscription creation; twenty cover the new explicit mark-return contract. Existing original row-preservation checks remain present.
- After correction: 220/220 on each PHP 8.2.35/8.4.25 × MariaDB 10.11.19/MySQL 8.4.13 lane, totaling 880 native assertions. Rejected response rows remain byte-for-byte unchanged; current response marks sandbox success and alone reaches the expected subscription boundary.
- Caller-binding fixture: 26/26 on each PHP runtime (52 total), including accepted/rejected sandbox admission and original attempt propagation. The original caller tests remain; two added stale-continuation assertions fail against #291.
- Touched PHP lint passes on both runtimes. Canonical `python tools/build.py --refresh` completes, including deployment PHP and JavaScript checks; the finance-charge inventory hash is refreshed. Documentation and whitespace checks pass. Existing recurring native workflow already invokes both expanded fixtures and preserves their reports; no extra dispatch or workflow broadening is needed.

The initial local test-harness attempt had a duplicate nonce helper declaration and failed before assertions; that diagnostic is preserved outside the source tree. The repaired harness uses the real nonce and response contracts, and the recorded before/after runs use the same corrected test code.

## Scope and integration gates

Provider responses, initial checkout preparation, profile creation and the subscription call boundary are synthetic. Native charge/attempt storage and the intervening SQL writes are real. Ledger checks still use an explicit boundary sentinel and do not claim settlement or license access. Actual gateway sandbox credentials remain unavailable. This evidence does not establish a real Authorize.net transaction, ARB subscription, HTTP/browser acceptance, independent approval or merge readiness.

Report counts, new assertions, full local report hashes and exact source/function identities: [review JSON](evidence/finance-sandbox-profile-review.json). Retain #291 and #290 ancestry; integrate only after independent review and required hosted checks. Final combined/main acceptance, paid-off protection and release gates remain mandatory. Production payments and deployment remain disabled.
