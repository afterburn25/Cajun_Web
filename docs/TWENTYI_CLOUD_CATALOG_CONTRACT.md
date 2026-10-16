# Cloud catalog request contract

This isolated component describes one unsent specs/zones lookup. It does not provide a usable catalog, call 20i, authenticate a current account, interpret responses, offer products or create servers. It starts from root candidate `427e3a233706f22cd39918d8b97256a7fb2b1e90` on `codex/twentyi-cloud-catalog-contract`; that frozen application and the original PHP observation acceptance remain separate.

## Verified sources and remaining schema

The [owner reference intake](TWENTYI_OWNER_API_REFERENCE_INTAKE.md) retains the distinct 195,404-byte source with SHA256 `34e713c3c0b4145b6a5e3f5929d0bd470605793f98d2d917c2b28f21814c3e95`. Its line-aligned redacted copy is in `docs/reference/20i-owner-api-34e713-redacted.txt`. Examples were read, never executed.

| Source lines | Established contract | Still unknown |
|---|---|---|
| 1 | Reseller `*` means self; JSON-object POST bodies are supported. | Endpoint-specific response shapes. |
| 2666–2672 | Account provider list; use a returned exact identifier for the next lookup. | No product/spec/zone entitlement follows from an identifier alone. |
| 2673–2680 | `POST /reseller/{resellerId}/cloudProviders`, sole example field `provider` as a string, described as a specs/zones lookup. | Top-level response kind, keys, nesting, spec/zone types, empty/null/partial meanings and units. |
| 2711–2717 | `GET /reseller/{resellerId}/cloudServerProductData`, described as product specifications. | Actual response schema and account/pricing/eligibility semantics. This GET is not implemented here. |
| 2681–2710 | Server creation and renewal charge the account. | These operations are excluded completely. |

