# Native 20i mailbox controls

Status: implementing on `codex/twentyi-mailbox-controls`, starting from client-search evidence commit `c00874b1`. The documented creation boundary and synthetic gateway checks are implemented. Dedicated native persistence, owner/client authorization, forms, history and packaged/browser acceptance remain required. There is no application route that invokes mailbox creation in this checkpoint. This is not completed hosting mail management or a published release.

## Verified provider contract

Primary source: [20i mailbox provisioning guide](https://docs.20i.com/api/provision-mailbox-api), published18 February2020, read15 October2026. POST `/package/{packageId}/email/{domain}` creates the requested mailbox with `new.mailbox.local`, `send`, `receive` and `password`. The documented send/receive values are strings `true`. Its response contains the domain name and a single mailbox object with local ID, null generatedId and an echoed password. No live call was made.

The implementation admits only this exact creation action. It does not infer mailbox listing/deletion, password changes, forwarding, quotas or autoresponders from the example. Those remain required work with their own verified contracts. Account entitlement and actual limits must be established separately; no plan, quota or price is invented.

## Existing execution infrastructure

`MailboxCreate` is a typed request/response boundary within the existing `TwentyIOperationAdapter`, `ProviderGateway`, fixed HTTPS transport and shared operation ledger. It adds no competing execution engine. The default adapter and generic owner grants cannot execute mailbox creation. A dedicated application caller must supply a current-authority password resolver.

Safe operation parameters are allocated package ID, canonical lowercase ASCII domain/local part and an opaque32-hex password reference. Raw passwords and arbitrary nested provider JSON are rejected before resolution. The resolver receives that reference and the exact operation, so it can bind a protected secret to its immutable authorization. It must reauthorize before opening the secret. The application offering accepts bounded lowercase mailbox names and12–128-byte passwords without control characters; these are application rules, not asserted provider limits.

The builder resolves the secret only for the request body. The fixed transport permits only the exact package/domain path and canonical creation body. It retains TLS verification, public-address pinning, fixed authentication/header order, no redirects/proxies and bounded request/response sizes. Every other mail-object action remains refused.

Creation is non-idempotent: the existing gateway claims its ledger before dispatch, sends at most once and preserves ambiguous outcomes. Replays never resolve either password or contact the provider again. A lost result or authority check after claim cannot resume the old request.

Only the exact documented response matching the requested domain, local ID, object type and password confirms creation.202, malformed/mixed envelopes, wrong targets/passwords and unknown fields remain uncertain. Provider response/password material is discarded before constructing the safe result. The result retains only the provider's local mailbox ID, within the existing190-character ledger column; the original request retains its package/domain context. This confirms provider-reported creation, not independent mailbox login or delivery acceptance.

## Current verification

Local PHP8.4 default and no-INI runs pass95 named mailbox contract/gateway checks each; the existing operation suite passes230 named checks in each mode. These are independent executions of the same suites, not additional unique coverage. Tests use the existing test-only file ledger and isolated transport; native MySQL/MariaDB and actual HTTP mail controls have not been exercised. Fixed transport policy regressions also pass, and changed PHP/Python syntax passes. GitHub PHP8.2/8.4 and combined acceptance are configured and pending the pushed source.

Coverage includes malformed target/body/refusal before secrets, response mismatch/secret handling, actual shared-gateway replay/conflict, timeout and result-storage failure, revoked secret authority, default adapter denial, long addresses using bounded provider IDs, and transport admission stopping before network. Named local reports are retained under `work/20i-mailbox-*`. Apache protection coverage includes the new internal class.

## Immediate native continuation

Reuse existing provider operation grants/ledger and explicit `provider_domain_links`; do not infer ownership from a mailbox address. The inspected inventory exposes approved original package/domain/client bindings and current observations. A dedicated creation record must preserve immutable current client/link/profile/credential context, owner authentication version, expiry, accepted mailbox name and protected secret reference. Current resource authority must be rechecked before decryption, DNS and send. Require a recent current observation and refuse moved/unlinked/stale resources and support impersonation.

Keep passwords in an authenticated encrypted payload bound to the request identity/context using the existing protected provider master key; never put them in grant scope JSON, audit, history, logs, form redisplay or ordinary result metadata. Extend explicit additive preparation with recovery-safe schema/history validation. A lost request family or ledger must not allow recreation/replay. Retain proof of failed/unknown/confirmed attempts and require operation-specific recovery before another creation at an ambiguous target.

Add real owner and client controls in the existing provider/admin and portal architecture, including explicit resource approval, CSRF/current-session checks, private result projection, client isolation, current quotas/capabilities and actual native/database/browser acceptance. Do not stop at this boundary. Native forwarding/autoresponders/quotas, other hosting controls and the full [20i requirements](TWENTYI_REQUIREMENTS_EVIDENCE.md) remain in scope.
