# Registrar expiry component: retained contract and local parser

## Current application increment - 2026-10-16

`codex/twentyi-domain-expiry-observations` starts at frozen a132b5b3 and normally merges pure b3c1cc31 at2856904e, then combined427e3a23 at869c5ed9. Root's acceptance candidate remains frozen separately. The application adds an explicit owner expiry refresh and a private saved-observation client page. This is implemented source under verification, not released or current-account acceptance.

The original route and decoded scalar evidence below remain authoritative, with the same2018 redistribution and missing full timestamp/null-contract limits. Only the documented registrar GET is admitted. The local timestamp grammar is unchanged; the exact supported decoded timestamp is shown with its separate observation time. It never determines current expiry status, renewal eligibility, a charge, an eligible period, fulfillment or any renewal action. The client's renewal-date and renewal-review links remain separate.

### One execution and authority path

The owner prepares expiry storage locally and submits the current owner password to refresh an explicitly assigned registrar domain. `DomainExpiryBinding` composes the existing exact registrar document and the SHA256 of the current sealed credential bytes. This additional digest complements the current profile/revision and raw-key fingerprint. The immutable context binds allocated registrar ID, canonical name, client/link revision, original link event and audit, exact registrar source and operation. Posted fields are hints revalidated against this current local context.

The shared reader appends named `expiryContext` at argument8; refresh appends it at argument10. Existing DomainContractBinding-only `domainContext` stays read6/refresh8 and `packageContext` stays read7/refresh9. Operation/context mismatch is refused before credentials; the exact GET path rejects query, encoding and unrelated suffixes. The same durable intent/result transactions, staff mutex, cooldown and actor limit are reused. No transaction spans network. Authority is checked before the secret/send and after the response. The private writer uses an exclusive typed scalar branch and validates expiry readiness before inserting the common result; scheduler guards and list branches remain intact.

The credential-owning reader rejects complete decoded values containing the raw secret or its exact Base64 encoding and returns the existing finite `timestamp_unsupported` reason. Pure tests include a binary reader-only encoded-substring input; that input is not claimed to pass the settings Unicode validator. Native fixtures use a locally accepted synthetic timestamp-shaped credential through supported profile save/current revision and restore it through supported save. No actual20i key disclosure is claimed.

### Independent storage and client projection

New `domain_expiry_bindings` and `domain_expiry_timestamps` families have independent preparation, counters and paired audits. Timestamp storage is bounded ASCII VARCHAR32. Exact original binding/source/actor audits, scalar hashes and common intent/result witnesses detect partial family loss. The latest registrar and per-link expiry intent selected by sequence must equal the latest exact paired start-audit insertion order. This prevents a later failed/unfinished intent from being moved below an older success to revive current authority; it does not claim every older historical sequence is immutable.

The latest failed, unfinished, stale, changed-account or changed-assignment observation suppresses the current timestamp. Same-context earlier successful values remain explicitly observed history. Reapproval and sealed-credential change hide old-context history. A client must sign in directly to the active assigned account; support and sibling identities cannot use this page. A client GET performs no secret resolution or provider request. Expiry reuses registrar authority helpers without preparing renewal-date storage; dates remain independently usable without expiry preparation.

### Owned files and shared integration

Owned application files are `site/app/providers/domain_expiry{,_storage,_views}.php`, `site/database/schema/domain_expiry_schema.php`, dedicated admin/portal controller and view files, four canonical foundation classes plus exact shipping mirrors, and pure/native/browser fixtures. Shared edits are limited to the current inventory reader/executor/recorder, exact transport admission and mirror, provider admin includes/actions, portal routing, service navigation and test/workflow/private-path unions. No licensing or provider write code changes.

Source review by root clears current shared and owned runtime boundaries; catalog independently clears final native c4a7ab89 and browser1d83fc6d fixture source. Local normal/minimal PHP reports each pass139 pure contract and54 credential-reader/fixed-transport cases. Native fixture covers current authority, different clients, preparation independence, actual supported credential rotation/restoration, null/malformed/unsupported scalar persistence, exact failed/unfinished order-recovery denial before DNS outside caller transactions, original evidence restoration, mixed scalar/list rollback and audit/family loss. Existing date native adds an explicit no-expiry-preparation assertion. The focused four-way workflow runs expiry, dates, package PHP and scheduler as separate processes and independent schema prefixes. Native execution is now verified on exact f42ce633 as recorded below; actual packaged browser acceptance remains separate.

Packaged HTTP/Chromium coverage is prepared with a dedicated synthetic owner created and activated through real staff forms, genuine shared cooldown waits, and preservation of every inherited intent/audit byte. It exercises owner/client desktop/mobile views, exact timestamp preservation, failed/null latest state, assignment reapproval, sibling isolation, private headers and12 private paths at root/subfolder installations. Browser order is dates/cloud/periods/PHP/expiry/scheduler-last. No standalone full dispatch is authorized for this branch yet; root coordinates combined acceptance after source and focused evidence. Actual screenshots remain pending.

