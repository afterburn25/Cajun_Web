# Staff StackCP support access candidate

## Scope and user flow

The owner requested one-click access to client portals for support, including hosting administration. This increment extends the existing client support session with a separately authorized **Hosting support** page. It starts from frozen client StackCP source `51938fc80110931e80ebfd097066f2e4e1377df2` ([PR243](https://github.com/afterburn25/Cajun_Web/pull/243)) on `codex/twentyi-support-stackcp`. Its enclosing commit is `feat: open delegated StackCP accounts during client support`. It is a companion to PR243, not a replacement for that branch or a release.

An owner grants the administrator **Open explicitly delegated hosting control panels during client support**, alongside the existing client-support permission. In 20i account settings, the owner prepares the optional protected history, selects a named staff member and password-confirms access to a specific client's assigned StackCP account. Both global and client-specific support policy must also permit the session. A stale enabled grant can still be disabled.

Within that client's existing support portal, the staff member follows **Hosting services → Hosting support**, reviews the assigned account and explicitly opens it in a protected new tab. The page and audit history identify the real staff member and client. No client credentials are collected or impersonated. An unrelated ordinary client login may coexist in the browser under the existing support contract; it cannot replace the bound staff support identity. Contact provenance is refused.

This uses the existing default StackCP destination and documented provider exchange. Access is to the whole StackCP user. Fresh membership must include the selected identity and fresh grants must contain exactly the selected package; those observations cannot atomically constrain the remote session or prevent later remote permission changes. Ending support or disabling a local grant prevents new launches, but does not sign out an existing provider session. Custom destinations, remote revocation and interactive VPS SSH remain separate unfinished work.

## Current authority and durable effects

The support principal binds actual staff ID and authorization version, client ID and authorization version, exact browser-bound support session and fixed session expiry. Target staff permissions are evaluated with that staff member's current authorization version, never the reviewing owner's session version. Every effect checks the current support session, both staff permissions, client support policy, owner delegation, provider credentials, package assignment and original authorizing identities. Phase checks do not extend support idle time.

GET renders local observations and a signed review. It performs no provider request, credential opening or support-launch journal append. Ordinary portal resolution retains its existing support-view/idle accounting. Review expires within five minutes and no later than the support session. Explicit support-specific CSRF and consent are required on POST. A durable admission is committed before DNS, credential access, membership/grant reads or exchange effects. Five admissions per actual staff member across sessions and thirty per provider profile are allowed per rolling minute.

Membership, grants, default brand, token exchange and session exchange retain the fixed destinations and parsers from the owner/client implementation. Actual staff identity is carried into a distinct support handoff context. Each remote boundary and browser delivery rechecks current authority under the existing staff mutex with short transactions. One-use history prevents resend or redelivery after retries. Failed POST rendering retains the original approval and clears consent instead of silently creating a new approval.

The dedicated optional journal is `provider_support_stackcp_events`. Its independent schema, canonical signatures, checkpoints, source observations, actor records and corresponding general audit rows are validated. Permission scope includes provider profile, package and named staff member. Launch audit rows use the real staff ID. Only normalized observations and finite outcomes are retained; credentials, tokens, provider response bodies and destination session URLs are excluded. Uncertain outcomes remain visible and require a separately reviewed new attempt.

## Verification and remaining gates

The named local receipt is [support-stackcp-local.json](evidence/support-stackcp-local.json). It distinguishes pure/document/permission/context/executor/form checks, modeled HTTP/Chromium, source review and packaging checks. The separate native fixture uses disposable Linux SQL, actual staff activation and support sessions, original vault permissions, current mapped database port, real idle/cooldown timing, audit rollback and concurrency. This Windows workspace cannot execute that fixture faithfully; no native SQL success is inferred from model checks.

The full application workflow retains the exact 168 predecessor native commands and 182 browser / 226 final reports, then adds eleven native commands and twelve reports. The helper reconstructs every changed predecessor source byte before retaining the original 431 helper outcomes; new checks separately bind current source and require all added reports. The combined inventory is 179 native commands, 194 browser reports and 238 final reports. The HTTP fixture is a closed modeled business-boundary test, not evidence of an installed production portal or real provider login.

Native SQL, installed/full acceptance, normal reviewed integration and complete release/download gates remain required. Published v1.25.0 at immutable source `b79c67110829126916e3a65e616191542448bccc` remains unchanged. The separate reset-template [PR254](https://github.com/afterburn25/Cajun_Web/pull/254), payment-link privacy [PR253](https://github.com/afterburn25/Cajun_Web/pull/253), earlier queued candidates and Devin's non-20i work are preserved. No live provider exchange, mail, payment, deployment or SSH is performed by this work.

## Ownership and next step

- Catalog: support documents, storage, target/current permissions, optional schema and portable permission/document checks.
- Domains: one-use support executor, modeled executor checks and guarded native fixture.
- VPS: distinct support context, three bounded sender/parser/delivery union edits and closed HTTP/browser fixture.
- Root: permission editor, owner/support screens, route/navigation seams, form checks, additive acceptance, generated installer inventory, evidence, independent review and GitHub delivery.

Follow the first full exact-source application run through its original failures or completion. Do not cancel or duplicate queued acceptance to address queue delay. This feature progresses hosting support; it does not complete 20i, automatic full catalog/image import, domain purchase contracts or VPS SSH.
