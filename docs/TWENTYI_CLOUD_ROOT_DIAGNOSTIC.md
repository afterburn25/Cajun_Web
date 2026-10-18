# 20i cloud response type diagnostic

The original catalog diagnostic at source `1159648f968d46439f7e88a9cd0f6dbee3758643`, run `39090394774`, attempt 1, finished with failure. Its 39 offline checks passed. The package-types operation retained a safe shape; the cloud operation retained only `unexpected_root` after HTTP 200 and strict JSON parsing. Its code discarded the root type before recording the refusal. The original artifact does not identify whether the cloud response was null, a boolean, an integer, a number or a string, and does not establish entitlement, an empty catalog or an error-response meaning.

This separate follow-up proposes exactly one request:

`GET https://api.20i.com/reseller/*/cloudServerProductData`

It never repeats `/packageTypes`, selects a reseller ID or accepts an endpoint argument. It does not alter the original diagnostic, its failed run, retained output, production readers or the published release. Root must review these exact proposed bytes before applying and executing them. No live request has been made by this proposal.

The workflow admits only an owner-triggered push to `ops/twentyi-cloud-root-read` in `afterburn25/Cajun_Web`, with the exact commit message `ci: read one fixed 20i cloud root`, both actor fields `afterburn25`, and original attempt 1. There is no PR, schedule, main-push or workflow-dispatch trigger. The existing `TWENTYI_API_KEY` secret is referenced only by the diagnostic step, after offline tests. No key value is requested or inspected during preparation.

The original transport, parsing and disclosure controls are retained: direct verified HTTPS to port 443, TLS 1.2 or newer, no ambient proxy/cookie/netrc, no redirect, no retry, no request body, and no response headers or error-body disclosure. The request and parsing share a 35-second alarm; socket operations allow 10 seconds and the job allows 2 minutes. The body cap is 2 MiB, with strict HTTP framing, UTF-8 JSON, duplicate-key/nonfinite-number/surrogate rejection, and the existing depth/node/path/output bounds. An explicit TLS context avoids environment-enabled key logging.

For valid scalar JSON, the record has outcome `root_kind_observed`. Its shape contains only `root_type` from the fixed `null`, `boolean`, `integer`, `number` or `string` enum, the constant `root_kind_only_value_withheld` meaning, and false catalog/image-contract flags. No scalar value, length, hash or decoded content is exported. In particular, a returned string is not parsed again as JSON and is not treated as a URL, message or credential.

For an object or array, the record has outcome `shape_observed`. The original closed lexical vocabulary, type-only traversal and unknown-key collapsing apply unchanged. Limits fail closed; raw values, customer keys, identifiers, collection lengths, URLs and their hashes remain withheld. Recognized spellings still do not establish a provider field contract or image support. Other image spellings may remain hidden, so absence cannot be concluded.

`complete` and a successful diagnostic exit mean only that the bounded root-kind/shape observation completed. They do not mean that a catalog exists, product import works, prices have a known meaning, an image is available, or a response is not an error object. No image is fetched and no product is created or published. Later observations do not retrospectively identify the original failed response type.

Only `cloud-root-evidence/cloud-root-shape.json` is uploaded. The output directory/file must be new. The pinned checkout and upload actions, read-only checkout credentials, non-cancelling shared read-only concurrency group and seven-day artifact retention are preserved from the reviewed original workflow. Root must retain any future actual source/run/checkout and artifact as new evidence; no execution identity is invented here.

The offline suite preserves the original transport, JSON and disclosure cases with one-operation expectations, and adds all seven root kinds, scalar-value invariance, explicit package-operation refusal and invalid-scalar negatives. Fake HTTPS connections and blocked DNS/socket creation keep these checks offline. Passing them does not prove actual TLS, Linux alarm behavior, endpoint entitlement or a live response contract.
