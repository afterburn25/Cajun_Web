# Local domain renewal requests

This unreleased increment lets a signed-in client ask the studio to review renewal of an explicitly assigned registered domain. The owner can save a message visible to that client or decline; the client can withdraw. A review message leaves the request **pending studio review**. None of these actions renews a domain, charges an account, sends mail, creates a quote or approves a provider operation.

## Scope and provenance

Branch `codex/twentyi-domain-renewal-requests` starts at combined `38bb69ccd1c1fc45f479022fe11748ca78b756d2`. The previous registrar branch `725573f0` and nameserver documentation branch `9ea20f87` are preserved. The only imported later-root file is `tests/provider_mailbox_cases.py` from `2239a1569dfa691fe18f23a512fb93b1905079ef`: it obtains the foreign client's real logout CSRF before testing mailbox ownership refusal. No root shipping source is merged.

The [retained owner API reference](TWENTYI_OWNER_API_REFERENCE.md) maps original lines 239–249 to the renewal request fields `name`, `years` and optional `renewPrivacy`. The [existing operation contract](TWENTYI_OPERATIONS_CONTRACT.md) explicitly does not establish pricing, TLD eligibility, completed renewal or registrar entitlement. This increment records local preferences only and adds no provider API contract, transport or execution path. Its 1–10 years bound is a local input limit, not a registry promise. Privacy can remain undecided for studio review. No expiry, price, currency, payment, provider success or eligible renewal period is fabricated.

## Product flow

The owner explicitly enables request history in **Provider settings → Domain renewal requests**, using the current owner password. Prepared client accounts and registered-domain inventory are required. A GET never prepares storage. Clients open **Hosting services → Domain renewal requests**, select a currently assigned registered domain, enter their preferred years/privacy and confirm review-only intent. Each client sees only their own requests and studio messages. Support sessions cannot submit requests or withdrawals.

Creation requires a fresh successful latest registrar observation, the exact original account/key context, a current explicit client link and the captured link/profile revisions and observation ID. Hosting association cannot supply registrar authority. A newer failed/unfinished/partial read, changed account, stale observation or changed assignment fences the old form. An assignment with a pending request cannot create another pending request. Exact request replay returns the same original record; changed content under the same identifier fails.

Owner review requires current owner authority/password and the same still-current assignment/account binding. A newer fresh observation may confirm that unchanged binding. The owner can decline an obsolete request, and its original client can withdraw it after unlinking; these actions close historical intake without claiming current resource authority. Review and decline require a client-visible message. Terminal requests cannot return to review. A new request after closure has a new identity and separate history.

## Persistence and recovery

Owner preparation creates `domain_renewal_requests` and `domain_renewal_events`, with marker `domain_renewals_schema_version=1` and an independent monotonic request-count witness in settings. The witness increments atomically with each new original request and prevents paired loss of a pending request and its creation audit from looking like empty history. Shared staff locking and transactions serialize creation/review and commit audit events atomically. This is local intake history, not a second provider operation ledger. No grant or provider result is created.

The original canonical request is immutable and binds client, link/revision, account/revision, credential fingerprint, domain ID/name and observation ID. Credential plaintext is excluded. Every immutable review/decline/withdrawal event carries its actor, revision and previous document hash. Current state is a checked projection of that sequence. Event time cannot fall below the last retained event when the host clock moves backward. Schema validation checks columns, indexes, collations, transactional engines and restrictive relationships. Preparation refuses a caller-owned transaction before DDL. Established missing tables/marker, surviving orphan events, mismatched audits, altered originals or incomplete event chains require restoration of original records. They cannot be repaired by silently rebuilding empty history. Losing every history witness simultaneously cannot be detected locally; this workflow has no provider operation to replay.

Forms use existing CSRF/authentication, private response headers and escaped display. Failed forms preserve captured versions instead of silently adopting newer approval context. History is paginated. Additional requests have a local per-client hourly limit.

## Validation checkpoint

Local PHP 8.4 default and no-INI execution each passes all 28 named document/event cases in `tests/domain_renewals_document.php`. Changed PHP syntax and Python parsing are checked. Independent catalog-agent source review found no concrete blocker after the root-requested count-witness and clock-rollback corrections; this is source review only. Four-way PHP 8.2/8.4 × MariaDB 10.11/MySQL 8.4 native execution and real packaged HTTP/Chromium acceptance are **pending**, not inferred from source or earlier registrar checks. Windows' existing vault permission guard remains intact.

Prepared native coverage includes current client/account context, stale forms, sibling isolation, current owner authentication, atomic audit interruption, duplicate/nonmatching replay, concurrent distinct requests, terminal transitions, clock rollback, pending request/audit paired loss, retained count witnesses, later-sequence/earlier-timestamp failed and unfinished refresh fencing, unlink withdrawal, missing history/marker/schema and exact restoration. Browser coverage exercises the actual enable/assign/request/review/withdraw/decline screens, captures desktop/mobile views and checks private internal paths. Full application acceptance also includes packaged recovery and Apache protection. Root owns final review, sequential integration and release.

## Remaining domain work

Renewal quotes and eligible periods, explicit commercial consent/payment, provider execution through the existing shared operation ledger, authoritative outcome reconciliation, expiry/status observations, registrations/transfers, nameserver/DNS controls and their exact provider contracts remain separate unfinished work. No published version, live deployment or full-domain completion is claimed.
