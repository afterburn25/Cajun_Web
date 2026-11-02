# Client account security

Development integration; not yet a published installation package.

Customers can open **Your account** from their portal, view their current name/email and change their own password using their current password and a matching new passphrase. This is a genuine credential change, not a repeated internal administrator-password prompt. Existing installations require no database migration.

Changes use the current primary customer identity, not a submitted target ID. The service revalidates the same identity inside the shared account lock and transaction, checks the current password and writes a minimal audit together with the credential update. The successful change invalidates unused recovery invitations and other client sessions, rotates this browser's session ID and CSRF token, and retains its original absolute session expiry. Administrator credentials, projects, hosting, billing and licenses are unchanged. Support/review sessions and administrator-only sessions cannot use this action.

Scoped company contacts remain a separate pending integration. Any contact-session markers are refused by this primary-account service rather than silently falling back to the parent client. The older #203–#213 stack must be reconciled with newer licensing, financing, tickets and proposal permissions before contacts are enabled. This subset does not claim those historical PRs are fully integrated.

Verification uses disposable loopback databases, synthetic users and real browser sessions. It includes stale-session revocation, wrong/current/mismatched passwords, expired/disabled/customer-review contexts, concurrent writes, audit rollback, rate limits, CSRF and private response headers. Nothing here enables live providers, charges cards, sends email or changes actual customers.
