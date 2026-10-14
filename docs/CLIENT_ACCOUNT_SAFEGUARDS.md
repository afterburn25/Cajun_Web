# Client account safeguards — 1.23.0 candidate

Version1.23.0 is under acceptance. The current published download remains1.22.0. These safeguards improve the existing client administration and invitation screens; they do not add a hosting control panel.

When two administrators edit the same client, an older form is refused instead of overwriting the newer account. The page keeps a safely displayed copy of your unsaved details beside the current saved accounts. Review both, then deliberately re-enter the change you still want. Administrators with client-management permission retain access.

Client edits and invitation changes invalidate older client sessions. Invitation creation displays one private link for manual sharing, and replacing it invalidates the earlier link. Activation requires the still-active client and an unused, unexpired invitation; concurrent activation cannot set two passwords. Creating an invitation does not send an email.

Leave a client-support session before changing client accounts. Both opening and submitting client administration are refused during support. Client account changes and their audit records succeed together, so a failed audit cannot leave an unrecorded edit.

The existing one-page keyless installer, organized packages, website design, portal and mail sending remain. For existing installations, use the separately tested UPDATE package when1.23 is actually published, preserving configuration, private keys, files and database backups. Candidate tests include the actual published1.22 package and retained nonempty submission/draft history; those new tests are still pending at this preparation checkpoint.

The release also carries reviewed internal hosting records and durable operation groundwork. It does not enable hosting provisioning, deletion, a background worker or new client hosting controls. [Technical client contract](CLIENT_AUTHORITY.md), [hosting boundary](HOSTING_OPERATIONS.md), [release procedure](RELEASE_WORKFLOW.md).
