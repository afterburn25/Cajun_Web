# Hosting confirmation and the first invoice

Development branch: `codex/twentyi-hosting-first-invoice`, starting at `0c7cbae34220717c72e2f3dbeed1b41ca3545dbf`. This is separate from the preserved full-acceptance branch and the unpublished 1.26 release candidate. Published 1.25.0 remains unchanged.

The intended experience is one client confirmation showing the hosting service, exact price, full first billing period, due date and project choice. That confirmation will create the agreement and first unpaid invoice together. Card payment, provider provisioning and confirmed service availability are separate steps. The current implementation stage contains pure commercial documents only; it does not yet create an invoice or expose a checkout page.

## Commercial documents

Existing format1 publications and requests retain their original behavior. A new format2 publication requires an explicit owner checkout policy. It supports a primary client, one existing project belonging to that client or a new dedicated hosting project, and an invoice for the full monthly or annual period beginning on the displayed local date. Existing USD, positive-price and zero-setup-fee restrictions remain. No offer is automatically converted to the new mode.

`HostingCheckoutReview` binds the exact publication and plan, client, stable request identifier, project instruction, price, calendar and expected effects. Its lifetime ends at the earliest of the chosen short timeout, provider-mapping expiration and next local date. A changed date or source requires a new review; it never silently rebases the first period. `HostingCheckoutConsent` records one explicit acceptance of the exact displayed review fingerprint. Historical parsing retains the original terms without consulting current credentials or availability.

The public projection contains commercial terms and the selected project instruction. Private plan journals, provider mapping, wholesale costs, credential fingerprints and authorization details must remain server-side. Documents validate supplied facts; they do not authenticate a user, establish project ownership, grant billing authority or prove a database record was not rewritten. A valid alternate identity creates a different document and fingerprint. Native storage must bind the original request, authenticated client, exact retained consent and independent audit history.

## Client-attributed subscriptions

The next pure stage adds subscription format2. It retains the exact accepted publication, plan, composite consent, request fingerprint and resolved project. Its creator is the actual client. Two fixed initial events distinguish that client's consent from the application's automatic local activation; no staff member is recorded as performing either action. Later pause, resume and cancellation keep the existing actor and lifecycle rules. Existing format1 documents retain their fields, byte limit and behavior.

This is document validation, not a native writer. Database code must still authenticate the client, verify current owner authority and project ownership, and bind independently retained rows and audits. An initial format2 document is the complete two-event prefix; empty or one-event format2 documents are invalid. The separate bounded checkout size accommodates its retained documents without increasing the legacy limit.

Applied-source tests pass65 new subscription cases and67 existing cases in both default and minimal PHP8.4.25; the complete legacy output is byte-identical to the baseline. Independent review also verifies direct-parser loading without a dependency cycle. Commands: `php tests/service-plans/checkout_documents.php` and `php tests/service-plans/documents.php`, each repeated with `php -n`. The related130 checkout and119 publication/request cases pass again after applying this shared document change.

## Earlier commercial-document validation

Applied-source tests pass130 new checkout-document cases and119 unchanged publication/request cases in both default and minimal PHP8.4.25. The tests cover exact terms, client/project facts, expiration, DST and skipped dates, legacy-route refusal, historical parsing and private-field exclusion. Independent review repeated the original failing Santiago midnight witness against the corrected source; it now expires at the actual next local date and refuses next-day historical consent. Original failure evidence remains retained.

Run `php tests/hosting_checkout_document.php` and `php tests/hosting_catalog_document.php`, repeating each with `php -n`. These are pure tests with synthetic identities and current-context facts. They do not execute authentication, SQL or an invoice. The existing385-check artifact-helper suite also passes with the updated source pins. The new checkout test is not yet wired into the full application workflow; it will accompany the coherent native increment. Existing workflow commands and their original pending runs remain intact.

## Remaining implementation

1. Finish native client attribution for subscriptions, and add it to recurring schedules and invoice origins while preserving every legacy creator, document and audit. Pure subscription attribution is implemented; schema and native history acceptance remain separate.
2. Compose project selection or creation, client consent, explicit automatic local activation and period-zero invoice in one guarded database transaction. Recheck the real client, current publication owner permissions, exact source and project ownership. Roll back the entire operation on failure.
3. Return the original owned result on exact completed replay. Reject changed input, partial history or missing invoice/audit bindings; do not recreate financial history.
4. Add owner opt-in and the client review/confirmation page, then prove the complete same-clock journey with native database and packaged browser tests. Keep the existing eventual-purchase test unchanged.
5. Enforce the accepted verified-payment requirement for checkout-origin fulfillment and retain separate owner approval, exact mapping, client order/domain consent and confirmed package assignment. General payment links and gateways remain in Devin's separate lane.

The [full hosting acceptance work](TWENTYI_HOSTING_FULL_ACCEPTANCE.md) and its original runs remain separate requirements. This document does not certify native, HTTP, browser, upgrade, release or live-provider behavior. Automatic provider product images and full cloud catalog coverage remain unfinished; the saved owner API reference is already available and need not be requested again.
