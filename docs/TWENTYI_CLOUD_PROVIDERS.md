# Account cloud-provider discovery

This isolated increment starts at `175930618ab349246020602e634ce55ee49f176b` on `codex/twentyi-cloud-providers`. Its scope is an explicit owner refresh of account-available provider identifiers and a dated saved observation bound to the exact profile revision and credential fingerprint. It does not discover server specifications, locations, prices, purchase rights or an SSH session. Forwarding remains frozen on its separate branch.

## Contract and provenance

The owner supplied a new complete reference at `74ca50eb-af93-4063-a0a8-a89f5bc63ab4/Pasted text.txt`: 195,404 bytes, 3,116 lines, SHA256 `34e713c3c0b4145b6a5e3f5929d0bd470605793f98d2d917c2b28f21814c3e95`. This is a different identity from the formerly missing 195,457-byte attachment. Examples are documentation and have not been executed.

Lines 2666–2672 describe `GET /reseller/{resellerId}/cloudProviders` as an array of cloud-provider identifiers available on the account. Lines 2673–2680 use one returned identifier as the string `provider` argument to a separate specs/zones lookup. That lookup has no complete response schema in the supplied text and is outside this increment. The documented reseller self alias permits a fixed `/reseller/*/cloudProviders` request without an arbitrary URL or caller-supplied reseller selector.

The pure parser accepts a flat list of exact, case-preserved identifiers, rejects duplicates and refuses the entire list when any item is unsupported. Its 256-item capacity and 128-byte ASCII token bound are conservative local validation policies, not documented provider maxima. Numeric-looking strings remain strings; objects, numbers, booleans, controls, surrounding whitespace and path-like values are refused. An observed empty list differs from an unavailable response. No unknown fields or raw bodies are stored.

## Implemented integration

Reuse the existing inventory reader, fixed transport, durable refresh intents/results, limits, authority checks and transaction-owned recorder. Add only the fixed read descriptor and typed record. Dedicated storage references the existing refresh ID, with an immutable observation and explicit owner preparation. No second runner, journal, generic URL or implicit refresh is introduced. Domain-contract and registration-period additions must remain a union in the shared executor; preserve the scheduler recorder extraction and its exact caller-owned transaction/lock guard.

Only the owner can prepare, refresh or inspect these provider choices. The view distinguishes the last saved observation from current usable context, including later failed or unfinished attempts, old dates, changed profile/key context and incomplete retained history. The identifiers are informational and do not feed a provisioning selector in this increment.

## Verification state

The parser, shared reader/projector and fixed transport contract pass 85 named synthetic checks under default and no-INI PHP. Existing inventory139, reader28 and cloud-reader82 cases pass. Touched PHP/Python syntax and whitespace checks pass. Root independently reviewed the source and cleared the paired-loss, sequence-order and unbounded PHP materialization corrections. Native SQL/PHP hash parity, recovery outcomes, actual packaged browser inspection and exact final acceptance are still pending. No provider request has been made.

The Windows canonical installer refresh completed successfully on this source, checking every shipping PHP file and installer JavaScript; it produced no release archive from uncommitted files. Documentation validation passes221 Markdown files and1575 local links. Existing registrar pure execution also succeeds. These are local checks, not native database or browser acceptance.

The native fixture runs on PHP8.2/8.4 with MariaDB10.11/MySQL8.4. It exercises actual authority/storage, complete and empty observations, bounded failures, transactional audit rollback, key changes, another owner's later failed/unfinished history loss, sequence tampering, common-audit/counter loss and exact restoration. It waits through the real30-second cooldown instead of rewriting retained timestamps. HTTP/Chromium cases use the packaged application and loopback TLS fixture, including owner desktop/mobile views, client non-disclosure and unavailable/empty/recovery displays. These fixtures are authored; passing execution is not yet claimed.

## Retained-history checks

Preparation initializes two independent counters with its marker and audit in one transaction. Each common refresh intent and result advances the relevant counter together with a dedicated witness. Read-only readiness reconciles fixed SQL counts and exact common/dedicated actor/detail correspondences; complete results require a projection, all other outcomes forbid one. The dedicated start witness binds the actual database insertion sequence. The result witness uses the same explicit length-prefixed family in PHP and SQL, covering common context, sequence, projection bytes/date and its protected-key HMAC. Lost paired rows/audits cannot turn an earlier observation current while counts or other witnesses survive. No automatic history repair or replay is introduced.

The owner projection fetches only the latest intent and latest complete observation with bounded sequence queries. A later failure or unfinished attempt wins even if its wall-clock timestamp is earlier; altered sequence is a recovery refusal. Saved identifiers remain historical after expiry or connection/key changes. All API-derived identifiers remain informational, with local parser bounds explicitly separated from provider guarantees.

## First exact native run and bounded compatibility repair

Source `0e2cab2b39a6b1a9f1fbe70454f6ab12371b173f` is pushed in draft [PR154](https://github.com/afterburn25/Cajun_Web/pull/154). Its focused run [38841123530](https://github.com/afterburn25/Cajun_Web/actions/runs/38841123530) is terminal: both MySQL8.4 jobs pass97 named native checks and85 pure checks in each PHP mode; both MariaDB10.11 jobs fail during initial readiness with `Illegal mix of collations for operation concat`, after8 named passes. All four exact archives were downloaded, metadata/digest/size/CRC/safe-member checked and every report inspected. This is partial compatibility evidence, not four-way acceptance. Original full38841123460 and PR-triggered runs remain preserved.

The correction prefixes every fixed witness CONCAT family with explicit binary data, preserving its PHP ASCII bytes and exact audit comparisons. A named native SQL/PHP complete-projection byte-parity assertion is added. Root independently cleared the four-line delta at storage SHA256 `62915fdd5e8a750c8911157b5856bebea029d937df29a98013427bb6514b7af1`; canonical installer refresh25937 and syntax/whitespace/documentation checks pass. New exact native/full runs are required; the original evidence is not relabeled. Verified artifacts:

| Runtime/database | Artifact | SHA256 | Bytes / named results |
|---|---|---|---|
| PHP8.2 / MariaDB10.11 |12021282898|`e4a3fbc0dd2d7e0523986f6247c02ab9223b035c098dc8098289db29ac204463`|534;8 passes then SQL exception|
| PHP8.4 / MariaDB10.11 |12020493857|`580e4c8aa170f8d103039d899b2a42d066b2a62045517b7912298109eb7c2b46`|534;8 passes then SQL exception|
| PHP8.2 / MySQL8.4 |12020974757|`241fae63356c431ee900d13bb33c2d1e2c19089b83beee21d6f8c25a9d36e64c`|4448;97 native +85 default +85 no-INI|
| PHP8.4 / MySQL8.4 |12021502345|`de6853b7e33517d8c0305d860e85495ad280be8fdac7de1faca6278e9bb5f864`|4448;97 native +85 default +85 no-INI|

Proof and original ZIPs are retained in ignored local `qa/38841123530/`. Original full checkpoint12021597291 (71,152 bytes,43 safe members, SHA256 `a5ced52836967c7d32de67c03bb5985dd805f95837c43ff3a0f9d8a300275c1a`) verifies the same native SQL failure after8 checks; its other reports were not independently evaluated here and no browser case ran. Full recovery continues. Attachment to the chat was attempted once and rejected by its100-item capacity; the GitHub PR exists. No provider account was accessed.

## Separate newly precise scalar read

Lines 2484–2490 document `GET /package/{packageId}/web/phpVersion`, returning the active PHP version string in the platform format, for example `php82`. This is useful evidence for a later bounded hosting read. No descriptor, transport permission or UI for it is added here.
