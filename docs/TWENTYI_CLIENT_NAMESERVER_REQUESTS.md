# Client nameserver requests

This is a development candidate on `codex/twentyi-client-nameserver-requests`, starting from `af78abd81ce39839cd100e1c882a59b04258a25b` and the preserved owner workflow in [PR222](https://github.com/afterburn25/Cajun_Web/pull/222). It does not change the published 1.25 source, tag or assets, and it is not a live deployment. The enclosing commit identifies the exact candidate source.

## Behavior

An owner explicitly enables client request history after preparing registered-domain observations and owner nameserver changes. An active primary client can request a complete desired nameserver set for a domain assigned to that account. Submitting or withdrawing a request does not contact 20i, create an invoice, or grant provider access. Support sessions and contact sessions cannot use these actions.

The client can follow a specific request and withdraw it while it remains pending and its original account/assignment is current. The owner can decline it with a message, or review the latest complete nameserver observation and approve its exact desired set with the current owner password. The client cannot supply the provider's original nameserver set, choose another provider account, or edit an existing request through a retry.

The existing supported registrar contract remains a complete, nonempty, disjoint replacement: `POST /domain/{numericRegistrarId}/nameservers`, with whole `ns` and whole observed `old-ns` arrays. Partial overlap, add-only, remove-only and unchanged sets are not silently converted into a hosting package delta. The retained provider-authored SDK example and the existing [owner change guide](TWENTYI_REGISTRAR_NAMESERVER_CHANGES.md) remain the contract evidence.

Approval records an immutable association before any DNS resolution, credential access or provider send. The existing writer makes at most one attempt for that approval. A successful HTTP response alone remains uncertain. An owner must run a separate readback; only that exact saved read can report the desired set as observed. It does not prove that this request caused the change or that DNS propagation is complete.

The client sees pending, withdrawn, declined, interrupted, not sent, unresolved or observed status with the recorded follow-up result. Private provider bindings, credential digests, grants, execution ledgers and owner controls are absent from the client projection. Owners retain the existing interrupted-history recovery controls and exact request detail after verification/recovery. Direct owner version-1 requests keep their original workflow.

## Authority and persistence

Local intake uses the original complete registrar source retained by the current assignment. Its eligibility does not disappear merely because a later nameserver observation ages or fails. Owner dispatch independently requires the latest fresh, complete provider observations.

The immutable request binds the actual primary client and authentication digest, exact registrar assignment/event/audit, original source, profile revision, key fingerprint, encrypted-credential digest, and assigning owner's current identity/authentication digest. Account, owner, credential, key or assignment changes invalidate dispatch from the old review. The actual approving owner is recorded separately. Fresh authority is checked again at credential/send boundaries.

Dedicated explicit storage uses `provider_registrar_ns_requests_events`, `registered_nameserver_requests_schema_version` and an independent authenticated head. The shortened table name stays within MySQL's identifier limit even with the supported maximum installation prefix. It does not collide with the observation module's closed `registered_nameserver_` table namespace. Canonical documents, exact schema, ordinal/head, HMAC chain and ordered actor audits must all agree. Unknown or lost established history requires recovery from the original data; setup does not recreate it as empty history.

Owner version-2 approvals add only `client_origin: {id, request_sha256}` to the existing approval document. The reciprocal client dispatch event pins the actual owner approval document SHA. Its event ID is also the owner request/grant ID in their separate tables. This avoids a circular hash while preserving both directions. The grant, owner request and client dispatch event are committed in one transaction under the existing staff mutex. A final audit/head failure rolls back all three; withdrawal and approval cannot both win that mutex. The provider call runs outside the transaction while the same mutex remains held.

Raw client-graph authentication never invokes owner-history validation. The owner reader authenticates its version-2 origin and, separately, checks every retained client dispatch against owner history. Missing origin evidence cannot fall back to a version-1 direct-owner approval. Historical proof checks are distinct from current dispatch authority, so a later legitimate account change does not rewrite saved facts.

The existing global profile/numeric-domain unresolved fence, 4,096-byte owner approval limit and 8,192-byte provider request limit remain. Local event documents have a separate 32,768-byte bound and signed-32-bit date/sequence bounds. Complete local history is validated without silent truncation; that has an O(n) retained-memory cost. Pagination limits visible requests, not integrity validation.

## Entry points

- Client: `studio_url(['nameserver_requests' => 1])`; a `request_id` selects one owned request.
- Owner: `admin_url('nameserver-requests')`; a `request_id` selects the exact request and its original owner dispatch.
- New actions: client create/cancel and owner prepare/decline/approve. Existing owner verify/recover actions remain the only readback/recovery handlers.
- Service: `site/app/providers/registered_nameserver_requests.php`; document and storage modules are separate.
- Explicit migration: `site/database/migrations/registered_nameserver_requests_migrate.php`. Merely including it does not create tables.

Forms retain bounded request IDs, review tokens and entered text after a failure. Passwords and confirmation boxes are cleared. A stale review is not silently replaced by a newer observation. Mutations require the actual application session, CSRF, and owner password where applicable.

## Verification checkpoint

Root's applied-source local runs currently pass 36 service/model checks, 109 pure document/transition checks, 94 storage/model checks, 85 new renderer/controller checks, and the retained owner suites (65 service/model, 90 storage/model, 38 renderer), each in default and minimal PHP modes: 1,034 named executions. These use actual production function bodies, canonical documents and the typed gateway, with synthetic sessions, SQL/storage and provider responses. They are not native database or deployed-site acceptance.

The service suite exercises client submission without an owner session, exact canonical retries, support/contact denial before normalization, withdrawal/decline, late atomic-admission rollback, reciprocal-origin loss, one synthetic gateway send, separate differing/matching readbacks, client-safe status, aged/failed observation intake, and account/credential changes before and during resolution. Both real module entry files also load without database calls or declaration conflicts.

The initial new service harness loaded both repository and embedded copies of the same foundation class and failed before its scenarios. It was corrected to evaluate the actual document functions against the already-loaded foundation. A later report collector initially expected JSON on stdout from the existing renderer suite; that suite correctly wrote its JSON report to its named file. Both original outputs are retained in `work/registered-nameserver-requests-validation`. These were harness corrections, not passing native results.

The UI proposal preserves eight existing-file seams through exact finite inverses. Its local renderer/CSS browser harness passes 56 checks with eight synthetic desktop/mobile captures; all eight were inspected by the UI agent and three full mobile views independently by root. These are synthetic views, not installed-site screenshots. The layout fixture inherited a synthetic 1.26.0 footer; this branch VERSION remains 1.25.0 and actual packaged pages must use that value. Canonical installer refresh, shipping syntax and pinned mail dependency bundles pass.

The isolated native producer declares 106 scenarios and retains its Linux-only disposable-database guard; source/lint/platform-refusal checks do not establish SQL success. Actual native database races/rollback, packaged HTTP/browser flows, published-1.25 update/recovery retention, exact-source GitHub acceptance and release publication remain separate gates. Their first results must be recorded against the exact shipping source. Preserve original runs and failures; do not cancel or rerun queued predecessors merely to obtain a newer run.

The new packaged browser continuation follows both unchanged original nameserver callbacks. It discovers and assigns a second synthetic registrar domain through real owner forms, then exercises three client requests (withdrawn, declined, approved), one owner send and three separate readbacks. Eight scoped desktop/mobile captures are required. Existing domain history, ledger rows, timestamps, assertions and 16 nameserver captures are preserved. Root's applied-source loopback TLS run passes 91 named checks, retaining the previous 80 checks. The first root launch selected an environment without `cryptography` and stopped at import; its log is retained separately from the successful run in the existing Python environment. No authenticated application browser run is claimed by those fixture checks.

The installed upgrade producer starts from the actual immutable published 1.25 archive and applies the exact candidate UPDATE package. It retains original client/provider/assignment records and private bytes, then adds pending, withdrawn, declined, observed and unresolved request histories through application services. Seven real owner updater actions (`apply`, `apply`, `recover`, `apply`, `apply`, `recover`, `apply`) check retry, complete-journal file rollback and reapplication. Intentional loss of the client history head must remain refused through the second rollback/reapplication; file recovery cannot invent database history or resend a provider request. Root's 39 portable archive/retention/source guards and both companion PHP syntax modes pass. Actual installed Apache/SQL execution is pending.

An independent source review traced the current session, transaction, mutex, reciprocal history and version-1 compatibility paths and found no actionable correctness issue. This review does not replace runtime acceptance.

## Acceptance wiring

Full acceptance retains the predecessor suite and adds nine native commands, two Apache commands and eight browser captures. The resulting inventory is 173 native commands, 186 browser reports and 236 final reports. The existing HTTP fixture report carries the extended 91 TLS outcomes; the retention diagnostic cannot count as a passing report. Focused PHP 8.2/8.4 and MariaDB/MySQL jobs include the new native fixture.

Root's applied-source helper run passes 473 named outcomes, retaining all 334 predecessor outcome objects and their relative order. Current source/report hashes are checked before the finite reconstruction used by earlier contract assertions. The first external composition attempted to pin its own enclosing contract hash; that failed run is retained. The corrected extension authenticates its closed source inventory and verifies the complete reconstructed predecessor contract, without a recursive self-hash. No archive, evidence-ownership or release validator is weakened.

The distro full-workflow minimal service tests explicitly load tokenizer and PDO because the actual services use `token_get_all` and `PDOException`. This includes one documented correction to the existing owner-service command. The official Docker focused image and Windows interpreter retain their built-in modules and plain minimal mode. No prior workflow run is cancelled or replaced by this correction.

## Continuity

The separate hosting purchase inbox remains at `7535f861c2c3accf37482f5af94099780c7806a6` on [PR229](https://github.com/afterburn25/Cajun_Web/pull/229), with its locally verified packages and original GitHub acceptance runs preserved. That work is not implicitly merged into this branch. Remote main was checked at `e397d9a6e293850e8dff61a593e3ba39ee178400` at this turn's start. Devin retains non-20i/licensing work. Complete 20i, full cloud catalog, automatic provider images, VPS/SSH and the whole project remain unfinished.
