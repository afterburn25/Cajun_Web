# Authorize.Net sandbox workflow security review

## Correction and enforcement boundary

The manual-dispatch ref supplies both the workflow and checked-out source. Shell guards, driver hashes and source contracts are editable on that ref. They can detect accidental regression in reviewed source, but cannot independently authorize access to repository or organization secrets.

Both credential-bearing jobs now reference `authorize-net-sandbox`. Before any credentials or real run, the repository administrator must configure this environment with a selected deployment **branch** rule for exactly `main` and store all three `ANET_SANDBOX_*` values only in that environment. Do not configure an equivalent tag rule, and do not leave repository/organization-scoped copies accessible to this repository. The environment policy is the server-side restriction; the YAML declaration alone does not create it. A newly auto-created environment is not evidence of protection.

The original follow-up still installed mutable Python packages on the same
runner that later received `ANET_SANDBOX_TRANSACTION_KEY`, and used mutable
`actions/*@v4` tags. Step-scoped environment variables do not make that safe:
downloaded install code can persist on the runner and observe a later process.
The corrected workflows pin every action by commit and split each probe across
fresh runners. The first runner receives only the browser-visible API login and
public client key, creates three one-use Accept.js nonces, and transfers only
that strict JSON envelope. The second runner receives the transaction key, has
no `pip`, Playwright or Requests dependency, validates the nonce envelope, and
uses only Python's standard library against the fixed sandbox API. The short-
lived nonce artifact is retained for one day and is never copied into reports.

Retain explicit sandbox confirmation, the no-secret main-ref guard, exact event-SHA checkout without persisted credentials, read-only permissions, non-canceling per-workflow concurrency and pinned sandbox-only provider probes. The source contract pins complete reviewed workflow and driver bytes, requires fresh-runner token handoff, rejects a transaction key in the dependency runner, rejects downloaded Python code in the transaction runner and rejects mutable action tags. This does not prevent a branch author from changing the contract too; external environment policy remains required.

## Evidence and remaining gates

Independent review of PR279 head `25dfe346d2c0bb27643d6ee2ede4916ca98b2fb5` verified both provider probes byte-for-byte against integration `03c4f606b5e536c7173ebf35200a40715228e527`. The original substring contract passed four offline negative fixtures with dependency or ref guards commented out. No changed workflow was executed. A read-only repository query during the 28 October 2026 review found zero environments. Credentials are unavailable; no environment configuration, secret access or hosted enforcement is claimed.

For this correction, run the dedicated pull-request workflow and both offline
contracts. They verify exact reviewed bytes, sandbox-only endpoints, strict
nonce parsing, redirect refusal, fixed authentication envelopes, immutable
actions and transaction-key runner isolation. These are offline regression
checks, not sandbox payment evidence.

Before running, verify the actual environment branch policy and credential scope through GitHub settings/API, then complete independent source review and normal main integration. A denied non-main test using non-sensitive fixtures may validate platform policy separately; never expose real merchant credentials for a negative test. Provider contracts remain separate from installed application, ledger, webhook, failure/reversal and release acceptance.

References: [dispatch ref semantics](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#workflow_dispatch), [environment branch rules and secret access](https://docs.github.com/en/actions/reference/workflows-and-actions/deployments-and-environments).
