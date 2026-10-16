# Package document-root observations

This separate pure-component branch, `codex/twentyi-document-roots-contract`, starts at frozen `427e3a233706f22cd39918d8b97256a7fb2b1e90`. It implements a fixed read and bounded parser with synthetic transport tests outside the deployed application. It does not expose routes, save database records, resolve observed paths or operate on files. The current release candidate and accepted DNS proposal branch remain unchanged.

## Exact evidence

The owner reference `74ca50eb-af93-4063-a0a8-a89f5bc63ab4/Pasted text.txt` is 195,404 bytes, 3,116 lines, SHA256 `34e713c3c0b4145b6a5e3f5929d0bd470605793f98d2d917c2b28f21814c3e95`. Lines 371–377, retained in the [redacted reference](reference/20i-owner-api-34e713-redacted.txt), document GET `/package/{packageId}/web/documentRoots`. The response is a map from each DNS name on the site to its document-root subdirectory; an empty array means error. This package route does not need the unresolved allocated `domainId` required by DNS editing.

The retained provider-authored SDK is [immutable 696a5f12](https://github.com/LunarDevelopment/20iRestModule/tree/696a5f1299cd2b1e67ae7f688d644ac800f41b67), distributed as `20i/api-modules` 1.0.6 with 2018 20i copyright and a fixed `api.20i.com` origin. [REST.php lines 168–171](https://github.com/LunarDevelopment/20iRestModule/blob/696a5f1299cd2b1e67ae7f688d644ac800f41b67/lib/TwentyI/API/REST.php#L168) passes the GET response directly to JSON decoding. All23 retained files (19 PHP plus README, changes, license and composer metadata) match their immutable manifest hashes. Searching every retained PHP example finds no document-roots-specific envelope or richer schema. Source hashes and the bounded intake are retained in this branch's ignored `qa/contract-source-proof.json`, using the earlier immutable manifest at `work/catalog-dns-source/sdk-source-manifest.json`. No SDK example or live provider request was executed.

The evidence supports a direct string map, without a `result`, `data` or other invented wrapper. It does not define every path/name grammar, error payload or account entitlement. The restrictions below are local acceptance policy, not a complete provider schema.

## Typed read contract

`DocumentRootsBinding` copies the existing package assignment witness, rebuilding its canonical JSON, digest and scope to reject inconsistent restored objects. The copy detaches caller-owned scalar references. Its new operation is `package.document-roots`; its digest and scope bind that operation plus every retained account, profile, sealed credential, package, client, assignment and source witness. It does not inherit PHP-version permission or prove that the supplied witness is current in the database.

`DocumentRootsReader` makes one explicit GET through caller-supplied `HttpTransport` and `ProviderCredential`, using only the validated numeric package ID in `https://api.20i.com/package/{id}/web/documentRoots`. It supplies fixed Bearer/Base64 and JSON headers, a null body and a 15-second timeout. It accepts no caller URL, domain ID, path override or mutation. The binding, provider/account identity and observation time are validated before resolving the credential. The module is not registered in the shipping transport or executor.

`DocumentRootsObservation` retains an exact operation binding, observation time, finite state/reason and a list of decoded `{name,path}` text pairs. All-or-nothing projection prevents a malformed or secret-bearing row from leaving earlier rows visible. It has no filesystem or mutation methods.

| Response | Observation |
|---|---|
| HTTP200, valid nonempty flat object of accepted strings | `complete`, exact decoded entries in response order |
| HTTP200, `{}` with optional JSON whitespace | `complete` with an explicitly empty validated map; never proof of no DNS names, services or current hosting state |
| HTTP200, `[]` with optional JSON whitespace | `unavailable` / `provider_empty_array`, matching the documented error form |
| Null, scalar, nonempty array, nested/non-string values or invented wrapper | `unavailable` / `shape_unsupported` |
| Invalid JSON, UTF-8 or string escaping | `unavailable` / `malformed_body` |
| Duplicate decoded name, including equivalent Unicode escapes | `unavailable` / `duplicate_name`; no whole-map decoder can silently retain the later value |
| Unsupported name/path or bounded size overflow | Finite unavailable reason, no retained entries |
| Credential material in a decoded key or value | `unavailable` / `credential_echo`, no retained entries |
| HTTP/auth/rate/transport failure | Finite unavailable classification; raw body, headers and exception text are not retained |

The parser consumes only flat JSON string pairs and decodes each string token separately. It detects duplicate names before accepting a map. A whole-document JSON decode is used only to classify already-refused syntax/types, never to supply accepted map data.

## Defensive policy and path limits

The response body is at most 65,536 bytes and an accepted map has at most 256 entries. DNS names use a deliberately narrow lowercase ASCII multi-label subset: labels at most 63 bytes, total at most 253, no wildcard, trailing dot, IP literal or normalization. Punycode-shaped ASCII labels can be represented; Unicode names are not transformed.

Each path is an opaque UTF-8 string of at most 1,024 bytes without control characters. Empty strings, whitespace, Unicode, quotes, backslashes and unusual relative/absolute-looking text remain exactly as decoded. They are not normalized, joined, resolved, opened, executed or interpreted as instructions. Acceptance does not prove confinement to a document root. An empty path has no inferred root-directory meaning. Any later UI must escape these strings as text and must never feed them to filesystem or shell operations.

While it owns the supplied credential, the reader rejects its raw bytes or exact standard Base64 form in decoded map keys and values, including JSON-escaped forms. It retains neither credential nor raw response. This does not claim coverage for every possible encoding. Directly constructing a typed observation is not a substitute for the credential-owning reader or current application authorization.

## Future application prerequisites

- Root-reviewed additions to the existing shared typed executor, fixed transport allowlist and exclusive recorder; preserve scalar argument positions and the scheduler's transaction/mutex guard. Do not create a parallel provider client or arbitrary URL route.
- Current owner approval and account/profile/key/package/client/assignment authority before credential resolution and dispatch, with post-response revalidation. Do not infer DNS authority or package-domain identity from this map.
- Immutable intent/result/audit history, independent loss/order witnesses, explicit pending/failure/stale states, additive schema preparation and no read-path repair. A newer failed/unfinished read must not revive an earlier map as current.
- Explicit client visibility under the exact current assignment, escaped text-only UI, native database regressions, actual packaged browser evidence and regenerated installer/package acceptance. No automated file operation is part of this feature.

This pure branch changes no application, shared module, database schema, installer or licensing file. It remains outside the frozen 1.25 candidate. No native/storage/browser/provider acceptance is inferred.

## Local evidence

All four new PHP files pass syntax checks. Local PHP8.4 normal and no-INI runs each pass 143 distinct named checks. Cases cover fixed request/credential boundaries, empty object versus error array, malformed/non-string/duplicate maps, exact decoded opaque paths, limits, raw/Base64 credential echoes, all-or-nothing projection, finite errors, every assignment witness, forged restored fields and scalar-reference detachment.

The first local fixture encoded a tab where its expected path contained a backslash; the parser correctly refused it. Only that test input was corrected using JSON encoding, and both original failed reports remain under `qa/document-roots-first-*.json`. Corrected named reports are `qa/document-roots-{default,minimal}.json`. Root independently reviewed the exact stable three classes,143-case fixture and guide with no blocker for the pure scope. The approved narrow PHP8.2/8.4 workflow runs synthetic tests in containers with network disabled; its single automatic run is verified below. No application/shared wiring is authorized by this pure review.

### Exact remote evidence

Tested source `1e8ee56777845adc56832ed455ea20d7191bfabe`, tree `6ba84333706b481feda2436fb9aed78a0bc2f3a6`, passes the single automatic [run38856621132](https://github.com/afterburn25/Cajun_Web/actions/runs/38856621132). Both PHP8.2/8.4 Linux test containers had network disabled. Each mode has143 distinct passing names:572 executions total. Actual downloaded metadata, SHA256, byte size, CRC, four safe unique members, source/tree, all11 source/dependency/workflow hashes and PHP versions match.

| PHP | Artifact | Bytes | SHA256 |
|---|---|---|---|
| 8.2 | 12027870194 | 4740 | `05110966ba7ddd420d023b8b0b8b24818b8bdbd6c0817f7fce6458666d9aeeeb` |
| 8.4 | 12027586456 | 4776 | `fad7d91741615afb36eac5658c2a8bc20df29a8498bb10ed510cee13f9761b97` |

All artifacts and named proof are retained under `work/20i-document-roots-contract/qa/`, including `pure-matrix-verification.json` and its verifier. The evidence descendant changes documentation only. The reviewed current-authority, storage/recovery, UI and shared-executor prerequisites remain for a future application increment; none is implied by these pure passes. No PR, native/full dispatch, shipping change, live request or release occurred.
