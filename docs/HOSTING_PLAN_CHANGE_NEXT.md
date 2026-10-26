# Next hosting increment: explicit plan amendments

Recorded 2026-10-19 during roadmap continuation. This is an implementation handoff, not an enabled feature or a release claim.

Start a separate successor from `codex/twentyi-installed-billing-options`, source `d2903bfb81c93eb9da756c128536f23a3d34e822`. It carries the preserved catalog variants, billing selector and installed journey producer. It already contains main through `dcd6966c`; retained main `a86f6f4f` adds continuity records. Merge current main normally before implementation and retain every predecessor's acceptance scope.

## Intended customer flow

An existing hosting customer requests a different plan. The owner prepares an exact quote for that service; the primary customer accepts the plan, price and future billing date. The owner separately approves one 20i package-type change. A separate read confirms the package's observed type. Only then may one local transaction end the original billing schedule and activate the linked successor at the agreed, still-future boundary.

The proposed first policy charges the existing rate until that whole-period boundary, in the original billing timezone. The owner configures the new price. No mid-period charge, automatic proration, credit or refund is implied. Restrict eligibility initially to fulfilled checkout services with authenticated original order/account bindings. Target mapping and eligibility require explicit review; a type name or label does not establish cross-platform migration support or safe quota reductions.

## Provider boundary

The retained owner reference documents `POST /reseller/{resellerId}/updatePackage`, with one `id` entry and a `packageBundleTypes` mapping to the requested type. The dedicated operation must exclude billing, deletion, labels and limits. Expected old type belongs to the local review; the endpoint has no documented compare-and-swap field.

Returned affected IDs are an acknowledgement, not completed migration or billing authority. Independently read the exact package's `typeRef` in the same authenticated provider context. An unchanged type, missing package, timeout or failed observation never establishes that retry is safe. Preserve the attempted operation and its uncertainty; a late matching observation cannot silently move or backdate the commercial date.

The existing detached `foundation/hosting/PlanChange.php` marks an accepted operation result applied and allows retry after an unapplied resolution. Do not directly map an HTTP acknowledgement or a single old-type observation into those transitions. Use a persisted 20i-specific coordinator.

## Commercial and persistence requirements

- Add a dedicated immutable amendment quote with append-only decisions. Bind the original service, client/project, order/account association, exact agreement and schedule head, provider profile/credential/assignment, current observed type, target plan/mapping revision and price, timezone and future period boundary.
- Preserve legacy and checkout-origin document formats byte-for-byte. New amendment-origin agreements and billing terms need explicit reciprocal lineage; do not disguise a successor as an unrelated ordinary agreement. Owner execution must validate actual retained customer consent, never create a synthetic customer decision.
- Serialize the cutover using the existing shared mutex and one database transaction. Recheck the exact old head, matching provider observation, current consent, still-future boundary and absence of invoices at or after the cutoff. Append old schedule end, successor activation, reciprocal linkage and audit atomically. Network calls stay outside the transaction.
- Hold successor invoice issuance until the authenticated cutover. Enforce the origin guard in generic activation, resume and invoice writers as well as the new screen. Failure or missing lineage must refuse, not fall back to an ordinary agreement.
- Exclude amendment agreements and invoices from initial hosting-order creation and fulfillment. Changing an existing package must never grant a second `addWeb` purchase.
- Preserve all old invoices, terms, schedules and original provisioning history. Derive the current plan from authenticated amendment history rather than replacing the original creation snapshot.

If provider confirmation arrives after the agreed billing boundary, retain the technical observation and require a new owner quote and customer consent for a new future date. That commercial recovery must not send the provider change again.

## Acceptance still required

Implement the request/quote/consent/status screens and all runtime guards together before enabling provider actions. Native tests must cover atomic rollback, concurrency, stale head/consent, invoice holds, unchanged prior invoices, late boundaries, attempt replay and exclusion from initial provisioning. Extend the actual installed owner/client journey, then pass normal exact-source integration and release acceptance. Pure request/model tests alone do not complete this increment.
