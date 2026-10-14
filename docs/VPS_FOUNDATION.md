# VPS lifecycle foundation

Started 2026-10-13 as an independent Devin increment under the dependency-gate policy ([DEPENDENCY_GATES.md](DEPENDENCY_GATES.md)): HC-10's gate (`Development pending — API verification`) does not block the implementable portion — the resource/OS/region model, offering/price display, order lifecycle and teardown protection. Branch: `devin/vps-foundation`. Scope: the VPS contract core only — no catalog UI, no persistence, no provider calls.

## Implemented component

The standalone PHP 8.2+ classes in [foundation/vps](../foundation/vps) need no framework, database, network access or Composer installation. Keep this directory outside deployment packages until the adapter contract is agreed and the release inventory is updated.

- [VpsSpec](../foundation/vps/VpsSpec.php) — a concrete configuration (vCPU, RAM, disk, region slug, OS image); caller-supplied, validated, no invented provider sizes.
- [VpsOffering](../foundation/vps/VpsOffering.php) — a sellable spec with an admin-configured monthly price; `null` price = comparison-only, not orderable.
- [QuotaUsage](../foundation/vps/QuotaUsage.php) — entitlement/usage display pairs; unknown entitlements are `null`, never zero; percent math avoids division by zero.
- [VpsServer](../foundation/vps/VpsServer.php) — guarded lifecycle state machine with append-only, actor-attributed history.

## Server lifecycle

```
pending → provisioning → active ⇄ (suspending →) suspended (→ resuming)
   │          │
   │       fail → failed ──retry──► pending     (ref cleared)
   │
   ▼
cancelled (terminal, pending/failed only)

active/suspended ──requestDecommission(A)──► decommission_requested
      ▲                                          │ approveDecommission(B≠A)
      │                                          ▼
      └── cancelDecommission /           decommissioning ──► terminated (terminal)
          decommissionFailed (rollback)          │
                 ▲                               └── decommissionFailed
                 └───────────────────────────────┘
```

- **Two-actor teardown:** `approveDecommission` requires a *different* actor than the requester — a single compromised or mistaken account cannot destroy a server alone.
- **In-flight failure rollback:** `suspendFailed`/`resumeFailed`/`decommissionFailed` return to the prior stable state; a failed teardown clears the request so destruction must restart the full two-actor flow.
- **No silent loss:** active/suspended servers cannot be `cancel()`ed — decommission is the only way out.
- Every transition appends a `{status, at, actor, note}` history entry.

## What this component deliberately does not do

- No provider endpoints, real specs, real regions or real prices — provisioning ops map onto `ProviderOperation` names (`vps.create`, `vps.suspend`, `vps.resume`, `vps.terminate`) through the provider gateway foundation (Devin [PR #43](https://github.com/afterburn25/Cajun_Web/pull/43)); capabilities stay `unverified` until the real 20i VPS API is inspected.
- No persistence, comparison UI, staff permissions or billing wiring — pure contract; the site adapter owns storage and display.

## Integration work for the adapter phase

1. Site adapter: schema for servers/history, comparison display over `VpsOffering`+`QuotaUsage`, provider operations via `foundation/provider`, staff-permission checks for the decommission approver role.
2. Admin: offering editor (spec + optional price) and quota display fed by real provider reads.
3. `build.py` gains `'vps'` in the foundation bundling list; post-refactor destination `site/includes/foundation/vps/`.

## Verification

Run `php tests/vps/run.php`. The dependency-free [runner](../tests/vps/run.php) uses `fx-*` fixture specs/prices only. 58 named checks cover spec/offering validation, quota math, provisioning/retry, suspend/resume with in-flight rollback, two-actor decommission (including self-approval rejection, withdrawal and teardown-failure rollback), cancellation limits and history attribution. [GitHub vps workflow](../.github/workflows/vps.yml) runs syntax + the suite on PHP 8.2 and 8.4 with no network or secrets.

Local PHP 8.4.25: 58 checks pass; all files `php -l` clean. These checks do not establish catalog UI, persistence, real provider provisioning or deployment — those remain gated on the adapter phase and API verification.
