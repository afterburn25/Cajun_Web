# Admin webmail, staff access, templates and email notifications

Approved scope: 2026-10-12. Requirements only; no implementation or account connection is established. Inspect the owner's current 1.1.0 source first and extend existing capabilities without replacing functioning work.

2026-10-13 progress: the isolated [access foundation](ACCESS_FOUNDATION.md) implements staff/mailbox permission decisions and local unit tests while the separate worker completes 1.1.0. It does not implement administrator creation, sessions, invitations, mailbox connections, mail UI or sending; A-05/A-06 remain incomplete until application integration and acceptance.

## Owner request and intended experience

Add an Email section inside CajunWebPro admin for sending and receiving mail from multiple accounts, with account setup controls and reusable sending templates. Allow the owner to add additional admin accounts. Show an unread-email bell in admin and on the public site only for authenticated admins; a notification opens the corresponding message inside admin webmail. Preserve the original navy/gold design and PHP/MySQL shared-host deployment.

## A-05 — Additional admin accounts and access (foundation)

- Admin -> Administrators: list accounts, add/invite an administrator, edit display name/login email, assign role and mailbox access, reset access, and deactivate/reactivate an account.
- Preserve the existing owner account. Distinguish Owner, Administrator and optionally restricted staff roles; permissions cover administrator management, mailbox configuration, mailbox read/send, template management, financial settings and other existing admin functions. Adding an admin must not silently grant access to every mailbox or payment credential.
- Only the owner or an expressly authorized role can create accounts or grant permissions. Prevent privilege escalation and removal/deactivation/demotion of the last active owner. Deactivation invalidates active sessions and pending invitation/reset tokens immediately.
- Use one-time expiring invitation/password-setup links, existing strong password/session/reset protections, rate limits and optional MFA. Never email a reusable password. If outbound mail is not configured, provide an owner-only way to copy the expiring setup link; do not report an invitation as delivered when it was not sent.
- Audit account creation, invitations, permission changes, resets and deactivation without passwords/tokens. Keep staff administrators separate from customer portal accounts and enforce permissions server-side on pages and APIs.

**Acceptance:** Owner creates a second admin and that account signs in with only assigned permissions. Ordinary customers and restricted staff cannot create admins or elevate permissions. Duplicate login, expired/reused invitation, reset, deactivation with an active session and last-owner protection are tested. Existing owner access and customer isolation survive migration.

## A-06 — Multiple email accounts and webmail

### Account setup and access

Admin -> Email -> Accounts provides Add account, Edit, Test incoming connection, Test outgoing connection, Enable/disable sync, Reconnect and Remove connection. Store display name, email address, incoming IMAP server/port/encryption, outgoing SMTP server/port/encryption, username and supported authentication credentials. Offer provider presets only where verified; support manual server settings. Use OAuth where the provider requires it and supported password/app-password authentication where allowed. Incoming/outgoing connection checks show separate sanitized errors and must not silently send a test message. A test send requires an explicit action and chosen recipient.

Assign each mailbox to specific admins with read and send permissions and a default sending identity/signature. Verify allowable From identities; do not allow arbitrary address impersonation. Mask stored credentials, preserve an existing secret when an edit is blank, encrypt secrets/tokens with a key protected outside the public root, and keep them out of logs, exports and screenshots. Validate permitted remote endpoints, certificate verification, ports, timeouts and network boundaries before connections.

Staff webmail account setup connects existing mailboxes; saving an IMAP/SMTP connection must not pretend to provision a mailbox or change DNS. The owner's later full client-portal requirement adds customer-owned mailbox management/provisioning through verified 20i capabilities; see [Client portal roadmap](CLIENT_PORTAL_ROADMAP.md). Keep those customer controls, admin webmail and application notification SMTP separate, with their respective permissions.

### Inbox and compose

- Account switcher and optional combined inbox showing only permitted mailboxes, always with a visible account label.
- Inbox, Sent, Drafts and supported provider folders; paginated message list, search, unread/read state, star/flag where supported, refresh and last successful synchronization status.
- Message view with sender, recipients, subject, time, body and attachments. Compose, reply, reply all, forward, save draft, choose permitted sending account, To/Cc/Bcc, signature and attachment controls.
- Use SMTP submission for outgoing mail and IMAP for reading/syncing supported folders. Record successful server acceptance truthfully; it is not proof of recipient delivery. Preserve drafts after errors. Confirm provider Sent-folder behavior to avoid duplicate sent copies.
- Handle provider failures, authentication expiry and ambiguous send timeouts without dropping drafts, claiming success, or blindly resending a potentially accepted message. Display actionable status, with bounded retries and a controlled refresh action.
- Refresh read/unread state from the provider so actions in another mail client can be reflected. Preserve provider identifiers and folder validity; never associate an old notification with another message after a folder move/rebuild or identifier reuse.
- Move to Trash only through a deliberate mailbox action; distinguish archive/trash/permanent delete according to provider support. Removing a connection revokes local access and sync, and never deletes the remote mailbox or remote messages. Local retention/cleanup needs an explicit policy.

