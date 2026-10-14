# Owner and client service-agreement screens

## Current combined candidate

Preserved screen head `76a1537` passed 8,847 full assertions, exact Windows/Linux package comparison and actual desktop/mobile inspection. Its bounded footer/mobile review remains pending. Current `codex/service-agreements-release` combines that source with main `68e4d41` and hosting `1c92e6b`; it adds 1.24 labels and actual 1.23 upgrade/agreement recovery cases. Combined acceptance and release are pending. [Owner guide](SERVICE_AGREEMENTS.md). Earlier preparation checkpoints below are historical.

## Implemented integration — acceptance pending

The owner/client read models, controllers, routes, navigation and permission mappings are implemented on PR92. Four admin views cover plan list/detail and agreement list/detail; the existing portal handles `agreements=1` and `agreement=<id>`. Owners explicitly prepare storage. No automatic schema activation, invoice generation, scheduled charge or provider action is added.

Local PHP8.4 default/no-ini each pass98 form/rendering checks; native MariaDB passes124 retained/new storage and projection checks. Actual packaged HTTP/Chromium fixtures cover setup, authoring, stale forms, publication, exact offers, client consent, lifecycle actions and audience exclusion; they are prepared and syntaxchecked only. Browser/release acceptance remains pending. The preserved storage contract in [SERVICE_PLAN_RECORDS.md](SERVICE_PLAN_RECORDS.md) governs each mutation.

## Owned scope

Codex owns new form/read helpers under `site/app/plans/`, admin controller/views, portal controller/view and the minimal router/navigation/permission integration. Existing branding and layouts are reused. No canonical foundation changes, new identity system, payment scheduler or provider integration are needed. The reviewed parent branch remains frozen while its full acceptance runs.

## Owner workflow

An owner can explicitly prepare storage with the current password. Setup preserves existing clients, invoices and projects. The list shows saved plans with bounded pagination; draft creation/editing uses name, supported cadence, exact USD price, one concrete inclusion/exclusion per line, named whole-number usage limits and support expectations. Use the existing exact decimal-price parser. Never silently truncate submitted terms or accept arrays in scalar fields. Failed forms keep escaped unsaved inputs while retaining the originally submitted revision and intent identity. Passwords are never redisplayed.

Plan details show current terms, state and retained history. Publishing and retirement require an unchecked explicit confirmation, the reviewed exact revision, stable event identity and current owner password. Published terms cannot be edited. Offering a plan identifies an existing active client by name/email, displays the exact reviewed terms and binds the active plan revision/hash. A stale offer cannot silently use newer terms. Owner enrollment controls expose only currently valid activation/pause/resume/end/decline actions. A revoked or disabled client may still have an existing agreement ended or paused by a current owner.

## Client workflow

Authenticated clients gain a Service agreements entry in their existing portal. The list and individual agreement use the current client's identity, never a submitted owner/client selector. Support access cannot view or submit agreement actions. A client sees their exact offered name, price/cadence, inclusions, exclusions, limits, support terms, agreement state and attributed history. The page explains that accepting service terms does not authorize automatic payments; existing invoice/payment instructions remain separate.

Accepting requires an unchecked confirmation of the displayed terms, exact subscription revision, pinned terms hash and stable event identity. The owner cannot supply client consent. Pending offers can be declined; eligible existing agreements can be ended with an explicit confirmation and concrete reason. Cancellation updates the local agreement and makes no promise of a refund or automatic infrastructure action. Another client's records, stale sessions and support impersonation remain inaccessible. Service agreement and payment-method consent must never share an ambiguous checkbox.

## Request and rendering boundaries

All mutations require POST plus the existing session CSRF check. Current owner/client authority is rechecked by storage after locking. Narrow page/action allowlists never call arbitrary model methods. Lists are bounded and current-authorized; each displayed record is validated against retained history. Private pages use no-store/no-referrer. Strict input parsing rejects noncanonical numeric identifiers/revisions, malformed hashes and unsupported actions. Mutation errors preserve source records and show escaped actionable messages. Read endpoints do not activate storage or mutate agreement state. CSRF fields and random intent identities may be created by rendering.

## Acceptance before integration

Preserve all parent suites. Add meaningful form/escaped-template checks for every cadence, exact prices, multiline/UTF-8 limits, scalar/array confusion, stale intent preservation, unchecked confirmations and password exclusion. Add native owner/client list bounds and authorization tests. Run actual packaged HTTP/Chromium owner setup/create/edit/publish/offer and client agree/decline/end flows, including foreign clients, support exclusion, stale revisions and CSRF failure. Inspect desktop/mobile screenshots. Verify both Apache modes deny new private files, exact installer/package inventory and current published1.22 update/recovery preservation. Neither local rendering tests nor this contract are browser or release acceptance.
