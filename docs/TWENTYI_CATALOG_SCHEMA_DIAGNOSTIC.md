# 20i catalog schema diagnostic

The owner has requested full product import, including images. The existing [hosting draft importer](TWENTYI_PRODUCT_IMPORT.md) uses saved hosting inventory and local image uploads. This separate diagnostic is proposed to learn enough response structure to plan the next bounded implementation. It does not change the importer or any production transport allowlist.

The retained owner API reference documents two metadata reads:

- `GET https://api.20i.com/reseller/*/packageTypes`
- `GET https://api.20i.com/reseller/*/cloudServerProductData`

The owner's import request authorizes these necessary metadata reads. The existing Actions secret `TWENTYI_API_KEY` supplies the raw General API key only to the diagnostic step. The secret value is neither requested from the owner nor read during proposal preparation. Root confirmed the secret name exists, with update time `2026-10-13T19:59:22Z`; that metadata does not establish the current key's endpoint entitlement.

## Execution scope

The proposal has not made a provider request. Its exact source must be reviewed before root creates a diagnostic branch from the current documentation-integrated main. The workflow runs only on an owner-triggered push to `ops/twentyi-catalog-schema-read`, with the exact commit message `ci: read two fixed 20i catalog shapes`, in `afterburn25/Cajun_Web`, on original run attempt 1. There is no pull-request, main-push, schedule, general dispatch, URL input or reseller-ID input. The fixed `*` means the authenticated reseller, as documented by 20i.

The diagnostic attempts each operation at most once. A finite failure on the first operation may still permit the distinct second operation; neither is retried. A total deadline stops remaining work. Missing or invalid credentials, unsupported runtime, or unexpected command-line input cause no request. Offline guards run before the secret-bearing step. Root must retain the actual workflow source, run/attempt, checkout and shape artifact if it is later executed; this document is not an execution receipt.

Requests use a direct verified HTTPS connection to `api.20i.com:443`, TLS 1.2 or newer, and a bodyless GET. The client does not consult proxy variables, a cookie jar, netrc, redirect handlers or application plugins. Redirects are refused; credentials are never forwarded. TLS key logging is not enabled by `SSLKEYLOGFILE`. No response headers or error bodies are exported.

Bounds are 10 seconds per socket operation, a shared 35-second alarm covering both reads, DNS and parsing, and a 2-minute workflow job. Each response is limited to 2 MiB. Compressed responses, conflicting Transfer-Encoding/Content-Length headers, unsupported transfer encodings and declared/actual body-length disagreement are refused; JSON must be UTF-8, with no duplicate keys, nonfinite numbers or isolated Unicode surrogates. Parsing/traversal is bounded by the response cap, 12 nesting levels, 50,000 nodes and 256 distinct shape paths. Excesses fail rather than yielding a supposedly complete truncated schema. The shape budget is 48 KiB per operation and the single output file is at most 100 KiB.

## Safe output and interpretation

Only `catalog-schema-evidence/catalog-schema-shape.json` is uploaded. An existing output directory is not overwritten. Upload uses immutable `actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02` (v4.6.2); root verified both official `v4` and `v4.6.2` Git refs and the [official release](https://github.com/actions/upload-artifact/releases/tag/v4.6.2) on 2026-10-18. Checkout uses the existing immutable release-workflow commit and does not persist Git credentials. The secret is scoped to the read step, not the artifact step.

Output contains finite operation/outcome identifiers, JSON types, and paths constructed from a closed vocabulary. `$` denotes the root, `[]` denotes an array element, and `{withheld-key}` collapses all unrecognized object keys. Empty containers have no observed child type. All values, collection sizes, identifiers, URLs, raw response hashes and unknown key names/hashes are withheld. Error text is a finite code; response or exception text is never printed. Because withheld paths collapse, heterogeneous records and unknown maps can merge observations; the result is not a lossless schema.

The reference vocabulary contains exact spellings already in the owner reference or existing inventory projection. Some are adjacent package-type CREATE inputs, which do **not** establish GET output. A separate closed catalog vocabulary probes generic identity, specification, pricing and unit spellings such as `id`, `name`, `specifications`, `price` and `unit`. The separate image vocabulary probes `image`, `images`, `imageUrl`, `icon`, `logo`, `thumbnail` and listed casing/underscore variants. Both probe lists are literal strings in the script, not claimed provider response contracts. A match reports a spelling and value type only. It may be a dynamic map entry rather than a schema field. A match cannot establish product identity semantics, price/term meanings, artwork availability, a usable URL, download rights or a supported import contract. No match cannot establish absence of images because other key spellings are withheld. Unknown/dynamic keys remain unresolved, without guessed semantics.

The output deliberately cannot import products or images. It does not enumerate catalog values, prices, terms, customer data or account identifiers. It does not fetch images, follow returned URLs, provision services, publish products or authorize those actions. Even a successful read establishes only the bounded response shape observed for this credential context and time; completeness, image policy and production mappings still require source review and documented contracts.

## Offline verification

`ops/twentyi-catalog-schema/test_diagnostic.py` uses synthetic responses and fake HTTPS connections, with DNS/socket creation forbidden. The adversarial cases check the actual request builder, TLS context, fixed paths/headers, finite errors, absent retries, response limits, JSON/Unicode rejection, secret removal, output overwrite refusal and disclosure policy. They also prove changed private values and dynamic key names cannot change the exported shape when their structure is the same.

Offline passing checks are neither provider authentication nor endpoint/schema acceptance. No production adapter, application test, database fixture, release acceptance gate or image publication policy is changed by this diagnostic.
