# Dedicated hosting order fulfillment

Status: implementing from d3541693 on `codex/twentyi-service-orders` / [PR138](https://github.com/afterburn25/Cajun_Web/pull/138). This is an internal application service. No HTTP approval action or scheduled worker enables it yet. Automated provider catalog/configuration verification, the native approval screen, result recovery and authenticated active-service confirmation remain required. Published version stays1.24; no live provider operation is claimed.

## Authorization and execution

`hosting_fulfillment_execute` uses the existing client, accepted service order, invoice evidence, original service mapping, hosting journal and provider execution engine. Preparation alone cannot dispatch. A new request requires the current owner/password, exact current order and mapping hashes, an active client, current verified receipt or explicit manual-fulfillment policy, and the originally reviewed provider revision/credential context.

The separate configuration review records that the owner reviewed the provider account and type, package defaults and costs, and explicitly confirmed `CREATE` plus the accepted domain. Its provenance is `owner-panel-review`; it is not an API entitlement or catalog verification. Automatic package-type discovery remains unfinished. Package defaults may buy credits, send mail, install applications or assign access, so that review must not be replaced by an assumption that the minimal addWeb body has no additional effects. The native UI must present these distinctions when it is integrated.

One transaction retains the exact request, commercial evidence, configuration-review acknowledgements and a 15-minute dispatch authorization together with the existing hosting attempt. A shared internal persistence helper is reused; generic dispatch still rejects order-derived accounts. Only the invocation that commits a new approval receives a dispatch claim. The existing ProviderGateway, TwentyIOperationAdapter, FixedHttpTransport and ProviderOperationLedger execute the request. There is no second provider engine.

Current owner/session, client, order consent, payment/manual policy, immutable request/history, provider revision and credential context are checked before decryption, again when the credential is requested, and at the send boundary. Once a provider response exists, the trusted finalizer records reality even after session revocation. Return values describe whether the gateway attempt started; they do not independently prove that a network request was sent.

An assigned package ID remains accepted-pending on the original client hosting record. It is not an active service. Definite rejection can support a separate explicit local retry followed by a fresh, currently authorized fulfillment request. Ambiguous responses, transport failures and interrupted attempts cannot be resubmitted by replay or a new request identity. Local suppression does not promise exactly-once provider effects.

## Storage and recovery

The additive `hosting_fulfillments` family retains immutable approval bindings to existing order links and dispatch rows. Preparation is an owner action, never a GET; established missing storage, marker, approval row, original audit or original accepted provider result requires restoration. Repeated preparation preserves all histories. Strict columns, indexes and foreign keys remain enforced.

Existing order progress now validates retained fulfillment history when an order-derived provider dispatch exists. Clients still receive only the local service reference, creation time, lifecycle state and order-cancellation flag. Provider IDs, configuration review, payment evidence and credentials remain private. Cancelling the order does not erase an accepted provider purchase or delete hosting.

A failure recording the hosting result can leave the original provider acknowledgement in the existing operation ledger with an unresolved hosting dispatch. Replaying the submission cannot resend it. A dedicated verified local-result recovery path must connect those exact retained records; this increment does not reconstruct missing provider evidence or claim that recovery is complete. Authenticated exact-ID inventory confirmation remains separate work.

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
