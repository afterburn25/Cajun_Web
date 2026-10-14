# Service-plan records and client agreement contract

## Current combined candidate

Preserved screen head `76a1537` passed 8,847 full assertions, exact Windows/Linux package comparison and actual desktop/mobile inspection. Its bounded footer/mobile review remains pending. Current `codex/service-agreements-release` combines that source with main `68e4d41` and hosting `1c92e6b`; it adds 1.24 labels and actual 1.23 upgrade/agreement recovery cases. Combined acceptance and release are pending. [Owner guide](SERVICE_AGREEMENTS.md). Earlier preparation checkpoints below are historical.

## Scope and ownership

Unreleased Codex application increment from PR90/148606fbab8fac1eba0c7c300927bb81f6d6d954. It preserves the exact reviewed Devin service-plan classes and supplied tests. HC-06/HC-11 are partially implemented; this increment supplies durable local service terms and enrollment, not recurring billing. Owner UI and authenticated client agreement screens are the next bounded layer. [Canonical terms](SERVICE_PLANS_FOUNDATION.md), [dependency gates](DEPENDENCY_GATES.md) and [client authority](CLIENT_AUTHORITY.md) remain applicable.

Codex owns `site/app/plans/`, `service_plans_schema.php`, `service_plans_migrate.php`, the native/document fixtures and their application integration. Devin retains its canonical branch and read-only 20i inventory assignment. No competing identity, invoice or payment ledger is introduced.

## Documents and states

`ServicePlanDocument` retains original terms plus ordered named staff events. Draft terms include name, cadence, exact integer USD amount, inclusions, exclusions, named usage caps and support expectations. The existing reviewed model enforces concrete bounded UTF-8 text and rejects unsupported unlimited/guaranteed promises. Application caps are 120 name bytes, 32 entries per terms collection and 99,999,999 cents. Cadences are one-time, monthly, quarterly and annually. Published terms cannot change: create another plan to change future terms. Retirement prevents new offers and preserves earlier offers.

`ServiceSubscriptionDocument` embeds the complete active plan prefix, named client/creating owner and every subsequent event. It begins pending consent. The exact client must agree to its pinned terms hash before owner activation, even for one-time plans. Owner pause/resume and owner/client cancellation retain the original agreement; owner/client abandonment can decline an unactivated offer. States follow the reviewed canonical model. A cancelled or abandoned agreement cannot restart. Returning a mutable model cannot alter saved document bytes.

Documents have strict fields, canonical JSON, unique 32-character lowercase hexadecimal identities, explicit actors, monotonically ordered bounded UTC epochs and SHA-256 fingerprints. No PHP object deserialization occurs. Plan documents cap at 512 KiB and subscription documents at 1 MiB, with at most256 events. Draft edits stop at254 events and reserve1,024 bytes; active plans reserve512 bytes. Publication and retirement therefore remain possible after the last accepted edit. Nonterminal subscription changes stop at255 events, leaving the final event for cancellation/abandonment. Exact old event replay remains a no-op at these limits. New enrollment requires a new explicit agreement.

## Storage, authority and atomicity

Four additive tables retain plan documents/events and subscription documents/events. Each event has a unique stable identity, parent revision, exact input, actor/time and before/after document hashes. Schema validation checks columns, case-sensitive identifier/text collation, exact indexes and restrictive foreign keys. Explicit setup requires the current owner/password and the existing staff/client systems. Unknown populated tables or readiness versions are refused; DDL may remain empty after a setup audit failure, but readiness and audit commit together. No existing configuration or customer data is deleted. Neither base setup nor router activates this storage yet.

All mutations use the existing shared staff authorization guard and an owned transaction. Current owner status, permissions and password are checked after acquiring the guard. Client changes lock and recheck the real active client, idle/absolute session expiry and authentication version; a support context or even a support selector is refused. Callers cannot choose a different client identity or make a staff consent event. Client audit uses the existing non-staff actor convention plus explicit client attribution.

Every read/mutation validates creation, current canonical history and every retained event/hash. Subscription validation also checks its pinned original plan prefix against retained plan history. Missing or ambiguous events, changed original proof and conflicting identities fail closed. A write atomically inserts the event, updates by expected revision/hash and records strict audit. Audit failure rolls everything back. An exact old request replays without another event even after later state changes; a changed/stale request cannot overwrite current terms. Existing offers remain pinned if their original plan retires; a fresh offer must select the current active revision and an active client.

Owner pause/cancel/abandon remain possible after a client is disabled, while activation/resumption/new offers require an active client. Client actions always require an active current session. Owner and client public read helpers refuse inherited transactions. Internal row/validation/append helpers are not HTTP authority boundaries and must only be called through current-authorized entry points. Future routes need their own method/CSRF checks, bounded form parsing and no-store/no-referrer handling.

## Evidence and remaining work

Local PHP8.4.25/MariaDB10.11.16:67 document checks in each default/no-ini mode and87 native checks. Named cases cover every cadence, exact snapshots, stale/foreign client refusal, support exclusion, expiry/revocation, audit rollback, conflicting/idempotent concurrent requests, authority loss while waiting, restart, schema/history tampering and bounded termination. The initial pure fixture's mistaken history count was corrected to assert the actual six-event sequence; runtime was not changed to satisfy it. Separate PHP8.2/8.4 CI and current combined package/Apache acceptance are still required.

No plan screens, merchant tokenization, invoice scheduler, tax/proration, cancellation-at-period-end, renewal notices, payment-method consent or automatic charge is provided here. Service agreement must be visibly distinguished from payment authorization in the next UI. Real recurring payment operations retain operation-specific merchant acceptance and owner activation gates. Independent review is required before integration. Published1.22 and candidate1.23 remain separate from these test sources.
