# Client directory listing requests

This increment starts at `1a8ae8507416cb2c79dd41e5e06cadf7e73d0bab` on `codex/twentyi-client-directory-listing`, above the separate [client HTTPS request proposal](https://github.com/afterburn25/Cajun_Web/pull/244). Published 1.25.0 stays unchanged. This branch does not integrate the other pending hosting, catalog, StackCP or Devin proposals.

## Using the feature

An owner opens **Hosting & Domains → Directory listing requests** and enables request history after preparing directory listing observations and changes. The owner must explicitly share a saved setting for the client's current hosting assignment. The primary client then opens **Hosting services → Directory listing requests**, selects Enabled or Disabled and confirms the request. A client can withdraw a pending request.

The owner reviews the request against a fresh saved setting, approves one change with their current password, or declines with a reason the client can read. Changing assignment, account authentication, provider credentials or sharing invalidates the old authority. Re-enabling sharing does not revive the old request. Contact and support sessions cannot make these decisions.

Each client may submit at most ten new requests per day; an unresolved request fences its hosting package. An exact repeated submission returns its existing history. Conflicting submissions cannot reuse its identifiers. Failed forms retain the original request and review identifiers; passwords and confirmation boxes are cleared. A reload link obtains current details without submitting another change.

The owner separately checks the provider's reported flag after dispatch. Enabled, Disabled and Unavailable remain distinct. A matching flag does not prove this request caused the change or establish which files visitors can access. Enabling directory listing can show a list of files instead of an index page. Uncertain results never automatically resend. Eligible local-history recovery restores the saved record without another provider request.

## Provider and storage contracts

The retained [owner API reference](reference/20i-owner-api-34e713-redacted.txt), lines 2515–2529, documents GET and POST `/package/{packageId}/web/directoryIndexing`. POST sends one boolean `value`; GET can return a boolean or null. This increment uses that existing endpoint and does not invent an acknowledgement response.

The new additive request journal authenticates its complete event sequence and reciprocal owner approval. Preparation is an explicit owner action. Ordinary page reads do not create storage, open provider credentials or send provider traffic. Lost or inconsistent history requires recovery; it is not silently reset as a fresh installation. A surviving client-origin approval also prevents missing request history from being presented as unprepared.

Client intent and owner approval keep their separate actors. Admission commits the client decision and owner approval together. Current authority is checked again at dispatch, DNS resolution, follow-up observation and recovery. The existing direct owner format remains version 1; client-origin approvals use version 2. Existing owner requests, grants and results are not rewritten.

## Verification and release status

Local checks pass: document105, storage92 and service62 in default and minimal PHP, plus unchanged owner suites78/37/57 in both modes (862 total portable executions);111 modeled HTTP/Chromium checks;416 acceptance-helper checks preserving the original309 outcomes. The installer refresh and syntax checks pass;2910 dependency hashes match.

The [local evidence receipt](evidence/client-directory-requests-local.json) records named portable checks, actual controller/render checks over local HTTP and Chromium with modeled business storage, independent source review and the acceptance contract checks. These scopes are separate from native database execution and installed application acceptance.

The new native fixture requires Linux CLI, an explicit opt-in, a dedicated test database and exact loopback connection. On this Windows host only syntax and refusal guards are exercised. Its later isolated SQL run uses synthetic provider transport; it is not a live account test.

The first exact-source full GitHub run, candidate integration, exact-main acceptance, packaging and download verification remain required before publication. No live deployment, provider change or new release occurred in this increment. Earlier queued runs remain preserved and are not acceptance evidence for this new source.
