# Project estimator foundation

Started 2026-10-13 as the second independent Devin increment while another worker finishes 1.1.0 and publishes its source to GitHub (issue #2; agent coordination allows scoped public lead/estimator work after the portfolio component). Branch: `devin/lead-estimator`. Scope: the L-01 deterministic pricing core only — not a working estimator form.

## Implemented component

The standalone PHP 8.2+ classes in [foundation/estimator](../foundation/estimator) need no framework, database, network access or Composer installation. They do not replace or create the application's estimator, leads, forms or admin. Keep this directory outside deployment packages until the verified 1.1.0 source is inspected, an adapter is integrated and the release inventory is updated.

- [EstimateRequest](../foundation/estimator/EstimateRequest.php) carries a normalized public submission: a required service-type selection, optional size band and timing selections, additional option ids (deduplicated so a repeated id cannot be charged twice) and optional free-text custom scope.
- [PriceRule](../foundation/estimator/PriceRule.php) carries one admin-editable rule: an exact selection id, a display label and an integer minor-unit low/high range.
- [EstimateRuleSet](../foundation/estimator/EstimateRuleSet.php) carries the current rules; duplicate selection rules are rejected rather than resolved by input order.
- [Estimator](../foundation/estimator/Estimator.php) evaluates a request deterministically and returns an `EstimateResult`.
- [EstimateResult](../foundation/estimator/EstimateResult.php) is either `estimate` (an explained low/high range of `EstimateLine` contributions) or `consultation` (no total), with factual notes and the required `Estimate only — not a final quote.` disclaimer.

## Contract semantics

Every request selection — service type, size band, each option, timing — is priced only if the rule set supplies an exact-matching rule. Selections without rules are named in `unpriced` and force a `consultation` result, as does any non-blank custom-scope text or a request with no priced lines. A `consultation` result keeps already-priced lines for context but never carries a total, so a partial range cannot masquerade as a price. Line items preserve selection order for explanation.

No prices exist inside this component: rules are adapter inputs from authoritative admin configuration (L-01's admin-editable price rules and ranges). The code path from input to output is pure and deterministic; there is no randomness, time dependence or hidden fallback.

## Trusted adapter boundary

The later adapter validates that every submitted selection id is one the admin actually offers, supplies current rules, and persists the lead/brief before any notification (L-01 acceptance). Server-side recomputation happens by re-evaluating `Estimator::evaluate` with stored rules — client-submitted totals are never trusted. Form validation, input preservation on failure, spam controls and saved-lead storage remain adapter/application responsibilities and are not claimed here.

## Integration work after 1.1.0 is published

1. Inspect the real services/rates/leads storage and admin surfaces; agree any new storage/admin fields with Codex before changing shared schema.
2. Build the adapter mapping real selection ids and admin-editable rules onto this contract.
3. Render the single-page form through the actual PHP application with input preservation on failure and honest consultation/estimate presentation; keep navy/gold branding and the one-page keyless installer untouched.
4. Persist leads before notification, wire spam controls, then run native form/database/browser acceptance.

## Verification

Run `php tests/estimator/run.php`. The dependency-free [test runner](../tests/estimator/run.php) checks deterministic evaluation, exact rule matching, unpriced-selection consultation routing, custom-scope consultation, deduplication, zero-priced selections, truthful-kind enforcement and malformed/ambiguous input rejection. It uses synthetic `fx-*` fixtures and fixture amounts only — no real prices — and exits nonzero on failure without relying on PHP's configurable assertions.

Local PHP 8.4.25: 40 checks pass and all seven PHP files pass syntax checks. [GitHub estimator workflow](../.github/workflows/estimator.yml) runs syntax and the same isolated decisions in official PHP 8.2 and 8.4 CLI containers with a read-only checkout mount, no container network, no application secrets and no database.

These checks do not establish a working form, persistence, lead capture, notifications, admin pricing UI, database behavior, installation or deployment. Those remain pending integration with the verified current source.
