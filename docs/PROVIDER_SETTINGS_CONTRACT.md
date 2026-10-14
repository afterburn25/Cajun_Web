# HC-07 application contract — owner 20i connection settings

Proposed contract for independent review, not an implemented feature. Codex claims `codex/provider-settings` from main44926230a2359cf82793eb3cdd55fe940bbb786f (verified1.18.0 publication record). Email-notice PR72 remains the current release priority. This branch initially changes documentation only; reconcile the completed1.19.0 baseline before application integration. Devin retains canonical provider-foundation corrections in PR43. No new release version is reserved by this contract.

## Existing evidence

Evidence: owner-requested diagnostic run38458492995 / job115510304263 authenticated one fixed GET https://api.20i.com/package. This establishes that endpoint's read access only. The key remains in GitHub secret storage; do not extract it into this workspace. Provider PR43 through688ff55 retains reviewed canonical classes; php default/-n each150 checks at b58366a, with only session documentation changed since. Do not merge stale shared application files from its old baseline.

## Scope and ownership

Bounded first application scope: protected owner-only 20i profile, explicit current-password connection check, durable sanitized attempt/result history, capability status explaining that authentication does not enable provisioning, and update/recovery preservation. Decide whether documented inventory response schemas permit adding an owner-only read inventory before reserving the final release scope. No client mapping may infer ownership from matching email/domain; maps require separate explicit client/service review and current access checks. No whole-reseller response reaches customer portal code.

## Private credentials and current authority

Credential boundary: separate provider-master.key under existing configured private storage, AES-GCM context binding to installation prefix, immutable provider-profile id and environment. Refuse symlinks/permissive modes, reject lost keys with existing ciphertext, preserve blank-edit secrets, no reveal route, no secret fragments in versions/audit/screenshots. Existing payment/mail keys and application notification SMTP remain unchanged. Profile revisions increment on edits and disable; owner intent captures exact id/revision. Owners in client-support context cannot configure/check providers. Fresh setup stores no credential and does no network; additive upgrade is explicit password-confirmed owner setup.

## Explicit read-only check

Check boundary: POST+CSRF+current owner password and exact saved revision. Append audited intent before network, recheck owner/session/revision before credential resolution and after response before display. Network holds no database transaction. Serialize credential use with staff/profile edits using the existing canonical lock order, without adding a competing auth model. Fixed HTTPS host api.20i.com, fixed GET /package, standard trust/hostname verification, no proxy/redirect/caller URL; public DNS validation and pinned validated address, bounded total/connect/I/O deadlines and streaming response cap. Encode raw General API key once into bearer base64 as in the reviewed diagnostic. No automatic retries or scheduled polling; finite fixed failure codes, no raw provider errors, credential values, inventory body or headers in audit/logs.

Reuse reviewed ProviderCredential and ProviderGateway::checkConnection()/ProviderAdapter::probe() with a concrete first-party PHP transport. Mark every other capability unverified/unsupported. The generic operation ledger must get separate native persistence/concurrency acceptance before any mutation path is exposed. Authentication succeeds only for strict accepted JSON list, including empty list. A timeout or lost result is unknown/incomplete, not an invented failure or successful connection. Display check time and saved revision, preserving prior attempts as historical after edits.

## Acceptance before integration

Acceptance: native additive schema exactness, owner/support/client/staff denial, CSRF/password/revision, ciphertext context/lost-key/modes, strict intent/result audit rollback, two-process edit/check races, limits, interrupted results and recovery. Isolated local HTTPS server tests real PHP TLS/hostname/certificate failures, fixed method/path, exactly one authentication encoding, proxy/redirect refusal, DNS/private-IP rejection, byte/time caps, malformed JSON/list/schema and finite error mapping. Packaged actual HTTP/browser tests current forms and honest capability labels; never call real20i from standard CI. Run fresh/install/update/recover/reapply against latest published release with nonempty profile/history and original private key retained. Explicitly distinguish synthetic transport proof from a separately owner-authorized target-host/API operation.

## Independent component review

Devin review request: inspect canonical probe/result/transport interfaces against this application contract, identify necessary finite-classification corrections on provider branch only, and rewrite PR43's stale69-check description for the final150-check scope. Codex owns site schema/settings/controller/native/browser/build/release integration. Keep inventories/claim mapping, actual provisioning, domain/VPS lifecycle, billing activation and SSH separately gated.

## Evidence links

- [Successful owner-requested GET diagnostic](https://github.com/afterburn25/Cajun_Web/issues/65#issuecomment-6154083380).
- [Reviewed provider component](https://github.com/afterburn25/Cajun_Web/blob/688ff554761bf9f47a256657bb28f247d3064dd2/docs/PROVIDER_FOUNDATION.md).
- [Current email release PR](https://github.com/afterburn25/Cajun_Web/pull/72).
- [Dependency gates](DEPENDENCY_GATES.md), [hosting roadmap](HOSTING_AND_BILLING_ROADMAP.md) and [release discipline](RELEASE_WORKFLOW.md).