The separate root427 domain-date run38853749575 has all four actual archives verified:98 native,87 default and87 minimal cases each. Original f725 full38849384530 is terminal failure at the known period fixture; all49 date browser checks and four actual date views were reviewed. Its final12027326438 verifies122 reports/10 diagnostics,289 recovery and2167 Apache passes,319 unchanged checkpoint files/198 images,11 checksums and exact Git packages. These are existing date evidence, not expiry acceptance.

Installer refresh completes successfully; all2,772 shipping files match staged Git bytes and2,771 generated inventory hashes. Thirty-nine local syntax/AST/mirror/pure checks and1,615 local documentation links pass. The single automatic focused matrix is now verified. Next: preserve frozen f42 and coordinate actual packaged browser acceptance with root, retaining original failures. Root reviews and normally integrates ready source before actual combined package/browser/recovery/Apache, exact-main and release gates. Full20i and1.25.0 remain unfinished. No live provider request, charge, mail, SSH, deployment or publication occurs in this increment.

### Reviewed fixture correction after combined427 failure - 2026-10-16

Read-only inspection of combined427 browser artifact12027447378 identifies its period setup check as comparing a full original-owner row from before a successful login, although `login_user()` updates `last_login`. Expiry browser fixture1d83 has the same source pattern but has not executed. From frozen9e33, the expiry-only correction explicitly checks successful setup dashboard login, all account fields except the permitted login-time bookkeeping and the exact original staff profile, then uses the entire post-login row for activation and final equality. Password hash/auth version, every inherited intent/audit byte, six-read outcomes, activation checks, rates and real cooldown remain enforced. Redacted setup diagnostics contain outcomes and changed field names only. Local AST/whitespace pass; actual corrected packaged acceptance is pending root coordination. Shipping/native/workflow bytes and installer remain exactf42; the original427 failed run and native evidence are preserved. Local source/artifact proof: `qa/expiry-owner-login-fixture-review.json`. Root reviewed fixture b8540495 and authorized this test/docs-only descendant; no separate manual full or focused dispatch is requested. The existing push-path filter can automatically queue focused checks.

## Exact application-focused evidence - 2026-10-16

Exact f42ce633 focused38855352194 is terminal success. All four downloaded PHP8.2/8.4 and MariaDB10.11/MySQL8.4 archives verify SHA256, size, CRC, safe unique eight-report membership and all named outcomes. Per environment:111 unique expiry native,99 unique renewal-date (including no-expiry-preparation),99 PHP executions/98 distinct labels,102 unique scheduler,54 normal+54 minimal expiry reader and139 normal+139 minimal pure contract. The inherited duplicate PHP label is recorded explicitly. Actual packaged browser acceptance remains pending; no standalone expiry full was dispatched.

[Run38855352194](https://github.com/afterburn25/Cajun_Web/actions/runs/38855352194) tested `f42ce633e89bca930c9b29f8a167b9ba998fae05`. Retained full named reports and metadata: local `work/expiry-f42-native-verification.json`. Thirty-eight critical named expiry cases cover failed/unfinished altered-sequence refusal before DNS, credential rotation/echo/restoration, independent preparation, mixed scalar/list rollback and unavailable scalar persistence.

| PHP/database | Artifact | Bytes | SHA256 |
|---|---:|---:|---|
|8.4-mysql-8.4|12027217624|13754|`c629ce4a6882d61ffd365f9ebdb312db346806b81197c9b313dbfdf2ed708a51`|
|8.4-mariadb-10.11|12026478896|13755|`8c763a6d75cc018022c3e8ba09ca1a92a80baa4b7681dd9efb071a042a2ec004`|
|8.2-mysql-8.4|12026344456|13746|`a3c3e7fd3d55ad30a11b78c6dc7e486d3113b220b32c859d8b8e974801a8b63d`|
|8.2-mariadb-10.11|12026278376|13741|`1a64865c49c0480a1ba3e203e6b423ce201aa903d2910dd36948dc28edb33911`|

This is synthetic native database/application execution, not live20i or packaged browser acceptance. Browser fixture1d83fc6d remains source-reviewed only. The docs-only evidence descendant preserves every f42 shipping/test/workflow byte and generated installer.

## Historical pure scope — 2026-10-15

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


## Preserved original date full result - 2026-10-16

Original f725 full38849384530 completed with its known empty-period browser failure. Final artifact12027326438 has163,374,080 bytes, SHA256 `6cd42c15d7ac2a577a7767fdb39eed358b82427eac10d8649ef36c8ec1d7e244`,440 safe unique CRC-clean members,122 named reports and10 clean diagnostic reports. HTTP2131 has exactly the retained failure; all289 recovery and2167 Apache checks pass. All319 browser-checkpoint files and198 screenshots are byte-identical to the previously inspected checkpoint. Eleven checksummed files and exact Git FULL5,593/COMPLETE2,749/UPDATE2,736 members verify. No same-source Windows package comparison was performed; the final CI source-mutation step was skipped after the browser failure. Proof: local `work/domain-date-f725-final-failure-verification.json`. This closes original evidence follow-up and does not certify the new expiry browser or current combined candidate.
