# Master client portal — 1.26.1 development candidate

## Scope
The owner asked to continue integration and verify the previously requested master portal for administrator accounts. Before this increment, the portal login directed administrators back to Client support and did not have a unified master landing page.

This increment adds a portal hub at `portal/index.php?master=1`, plus a Master client portal link in the authenticated admin sidebar. A signed-in administrator visiting the bare client-portal URL is directed to the hub. An unsigned master request goes through the existing administrator sign-in with one fixed internal return destination; invitation, checkout, invoice, support and other customer deep links are not hijacked.

Owners can search and open any active client through the existing audited, time-limited support mechanism. Ordinary administrators require support.login permission and an explicit client support policy. The list enforces these limits in SQL before search and pagination. Customer-only accounts, matching emails, disabled users, expired/revoked sessions and active support contexts do not gain master access.

The hub links to existing permitted client, product, hosting, billing and 20i screens. It is not a combined cross-client financial report. It does not automatically manufacture a customer account for an administrator, merge identities by email, copy passwords, approve client contracts, sign finance agreements, charge cards or provision services. An administrator's personal purchases still require a real customer identity. The portal entry uses the existing administrator credentials rather than treating them as customer credentials.

## Source and tests
Five exact-before/after source hooks are applied by `tools/integration/master_portal_wiring.py` on the existing isolated integration branch. The ordinary expanded PHP source, receipt and regenerated installer inventory are then committed by the existing assembly job; main and production are untouched. No existing authentication, support, payment or sandbox guard is removed.

Local PHP syntax checks and 42 pure routing/search-policy assertions passed. Real SQL assertions and installed Chromium journeys are added for PHP8.4 with MariaDB/MySQL and must actually complete before their results are claimed. The tests exercise administrator sign-in return, client search/pagination, owner review, staff restrictions, CSRF refusal, customer/admin email collision, session revocation and desktop/mobile layout against synthetic data only.

The broader 1.26.1 release gates remain: exact-source integration and populated upgrade regression, expanded financial/payoff-state retention, Apache/rewrite and historical full-suite reconciliation, independent security review, reviewed main integration and verified standalone packages. Prior source successes are not evidence that this increment already passed. This is not a production deployment or an approved UPDATE ZIP.
