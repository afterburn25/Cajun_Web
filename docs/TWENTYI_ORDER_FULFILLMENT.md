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
