# Initial shared-hosting order workflow

Codex owns this necessary 20i commercial dependency. Development branch: `codex/twentyi-service-orders`, based on `7038cb50bafecfd6f96b0fcc3df944622fa0900b` (PR #137). This is implementing, not released or provider-live verified. The stable integration candidate continues its original acceptance runs without cancellation.

## Current implementation

Existing service-agreement details now carry owner/client hosting order cards. An owner with current financial permissions and password explicitly selects an invoice for an accepted active agreement. The first order permanently consumes that agreement and invoice, including after withdrawal/cancellation; new service requires new reviewed commercial records. One-time terms use a separate matching existing invoice. Recurring terms require period zero of that agreement's accepted active billing schedule. Catalog purchase invoices cannot be repurposed. Selection never infers ownership by matching an email or domain.

The real signed-in client confirms the exact offer and canonical ASCII domain, or declines it. Support access and staff cannot provide client consent. Events retain exact revision, input, actor and before/after hashes. The original offer audit prevents silent re-creation after row loss. Two additive tables preserve references to existing clients, agreements and invoices. Setup requires explicit owner POST and refuses missing established history, including a lost setting with original setup evidence.

Commercial assessment rechecks agreement state, exact current invoice, receipt origin/reversal history and unresolved payment attempts. An owner may separately permit manual fulfillment for at most one day; the UI grants at most one hour from form preparation. The policy is revocable and expires, never changes paid status, and never bypasses inactive clients, void invoices or unresolved payments. Client output excludes the owner's private policy reason and receipt details. Cancellation retains history and invoices and performs no provider deletion.

## Scope still required

These cards collect intent and review commercial eligibility. They do not yet map a provider package, grant a purchase, dispatch provisioning, identify a resulting hosting resource or activate a service. Complete those connections through the retained provider gateway/operation ledger and hosting journal; do not create another execution engine. Renewal/cancellation service effects and the broader native 20i controls remain unfinished.

## Validation checkpoint

Source inspection only for this new order implementation before its first native run. The existing recurring native suite includes the new order cases without removing prior assertions, runs on PHP 8.2/8.4 with MariaDB 10.11/MySQL 8.4, and saves named order results. Intended coverage includes explicit setup, exact agreement/invoice binding, two-client isolation, current sessions, stale forms, audit rollback, receipt reversal, manual policy expiry/revocation, cancellation with a changed invoice, recurring first-period rules, and restoration of missing original tables. Actual results must be recorded after running. Server-rendered form assertions are not HTTP/Chromium evidence. Dedicated browser journeys, package acceptance, visual artifact inspection, independent review and final release verification remain required.

Parent hosting-recovery evidence: run [38767511429](https://github.com/afterburn25/Cajun_Web/actions/runs/38767511429), all four native matrix jobs passed. Actual JSON in each: records112, operations104, retry44, delete67, reconciliation157. The PR merge tree `83345c4f` equals source `7038cb50` tree `9149f9ef`. Parent full acceptance [38767510944](https://github.com/afterburn25/Cajun_Web/actions/runs/38767510944) was still running at this checkpoint, with native and HTTP/Chromium stages passed; no final success claim.

## First native correction

Source146090fd run38770517159 failed all four matrix jobs at the first valid recurring hosting offer: the adapter checked `active` while the retained schedule phase is `running`. This is a new product-adapter defect, not a fixture problem. The correction uses the actual `running` phase and period-zero eligibility from the existing schedule assessment. The original case remains. The follow-up also gives both portals an explicit restoration state for missing established order tables and adds actual HTTP/Chromium setup/offer/accept/manual/revoke/cancel/recovery cases to the retained browser suite. Browser additions are unexecuted until the full candidate runs. Native reports now print even after failure.

## Native success and stable browser candidate

Source226f7cb passed native run38771029583 on PHP8.2/8.4 × MariaDB10.11/MySQL8.4. Actual JSON parsed from all four job logs: 72 new order cases within229 retained recurring/order cases, plus108 separate ledger cases, all passed. Browser driver syntax parsed in memory; real order browser execution still pending. This follow-up adds controlled malformed-journal recovery, read-without-write and concurrent initial-order tests; rerun the exact descendant.

The earlier payment-evidence source423dd598 completed full run38765096283 successfully (PR merge33db5925, verified identical tree). Browser and recovery passed; final Apache log reports1715 checks. Artifact11990319904 SHA256 `c5108b039d4c44431293ad5a914e39890ed7a5e4cff74bd4a0cc0821cfaa04dd`, 83,446,431bytes. Browser checkpoint11988129085 SHA256 `57581bcd8c924ef970c3a54b3734548924356999373b8780ee050ed82b1b5b68`,65,698,183bytes. These metadata/logs are read; artifact bytes/screenshots are not inspected in this environment. This does not certify order descendant source.