The official [Managed Cloud Server API Guide](https://docs.20i.com/api/managed-cloud-hosting-api-guide), updated 2025-11-10 and reread 2026-10-16, has static spec/location tables and order instructions. These do not establish either catalog endpoint's JSON response or current account availability. Retained raw public HTML is 192,565 bytes, SHA256 `19029ea999a7ea6c5997fe8f4677eaec74e273dd120be62dc5c2d2d5a710e26f`.

The official [Cloud Hosting API guide](https://docs.20i.com/managed-cloud-servers/api-cloud-hosting), updated 2024-02-13 and reread 2026-10-16, supplies other cloud inventory/addWeb examples. Its API-documentation and SDK links redirect to My20i login; no login was attempted. Retained raw public HTML is 178,305 bytes, SHA256 `a223cbc84b4fa2a974da63975dc48dae37d320fb3fe489dedb3672c0195201ce`.

Retained provider-authored SDK1.0.6 is redistributed in [REST.php at immutable696a5f12](https://github.com/LunarDevelopment/20iRestModule/blob/696a5f1299cd2b1e67ae7f688d644ac800f41b67/lib/TwentyI/API/REST.php). Its SHA256 is `0f9b724e34bd26fa0cc85dc3d266e97472e2ae70396d2b7451f5995330860031`. Lines184–200 send JSON fields and directly JSON-decode the returned body; GET lines168–171 also decode directly. [Services.php](https://github.com/LunarDevelopment/20iRestModule/blob/696a5f1299cd2b1e67ae7f688d644ac800f41b67/lib/TwentyI/API/Services.php), SHA256 `ef33bfb747e5c2917ab57c0c9f73403c31af70007bd66c7b08571a3803cde0c1`, inherits REST. This is wrapper behavior from2018, not current response-schema or live-account acceptance. No `result`, `specs`, `zones` or product-price envelope is invented.

## Pure binding and request

`CloudProviderCatalogBinding::fromSavedList()` accepts an exact-key context, a list of identifiers and one selected string. It requires the supplied context to describe the latest COMPLETE, zero-skipped `cloud.providers.list` result with its exact profile scope. It retains profile/revision, key fingerprint and sealed-credential hash, source ID/sequence/context hash/proof, dates and the hash of the canonical identifier document. The complete list and selected identifier form part of the immutable canonical binding hash.

The existing identifier/list validators preserve case and numeric-looking strings without trimming, coercion or inferred brands. Membership is strict. Unsupported/duplicate entries refuse the whole supplied list. The document hash must match exactly. The existing local fifteen-minute age and thirty-second future tolerance apply at construction and again at request description. These bounds are defensive local policies, not provider limits.

`CloudProviderCatalogRequest::forBinding()` additionally requires the exact reviewed binding hash. Its private constructor fixes `POST https://api.20i.com/reseller/*/cloudProviders`, JSON headers and a body with only the selected `provider` string. It accepts no host, URL, reseller, arbitrary headers, product/spec/zone or price input. It has no credential, send/read method or response parser and is deliberately not an application `TransportRequest`.

**A syntactically valid supplied tuple does not prove database provenance, HMAC validity or current authorization.** Future application wiring must read and independently validate retained intent/result/document/audit/count witnesses using the existing cloud-provider readiness machinery under the staff lock. It must prove current owner permission, enabled profile/revision/key, latest complete list, exact membership and current dates, then recompute the whole binding before review and immediately before any send. Loss of a later failed/unfinished intent, changed ordering, revocation, key replacement or missing original witness must refuse authority. This component cannot perform those checks without the application.

No transport allowlist, inventory register, recorder, table, page, mirror, installer, scheduler or provider-operation ledger changes are part of this branch. Usable catalog implementation remains dependent on a verified response schema, followed by separately reviewed application authority, typed persistence and actual native/browser acceptance.

## Checks and evidence

Local PHP syntax passes for all three new PHP files. The exact pure fixture passes141 distinct named cases in both normal and no-INI PHP modes. It covers the fixed request, strict context fields, case/numeric identity, full binding/document changes, stale review hashes, unsupported/partial/later-result context, freshness boundaries and immutable detached data. These are synthetic contract checks; they do not establish any database, provider or browser behavior. Root reviewed exact binding7cc758ab/request574d721a/test93c3f406 with no source blocker for this pure-only scope. Exact PHP8.2/8.4 focused acceptance remains pending; a single narrow pure workflow runs on push. No native/full application workflow is requested.

Parent PHPcfc6 remains frozen. All four native archives pass99 executions/98 distinct labels plus99 pure checks per mode. Its original full38850899573 browser checkpoint12026415411 (SHA256 `f5e570525b7bff3b7649759ed748c5267bac893a5051d70ef624a1fd1e0d3641`) has2093 HTTP executions/2082 labels,28 scoped PHP cases and six inspected actual owner/client desktop/mobile screenshots. Recovery/Apache/final acceptance remains separate. Root427's assigned PHP/cloud matrices38853749646/38853749580 are terminal success: all eight artifacts have verified metadata, digests, CRCs and named results, with identical root427/CI-merged106 tree925ec3ba. No pure-only work here enters that frozen candidate.

## Noncharging diagnostic proposal — not executed

A separately approved diagnostic could make at most one request to each named lookup: the exact bound provider POST above and bodyless `GET /reseller/*/cloudServerProductData`. Root must first review its source/redaction and obtain the owner's operation-specific authorization. Use the existing vault, owner checks, fixed HTTPS/DNS/TLS boundary, limits and cooldown. No generic runner, redirects, retries, polling, server orders, renewal, upgrade, power, VNC or SSH endpoints belong in it.

Proposed defensive limits are1MiB, JSON depth12,4096 nodes,256 collection entries and20 seconds, with failure on exceeding a limit. Process the body only in memory. Reject credential/raw-bearer echoes before serialization. Retain no response values, headers or raw body: only status, bytes, kind, cardinalities and a type tree. Unknown property names become ordinal labels unless included in a separately reviewed structural-key allowlist. Preserve source/request-binding/output hashes and date; do not create a usable-catalog or provider-operation success record. Ambiguous shape requires reviewed source or a redacted sample before a parser can be designed. This plan grants no permission to run it.
