# Administrator client review — 1.26.1 development candidate

## Scope
The owner asked for **Client portal** to show administrators the same client interface customers use. The earlier selector rendered the administration shell and administration shortcut cards even though its URL was under `/portal/`.

This increment keeps `portal/index.php?master=1` as a compatibility route but renders it with the shared client shell as **Client portal**. The page contains only the permitted client search/selector, a valid-session resume notice, and Return to admin. A signed-in administrator visiting the bare client-portal URL reaches this selector. An unsigned request uses the existing administrator sign-in and one fixed internal return destination; invitation, checkout, invoice, support and other customer deep links are not hijacked.

Owners can search and open any active client through the existing audited, time-limited review mechanism. Ordinary administrators require `support.login` permission and an explicit client support policy. The list enforces these limits in SQL before search and pagination. Customer-only accounts, matching emails, disabled users, expired/revoked sessions and unrelated support contexts do not gain administrator access.

Selecting a client opens the actual shared client workspace with a **Viewing [client] as administrator** banner. All client links retain the bound review token. **Switch client** ends the current review before returning to the selector; **Return to admin** ends it before returning to administration. Software/licenses and financing have audited read-only review presentations. Customer-only purchases, downloads, installation changes, signatures, decisions, payments and automatic-billing controls are not rendered, and the existing controller-level support blocks remain authoritative.

The selector is not a combined cross-client financial report. It does not manufacture a customer account, merge identities by email, copy passwords, approve client contracts, charge cards, change security settings or provision services. An administrator's personal purchases still require a real customer identity.

## Source and tests
The successor changes the ordinary expanded PHP source and refreshes the canonical installer inventory through the established builder. The historical assembly script remains as prior integration evidence; it is not rerun against this successor. No existing authentication, support, payment or sandbox guard is removed.

Pure routing/search/presentation assertions, real SQL assertions, and installed Chromium journeys cover administrator sign-in return, client search/pagination, the shared client shell, owner review, staff restrictions, CSRF refusal, customer/admin email collision, read-only software/financing views, switch/end behavior, session revocation and desktop/mobile layout against synthetic data only. Hosted database/browser results are claimed only after the exact candidate completes them.

The broader 1.26.1 release gates remain: exact-source integration and populated upgrade regression, expanded financial/payoff-state retention, Apache/rewrite and historical full-suite reconciliation, independent security review, reviewed main integration and verified standalone packages. Prior source successes are not evidence that this increment already passed. This is not a production deployment or an approved UPDATE ZIP.
