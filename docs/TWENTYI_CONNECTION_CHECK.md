# 20i saved-key connection check

## Current status — retry succeeded, 13 October 2026 at 20:00 UTC

The owner reported that the key had initially been saved in the wrong repository, corrected the location, and explicitly requested another check. The same diagnostic job was rerun without modifying its code or expanding its endpoint/method scope. **The job received `TWENTYI_API_KEY` and 20i accepted the authenticated package-list read.**

- Executed workflow source: `caae2cf142d95bb47d16d1b8dfd508787363d13b`.
- [Workflow run](https://github.com/afterburn25/Cajun_Web/actions/runs/38458492995).
- Successful retry job: [`115510304263`](https://github.com/afterburn25/Cajun_Web/actions/runs/38458492995/job/115510304263).
- The connection step and job completed successfully. Its actual sanitized log at `2026-10-13T20:00:46Z` states: `PASS: 20i accepted GET /package (HTTP 200 and a valid package list).`
- The result was verified from the executed job log, not inferred solely from a success badge. The log masks the secret and does not disclose its value or returned package contents.
- Follow-up tracker: [issue #65](https://github.com/afterburn25/Cajun_Web/issues/65).

The prior missing-secret blocker is resolved for this job. **This establishes real authentication and package-list read access only.** It does not establish package count, reseller package-type availability, domain/VPS entitlement, costs, provisioning, DNS/transfer/SSO acceptance, payment readiness, or a shipped website integration. Empty and populated lists both satisfy this diagnostic; no count or customer information is published.

Only one fixed GET was performed by the retry. No provisioning, DNS/domain changes, deletions, SSO sessions, customer mail or payments occurred. No application source, live hosting, main branch or published release was changed by this retry. The operations-branch documentation and issue record are the only repository changes. No background monitoring or Codex/Devin dispatch is implied.

## Historical initial failure — superseded for secret availability

The owner initially reported saving the raw General API key as repository Actions secret `TWENTYI_API_KEY`. The initial diagnostic did **not receive that secret**. That initial attempt made no request to 20i and established nothing about key validity or account entitlement.

- Starting main for the diagnostic branch: `c105e0456538eaf600042f5f4d053e578b7d19f0`.
- Isolated branch: `ops/twentyi-read-only-check-20261013`.
- Initial job `115507247771` failed on 13 October at 19:53 UTC.
- Initial sanitized output: `FAIL: TWENTYI_API_KEY is not available to this job. No API request was made.`

This is a diagnostic/operations branch, not an application release or an integrated hosting feature. A branch or issue comment does not dispatch Codex/Devin.

## What the diagnostic does

`.github/workflows/twentyi-read-only.yml` contains one self-contained Python-standard-library diagnostic. It makes at most one fixed HTTPS GET to `https://api.20i.com/package`, with base64(raw General API key) bearer authentication, normal certificate/hostname validation, no proxies or redirects, a 10-second socket timeout, a 30-second total deadline, a 2 MiB response cap and strict JSON/list validation. It makes no internal retries or writes and accepts an empty list as a successful authenticated read. The second job execution above was explicitly requested by the owner after correcting the secret location.

There is no checkout, third-party Action, dependency install, application bootstrap or output artifact in the secret-bearing job. GitHub token permissions are empty. Secret values/encoded bearers, response bodies, account IDs, names, counts and server-provided diagnostic text are never intentionally emitted. Failures use fixed messages only. This avoids publishing customer information in a public repository's logs.

The first check is restricted to the exact diagnostic branch, owner actor/triggering actor, changed workflow path and explicit commit message. Ordinary main pushes and PRs do not run it. Once reviewed/merged onto main, `workflow_dispatch` additionally requires owner execution on main with confirmation `READ_ONLY`. No recurring monitoring or unattended provider calls are configured.

## Offline checks and their limit

Fourteen local Python unittest cases with synthetic tokens/responses passed before the workflow was originally created. They cover empty/populated results, exact GET/URL and one-time bearer encoding, verified TLS, no proxy/redirect, timeout/bounds, missing/malformed secret, invalid JSON/content type/shape, provider HTTP failures without retry, suppressed sensitive exception messages and total-deadline behavior. These historical local tests were not rerun during the owner-requested retry.

The preserved runner at `tests/twentyi-read-only/run.py` loads the exact inline script without invoking its main entry point and mocks the network boundary in every request test. Run from a checkout with `python3 tests/twentyi-read-only/run.py`. These isolated checks are not PHP application acceptance. The separate successful Actions retry now provides real provider package-list-read evidence as recorded above.

## Next action and credential handling

No further key value or screenshot is required for this successful diagnostic. Keep the value in protected configuration; the repository records only the secret name `TWENTYI_API_KEY` and sanitized evidence. Never export the key into chat, source, logs or release packages. The successful Actions job does not install credentials into the live website or automatically grant them to Codex/Devin working environments.

Codex's next bounded 20i integration step is a protected first-party PHP adapter, owner-only connection/settings controls, and read-only inventory/client-service mapping. Inspect current main and coordinate with other work before taking ownership. Reuse the uploaded-module review in issue #65, including header-merging, TLS/SSO, timeout, JSON and PHP compatibility findings. Do not import the old wrapper unchanged or expose the reseller's entire inventory to every client.

Package-type/domain/VPS endpoints and entitlements still require their own contract/account validation. Real provisioning, DNS, transfers, SSO and payments require their own acceptance and authorization. Do not infer those permissions from this successful read or silently expand the one-endpoint diagnostic. Continue feasible adapter/UI/schema/contract work consistent with the existing dependency-gate policy; do not keep labeling the now-resolved secret-availability check as blocked.

Record actual integration in shared status/handoff/changelog during its review/merge. Do not advance the application version or close the full integration issue merely for this diagnostic success.

## Sources checked during the original diagnostic preparation

These references were recorded in the original preparation session, not independently rechecked during this retry:

- [20i authentication guidance](https://docs.20i.com/api/20i-api-documentation): base64-encoded General API key.
- [20i package-list documentation](https://docs.20i.com/api/retrieve-packages-api): GET `/package`, array of objects.
- [20i API base-host guidance](https://docs.20i.com/api/20i-api-browser-console): `https://api.20i.com/`.
- [GitHub Actions secrets](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets): an unset secret expression returns an empty string.

## Session records

### Initial preparation and failed attempt

Request: the owner confirmed the key was saved after instructions to use `TWENTYI_API_KEY`. Read current main/agent/handoff/workflow and open-work records; preserved ongoing application work. Built and locally tested the bounded diagnostic, committed it to the isolated branch and initiated the single push-gated run. Read actual job steps and logs: secret unavailable, no provider request. Preserved the synthetic test runner and precise status/handoff. No production data/configuration, credentials, charges, domains, services, SSO or customer mail changed. The next action then was name/scope confirmation.

### Owner-requested retry after correcting the repository

Request: the owner said the secret had been added to the wrong repository and was now fixed, and requested another attempt. Read the exact previously executed workflow at `caae2cf142d95bb47d16d1b8dfd508787363d13b`; confirmed its single fixed GET and sanitized output. Confirmed the original failed job, then reran job `115507247771` using the Actions job-rerun operation. Read the resulting job `115510304263` steps and decoded log: authenticated GET `/package` returned HTTP 200 and a valid list. No additional endpoints were called. Updated this guide and issue #65 so the former missing-secret result is historical, while keeping the narrow success and remaining integration gates explicit. No runtime implementation, source merge, release or live service change occurred.
