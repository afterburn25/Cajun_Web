# Client domain availability search

Status: native-tested at `b09b7927` on `codex/twentyi-client-domain-search`, [draft PR139](https://github.com/afterburn25/Cajun_Web/pull/139). Parent `f86c745c` has verified actual HTTP/Chromium acceptance and inspected client desktop/mobile screenshots. Combined b09b full acceptance and independent review remain pending. This is unreleased development above hosting PR 138, not a live-enabled service.

## Owner setup

In Provider settings, prepare owner domain search, select the protected connection and offered extensions, and enable owner lookups. Prepare client domain search with your current owner password, then explicitly enable client availability checks. Preparation preserves existing staff search history and does not contact 20i. Viewing either settings or the portal does not prepare storage or perform a lookup.

Client approval is bound to the current owner authentication, provider connection revision/encrypted credential context and domain-search configuration. Changing these requires a new explicit client-search approval. Disabling client search preserves earlier client results. It does not grant permission to reserve, register, transfer or buy a domain.

## Client experience

Active signed-in clients see Find a domain in their workspace when search is enabled. They submit a complete domain name within the offered extensions and receive their own recent lookup history. A checked availability result is an observation at its recorded time. Suggestions require their own checks. Incomplete streams, failures and changed access never claim availability.

Forms use CSRF protection and an individual request identifier. Repeating a saved request returns its original history without sending it again, even if the original attempt has no result. The history projection omits provider identifiers, credentials, owner policy details and other clients' records. Support impersonation cannot perform a lookup. Pages use private/no-store headers and the existing root/subfolder URL helpers.

## Shared limits and recovery

Admin and client searches consume the same protected provider budget: a 30-second cooldown, at most 20 attempts per hour and a 24-hour pause after a recorded provider rate-limit response. Pending/incomplete intents count. Current access is rechecked before credentials, transport and persistence; an access change discards the response and credential fingerprint.

Storage is additive: `provider_domain_client_policies`, `provider_domain_client_searches` and `provider_domain_client_results`. Policies are immutable, sequential and audit-backed; a restored older pointer cannot revive a revoked grant. Client audit entries identify the client in their detail with staff user ID zero. Requests retain their actual client identity and owner policy instead of pretending the client is an administrator.

An interrupted first preparation can resume only with compatible empty tables and no established history. Missing existing tables, markers or policy history require recovery from the original database; preparation never replaces established history. Surviving client audit evidence also prevents a lost family from disappearing from the shared budget. Owner-search migration preflights the complete family before any DDL. Restore the original data rather than clearing records to remove a pause.

## Verification and remaining scope

Local changed-file PHP lint and Python AST checks pass. New native cases cover owner approval, interrupted preparation, isolation, current sessions, audit rollback, shared limits, concurrency, revocation, idempotent replay and exact history restoration. Actual browser cases cover owner/client forms, invitation/login, support refusal, private headers, real synthetic authenticated HTTPS, desktop/mobile screenshots and private-file protection. Native cases now pass as recorded below. Browser cases must execute and their artifacts/screenshots be inspected before browser acceptance is claimed.

Linux native matrices use PHP 8.2/8.4 with MariaDB 10.11/MySQL 8.4; the Windows private-key guard is not relaxed for local tests. Provider responses are isolated fixtures. No live API call, domain reservation, price quote or purchase is performed by these tests. Domain periods/pricing, checkout, registrar ownership, registration/transfer/renewal and the remaining full20i workflow are separate required implementation work.


## Native client-search acceptance — 2026-10-15

Source `1fbebcac0992cc7f3d71c91b33e4c97d047f4a4b`, [PR139](https://github.com/afterburn25/Cajun_Web/pull/139), [run38810375887](https://github.com/afterburn25/Cajun_Web/actions/runs/38810375887). All four PHP8.2/8.4 with MariaDB10.11/MySQL8.4 combinations pass. Downloaded artifacts match GitHub SHA256, ZIP CRC and every named result. Each contains106 combined owner/client checks, including the50-case client subset; those counts are not additive.

| Artifact | SHA256 |
|---|---|
|12008521772|`ab5d4c85b96ebcfc79723019e74ab9e5410b3948792e64e96defe8bb14dce419`|
|12008227522|`3cea24f5b2cde07e8cf637c668428da417fbc6a61ce5e03e63b85fba85b62829`|
|12007977896|`daaebcdd286e8b7695d5db729c94e099de4a4dac6432df440fd90adeabe35de7`|
|12007059361|`651ff868a12b4f890973e838597a58845a56ce90854d64098f382fa8e14b80f0`|

Proof retained locally as `work/20i-1fbeb-client-native-verification.json`. Actual browser acceptance remains pending. Source inspection found that the new browser fixture client lacked a support-access policy: the follow-up fixture uses the real owner policy form before opening support, retaining the production refusal assertion. No runtime authorization rule is relaxed.

Exact1fbeb Windows artifacts independently verify11 checksums and all source/member/directory/update bytes: FULL5416, COMPLETE2659, UPDATE2646. Stored under `work/20i-client-1fbeb-windows-dist` with verification JSON. This is development package evidence, not a published release.

## Verified browser and merged-head native checkpoint — 2026-10-15

Parent source `f86c745c982ae1daedc1a7210bcf91052e4f9c49` and GitHub test merge `22c090fbb56ff1ac815d7256af5281f447b68740` share verified tree `a8c5a8231a112644ba72367925e61aa0d05a375c`. [Application run38811407918](https://github.com/afterburn25/Cajun_Web/actions/runs/38811407918) browser artifact12009795846 SHA256 `bd50a74b1ad4474344c85406c095afd56480fff421a632fc6927a53c729bab56` was downloaded and verified: all1,774 named HTTP/Chromium checks pass and unexpected browser/PHP diagnostics are empty. Actual Client-Domain-Search desktop/mobile screenshots were inspected: forms, private result history and suggestions remain readable without horizontal clipping. Synthetic `.invalid` results are not live availability evidence. Recovery/Apache acceptance remains in progress.

Merged source `b09b79278d1a459e4235f9a9431031f53abf6334` [native run38812919607](https://github.com/afterburn25/Cajun_Web/actions/runs/38812919607) passes all four combinations. Each downloaded artifact contains106 combined checks including50 client checks, not additive.

| Artifact | SHA256 |
|---|---|
|12009312326|`172a878a01fb25838b011cd4ee77bb871f9e89b1339d02530c38bb667c78b812`|
|12009297315|`de080dfd87aa02272e734fd07de177cd964e1f98dbe241137482b4978be765b1`|
|12009047634|`2612ecfd6848d134788c15534366afb38c5bb33bd67307da5f348ec0572dc693`|
|12008684072|`f2e9fb23662deb1f464422c42f612c1102209f6f22c3c8029ffed69fff0e2dc7`|

Exact b09b Windows build86750 completed and independently verifies11 checksums, exact Git source, archive members/directories and update bytes: FULL5417, COMPLETE2659, UPDATE2646. Proofs are retained locally under `work/20i-client-b09b-windows-*`, `work/20i-b09b-client-native-verification.json`, and `work/20i-f86-client-browser-verification.json`. Do not rerun the non-idempotent Windows copying verifier. Current combined application38812924437/job116690370817 has completed package checks and is executing native integration. Preserve the same live run. These results do not certify the remaining full20i scope or a published1.25 release.
