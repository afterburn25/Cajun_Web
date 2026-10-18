# Finish an imported hosting offer

Development branch: `codex/twentyi-imported-plan-setup`, starting at catalog storefront source `460eebfb5d8d87c3816576aeb7289bfa90478668` (PR223). Published 1.25.0 remains unchanged. This increment joins the existing import, comparison and invoice-checkout path; it does not establish complete 20i or whole-project completion.

## Owner workflow

After importing and pricing a monthly or annual hosting draft, choose it in **Hosting offers**. **Finish hosting offer** displays the saved name, USD price, billing period and imported 20i type. Enter the client service terms and review the provider account, location, limits and optional private cost. Confirm both the provider review and plan creation with the current owner password.

The application creates the matching active service plan and its provider mapping together. The returned plan is selected even when it is outside the current search page. The existing **Preview offer** and explicit publication remain the next steps. Existing matching plans can still be selected without creating another plan. Invoice checkout remains an explicit publication choice with its existing preparation, client-consent, verified-payment and provider-fulfillment requirements.

Provider account settings, limits and cost are owner-reviewed facts; they are not inferred from product names or imported as unverified API fields. The package type and provider profile come from the authenticated saved import. Product prices and images remain editable through the existing catalog. This increment does not discover VPS products or provider artwork.

## Transaction and retry behavior

One existing account mutex and database transaction cover plan creation, activation and mapping. The public plan/mapping writers keep their existing signatures, validation and audit behavior; guarded internal helpers allow this composition without nested transactions. No database schema or provider operation is added.

The owner review binds the exact product and image revision, original import provenance, newest complete saved catalog, current profile and credential context, and current owner identity/authentication revision. Submission verifies the saved provider key against the observation without making a network request. Changed or missing evidence requires a new review.

All three submitted identities remain stable on failure or retry. A completed exact retry returns the original records without another plan, activation, mapping or extended expiry. Partial identities, changed terms/configuration, a retired/edited plan or a superseded/expired mapping cannot silently reopen setup. Existing immutable plan, event, mapping, audit and pointer records remain authoritative. Late failure rolls back the combined write.

Rejected forms retain bounded nonsecret entries and the original identities/digest. Password and confirmations are never redisplayed. Stale submissions are disabled. If the page cannot read the database, its fallback renders only the locally retained submission and does not try the failed database read again.

## Verification and remaining gates

Applied source passes 748 named pure review/terms/mapping and existing catalog, service-plan and checkout document executions across default and minimal PHP 8.4.25. The new UI suite adds 68 checks in each mode, exercising the actual parsers, controller functions and renderers with modeled database/authority boundaries. The extracted-writer proposal separately checks public-wrapper equivalence and transaction/lock refusal. These are not native database results.

Local Chromium passes 46 named checks against the actual PHP form and deployment CSS using synthetic product details at desktop and mobile sizes. All four resulting captures were visually inspected. The checks cover labels, keyboard focus, optional-cost expansion, confirmation controls, button reachability and horizontal overflow. They are not installed HTTP submission or provider acceptance.

The new isolated Linux fixture prepares 74 named assertions for actual imported products, monthly/annual plan creation, exact retries, stale/current-authority refusal and late audit rollback through the real writers. It uses an unused `hpsci_` namespace and preserves original failed evidence. Its synthetic catalog observations honor the real refresh cooldown. Windows entry remains refused; Linux SQL execution and installed browser continuation remain pending until their original runs produce results.

The installed continuation follows the original five storefront purchases. Actual owner forms exercise stale review, CSRF, explicit confirmation, client/support exclusion, password retry, creation and exact replay, then separately preview the new offer. Four additional desktop/mobile captures are required through the existing checkout report. Its 46 portable helper/wrapper checks pass; they do not execute that installed journey. The initial support-session token defect is retained in the external proposal evidence and corrected by obtaining the genuine current token and reloading the final review after support ends.

Full acceptance adds five commands and reports: 176 native commands, 197 required browser-lane reports and 248 final reports. The focused workflow retains its four PHP/database combinations. Distro minimal PHP explicitly loads PDO and tokenizer for the actual-source UI checks; the official PHP Docker image uses its built-in modules. The original checkout input/output guards pass all 52 named checks. The installer inventory refresh passes; a clean committed package and actual Linux outcomes are separate remaining checks.

The final applied artifact-helper suite passes 570 named checks, retaining every original 492 outcome and its order. All 21 current source pins and six applied proposal files match their review receipts. Independent browser/CI review passes 85 finite source/hash/inverse checks. Earlier proposal failures remain recorded: the original UI fallback read the database again; an initial browser draft reused a token across support-session rotation; and new portable probes initially missed the exact collision exception and one historical source-read adaptation. Each correction is bounded to its stated source; none is represented as successful database or browser execution.

Full and focused acceptance must retain all predecessor commands, reports and captures while adding this increment's checks. Review the actual final-source database/browser outcomes, corrected captures, supported upgrades and release package results before integration and publication. Never cancel or rerun an original queued job solely because it is waiting. The unchanged source paths and finite acceptance inverses document preservation; they do not certify runtime behavior.

No live deployment, provider write, payment, customer mail or SSH action occurs in this development increment.
