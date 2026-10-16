# Registrar expiry component: retained contract and local parser

## Isolated scope — 2026-10-15

Branch `codex/twentyi-domain-expiry-contract` starts at frozen combined source `25e0de3b2b468d155c3bd51fb81bfaefda741c76`. Root assigned only a pure typed request, response projection and binding component while the separate 1.25 candidate and domain/PHP corrections continue. This component is outside that release queue. It has no shipping mirror, route, database schema, shared inventory or transport registration, installer change, credential lookup, dispatch method or background activation. It cannot perform a provider request by itself.

Owned files are [the descriptor/projection](../foundation/twentyi-domain-expiry/DomainExpiry.php), [the operation binding](../foundation/twentyi-domain-expiry/DomainExpiryBinding.php), [the observation](../foundation/twentyi-domain-expiry/DomainExpiryObservation.php) and [the standalone synthetic tests](../tests/domain_expiry_contract.php). Existing registrar and request/response value types are reused without modification. Current `main` was inspected read-only at `1aa421e57d36d6c47d3623f7dadad2967a907d1b`; no licensing or unrelated main changes were imported.

## Supported source and missing evidence

The retained 20i-authored SDK distribution at immutable commit `696a5f1299cd2b1e67ae7f688d644ac800f41b67` supplies two narrow facts:

