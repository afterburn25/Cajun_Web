# Dedicated hosting order fulfillment

Status: authenticated exact-ID confirmation is implementing from3929e891 on `codex/twentyi-service-orders` / [PR138](https://github.com/afterburn25/Cajun_Web/pull/138). Parent owner approval/recovery forms pass four native combinations; new confirmation and packaged browser acceptance are pending. Automated provider catalog/configuration verification remains unfinished. Published version stays1.24; no live provider operation is claimed.

## Authenticated service confirmation

The owner status action reuses the existing package inventory reader, durable refresh storage, shared cooldown and hourly limit. Its internal receipt identifies the exact newly recorded refresh; it never guesses the last observation. An additional internal authority check validates the existing order/client/owner and original provider revision/cipher before credential access, send and persistence. HTTP cannot inject a resolver, transport or scope callback.

Only the originally assigned accepted package ID, enabled flag, accepted primary domain and package type can confirm that original client service. A failed, disabled, missing or mismatched observation stays pending. It cannot prove absence or permit another purchase. The original provider ledger/attempt stays unchanged. The [official package response](https://docs.20i.com/api/retrieve-packages-api) documents these fields; [addWeb](https://docs.20i.com/api/provision-hosting-package-api) supplies the new ID. The application treats their agreement as confirmation of provider-enabled service state, not website, DNS or SSL health.

The saved refresh, selected package fields, digest, read-start/read-finish audits and checking-owner audit remain bound to the native hosting observation. History validation rechecks that original proof, including after later refreshes or context changes. Missing/tampered evidence refuses projection and adoption until restored. Conclusive native event, resource association and confirmation audit share one transaction using the existing reconciliation helper; audit failure leaves the completed authenticated read and pending service, with no new purchase. Repeated active confirmation is inert. This action can record a past purchase after commercial cancellation without authorizing any new purchase.

The owner card displays the last check/result and an explicit status-check form. Optional status storage is prepared through a password-confirmed additive POST, never GET; established missing tables/marker require restoration. Current client views retain their bounded local reference/status projection. New native and real HTTP/Chromium/TLS-fixture cases cover private controls, wrong intent, exact and disabled package responses, cooldown, replay, audit rollback, revocation and original proof restoration. Exact descendant CI/package acceptance is pending.

## Native owner and client screens

The existing service agreement now contains explicit additive setup, typed fulfillment approval, retained-response recovery and deliberate definite-failure retry preparation. Approval presents the accepted domain, original account/type and package label, and separately requires review of provider costs, defaults and account/type availability. It explains possible automatic purchases, welcome mail, applications and SSH grants. This records manual review, not an automated entitlement check. The current owner password, standard CSRF protection, exact order/mapping hashes and current authority remain required.

Clients see their local service progress, including a distinct unknown-outcome state, without provider IDs, private approval controls, credentials or payment internals. An assigned provider ID stays pending. Recovery verifies the original retained response without another request; retry preparation only resets a definite failed attempt for fresh approval. Missing original history is a restoration error, never silent setup. GET never performs migrations.

New native rendered-form cases and actual packaged HTTP/Chromium cases exercise these routes with synthetic orders/receipts and the isolated TLS fixture. They cover review/CSRF/password/hash refusal, one exact request, replay, client isolation, finalizer-audit failure, original-response recovery and definite-failure versus unknown outcomes. Changed syntax is locally checked; exact new native/full evidence remains pending. Parent8797 native38798355503 passed all four combinations; its full38798363572 is still in HTTP/Chromium.

## Authorization and execution

`hosting_fulfillment_execute` uses the existing client, accepted service order, invoice evidence, original service mapping, hosting journal and provider execution engine. Preparation alone cannot dispatch. A new request requires the current owner/password, exact current order and mapping hashes, an active client, current verified receipt or explicit manual-fulfillment policy, and the originally reviewed provider revision/credential context.

The separate configuration review records that the owner reviewed the provider account and type, package defaults and costs, and explicitly confirmed `CREATE` plus the accepted domain. Its provenance is `owner-panel-review`; it is not an API entitlement or catalog verification. Automatic package-type discovery remains unfinished. Package defaults may buy credits, send mail, install applications or assign access, so that review must not be replaced by an assumption that the minimal addWeb body has no additional effects. The native approval card presents these distinctions.

One transaction retains the exact request, commercial evidence, configuration-review acknowledgements and a 15-minute dispatch authorization together with the existing hosting attempt. A shared internal persistence helper is reused; generic dispatch still rejects order-derived accounts. Only the invocation that commits a new approval receives a dispatch claim. The existing ProviderGateway, TwentyIOperationAdapter, FixedHttpTransport and ProviderOperationLedger execute the request. There is no second provider engine.

Current owner/session, client, order consent, payment/manual policy, immutable request/history, provider revision and credential context are checked before decryption, again when the credential is requested, and at the send boundary. Once a provider response exists, the trusted finalizer records reality even after session revocation. Return values describe whether the gateway attempt started; they do not independently prove that a network request was sent.

An assigned package ID remains accepted-pending on the original client hosting record. It is not an active service. Definite rejection can support a separate explicit local retry followed by a fresh, currently authorized fulfillment request. Ambiguous responses, transport failures and interrupted attempts cannot be resubmitted by replay or a new request identity. Local suppression does not promise exactly-once provider effects.

## Storage and recovery

The additive `hosting_fulfillments` family retains immutable approval bindings to existing order links and dispatch rows. Preparation is an owner action, never a GET; established missing storage, marker, approval row, original audit or original accepted provider result requires restoration. Repeated preparation preserves all histories. Strict columns, indexes and foreign keys remain enforced.

Existing order progress now validates retained fulfillment history when an order-derived provider dispatch exists. Clients still receive only the local service reference, creation time, lifecycle state and order-cancellation flag. Provider IDs, configuration review, payment evidence and credentials remain private. Cancelling the order does not erase an accepted provider purchase or delete hosting.

A failure recording the hosting result can leave the original provider acknowledgement in the existing operation ledger with an unresolved hosting dispatch. Replaying the submission cannot resend it. The internal owner-reviewed local recovery service connects those exact retained records; it does not reconstruct missing provider evidence. Its native tests and UI acceptance are tracked separately below. Authenticated exact-ID inventory confirmation remains separate work.

## Verification

New native cases extend the existing recurring/order suite. They use real MariaDB/MySQL persistence and the real provider gateway, adapter, classifier and ledger with an isolated transport; no network or customer operation occurs. Cases cover owner/client isolation, typed consent, atomic approval/fence rollback, exact request bytes, verified receipts versus manual policy, payment reversal and revocation before credential/send boundaries, session revocation after acknowledgement, replay, competing processes, definite failure and explicit retry, false success responses, unknown outcomes, result-audit failure, lost history, repeated preparation and cancellation preservation.

PHP syntax, the retained pure hosting suites in both local PHP8.4 modes, Apache-driver syntax, deployment mirrors and installer inventory are checked locally. New native and packaged descendant acceptance is pending. Parent f6c has all24 focused workflows passing and its full38790555173 has passed native/browser/recovery and is running Apache; its browser artifact still needs inspection. Parent results do not certify this increment. [Prior mapping/pending evidence](TWENTYI_ORDER_SERVICE_LINKS.md).

## Native execution evidence and packaging correction

Source ab0ec30b7fe8c7bd9ab5a7580bab0db59372efb5, tree d6500f4cc1c8635b28a9bef8e37bef87839d0f92, passed [native run38795163945](https://github.com/afterburn25/Cajun_Web/actions/runs/38795163945) on PHP8.2/8.4 with MariaDB10.11/MySQL8.4. All four downloaded archives match their SHA256 and CRCs; each contains49 named fulfillment cases within368 combined recurring/order checks, plus82 order/42 association/38 preparation,108 ledger and68 form checks per mode. Subset counts are not additive.

- Artifact 12002226544: SHA256 `20d0da0cb92b2f8ea5bbcbc8037c29315e05c0ee34e5fe2e2f3e5905bb895ab6`.
- Artifact 12002026872: SHA256 `6009cd289ed08fd5ed7f76c4948639bb62321fcab9b91fedc2a0fbae17585d2c`.
- Artifact 12001378025: SHA256 `94f9baa693bbf6619b404601019d0bb025434fd1f77ff752e96bc75bbd94bf1a`.
- Artifact 12000709401: SHA256 `01f3f585182298a3c11cc143be37335e164485f2e583adb72c9b3359502982ed`.

Full38795172503 and domain38795172355 failed before application execution because the new fulfillment.php was locally CRLF while Git correctly retained LF; its installer inventory had hashed the local CRLF bytes. The clean Windows build also refused that exact working/source mismatch. This checkpoint normalizes the working file to its already committed LF bytes and regenerates the canonical installer hash. No runtime semantics or failing assertions are changed. Corrected-source full acceptance remains required; no package from ab0 is certified or published.

Parentf6c browser artifact11999264877 was downloaded and hash/CRC checked: SHA2561ef9c1be512eee34d89cea6cac8866889e5ae53b7e50fc72e29b7c23e9b829f1. Actual1652 HTTP checks pass, with empty PHP diagnostics and no unexpected browser/network errors. The actual320px admin/client order screenshots retain readable navy/gold progress cards. Its full38790555173 was still running Apache at inspection. This is parent evidence only.

## Exact retained-result recovery

From184c008b, `hosting_fulfillment_recover` requires the current owner/password, matching existing client/order/approval, the original provider revision and encrypted-credential digest, and a hash of the reviewed retained provider row. It uses the gateway's unchanged canonical fingerprint implementation to verify the original request and credential identity; the fingerprint helper is now available to this verifier. It does not call the gateway execution method, claim a ledger row, resolve a network destination or send a request.

Only a recorded packages.create result with one attempt and valid original claim/result times is recoverable. Accepted-pending needs its bounded allocated package ID; definite failure remains failure, and uncertain remains unknown. Missing, still-claimed, foreign, changed or inconsistent evidence is refused. Recovery never replaces a previously recorded different hosting outcome. Repeating recovery is inert. A later payment/policy cancellation cannot erase an already recorded purchase; recovery records the past fact and grants no further purchase authority.

The existing hosting finalizer now exposes its internal transaction-owned portion so the native result and recovery audit commit together. Audit failure rolls both back, retaining the original provider response and unresolved fence. Provider rows are not edited. Original response time and pending-versus-active distinction are preserved. The current original provider configuration/key is required to verify its fingerprint; if unavailable, restore that context rather than infer it. That internal-service-only limitation applied to the64d checkpoint; the newer native screen implementation is described above.

Local syntax, both pure hosting modes and the provider contract suite are checked. New native recovery cases cover mismatched proof/credential fingerprint, operation/ID/time/attempt tampering, missing/unresolved response, current owner/password/client isolation, audit rollback, later consent revocation, replay, and recovered pending/failed/unknown outcomes. Exact descendant database and packaged acceptance is pending; ab0's49 fulfillment checks do not certify this recovery increment.

## Verified recovery checkpoint and newer-main integration

Recovery source64d786da32b75484ca8d6298a83aef068e4881dc, tree2217968d9394254de4b37b96990a526ddbdda018, passed [native38796552263](https://github.com/afterburn25/Cajun_Web/actions/runs/38796552263) on all four supported PHP/database combinations. Downloaded/hash/CRC checked reports each pass75 fulfillment/recovery cases within394 combined recurring/order checks; the75 include26 newly added recovery checks. Retained order/association/preparation subsets remain82/42/38; ledger108 and forms68 per mode pass.

- Artifact 12003045393: SHA256 `0698dbc2065de2e492fa079e186e416716f0fe87abbda6146a25df0c73acdb30`.
- Artifact 12002656072: SHA256 `f41a6bdd744191b81be573c598b72ed0d66c53c583c7e46cefe66d02c38cac81`.
- Artifact 12002467363: SHA256 `7e198ae8b07d0e0f7b5b2e0c5773f780c588efaa42450238facc726b546fe912`.
- Artifact 12002457434: SHA256 `2b225bafc7057b62013efb553aad7311e6a4347c89fb5ac112e8bd130e16772f`.

The clean64d Windows build and independent verifier confirm all11 checksums, every archive member/directory, exact Git source and update payload: FULL5386, COMPLETE2643, UPDATE2630. These are development packages, not a published1.25 release. Full38796559132/job116634368707 has passed build/native and is running real HTTP/Chromium; recovery/Apache remain outstanding. Its retained hosting operation matrix38796558955 and PR native38796559191 also pass all4 jobs. Parent184c full38796052971 has reached HTTP/Chromium. Preserve both runs.

Parentf6c full38790555173 succeeded. Downloaded artifact12002476258,87,724,680bytes,SHA2564430492385c5a6acb2d0355a89a8150e57aeb14d67ca80c8a90fc3afbffc90ad, verifies all11 checksums, exact f6c source/identical test merge20d524ae,5381/2640/2627 members,1652HTTP/289recovery/1763Apache and no unexpected browser/PHP diagnostics. Its COMPLETE package is byte-identical to the independently verified61c Windows runtime build. This full acceptance belongs to the pending-state parent, not the newer execution/recovery service.

Main advanced from0b96 to161e3eddf21402f387bdd455e46cacabd9307786 with separate licensing/download work. The current integration retains that ancestry with64d rather than resetting either side. Inspection reproduced a PHP parse error from a committed conflict marker in database/schema/base.php; remove only that marker. Guard optional license tables before portal/admin GET queries and keep license administration under owner authority instead of ordinary website editing. Add real HTTP missing-table/restoration cases and a native authorization regression. Regenerate installer inventory. Licensing feature completeness is not certified by these compatibility repairs; its inherited limitations remain documented. Combined new native/full acceptance is required before release. No other repository or live system was modified.

## Verified native forms and inherited fixture correction

Source3929e891c51467663d0a3e243f45eab09db287e5/tree2afe933fd49a4d86763897f227dc830da3ba7cc9 passed [native38801170013](https://github.com/afterburn25/Cajun_Web/actions/runs/38801170013) on PHP8.2/8.4 with MariaDB10.11/MySQL8.4. All downloaded archives pass SHA256/CRC and every named report:32 native rendered-form cases,76 fulfillment cases within427 combined recurring/order checks,82/42/38 order subsets,108 ledger and68 forms in each mode. Subsets are not additive.

- Artifact12004616582: `9f0fcc736faa97631766470c234ef7424ed6a8ba08103402979da4e24a283ed1`.
- Artifact12004362300: `5dfa1ee215bb52487bbaf87067c5abbb563ccb79548ef3dba1de5516401bf88e`.
- Artifact12003639331: `f6a723ac02305cba459c784677e24d83750fd36bf1bcbe8f5553abb170cbbb3b`.
- Artifact12003314914: `bc095d2f2f905e3839056340423552afcac5fb1e17012fc5f3ac322deb9b77ed`.

Parent25a7 failed the retained unknown-outcome assertion because canonical timeout is failed plus awaitingReconciliation.3929 corrects both projections without changing the assertion or retry safeguards. Its repository check also exposed an archived relative link; corrected here. Parent8797 browser artifact12004012734, SHA071fd71fa6a44bf98543e6dcefcade8a0d28c59adc2b9d99de3bdbaed0268f0c, contains1651 successful HTTP checks followed by the optional-license fixture failure. The native base intentionally uses legacy schema, so those optional tables do not exist. The corrected fixture proves absence/readability/no GET DDL first, then explicitly creates only disposable canonical tables for the unchanged missing-table/restoration tests. Neither parent is claimed as complete packaged acceptance.
