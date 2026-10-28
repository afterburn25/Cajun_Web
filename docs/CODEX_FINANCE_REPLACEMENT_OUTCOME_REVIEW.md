# Financing replacement create-response review — 2026-10-28

Parent: exact integration `3789bb50c90cb836530f2d5dc2400cee394763d0`. This focused correction does not include domain-race PR #285; both target the integration writer's branch independently.

## Finding

**P1: unresolved replacement creation can enable another create.** After permanently retiring the old ARB, `license_finance_subscription_reenroll_opaque()` treated every unsuccessful or malformed creation response as a definitive decline and restored `billing_suspended`. An `Ok` response without a usable subscription ID, an empty/unknown response, an internal error or a duplicate error could therefore permit a second `ARBCreateSubscriptionRequest` while the first result remained unresolved. Using different payment details can also evade provider duplicate detection. The isolated reproduction proves repeated dispatch eligibility, not actual provider charges.

## Correction and retry behavior

Keep unresolved results in the existing `review` state, retaining the old authoritative gateway identity and preventing another customer create. Normal valid IDs still follow the existing independent readback and finalization. Existing transport-exception handling, retirement requirements, signed remaining-period checks, owner reconciliation and paid-off controls remain intact.

Preserve automatic retry only for an explicit `Error` response with no subscription ID field and a nonempty message list consisting entirely of the recognized request-validation codes `E00003` and `E00013`–`E00019`. This is a conservative classification based on the provider's documented parser/field validation failures. Mixed, unknown, duplicate (`E00012`) and internal (`E00001`) errors remain review-only. A response carrying a candidate identity is never treated as a safe rejection. Unlisted errors may require owner reconciliation even when the provider eventually confirms no subscription was created.

Official references: [Authorize.net response codes](https://developer.authorize.net/api/reference/features/errorandresponsecodes.html), [ARB identity and duplicate checks](https://developer.authorize.net/api/reference/features/recurring-billing.html). Duplicate detection compares multiple fields and does not replace application uncertainty fencing.

## Executed verification

The final regression has 25 cases / 100 assertions. It copies the four actual orchestration/reservation/review/decline function declarations verbatim into an isolated temporary PHP module and executes them with strict synthetic gateway and state dependencies. It performs no real PDO, HTTP, credential access or provider operations. Unexpected SQL mutations and gateway methods fail the fixture. The source and function hashes are recorded in its JSON result.

On both PHP 8.2.35 and PHP 8.4.25 with `php -n`, the identical assertions fail the original source with 42 failed assertions and pass the correction with 100/100. The original run changes only one fixture source path; reversing it restores the corrected fixture bytes. Cases cover malformed/absent/oversized/nonscalar IDs, empty/unknown results, duplicate/internal/mixed/malformed errors, conflicting candidate identities, all allowed validation rejections, transport failure and string/integer success identities. Checks verify the first state/result, repeated-create eligibility and authoritative identity retention. Original failing results remain preserved.

The existing webhook-isolation, delayed-financing-lifecycle, financing-policy, source-contract and prior-ARB-retirement suites also pass under both PHP versions, as do touched PHP lints. The existing isolated workflow runs the new fixture in its PHP 8.2/8.4 containers with networking disabled and preserves the JSON evidence. No duplicate full application workflow is dispatched.

Native persistence, installed HTTP and actual gateway behavior remain separate gates. Sandbox credentials are unavailable. This source is awaiting independent review, normal incorporation by the integration writer and fresh combined acceptance; it is not merged into main, released or deployed.