- [test-real/03-get-domain.php, original lines 63–67](https://github.com/LunarDevelopment/20iRestModule/blob/696a5f1299cd2b1e67ae7f688d644ac800f41b67/test-real/03-get-domain.php#L63) selects a registered domain's name from `/domain`, calls GET `/domain/{name}/upstreamExpiryDate`, and checks the decoded value against a loose date/time prefix pattern. The pattern proves neither the full suffix nor its timezone, precision or null/error meaning. File SHA256: `a71f70231fb2815a8a5415b81ec39811f4d2c2b2a62ccf3a60b3d8b4bdcef00c`.
- [lib/TwentyI/API/REST.php, original lines 168–171](https://github.com/LunarDevelopment/20iRestModule/blob/696a5f1299cd2b1e67ae7f688d644ac800f41b67/lib/TwentyI/API/REST.php#L168) directly JSON-decodes the raw response, without extracting an envelope. File SHA256: `0f9b724e34bd26fa0cc85dc3d266e97472e2ae70396d2b7451f5995330860031`.

Composer identifies the retained distribution as `20i/api-modules` version1.0.6, and its license and Services class attribute2018 copyright to20i. Services fixes the API host at `api.20i.com`. The original bytes and provenance are retained in the domain agent's `qa/domain-contract-source` investigation. This is an old provider-authored example distributed in a third-party repository, not current account or live endpoint acceptance. No SDK example was executed or copied into the application. The immutable source pages were independently read for this assignment.

The owner's distinct full reference has195,404 bytes,3,116 lines and SHA256 `34e713c3c0b4145b6a5e3f5929d0bd470605793f98d2d917c2b28f21814c3e95`; see [retained intake](TWENTYI_OWNER_API_REFERENCE_INTAKE.md). Lines772–778 describe a **package/domain** upstream-expiry route. Its registry/transfer/deletion/null descriptions are not evidence that the registrar route has identical semantics. This component does not borrow them. A null registrar response means only that this component has no supported timestamp.

The complete registrar response grammar, accepted offset/fraction forms, null/error meanings, current account permission and relationship to billing or renewal remain unverified. The operation name is retained as provider terminology; an accepted timestamp does not establish whether a domain is expired, paid, scheduled for renewal, transferable or eligible for any purchase.

## Exact identity and request boundary

`DomainExpiryBinding` accepts an existing `DomainContractBinding`, rebuilds its validated scalar document and verifies its canonical JSON, digest and scope. The new operation is `domain.upstream_expiry_date`. Its own canonical document includes this operation and the complete registrar document; its digest/scope therefore changes with any profile, revision, credential fingerprint, allocated domain ID, registered name, client/link, link audit/event or registrar source change. Renewal-date and expiry scopes differ even when the registrar authority is identical.

`DomainExpiry::request()` accepts this typed binding, not a separately posted name or URL. It creates a descriptor with fixed HTTPS host, canonical registered-name path, GET method, JSON Accept header, null body and15second local timeout. It does not add a credential or resolve DNS. No arbitrary path, escaped separator, numeric ID alias, query, userinfo or alternate host is accepted through the binding.

A syntactically valid immutable value is **not current authorization**. Future wiring must reconstruct it under current owner/account/key/client/link authority, using the reviewed [domain-date history guards](TWENTYI_DOMAIN_CONTRACT_OBSERVATIONS.md), including the separate correction that compares selected intent sequences to exact original start-audit order. That correction is an integration prerequisite; frozen25e alone must not authorize this new read. Recheck authority before credentials/dispatch and after the response. Use the existing shared executor, intent/result journals and guarded recorder, with no parallel network client or execution ledger.

## Defensive timestamp subset

Only HTTP200 with a direct JSON string can produce a complete observation. The accepted decoded string must have this exact local shape:

```text
YYYY-MM-DDTHH:MM:SS[.fraction](Z|+HH:MM|-HH:MM)
```

These are **application validation limits, not a verified full provider schema**:

- Four-digit years0001–9999, an actual Gregorian calendar date and hours00–23, minutes00–59, seconds00–59. Leap seconds and24:00 are unsupported.
- Uppercase `T` and `Z`. Optional fractions contain1–6 decimal digits. Spaces, missing seconds, lowercase separators, comma fractions, named zones, extended years and trailing characters are unsupported.
- A numeric offset has two-digit hour/minute components and a colon. Absolute offsets are at most14:00; minutes must be00–59 and must be00 at14hours. `-00:00` is unsupported because it can express an unknown offset. `+00:00` and `Z` remain distinct accepted strings.
- A response is at most4,096 bytes and JSON nesting is at most8. These are local defensive bounds. A timestamp string is20–32 bytes under the chosen grammar.

The exact accepted **decoded string** is retained, including fractional trailing zeros and offset spelling. JSON escape spelling is not retained. There is no timezone conversion, host-timezone dependence, rounding, midnight assumption, current-time comparison or inferred expiry/renewal/payment status. `observedAt` is a separate supplied nonnegative observation epoch; this pure component does not read the clock.

Malformed JSON, unsupported string grammar, null and other JSON types produce distinct finite unavailable reasons. Object envelopes are not unwrapped. HTTP401/403,429,5xx and other non200 statuses have finite categories; a404/410 does not establish deletion. Failed observations contain no timestamp. Raw response bodies, headers, redirect targets and provider error strings never become public reasons or observation fields. The pure projector has no credential; a later credential-owning reader must additionally reject credential echoes before persistence, retaining the existing redaction discipline.

## Actual evidence and next step

Local PHP8.4.25 passes **139 unique named synthetic checks in normal mode and139 in `php -n` mode**. All four new PHP files pass syntax checks. Tests cover exact descriptor/binding identity, every authority field changing the operation scope, detached input references, inconsistent restored binding fields, valid boundary/leap dates and offsets, malformed calendar/time/offset/precision values, JSON shapes/escapes/body bounds, HTTP categories, raw-detail redaction, typed observation invariants and host-timezone independence. Neither test mode loads a concrete transport or performs network/database work. Named local reports are retained in `qa/domain-expiry-default.json` and `qa/domain-expiry-minimal.json`.

An initial local fixture failed while trying to initialize a readonly property directly from global scope. The test-only restored-object setup now uses `ReflectionProperty::setValue` for an uninitialized object; the checked constructor behavior is unchanged. Both complete named reports above passed afterward. No native, browser, package, PHP8.2, current-account or live acceptance is claimed by these local results.

Root independently reviewed the three pure source files and cleared their bounded descriptor, binding and parser policy. The explicit later authority and credential-redaction prerequisites remain. A [dedicated pure workflow](../.github/workflows/domain-expiry-contract.yml) runs normal/minimal PHP8.2 and8.4 in network-disabled test containers and retains exact source hashes plus named reports. It does not dispatch application acceptance or create a PR. The original [run38850341309](https://github.com/afterburn25/Cajun_Web/actions/runs/38850341309) is now terminal success on exact source `b1e7609e854cebe580673b61e1299fedc27437ec`, tree `b26018e2ab926d8f40a7c415e97499370b2b7966`. Both actual downloaded archives match GitHub sizes/digests, pass CRC and safe unique four-member checks, and preserve all nine expected source hashes. Each has139 unique passing checks in normal mode and139 in minimal mode (556 executions across both versions). `qa/pure-matrix-verification.json` retains full names and provenance. These cross-version pure results still provide no application/native/browser/current-account acceptance.

| PHP | Artifact | Bytes | SHA256 |
|---|---:|---:|---|
|8.2|12025955519|4,532|`5b3367acf9a9c99d2f092445cc44b14c13904988ced51c7151a36f5528b714ee`|
|8.4|12024854985|4,568|`15f73dfc860596035454ed8d439ab3e3cff581a95741eb828815fc15f46ef8f9`|

Next: preserve frozen b1e7609e, both exact archives, the current release candidate and all earlier evidence. Only after root approves application extension should a new increment add shipping mirrors, shared scalar operation/transport/recorder union, immutable storage and current-authority projection, explicit owner refresh and client visibility, meaningful history-loss/failure regressions, native four-way verification and actual packaged/browser evidence. Installer refresh is required when that later increment changes shipping files. Full20i remains unfinished.
