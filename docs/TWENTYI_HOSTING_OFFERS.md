# Hosting offers from imported products

Status: successor implementation on `codex/twentyi-hosting-commerce`, based on frozen 1.26 candidate `6e3dbeff6dc6359a418837d0c95baa43e4c92533`. It is not a released or routed customer feature. The [1.26 candidate](RELEASE_SCOPE_1.26.0.md) remains separate and unchanged while its original full tests run.

The owner wants imported 20i products to be usable from the client portal. The full supplied API reference is already retained in [the reference directory](reference/20i-owner-api-34e713-redacted.txt); another copy is not needed. The observed hosting type identity and existing `addWeb` implementation support this local connection. Cloud product and automatic image contracts remain unresolved separately.

## Intended workflow

An owner selects an imported hosting draft and an exact active retail service plan with a current reviewed provider mapping. A separate immutable publication makes that offer available to clients. Generic catalog publication and catalog invoices continue to exclude hosting; an ordinary catalog invoice cannot authorize hosting provisioning.

The initial connection supports one shared-hosting package per request, USD monthly or annual pricing, and no separate setup fee. Product title, price and cadence must match the plan. Published inclusions, exclusions, limits and support terms come from the exact plan. Private draft wording, provider cost, reseller details and credential fingerprints never appear in public cards. Images use the existing local media system.

A signed-in client requests a specific published offer. An owner then converts that request into a service agreement for the same client and project. Request conversion and agreement creation use the same database transaction, with a retained unique link for retries. The existing consent, activation, recurring first invoice, externally verified payment, hosting order and explicit provisioning steps remain authoritative. Online card collection is a separate unfinished payment integration.

Editing a product, retiring a plan, withdrawing an offer, replacing a mapping or changing provider credentials prevents new requests against outdated details. Existing requests retain their original terms. This increment does not infer account continuity after replacing credentials; a separate explicit reassociation flow would be needed to reuse an import from different credentials.

## Implemented preparation

- Strict publication and request documents, current-availability checks and closed historical/public projections.
- A guarded agreement-writing composition point preserving the existing owner password, plan and client checks.
- Local publication, request, conversion, withdrawal and request-closure services, with current SQL context and retained request links. These services are not connected to routes yet.
- A neutral public read path that does not impersonate an owner, open provider credentials or make provider requests.
- Four additive history tables with explicit owner setup, exact document/audit checks, immutable request decisions and retained publication images protected from media cleanup.

Schema/setup and maintenance integration are present. Actual native database execution, controllers, CSRF routing, public/admin/client screens, next-step links, bounded query-work checks and browser acceptance remain pending. Do not mark ordering complete from component tests or source review.

## Verification and next work

The pure document suite passes 119 named checks in normal and minimal PHP 8.4.25 against the successor tree. PHP syntax and whitespace checks pass for the current new modules and extracted writer. Independent source review verified the original agreement writer body and examined publication/request replay and conversion boundaries. None of this establishes native SQL, HTTP, provider, payment or release acceptance.

The storage proposal also passes 85 modeled checks in each PHP mode; its modeled SQL is not native evidence. The focused workflow uses four actual environments: PHP 8.2/8.4 with MariaDB 10.11/MySQL 8.4. It runs the exact document suite, combined offer/request fixture and existing native service-plan regression. Its synthetic transport never accesses a provider account.

Next: retain and review all original four-environment results; connect the owner review and client request/progress screens; test the actual agreement/invoice/payment/order path with synthetic provider fixtures. Refresh installer inventory after each combined shipping change. Preserve every original 1.26 run and published 1.25 asset.
