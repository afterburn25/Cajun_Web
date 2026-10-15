# Current development: initial hosting orders

P0 20i remains Codex-owned and unfinished. `codex/twentyi-service-orders` begins from `7038cb50` in draft PR #137. Native owner/client order cards, exact agreement/invoice binding, receipt review and expiring manual policy are implemented for testing. Provider package mapping and dispatch are not yet connected. See [current order scope and evidence](docs/TWENTYI_SERVICE_ORDERS.md).

Published baseline remains v1.24.0; no main merge, release or live deployment is made by this checkpoint. First order run38770517159 failed at the recurring-state adapter (`active` versus existing `running`); this follow-up repairs that check and retains the assertion. Corrected source226f7cb passed all four native combinations with72 actual new order cases. This follow-up adds malformed-journal and concurrent-order checks and a stable browser candidate; exact descendant acceptance remains pending. Parent hosting-recovery native matrix passed; its full acceptance remains in progress.

Previous candidate details are preserved in [the 7038 checkpoint](docs/archive/2026-10-15-PROJECT_STATUS-7038cb50.md).
