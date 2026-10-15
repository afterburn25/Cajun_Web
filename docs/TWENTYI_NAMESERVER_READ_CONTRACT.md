# Nameserver observation contract investigation

2026-10-15: bounded parallel follow-up on `codex/twentyi-nameserver-observations`, based on preserved `725573f0a74192b420677c3556b0c7a983825bdf`. The original `codex/twentyi-domain-controls` branch stays unchanged. Inspected remote main `a1e64f81502e8478dcb98152038a219eee1be4dd`; no root merge, runtime edit, API call or release. Root retains integration and release ownership.

## Confirmed evidence

The retained [owner source map](TWENTYI_OWNER_API_REFERENCE.md), original-upload lines1337-1352, identifies a package-scoped GET and the separate POST delta fields. The original full upload is still unavailable locally; this investigation reread the retained map, not the missing upload.

The provider-authored SDK snapshot [README](https://github.com/LunarDevelopment/20iRestModule/blob/696a5f1299cd2b1e67ae7f688d644ac800f41b67/README.md#L43) selects a registered domain's allocated ID from `/domain`, reads GET `/domain/{id}/nameservers`, and accesses its `result` property. It passes that value unchanged to a subsequent POST. This confirms the registrar route and property access in the historical example; it supplies no returned JSON sample or element validation. The separate POST example must not become evidence of a successful write response.

The same snapshot's [Services class](https://github.com/LunarDevelopment/20iRestModule/blob/696a5f1299cd2b1e67ae7f688d644ac800f41b67/lib/TwentyI/API/Services.php#L15) fixes the service host at `https://api.20i.com/`. Its [REST decoder](https://github.com/LunarDevelopment/20iRestModule/blob/696a5f1299cd2b1e67ae7f688d644ac800f41b67/lib/TwentyI/API/REST.php#L157) uses generic JSON decoding, without a nameserver model or validator. Generic transport handling maps404 to null and throws for other4xx/5xx responses; that does not define endpoint-specific empty, unavailable or success semantics. Its [domain tests](https://github.com/LunarDevelopment/20iRestModule/blob/696a5f1299cd2b1e67ae7f688d644ac800f41b67/test-real/03-get-domain.php) contain no nameserver response assertion. These files were read as text only; no vendor code or tests were executed or imported.

The current official [API documentation guide](https://docs.20i.com/api/20i-api-documentation), checked2026-10-15, directs full documentation access through authenticated My20i. The official [module-update guide](https://docs.20i.com/configuration/update-whmcs-module), also checked, links its current module download to My20i; following that link returned a login page. No account session, credentials or authenticated endpoint was used. General nameserver support articles do not define this API's response contract.

## Concrete missing contract

Before adding an executable nameserver reader, obtain provider-authored schema or a suitably authorized redacted response for the registrar GET above that establishes:

- The successful HTTP status and exact JSON envelope, including the type of `result` and each element. No bare string array or object array is assumed.
- Whether an empty `result`, null, a missing field or a domain for which nameservers do not apply means known empty, unsupported or failed. The current-state view must not convert an unknown response into an empty configuration.
- Whether a successful response is the complete current registrar nameserver set, and any partial or pending-state indicators required before describing it as current.
- Whether the registered-domain ID route remains supported for this account. The package-scoped example is not proof of equivalence or registrar authority derived from hosting.

The missing full reference was already requested; this record does not repeat that request. No guessed response fixture, transport allowlist expansion, schema, form or capability was added. Nameserver reads and writes remain unavailable. Existing explicit registrar assignments and their current-identity/context/freshness checks remain the prerequisite.

## Next implementation boundary

Once the read contract is established, use the existing fixed-host reader, shared credential/profile context, inventory intent/result history and rate limits. Bind each request and saved observation to the exact current registrar identity and explicit local client assignment. Recheck authority before dispatch and client disclosure; the newest relevant attempt, including unfinished or failed attempts, must invalidate an older success. Keep observation time and unavailable states visible. Do not use a hosted-domain link as registration authority or add a parallel execution ledger.

Then implement owner refresh and private client display, persistence/recovery, two-client isolation, changed-account/key/link checks, malformed/partial/latest-failure tests and real packaged browser coverage. Independently verify the write response and consent/reconciliation contract before enabling mutations. Native database/browser acceptance and actual account acceptance are separate from this source-only investigation.

## Validation and handoff

Only documentation changes were made. Documentation link checks and whitespace checks are recorded in the enclosing commit's session entry. Runtime, installer bytes, versions and previously verified artifacts are unchanged; no new runtime pass is claimed. Root may carry this narrow evidence note into the combined documentation while preserving its current concise status summaries.
