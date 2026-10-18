# Hosting confirmation and the first invoice

Development branch: `codex/twentyi-hosting-first-invoice`, starting at `0c7cbae34220717c72e2f3dbeed1b41ca3545dbf`. This is separate from the preserved full-acceptance branch and the unpublished 1.26 release candidate. Published 1.25.0 remains unchanged.

The intended experience is one client confirmation showing the hosting service, exact price, full first billing period, due date and project choice. That confirmation will create the agreement and first unpaid invoice together. Card payment, provider provisioning and confirmed service availability are separate steps. Current development implements that transaction and its owner/client pages. Local document, migration/history and renderer checks pass within their stated scope; full native checkout and packaged browser acceptance remain pending.

## Commercial documents

Existing format1 publications and requests retain their original behavior. A new format2 publication requires an explicit owner checkout policy. It supports a primary client, one existing project belonging to that client or a new dedicated hosting project, and an invoice for the full monthly or annual period beginning on the displayed local date. Existing USD, positive-price and zero-setup-fee restrictions remain. No offer is automatically converted to the new mode.

`HostingCheckoutReview` binds the exact publication and plan, client, stable request identifier, project instruction, price, calendar and expected effects. Its lifetime ends at the earliest of the chosen short timeout, provider-mapping expiration and next local date. A changed date or source requires a new review; it never silently rebases the first period. `HostingCheckoutConsent` records one explicit acceptance of the exact displayed review fingerprint. Historical parsing retains the original terms without consulting current credentials or availability.

The public projection contains commercial terms and the selected project instruction. Private plan journals, provider mapping, wholesale costs, credential fingerprints and authorization details must remain server-side. Documents validate supplied facts; they do not authenticate a user, establish project ownership, grant billing authority or prove a database record was not rewritten. A valid alternate identity creates a different document and fingerprint. Native storage must bind the original request, authenticated client, exact retained consent and independent audit history.

## Client-attributed subscriptions

The next pure stage adds subscription format2. It retains the exact accepted publication, plan, composite consent, request fingerprint and resolved project. Its creator is the actual client. Two fixed initial events distinguish that client's consent from the application's automatic local activation; no staff member is recorded as performing either action. Later pause, resume and cancellation keep the existing actor and lifecycle rules. Existing format1 documents retain their fields, byte limit and behavior.

This is document validation, not a native writer. Database code must still authenticate the client, verify current owner authority and project ownership, and bind independently retained rows and audits. An initial format2 document is the complete two-event prefix; empty or one-event format2 documents are invalid. The separate bounded checkout size accommodates its retained documents without increasing the legacy limit.

Applied-source tests pass65 new subscription cases and67 existing cases in both default and minimal PHP8.4.25; the complete legacy output is byte-identical to the baseline. Independent review also verifies direct-parser loading without a dependency cycle. Commands: `php tests/service-plans/checkout_documents.php` and `php tests/service-plans/documents.php`, each repeated with `php -n`. The related130 checkout and119 publication/request cases pass again after applying this shared document change.

## Subscription database extension

An explicit owner/password migration now adds truthful checkout attribution without rewriting existing subscription documents or events. The original schema marker and default SQL stay unchanged. A separate checkout marker records preparation, recognized interrupted DDL stages and completion. Missing original tables, unknown schema shapes, unmarked extensions and new-format rows during preparation are refused. Normal use resumes only after the owner migration validates the completed schema and retained history.

Legacy subscriptions retain their staff creator. Checkout subscriptions retain the consent client, followed by a system-only activation event; neither can be relabeled as a fictitious staff action. Native validation checks the exact completed two-event initial document and every stored transition hash. Existing owner/client lifecycle commands continue to work.

The applied source passes58 actual checks on PHP8.4.25/MariaDB10.11.16 in an isolated local database. Coverage includes current owner/password checks, old record/audit preservation, a real final-audit failure with rollback and explicit resume, schema corruption refusal, client/system actor tampering and later lifecycle commands. The format2 rows in this fixture are synthetic retained history. This does not certify authenticated checkout creation, original publication/consent audit composition, project creation, invoice creation, HTTP/browser behavior or other supported database/runtime combinations.

The first run stopped after12 checks because the test helper rejected digit-bearing column names. The corrected fixture preserves every original assertion; the failed output and populated database remain retained, and the successful run used a fresh isolated database. The fixture is `tests/service_plans_checkout_native.php`, gated to local43319 and the two explicitly named disposable databases. Its setup and the new pure tests are not yet wired into the full application workflow. Keep the existing workflow commands and original runs intact until the coherent checkout increment is ready.

## First-period billing rules

Recurring terms and schedules now have a separate format2 checkout path. The application derives every commercial field from the exact retained two-event subscription prefix and its composite consent. The initial client decision explicitly starts local period0 on the accepted date; it cannot be replayed as a new activation or silently moved to another date. The first period is eligible for an invoice at the accepted instant. Later pause, resume and end decisions keep the existing future-period rules.

Legacy format1 activation remains unchanged and still refuses a same-day start. Foundation classes accept structural facts without loading application authority or database code. Native readers must bind the exact subscription, request, consent, schedule and independent invoice origin. These rules alone do not create an invoice, charge a card or provision hosting.

Applied-source tests pass92 new recurring cases,372 existing terms cases and321 existing schedule cases per default/minimal PHP8.4.25. The complete legacy outputs match their baseline. Related65 checkout subscription and130 commercial checkout cases also pass per mode. Run `php tests/recurring-billing/checkout.php`, `php tests/recurring-billing/terms.php` and `php tests/recurring-billing/schedule.php`, repeating with `php -n`. Independent review verifies cold loading, exact derivation and retained-prefix/date boundaries. Full native and checkout-page acceptance is still pending.

