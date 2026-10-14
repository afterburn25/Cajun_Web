# Client account authority

## Current1.23 candidate

Combined85/a9 has clear independent reviews6166706518/6167231488 and complete downloaded8,234-check application38554890744 across21successful workflows, actual current screenshots and Windows/Linux package agreement. Candidate1.23 adds actual1.22 upgrade/recovery coverage; that newer acceptance is pending. The runtime below is preserved. [Owner-facing guide](CLIENT_ACCOUNT_SAFEGUARDS.md). Earlier pending-source notes are historical.

## Unreleased integration

`codex/client-authority` starts from hosting operation PR82 at `1f590740290a3cb4716de3345dbc50b408350896`. It closes the remaining production writers to client identities before a future provider coordinator can rely on the shared authorization guard. It changes existing client administration and public invitation activation, without a new schema or release version. Published1.21 remains immutable; this branch is not a replacement download.

## Current account changes

`site/app/clients/accounts.php` owns all three client identity writes: create/edit, invitation creation and invitation activation. Each holds the same database-scoped advisory guard as staff and hosting operations and owns a separate transaction. Nested transactions refuse. Admin writes re-read current staff authentication, enabled role and `clients.manage` after acquiring the guard. Existing delegated administrators retain this permission; support cannot change client identities.

Existing-account edit and invitation forms carry the exact current32-character authorization version. A changed, missing or malformed version refuses without modifying the account, pending invitation or audit. Successful edits rotate that version and clear outstanding invitations while preserving the password. Invitation creation also rotates the version, stores only a SHA256 token hash and expires after24hours. These transitions immediately revoke older client sessions. Each identity change and its strict audit insertion commit together; an audit failure rolls back the whole change.

The browser shows an escaped read-only copy of unsaved client details on a failed edit, beside forms populated from currently saved accounts. Users must review and explicitly re-enter changes. Client administration responses prohibit caching and referrer disclosure. The original theme and existing form permissions remain.

## Public activation

A valid invitation remains usable without a staff login. Activation verifies the exact raw64-character token, current active client and expiry after acquiring the shared guard, then checks expiry again after password hashing. It consumes the token and rotates client authorization atomically with audit. Two activations cannot establish two passwords. Public actions use the audit schema's existing non-staff actor0 rather than inventing a staff identity. Audit detail contains only the client id, never the invitation, email, password or hash. Existing CSRF, password and rate checks stay in the portal route.

Invitation creation still displays a private URL once for manual sharing. It sends no email. Disabling a client or editing their account can invalidate a pending activation. An already-held shared guard prevents activation from bypassing a concurrent authorization decision. Lock timeout refuses instead of proceeding unguarded.

## Evidence and remaining integration

Local PHP8.4/native MariaDB10.11.16 passes60 checks, including strict audit rollback, competing process edits/invitations/activations, grant revocation while waiting, deactivation and expiry during activation wait, session revocation, password preservation and malformed versions. PHP8.2/8.4 CI also retains94 hosting-record and79 hosting-operation checks per runtime. Those current CI results remain pending until downloaded and inspected.

Actual packaged HTTP/Chromium cases are added for real forms, CSRF, stale revisions, unsaved markup, manual invitation replacement, public activation, sessions, delegated access and desktop/mobile conflicts. Direct internal access is covered by both HTTP and Apache. Full application/recovery acceptance and independent review remain pending. The application job retains its complete suite with a75minute ceiling and explicitly incomplete Apache progress checkpoints, following the confirmed45minute mail-suite timeout. No test is removed or treated as passed based on partial output.

This does not implement a provider coordinator, live hosting calls, payments, SSH or customer-mail delivery. The future coordinator must hold the shared authorization guard through bounded provider work while keeping database transactions short, and consume only a newly committed dispatch claim. Follow [hosting operations](HOSTING_OPERATIONS.md) and preserve original Devin branches.

## First packaged run and support renderer correction

Native push38549022824 and PR38549061993 each pass466 executions. Application38549059961 atdae2f82 passed all native checks but stopped after1,200 passing HTTP assertions at the expected support-selector403 check. The write guard already refuses support changes; the admin client renderer now also emits403 and excludes edit/invitation forms for GET or POST support selection. Tests separately assert no mutation, explicit refusal and absent forms. Full replacement acceptance and actual conflict screenshots remain pending; earlier partial output is not a release pass. The exactdae2f82 Windows test archives verify (COMPLETE2544, UPDATE2531, FULL5100), without changing published1.21 assets.
