# HC-07 application contract — owner 20i connection settings

Reviewed contract for draft PR #74. Codex owns `codex/provider-settings`; Devin retains canonical provider corrections. This branch now reconciles published 1.19.0 source22a2969 and publication recordsb472ed2, preserving provider46b2826 and isolated probe6ced20d. The release boundary is complete; proceed with bounded transport and protected owner application integration. No new release version is reserved and the probe is not exposed in the application yet.

## Existing evidence

Evidence: owner-requested diagnostic run38458492995 / job115510304263 authenticated one fixed GET https://api.20i.com/package. This establishes that endpoint's read access only. The key remains in GitHub secret storage; do not extract it into this workspace. Independent review of PR #43 at `7f1af0c0d73282d98b552860cbf3a3eee91c061f` reproduces all 163 checks under default PHP and PHP without extensions. UNKNOWN now preserves post-send ambiguity. A strict JSON shape fixture correction is requested before final acceptance: associative decoding falsely accepts empty and numeric-key objects as lists. [Reproduction and requested correction](https://github.com/afterburn25/Cajun_Web/pull/43#issuecomment-6160027189). Do not merge stale shared application files from its old baseline.

The JSON object/list fixture finding above is resolved at `46b282636ad83b22efe149310755d2d316d909bc`: independent default/no-extension runs each pass 171 checks. No raw provider response is accepted as durable detail. The imported canonical files remain outside deployment.

## Scope and ownership

Bounded first application scope: protected owner-only 20i profile, explicit current-password connection check, durable sanitized attempt/result history, capability status explaining that authentication does not enable provisioning, and update/recovery preservation. This first increment does not store or display inventory: the authenticated account-specific inventory response fields and ownership contracts have not been inspected. A connection result proves only the fixed package-list read; later inventory needs its own documented schema and acceptance. No client mapping may infer ownership from matching email/domain; maps require separate explicit client/service review and current access checks. No whole-reseller response reaches customer portal code.

## Private credentials and current authority

Credential boundary: separate provider-master.key under existing configured private storage, AES-GCM context binding to installation prefix, immutable provider-profile id and environment. Refuse symlinks/permissive modes, reject lost keys with existing ciphertext, preserve blank-edit secrets, no reveal route, no secret fragments in versions/audit/screenshots. Existing payment/mail keys and application notification SMTP remain unchanged. Profile revisions increment on edits and disable; owner intent captures exact id/revision. Owners in client-support context cannot configure/check providers. Fresh setup stores no credential and does no network; additive upgrade is explicit password-confirmed owner setup.

## Explicit read-only check

Check boundary: POST+CSRF+current owner password and exact saved revision. Append audited intent before network, recheck owner/session/revision before credential resolution and after response before display. Network holds no database transaction. Serialize credential use with staff/profile edits using the existing canonical lock order, without adding a competing auth model. Fixed HTTPS host api.20i.com, fixed GET /package, standard trust/hostname verification, no proxy/redirect/caller URL; public DNS validation and pinned validated address, bounded total/connect/I/O deadlines and streaming response cap. Encode raw General API key once into bearer base64 as in the reviewed diagnostic. No automatic retries or scheduled polling; finite fixed failure codes, no raw provider errors, credential values, inventory body or headers in audit/logs.

Reuse reviewed ProviderCredential and ProviderGateway::checkConnection()/ProviderAdapter::probe() with a concrete first-party PHP transport. Mark every other capability unverified/unsupported. The generic operation ledger must get separate native persistence/concurrency acceptance before any mutation path is exposed. Authentication succeeds only for the fixed endpoint and accepted successful JSON list, including an empty list. Preserve JSON object/list identity when decoding; reject empty objects, numeric-key objects, scalars, null, malformed/trailing data and excessive depth. The bounded result detail is not a redaction filter. The concrete adapter emits a fixed allowlist of local codes/messages; persistence independently validates this mapping before storing anything. No arbitrary adapter detail, short response body or exception text reaches durable history. A timeout or lost result is unknown/incomplete, not an invented failure or successful connection. Display check time and saved revision, preserving prior attempts as historical after edits.

## Isolated probe component

[Probe guide](TWENTYI_CONNECTION_PROBE.md) records the current `foundation/twentyi` adapter, refusal ledger and synthetic checks. These components are outside deployment and do not implement real HTTP transport or application persistence.

## Acceptance before integration

Acceptance: native additive schema exactness, owner/support/client/staff denial, CSRF/password/revision, ciphertext context/lost-key/modes, strict intent/result audit rollback, two-process edit/check races, limits, interrupted results and recovery. Isolated local HTTPS server tests real PHP TLS/hostname/certificate failures, fixed method/path, exactly one authentication encoding, proxy/redirect refusal, DNS/private-IP rejection, byte/time caps, malformed JSON/list/schema and finite error mapping. Packaged actual HTTP/browser tests current forms and honest capability labels; never call real20i from standard CI. Run fresh/install/update/recover/reapply against latest published release with nonempty profile/history and original private key retained. Explicitly distinguish synthetic transport proof from a separately owner-authorized target-host/API operation.

## Independent component review

Devin responded with canonical probe/result/transport corrections and an updated PR description at 7f1af0c. Codex reproduced 163 checks and requested the additional object/list cases plus accurate 200-byte validation wording. Keep those canonical corrections on the provider branch; re-review the final exact head before import. Codex owns site schema/settings/controller/native/browser/build/release integration. Keep inventories/claim mapping, actual provisioning, domain/VPS lifecycle, billing activation and SSH separately gated.

## Primary API references and limits

Public official documentation was checked on 2026-10-13. The [20i authentication/documentation guide](https://docs.20i.com/api/20i-api-documentation) confirms encoding the General API key for bearer authentication and directs account holders to their API documentation. The [API browser console guide](https://docs.20i.com/api/20i-api-browser-console) describes inspecting available endpoints and payloads in the account console; the [2026 documentation update](https://www.20i.com/blog/our-reseller-api-docs-just-got-a-major-upgrade/) describes the current access path. These public references do not establish this account's entitlements or package item schema. Preserve the diagnostic's narrow read-only evidence and do not invent inventory field mappings.

## Evidence links

- [Successful owner-requested GET diagnostic](https://github.com/afterburn25/Cajun_Web/issues/65#issuecomment-6154083380).
- [Reviewed provider component](https://github.com/afterburn25/Cajun_Web/blob/688ff554761bf9f47a256657bb28f247d3064dd2/docs/PROVIDER_FOUNDATION.md).
- [Current email release PR](https://github.com/afterburn25/Cajun_Web/pull/72).
- [Dependency gates](DEPENDENCY_GATES.md), [hosting roadmap](HOSTING_AND_BILLING_ROADMAP.md) and [release discipline](RELEASE_WORKFLOW.md).
