# 20i saved-key connection check

## Current status — 13 October 2026

The owner reports saving the raw General API key as repository Actions secret `TWENTYI_API_KEY`. The explicitly initiated diagnostic did **not receive that secret**. No request to 20i occurred, and no key-validity or account-entitlement conclusion can be drawn.

- Starting main: `c105e0456538eaf600042f5f4d053e578b7d19f0`.
- Isolated branch: `ops/twentyi-read-only-check-20261013`.
- Executed workflow source: `caae2cf142d95bb47d16d1b8dfd508787363d13b`.
- [Actual workflow run](https://github.com/afterburn25/Cajun_Web/actions/runs/38458492995), job `115507247771`, failed on 13 October at 19:53 UTC.
- Actual sanitized output: `FAIL: TWENTYI_API_KEY is not available to this job. No API request was made.`
- Follow-up tracker: [issue #65](https://github.com/afterburn25/Cajun_Web/issues/65).

This is a diagnostic/operations branch, not an application release or an integrated hosting feature. Main, published releases, live hosting and the separate consultation PR #66 were not modified. A branch or issue comment does not dispatch Codex/Devin.

## What the diagnostic does

`.github/workflows/twentyi-read-only.yml` contains one self-contained Python-standard-library diagnostic. It makes at most one fixed HTTPS GET to `https://api.20i.com/package`, with base64(raw General API key) bearer authentication, normal certificate/hostname validation, no proxies or redirects, a 10-second socket timeout, a 30-second total deadline, a 2 MiB response cap and strict JSON/list validation. It makes no retries or writes and accepts an empty list as a successful authenticated read.

There is no checkout, third-party Action, dependency install, application bootstrap or output artifact in the secret-bearing job. GitHub token permissions are empty. Secret values/encoded bearers, response bodies, account IDs, names, counts and server-provided diagnostic text are never intentionally emitted. Failures use fixed messages only. This avoids publishing customer information in a public repository's logs.

The first check is restricted to the exact diagnostic branch, owner actor/triggering actor, changed workflow path and explicit commit message. Ordinary main pushes and PRs do not run it. Once reviewed/merged onto main, `workflow_dispatch` additionally requires owner execution on main with confirmation `READ_ONLY`. No recurring monitoring or unattended provider calls are configured.

## Offline checks and their limit

Fourteen local Python unittest cases with synthetic tokens/responses passed before the workflow was created. They cover empty/populated results, exact GET/URL and one-time bearer encoding, verified TLS, no proxy/redirect, timeout/bounds, missing/malformed secret, invalid JSON/content type/shape, provider HTTP failures without retry, suppressed sensitive exception messages and total-deadline behavior.

The preserved runner at `tests/twentyi-read-only/run.py` loads the exact inline script without invoking its main entry point and mocks the network boundary in every request test. Run from a checkout with `python3 tests/twentyi-read-only/run.py`. These isolated checks are not PHP application acceptance or successful provider authentication. The actual Actions run established only secret unavailability.

## Required owner input / next action

Confirm only the secret's name and scope—never paste its value. Expected location: `afterburn25/Cajun_Web` → Settings → Secrets and variables → Actions → Secrets → **Repository secrets**, with the name exactly `TWENTYI_API_KEY`.

A differently named secret, a secret saved for Codespaces/Dependabot, an Actions variable, or an environment-scoped secret does not automatically populate this repository-secret reference. The exact cause has not been established. When an environment was intentionally used, obtain its name only and configure the job to reference that protected environment; preserve approvals rather than weakening scope. Do not export the credential to chat/local files or enumerate secret values.

After the owner corrects/confirms the scope, explicitly rerun the failed diagnostic job or review a required name/environment adjustment. Check the actual sanitized result. A successful result proves only package-list read access; package-type/domain/VPS entitlement, provisioning, DNS, transfers, SSO and payments remain separate gates. No broader operation is authorized by a successful read test.

Codex can continue protected PHP adapter/UI/schema/contract work consistent with the existing roadmap while this single external gate is resolved. Reuse the uploaded-module review in issue #65, including header-merging, TLS/SSO, timeout, JSON and PHP compatibility findings. Do not import the old wrapper unchanged. Record the eventual outcome and integration in the shared status/handoff/changelog during review/merge; no current application version should be advanced for this diagnostic.

## Sources rechecked in this session

- [20i authentication guidance](https://docs.20i.com/api/20i-api-documentation): base64-encoded General API key.
- [20i package-list documentation](https://docs.20i.com/api/retrieve-packages-api): GET `/package`, array of objects.
- [20i API base-host guidance](https://docs.20i.com/api/20i-api-browser-console): `https://api.20i.com/`.
- [GitHub Actions secrets](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets): an unset secret expression returns an empty string.

## Session record

Request: the owner confirmed the key was saved after instructions to use `TWENTYI_API_KEY`. Read current main/agent/handoff/workflow and open-work records; preserved ongoing application work. Built and locally tested the bounded diagnostic, committed it to the isolated branch and initiated the single push-gated run. Read actual job steps and logs: secret unavailable, no provider request. Preserved the synthetic test runner and this precise status/handoff. No production data/configuration, credentials, charges, domains, services, SSO or customer mail changed. Next action is name/scope confirmation, not an invented successful connection or blanket pause of development.
