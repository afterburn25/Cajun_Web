# Primary-client StackCP access candidate

## Scope

Owner request: continue complete20i and the client hosting portal. This increment starts at owner-launch source `3d449d79be02b5e19958bc1d44bddb0371309e46` on `codex/twentyi-client-stackcp`. It is separate from subdomain-notification PR242 and the other frozen candidates. Published1.25.0 remains immutable; neither release nor live deployment is implied.

The owner prepares protected client-access history in Provider settings, then confirms their password and enables the selected primary client to open the assigned whole StackCP user. Disable remains available for a stale enabled policy. A policy survives ordinary observation refreshes, but account, key, assignment, original granting-owner authority and client authentication changes invalidate access.

The client follows **Hosting services → Hosting control panel**, confirms the whole-account scope and opens the default StackCP destination in a new tab. A current primary client session is required. Contact accounts, support sessions and mixed staff/client sessions are excluded before session normalization. No additional client password is collected.

The session is scoped to the whole Stack-user. Fresh reads must show the selected identity in package membership and exactly one grant for that package. This cannot atomically restrict a remote session to a package or prevent later remote permission changes. Disabling local access prevents new launches; it does not sign out an existing provider session. Custom control-panel destinations and interactive SSH remain outside this increment.

## Authorization and transport

GET projects local records and prepares a five-minute signed form only; it does not decrypt provider credentials, append an admission or contact20i. An explicit CSRF-protected POST commits one client admission before DNS, reads or exchange effects. The client actor is distinct from the password-confirming owner and remains truthful in its journal and detached sender context.

Each admitted request reads the documented `/package/{id}/stackUserList` and `/reseller/*/stackUser/{id}/grants` routes with fixed HTTPS destinations and bounded parsing. It then uses the existing default-brand, token and session exchanges described in [owner launch](TWENTYI_STACKCP_LAUNCH.md) and the [retained handoff contract](TWENTYI_STACKCP_HANDOFF_CONTRACT.md). No undocumented provider endpoint or package-restricted OAuth claim is introduced.

The executor rechecks local authority before and after DNS, credentials, each remote phase and delivery. Protected history records phase intent before effects and records only finite results and normalized read witnesses. Provider bodies, credentials, bearer tokens and session URLs are never retained in its journal, HTML or flash messages. Browser delivery uses the existing strict303 helper with no-store/no-referrer policy and opener isolation.

Replays never resend or redeliver. An interrupted or uncertain remote outcome remains visible; a new attempt requires a fresh review and explicit consent. Failed POST rendering never creates another approval. Independent client history contains only that permission's attempts. Journal validation covers canonical bytes, signatures, schema, checkpoints, audit correspondence, original read provenance and phase ordering. Current authority is checked separately from historical validity.

New admissions are limited to five per client and thirty per provider profile within a rolling minute. Failed admissions count once recorded; a refused request has no provider effect, and exact replay does not consume another slot. These limits use the validated journal under the same transaction and lock. Raw and decoded read values are checked for credential echoes before any normalized value is retained.

## Files and ownership

- Catalog lane: `site/app/providers/client_stackcp_{documents,storage,permissions}.php`, optional schema and document/permission tests.
- Domains lane: `client_stackcp_launch.php`, fixed client read adapter/transport and portable/native executor tests.
- VPS lane: distinct `StackCpClientHandoffContext`, bounded shared sender/parser/delivery union, context and HTTP/browser tests.
- Root: owner/client UI/controllers, actual route/navigation seams, acceptance binding, installer refresh, documentation, independent review and Git delivery.

These module names describe repository files, not additional installation requirements. The PHP/MySQL shared-hosting deployment model is unchanged. Existing installations must retain database, configuration, private storage and original encryption keys together.

## Verification state

Local renderer checks cover actual HTML, CSRF fields, escaped metadata, unchanged failed-form proof, unchecked consent, excluded identities and finite history wording. Context checks exercise the actual shared sender/parser/delivery with synthetic responses and preserve the original owner behavior. Portable permission/executor tests model database/session boundaries; they do not establish native SQL acceptance.

The guarded native fixture requires Linux CLI, explicit test opt-in, the isolated test database and exact loopback host. These guards must not be bypassed for local convenience. The HTTP/Chromium fixture uses actual controller/rendering/security bodies with modeled business seams and a closed synthetic destination. It does not prove installed production authority or real provider login.

[Named local evidence](evidence/client-stackcp-local.json) records68 document,79 permission,194 context,88 executor and78 renderer checks per mode, unchanged315 owner/155 transport results per mode,59 actual modeled HTTP/Chromium checks and431 artifact-helper checks (all323 predecessor outcomes retained). Final shipping PHP/JS/dependency refresh and independent source reviews pass. The enclosing commit and its GitHub PR identify the first exact-head acceptance dispatch. Exact candidate/full application, installed browser, exact-main integration and publication/download gates remain required. No live provider operation or target-host deployment was performed.

## Known limits and next work

Whole-family validation deliberately refuses damaged or incomplete history. Its cost near the10,000-event defensive capacity has not been load-tested; preserve history and plan reviewed capacity work before reaching it. Existing remote sessions cannot be revoked through this local feature. Custom brands, delegated contacts, support launch, VPS SSH and unrelated provider capabilities require their own verified scope and acceptance.
