# Client domain availability search

Status: implemented on `codex/twentyi-client-domain-search`, parent `5bec4fb2`; local syntax validation only. Native database and actual HTTP/Chromium acceptance are pending. This is unreleased development above hosting PR 138, not a live-enabled service.

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

Local changed-file PHP lint and Python AST checks pass. New native cases cover owner approval, interrupted preparation, isolation, current sessions, audit rollback, shared limits, concurrency, revocation, idempotent replay and exact history restoration. Actual browser cases cover owner/client forms, invitation/login, support refusal, private headers, real synthetic authenticated HTTPS, desktop/mobile screenshots and private-file protection. These cases must execute and their artifacts/screenshots be inspected before acceptance is claimed.

Linux native matrices use PHP 8.2/8.4 with MariaDB 10.11/MySQL 8.4; the Windows private-key guard is not relaxed for local tests. Provider responses are isolated fixtures. No live API call, domain reservation, price quote or purchase is performed by these tests. Domain periods/pricing, checkout, registrar ownership, registration/transfer/renewal and the remaining full20i workflow are separate required implementation work.
