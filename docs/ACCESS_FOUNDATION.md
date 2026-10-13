# Staff and mailbox permission foundation

Started 2026-10-13 under the owner's instruction to start independent work while another worker finishes 1.1.0 and publishes its source to GitHub. Branch: `codex/platform-foundation`. Scope: the decision component for A-05/A-06, not a completed admin-account or webmail feature.

## Implemented component

The standalone PHP 8.2+ classes in [foundation/access](../foundation/access) need no framework, database, network access or Composer installation. They do not replace the existing application's authentication. Keep this directory outside deployment packages until the verified 1.1.0 source is inspected, an adapter is integrated and the release inventory is updated.

- [AccessContext](../foundation/access/AccessContext.php) carries an immutable snapshot of current server-side identity, permissions and mailbox grants.
- [MailboxGrant](../foundation/access/MailboxGrant.php) carries exact mailbox identity, separate read/send rights and explicit enabled state. Duplicate grants for the same identifier are rejected rather than resolved by array order.
- [PermissionPolicy](../foundation/access/PermissionPolicy.php) returns a boolean for a named permission and, for mail, an exact mailbox identifier. Unknown permissions and invalid contexts deny access.

An administrator or restricted staff member starts with no implicit privileges. An owner has the known global management permissions, but still needs an explicit mailbox grant to read/send that mailbox. Customer identities cannot gain staff privileges by supplying an owner role or permission list. Blank identities, unrecognized roles, inactive accounts and mismatched/nonpositive authorization revisions are denied.

## Logical permissions

These are adapter contract identifiers, not assumed existing routes or database column names.

| Identifier | Scope / purpose |
|---|---|
| `staff.manage` | Global: staff-account management entry point |
| `staff.permissions.manage` | Global: permission assignment entry point; separate from ordinary staff management |
| `mail.accounts.manage` | Global: configure mailbox connections; does not grant message read/send access |
| `mail.templates.manage` | Global: manage reusable staff mail templates |
| `billing.settings.manage` | Global: financial configuration entry point |
| `hosting.settings.manage` | Global: provider configuration entry point |
| `mail.read` | Exact mailbox: requires capability and an enabled grant with read access |
| `mail.send` | Exact mailbox: requires capability and an enabled grant with send access |

For non-owners, both the explicit capability and mailbox-specific grant are required. Read access does not imply send access or vice versa. No wildcard capabilities or mailbox IDs exist. IDs are compared exactly without case folding or numeric coercion. Supplying a mailbox scope to a global permission is rejected. Support impersonation, VPS SSH and other future capabilities are not introduced by this module; unknown requests deny access.

## Trusted adapter boundary

Never construct an access context from browser fields, query parameters, headers supplied by the user or client-side storage. Authenticate through the existing 1.1.0 code first. Resolve current server-side values for each protected request:

| Context field | Required source / meaning |
|---|---|
| `actorId` | Authenticated actor's existing immutable identifier |
| `principalType` | Authoritative identity kind; only `staff` is accepted here |
| `role` | Current server-loaded role: `owner`, `administrator` or `staff` |
| `active` | Current server-loaded account status |
| `authorizationVersion` | Positive current server-side revision, advanced when authorization must be revoked |
| `sessionAuthorizationVersion` | Revision captured in the authenticated server session |
| `permissions` | Current server-loaded explicit capability list |
| `mailboxes` | Current server-loaded memberships and mailbox enablement; no stale session-only grant cache |

The snapshot's values must be fresh enough to enforce revocation. The component can reject a mismatched revision, but it does not increment revisions, revoke real sessions or supply concurrency guarantees. Build a fresh context for each request; do not keep it across a role/deactivation/grant change. Mailbox enablement combines both the actual connection's enabled state and current membership status.

`allows()` is one authorization condition. Callers must additionally validate CSRF, request data, the actual target record and any operation-specific constraints. A global management permission is not authorization to expose raw credentials, assign any desired role, perform live provider writes or execute arbitrary account mutations.

## Integration work after 1.1.0 is published

1. Inspect the real owner/admin/customer authentication and schema; map this contract to existing identities without duplicating login systems or guessing migration column names. Preserve the existing verified owner explicitly.
2. Implement additive schema/migration support only where needed. Test a populated installation and preserve customer data, config, media and locks.
3. Connect current permission/session revisions to every protected page/API and operation. Atomically advance the revision and revoke invitations/reset tokens on deactivation or relevant account changes. Verify rejection on the very next request.
4. Implement grants and staff lifecycle in a dedicated service: cannot grant beyond the actor's authority; owner assignment needs explicit owner authority; serialize last-owner changes in a native database transaction so concurrent deactivations/demotions cannot remove every owner. The pure policy alone does not provide these controls.
5. Add one-time expiring hashed invitation/setup/reset tokens, existing login/password protections and the administrators UI. Keep mail delivery distinct from link generation; never send reusable passwords. Integrate owner-only link copying when SMTP is unavailable.
6. Apply mailbox decisions to list/detail/attachments/search/send/templates/notifications and verify resource ownership using authoritative records. Filter notification counts before returning them. Keep staff mail out of public caching and customer sessions.
7. Run native database, browser and upgrade acceptance from A-05/A-06. Add source files to the canonical installer/build inventory and regenerate hashes only after integration. Do not claim the complete feature from isolated unit tests.

## Verification

Run `php tests/access/run.php`. The dependency-free [test runner](../tests/access/run.php) checks the role/capability matrix, invalid identities, inactive/stale sessions, scope separation, mailbox membership, read/send independence, wildcard/unknown denial and malformed grants. It uses synthetic identifiers only and exits nonzero on failures without relying on PHP's configurable assertions.

Local PHP 8.4.25: 142 checks pass and all four PHP files pass syntax checks. [GitHub access-policy workflow](../.github/workflows/access-policy.yml) runs syntax and those same isolated decisions in official PHP 8.2 and 8.4 CLI containers with a read-only checkout mount, no container network, no application secrets and no database. [Run 38349906968](https://github.com/afterburn25/Cajun_Web/actions/runs/38349906968) verified source commit `5cf3f01975dec7365d57415eecd061dda43a4823` on PHP 8.2.34 and 8.4.26: 142 checks and all syntax checks passed on both. [PR merge-ref run 38349921814](https://github.com/afterburn25/Cajun_Web/actions/runs/38349921814) also passed, alongside the documentation check.

These checks do not establish login, invitation delivery, real revocation, last-owner concurrency, database migrations, admin UI, live webmail, installation, target-host behavior or production deployment. Those remain pending integration.