### Message and attachment protection

Treat all email content as untrusted. Sanitize HTML and isolate message rendering; block scripts/forms and remote tracking images by default, with explicit per-message image loading. Protect downloads and uploads with mailbox authorization, size/type limits, safe filenames and non-executable private storage. Escape headers, reject header injection, and apply CSRF protection to sends and state changes. Do not store raw message bodies or attachments in public web assets. Offer plain-text reading and accessible keyboard/mobile controls.

**Acceptance:** With two isolated test mailboxes, independently connect, receive, read, compose, reply/reply-all, forward, save/recover drafts, attach/download and send from the chosen authorized account. Verify mailbox isolation, invalid credentials, provider outage, token expiry, attachment limits, hostile email HTML, uncertain send outcome, Sent-folder behavior, external read-state changes and safe connection removal. Connection checks do not send customer email.

## A-07 — Reusable sending templates

Admin -> Email -> Templates supports create, edit, duplicate, preview, archive and select a template while composing. Each template has a name/category, subject and formatted body with a plain-text alternative. Support generic messages and optional project-related templates such as inquiries, proposals, payment requests, follow-ups and maintenance updates. Use the same brand styling as the site.

Provide a documented allowlist of placeholders, such as customer name, company, project name, approved invoice amount, due date and payment link. Values must come from records the composing admin is authorized to access or explicit validated input. Escape inserted values by context; no arbitrary code or unrestricted template expressions. Show a fully rendered preview, block unresolved required placeholders, and allow editing the resulting draft before sending. Selecting or saving a template never sends mail. Keep a snapshot of what was sent so later template edits cannot alter history. Shared versus personal template visibility follows staff permissions.

Signatures and sending account remain explicit in compose. Transactional receipt/reset templates must retain required system fields and use controlled notification workflows; choosing a normal email template cannot mark an invoice paid, approve a project or generate a privileged reset token.

**Acceptance:** Create and reuse a template from two permitted mailboxes, preview correct subject/body and authorized project variables, block missing required values, deny unauthorized template/record access, verify escaping and archived-template handling, and confirm draft editing precedes an explicit Send action.

## A-08 — Unread-mail bell in admin and on the public site

- Show an accessible bell with the unread-message count across mailboxes the signed-in admin may read. The popover lists account, sender, subject and received time; each item opens that exact message in authenticated admin webmail, with a View all email action for the inbox.
- Show the same bell on the public site's header only for authenticated, authorized administrators. It is an admin convenience on public pages, not a customer or visitor email feature. No unread counts, sender/subject previews, mailbox identifiers or credentials may be included in anonymous HTML, cached pages, client bundles or API responses.
- Fetch notification data through a permission-checked private endpoint with non-cacheable responses and server-side session validation. Public page caching/CDNs must not distribute admin notification data. Hiding an element in CSS is insufficient.
- Counts represent current provider unread state after synchronization; opening the bell does not mark every message read. Opening/marking a particular message updates the relevant provider flag and count after confirmation. Shared-mailbox read state is mailbox-wide; per-admin popup dismissal must not silently change that provider state.
- Refresh while the authenticated admin is active using bounded polling or another verified shared-host-compatible mechanism; allow manual refresh and show a stale/connection-error indicator. Background refresh uses configured scheduled jobs where available; do not promise instant/push delivery or require a persistent server process.
- Recheck permission when following a notification. Revoked mailbox access or disabled admin sessions deny access immediately; moved/deleted messages produce a safe unavailable state or resolve through verified provider mapping, never to a different message. Preserve only validated internal return paths through sign-in.

**Acceptance:** New unread test mail updates the admin bell and admin-only public-header bell after sync. Clicking an item opens the correct account/message; read/unread changes reconcile across views. Anonymous visitors and ordinary clients receive no private markup, counts or payloads even with shared caching. Test two admins with different mailbox grants, logout/revocation, deleted/moved messages, stale sync, keyboard navigation and mobile layout.

## Delivery order and verification limits

1. Recover/inspect 1.1.0 and preserve existing owner access, data and settings. Verify actual host capabilities for secure IMAP/SMTP and scheduled work; bundle any necessary PHP libraries in the deployable package without requiring customer-side Composer/SSH.
2. Deliver A-05 admin accounts/permissions in M1 alongside identity. Mailbox access must be enforced before messages or attachments are exposed.
3. Deliver A-06 account setup and tested webmail, then A-07 templates and A-08 bells as one coherent admin-mail milestone within M5/HC-12. These can proceed independently of VPS once their identity, mail-host and private-storage prerequisites pass.
4. Complete native provider/HTTPS/multi-user, hostile-message, cache-isolation and recovery checks before rollout. Extend backup/migration/retention rules for private mail metadata, drafts, attachments and encrypted credentials. Avoid duplicating remote email unnecessarily.

Current status is approved scope only. No mailbox is connected, no new administrator is created, no message is sent and no notification UI is claimed implemented by this document. Account credentials belong in the eventual secured admin settings, not chat or Git.
