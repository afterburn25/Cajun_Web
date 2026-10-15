# Explicit local provisioning retry

## Follow-up — fenced deletion retry

A separate original-approver/current-owner function now atomically fences eligible definite-failure deletion retries. This supersedes earlier blanket deletion-retry exclusions only for the new bounded path. Unknown reconciliation remains refused. [Contract](HOSTING_DEPROVISION_RETRY.md). Native CI and independent review are pending.

Unreleased work on `codex/hosting-provision-retry` from PR82/1f59074, separate from client authority PR83 and mail PR77. No route, provider request, worker, credential decryption by the retry function, schema change or release version is added.

`hosting_operations_retry_provision()` is a deliberate current-owner/password action. It requires an active selected client, exact current hosting revision, stable request identity, ready operation storage and a fully validated journal/fence/result chain. Eligibility is limited to a recorded definite `hosting.provision` failure with `retryable=true`. An unknown timeout, missing result, successful response without a resource identity, unsafe failure, active account or already-pending retry is refused. Generic local-record commands still cannot request retries.

The shared guard and transaction atomically append the canonical `retry_provision` event, compare the current journal revision, and insert strict audit. Audit failure restores the original failed state. Exact request replay returns the retained journal without another transition or audit; altered identity reuse and competing stale requests refuse. A previously accepted request remains idempotent after subsequent dispatch, but never grants another call.

The result is only a local pending journal, with no pending attempt and no new dispatch fence. A subsequent ordinary `hosting_operations_claim()` must independently pass current owner/client/provider settings and revision checks. Only that new claim commits a fresh attempt and can later be consumed by a verified provider coordinator. The earlier failed observation and dispatch remain immutable; a late first-attempt response cannot settle the new attempt. Disabled provider settings block the new claim.

History validation recognizes this one additional local command only when its canonical preceding state contains the same eligible recorded failure. Existing fence/result checks still cover every remote event, so removing the original failed observation makes the retained retry history unreadable rather than implicitly reconstructing evidence. No general reconciliation or deletion retry is enabled. Canonical deletion retry can arm an attempt immediately and requires a separate atomic-fence design; it must not be added to this local path.

Native fixtures cover current authority, stale revisions, nested transactions, strict audit rollback, exact and competing requests, process restart, new-attempt allocation, disabled provider settings, old-result isolation, unsafe/unknown failures and missing retained proof. PHP syntax and installer/documentation checks are local. Local Windows execution stopped at the existing provider key0600 filesystem check before retry assertions; this is not a passing native result and the protection is retained. PHP8.2/8.4 Linux/MariaDB runs must verify the new cases plus all existing94 local-record and79 operation cases. Current CI and independent review remain pending.

Client identity PR83 separately serializes legacy client writers; integrate that accepted branch before relying on the guard during provider coordination. Parent82, journal80, foundation79 and original Devin branches remain intact. Published1.21 stays immutable. See [operation contract](HOSTING_OPERATIONS.md), [local storage](HOSTING_RECORDS.md) and [activation gates](DEPENDENCY_GATES.md).

Initial Linux run38550491194 passed the retained94 record and79 operation cases per runtime, then stopped before retry assertions because the new fixture reused the record suite prefix. The retry fixture now uses its own `htry_` prefix; application code and all assertions are unchanged. Replacement acceptance remains pending.

Replacement38550760697 reached the intended client-deactivation refusal, but its worker caught only RuntimeException while client absence uses InvalidArgumentException. Catch that expected refusal too; unexpected PDO failures still escape. This is a fixture correction, with no application or assertion relaxation. Complete replacement evidence remains required.

## Verified native correction

Downloaded push38550996210 and PR38551004850 atd101665 each pass44 new retry +94 retained record +79 retained operation assertions per PHP8.2/8.4,434 executions. All six reports per run verify; the complete push log has no PHP warnings. Runtime remains9e599ef; later corrections affect only fixture isolation/expected refusal handling and documentation. Full generated refresh,127 Markdown/1085 links and2542 exact committed installer dependency hashes pass. Independent review is still required; this is not provider, application, release or deployment acceptance.
