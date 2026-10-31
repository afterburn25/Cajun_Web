# 20i account and catalog discovery contract intake

## Outcome — 2026-10-15

Automatic account/package-type discovery remains unimplemented. This investigation confirms the named read endpoints and identifies the exact missing response contracts; it does not add a reader based on inferred fields. The existing [owner-reviewed plan mapping](TWENTYI_PLAN_CATALOG.md) remains usable independently, with current connection, retail-plan, review-expiry and fulfillment gates.

Branch `codex/twentyi-catalog-discovery-contracts` starts at verified catalog source `8f7028dbbc23d9926d7d0ebde8d2f6b5e6861e98`. The original `codex/twentyi-catalog-controls` branch is preserved at that commit. The root integration candidate `38bb69ccd1c1fc45f479022fe11748ca78b756d2` is separate and awaiting its own acceptance. Main was inspected read-only at `1fcfb5e9482ff140fcc8f35091bfeef06d56b75f`; unrelated licensing tests were not merged or executed.

## Primary evidence inspected

| Source | What it establishes | What it does not establish |
|---|---|---|
| [Retained owner-reference map](TWENTYI_OWNER_API_REFERENCE.md), original upload SHA-256 `f7c738e746c2aebb9e1d047a555f3874a0766255795ed80edeebe8e599b9ec4d` | Original lines 648–654 name `GET /reseller/{resellerId}/packageTypes`; lines 30–37 name `GET /reseller/{resellerId}/accountBalance`. | The map explicitly says the full packageTypes response schema is elsewhere and balance fields/currency are unspecified. It is a selected summary, not the original 195,457-byte upload. The original was not located in the current attachment directory or retained reference candidates during this investigation. |
| [Official package provisioning guide](https://docs.20i.com/api/provision-hosting-package-api), read 2026-10-15 | `addWeb` accepts a string `type` corresponding to a packageTypes ID; its own response contains the newly allocated package ID. | No packageTypes read-response envelope, item fields or account entitlement contract. The addWeb response cannot be reused as the list schema. |
| [Official package type guide](https://docs.20i.com/reseller-hosting/create-hosting-package-types), read 2026-10-15 | Package types carry configuration and limits. Defaults can enable welcome mail, applications, SSH or Website Turbo. | No machine-readable schema, unit conventions, pagination or current account offerings. GUI options are not authoritative JSON field names. |
| [Official API documentation guide](https://docs.20i.com/api/20i-api-documentation), updated 2025-04-08, and [provider documentation update](https://www.20i.com/blog/our-reseller-api-docs-just-got-a-major-upgrade/), published 2026-05-01 | The provider directs users to My20i → Manage Reseller → API → View Documentation for endpoint methods, parameters and responses. | The public documentation link redirected to login during this read-only inspection. No authenticated console request or live API call was made. |
| [Owner module investigation, issue 65](https://github.com/afterburn25/Cajun_Web/issues/65) | The supplied `20i/api-modules` 1.0.6 wrapper has packageTypes examples; a separately authorized historical `/package` probe succeeded. | Generic wrapper methods and the `/package` probe establish neither packageTypes/balance schemas nor permission or availability for other operations. |

Public marketing quotas, prices and third-party wrappers were not treated as account contracts. The official WHMCS setup guide describes selecting a configured package type but does not supply the missing response schema. No credentials, customer records or raw account response were requested, read or copied.

## Exact information still required

| Contract area | Missing evidence needed before an executable reader |
|---|---|
| Package-type collection | Successful response envelope: top-level list, ID-keyed map or nested result; empty-list representation; complete versus paginated result; pagination parameters and termination; documented error/partial-result forms. |
| Package-type identity | Exact ID field/type and its relation to addWeb `type`; label/name fields; platform discriminator and supported values; whether IDs remain unique and stable within a reseller account. |
| Account and location binding | Exact reseller selector semantics, including wildcard behavior; fields indicating account ownership, location availability and current eligibility. Absence of a field must not be interpreted as access. |
| Limits and defaults | Exact field names, nesting, scalar types, measurement units and unknown/unlimited encoding for limits; exact defaults controlling welcome mail, applications, SSH and paid addons. The separately documented bundle-limit string `INF` cannot be assumed to apply everywhere. |
| Account capabilities | A provider-defined response or documented derivation identifying account-level available operations/products. Endpoint existence, a listed type or one successful package read is insufficient. No general capabilities endpoint is invented. |
| Account balance | Successful envelope and exact amount field/type, major/minor units, currency, credit/debit sign, tax and pending/reserved amount semantics; documented empty/error forms. A balance is not a per-package cost or a provider quote. |
| Provider cost feeds | Exact operation, price units/currency/period/tax, account/product applicability, quote expiry and side effects, if a future price feed is used. Current owner-entered private cost evidence stays separate from retail prices. |

The smallest useful next input is the provider-authored response/schema section for packageTypes, including one complete example and its empty/pagination/error rules. Account capabilities and accountBalance can follow independently; they do not need to delay a precisely documented packageTypes reader. Retrieve documentation without selecting **Try** or **Call Resource**. If an account response must later be collected for acceptance, that is a separately scoped account read; this investigation does not authorize one.

## Implementation boundary once the contract is available

Retain the closed read allowlist, bounded transport, sanitized projections and existing account/profile/credential scope. Add only evidenced fields, reject malformed or ambiguous response shapes and preserve explicit unknown values. Keep the last usable observation with a separate failed-refresh result; failed refresh must not silently replace it with an empty catalog. Observe changes without granting purchase or execution authority.

A discovered package type may prefill a proposed owner review. It must not automatically approve a mapping, change a client's accepted retail terms, infer a provider cost, grant entitlement or dispatch fulfillment. Replaced plan versions, profile/key changes and withdrawn/expired reviews continue to invalidate new preparation. Historical associations keep their immutable original evidence. Meaningful follow-up tests must cover response shape/size bounds, account isolation, stale observations, key changes, schema recovery and the owner review path before native/browser acceptance.

## Verification and continuation

This commit changes documentation only. Catalog source `8f7028` already has independently verified [full acceptance run 38819685543](https://github.com/afterburn25/Cajun_Web/actions/runs/38819685543) and [four-combination native matrix 38819685552](https://github.com/afterburn25/Cajun_Web/actions/runs/38819685552); exact counts, artifact hashes and package checks are retained in the [catalog evidence](TWENTYI_PLAN_CATALOG.md). Those results certify their original source, not the root's newer combined candidate or an automatic discovery implementation.

Next: obtain the precise provider response contract, implement the smallest evidenced read and owner-review prefill, then run its focused synthetic/native/browser checks. In parallel, root completes acceptance of the already implemented catalog/mailbox/registrar/cloud integration. No live API call, provider write, payment, customer mail, SSH, deployment, release or installer change occurred in this intake.