## Recurring storage and first-invoice origin

An explicit owner/password migration extends recurring schedules and invoice maps with truthful client attribution. Default legacy schema and marker1 remain exact. A separate marker tracks recognized ordered DDL stages; readiness validates schema, retained records and independently counted origins. Unknown or missing tables, extra columns/indexes, unmarked extensions and incomplete history refuse use instead of recreating financial records.

A checkout schedule stores its complete one-event initial document. Its terms must derive exactly from the retained subscription and consent. Period0 requires an original invoice map and a compact `recurring_billing.checkout_issued` audit witness, with actual client, request, input, consent, schedule, invoice, issuance and accepted-time bindings. Its maximum encoded detail is381 characters within the existing400-character field. Legacy staff-origin syntax and return shape remain unchanged. Staff cannot adopt the client's issuance identity or recreate a lost checkout first invoice.

The applied source passes66 named native PHP8.4.25/MariaDB10.11.16 checks without clock adjustment. These cover real owner migration, interrupted/failing DDL and audit recovery, legacy issuance/replay, schema/history corruption refusals, truthful client attribution, exact invoice origins and missing-map refusal. Format2 rows are synthetic retained history; authenticated atomic project/agreement/invoice creation and HTTP/browser purchase remain untested here.

The first run passed34 checks before a fixture lookup returned null after reading its inserted audit ID too late. The corrected fixture captures the ID immediately and adds one exact fresh database allowance; runtime source and all66 assertions remain unchanged. Failed evidence/database state are preserved. The successful run uses `tests/recurring_billing_checkout_native.php` on the isolated43319/cwp_checkout_recurring_retry1 database. These new fixtures remain separate from the existing full workflow until the coherent checkout increment is ready.

## Earlier commercial-document validation

Applied-source tests pass130 new checkout-document cases and119 unchanged publication/request cases in both default and minimal PHP8.4.25. The tests cover exact terms, client/project facts, expiration, DST and skipped dates, legacy-route refusal, historical parsing and private-field exclusion. Independent review repeated the original failing Santiago midnight witness against the corrected source; it now expires at the actual next local date and refuses next-day historical consent. Original failure evidence remains retained.

Run `php tests/hosting_checkout_document.php` and `php tests/hosting_catalog_document.php`, repeating each with `php -n`. These are pure tests with synthetic identities and current-context facts. They do not execute authentication, SQL or an invoice. The existing385-check artifact-helper suite also passes with the updated source pins. The new checkout test is not yet wired into the full application workflow; it will accompany the coherent native increment. Existing workflow commands and their original pending runs remain intact.

## Integrated owner and client flow

An owner explicitly prepares the optional checkout tables and subscription/recurring extensions using a current password. New publications default to request-only; choosing invoice checkout retains the billing timezone, calendar alignment and verified-payment policy in the reviewed publication. Existing offers are not converted. Month-end alignment permits confirmation only on the month's last local date; day-of-month alignment clamps to the last day in shorter months. Failed forms retain submitted identities, hashes and choices.

Primary clients choose an owned existing project or a dedicated hosting project. The retained review displays the service, exact price, first full period, due date, next billing date, timezone and expiry. Confirmation submits only the stable request identifier and exact review fingerprint with explicit consent. The transaction rechecks current client/owner/publication/project authority and writes the project if needed, truthful client consent/system effect, recurring schedule, first unpaid invoice and independent result/origin witnesses. A final audit failure rolls back business rows. The result links to the owned invoice, project, agreement and billing schedule.

Completed retry reads the original complete retained graph after fresh primary-client authentication; it does not depend on the offer still being available. Changed input, contacts, support impersonation, revoked sessions, foreign projects and missing/corrupt history are refused. Checkout-origin service orders require the original first invoice and provider mapping. Manual fulfillment bypass cannot replace the accepted verified-payment policy, including after payment or on a repeated event. Ordinary legacy order behavior remains separate.

## Current verification and next steps

Applied default/minimal PHP8.4.25 runs pass130 commercial-document,65 subscription,92 billing,46 portal-form/renderer and119 existing publication/request checks per mode. The view fixture executes actual routing/parser/render functions with explicitly modeled storage and authority seams. It verifies exact retry fields, correct recurring links, escaping and preview return routing. These results do not establish SQL, HTTP or browser behavior.

The first Linux native fixture uses actual owner migrations, protected synthetic provider credentials, closed local package-types transport, authenticated client wrappers and database rollback/history checks. Its separate synthetic receipt slice exercises actual ledger and local order preparation; it does not charge a card or send a provider operation. Upload only the named QA report, never private runtime/session/key files. The frozen original had an invalid in-process unauthorized ledger check because require_admin redirects/exits; the corrected fixture defers that HTTP assertion and explicitly prepares attempts via the real migration. Native assertion declarations are not passed results.

The focused workflow runs the new pure/view/native tests on PHP8.2/8.4 with MariaDB10.11/MySQL8.4. The full application workflow remains unchanged until its strictly preserved recipe/report inventory can be extended alongside the complete packaged browser journey. Preserve all original runs and failures; do not cancel, rerun or replace them to obtain a cleaner result.

Next: inspect the first exact-source focused native run, finish the same-clock packaged owner/client browser flow, then integrate the bounded recipe/report additions into complete application/upgrade/recovery acceptance. No new source is published or deployed until the applicable gates and exact downloaded release verification pass.

The [full hosting acceptance work](TWENTYI_HOSTING_FULL_ACCEPTANCE.md) and its original runs remain separate requirements. Automatic provider product images and full cloud catalog coverage remain unfinished; the saved owner API reference is already available and need not be requested again.
