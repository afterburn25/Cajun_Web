# Public component integration contract — 2026-10-13

Owner request: continue the roadmap and integrate finished Devin work sequentially. Starting main: `6f0120913c93eace492b6c5f333c754fea3bea31`; latest published application: v1.10.0 at `4649a913d40166b9874f9550e57b4186edfc43ab`.

## Estimator, first integration

Codex owns `codex/integrate-estimator`: merge Devin PR #27 at `b69da9626448a2ddfdf30b425dc7c2d5e16d1582`, preserving its ancestry and branch. Reconcile shared documentation against current main. Own `foundation/estimator/`, `tests/estimator/`, its isolated workflow and continuity documents for review fixes. This component stays outside `site/`; no application version change is justified by this step.

Contract additions before dependent fixes: amounts and totals remain exact nonnegative integers. Summing beyond PHP_INT_MAX returns consultation with no totals and an explanatory note. Directly constructed estimate results require unique valid lines, no unpriced selections and exact line-sum totals. Consultation permits partial priced context but never totals. Tests include integer boundaries, malformed direct results and exact numeric-string identifiers. Public form, schema and pricing configuration remain unchanged.

Inspection found the current application already has `growth_estimate`, admin `growth_prices`, quantity pricing for pages/products, server recomputation and saved leads before notification. The standalone core does not yet model quantity pricing or the existing custom-project/integration exclusions. A future adapter must preserve those semantics and the configured amount cap; replacing the live estimator directly would regress current behavior.

## Portfolio, second integration

After estimator checks and merge, Codex owns a separate `codex/portfolio-browsing` branch. Reviewed target is Devin PR #7 at `016a4057a3a17b62b2edcf583252f52cb9ff06a2`, including adapter `c8821f8` and its subsequent 1.10.0 refresh. Estimator PR #29 passed combined acceptance and merged at `39de8ff50ea245928a1f6528f10057afcf55e5b7`; its later no-code refresh `65c2084` is also preserved. An older unused local branch already had the planned integration name; the distinct branch avoids replacing it. Preserve original branch/history. Own its portfolio component, application adapter, public work/detail routes, narrowly scoped view/CSS/JavaScript changes, build inventory and acceptance tests. Preserve current activity header and v1.10.0 schema/business modules.

Existing projects have unique slugs, explicit published status, `is_demo`, one category, sort order and private media references. Existing case-study rendering and device/before-after controls remain. No invented projects/results, schema migration or new admin subsystem.

Review contract: numeric category strings remain strings; duplicate published slugs are rejected independently of input order. Unfiltered state uses an empty category so a genuine category named `all` works. Server and enhanced browser filters use identical normalized category values; unknown filters show an empty state. Link modifiers retain normal browser navigation; keyboard, back/forward, no-JavaScript and 320px layouts are tested. Only published projects appear anywhere in the response.

Bounded release target: v1.11.0 portfolio browsing, after exact combined-main acceptance. Verify an actual retained v1.10.0 UPDATE preserving read status/business/private data, all earlier upgrade paths, extracted flat packages, installer hashes and fresh installation. Publish immutable complete/update/evidence assets only after draft download verification; verify public downloads and tag afterward. No live deployment or provider/customer operation.

## Evidence and handoff

Record actual PRs/checks and remaining W-01/L-01 work in status, handoff, changelog and session log. Both original PRs must land through ancestry-preserving integrations after review and checks, one at a time. Candidate checks are not release acceptance. No full W-01/L-01 completion claim.
