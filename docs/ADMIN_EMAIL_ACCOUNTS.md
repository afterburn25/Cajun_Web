# Email account preparation — bounded A-06

Candidate 1.13.0 on `codex/admin-mail-accounts`, from verified 1.12.0. Application source is implemented; native/browser/upgrade acceptance and publication are pending. This increment prepares multiple email profiles and staff assignments. It has no IMAP/SMTP connection, inbox, message synchronization or sending. Existing website email delivery settings remain separate and unchanged.

## Owner setup

Enable Administrators first on an older installation. Open **Email accounts** and enable additive storage with your current administrator password. Fresh installation prepares empty account tables. Setup never adopts unknown populated tables or replaces existing records.

Add a label, email address, incoming IMAP server/port/TLS mode/username/password and outgoing SMTP equivalents. Full server hostnames are required; URLs and plaintext modes are rejected. Use the settings supplied by your provider. Saving validates the form only: **Connection not checked** remains the correct status. Default ports are editable suggestions, not proof of provider compatibility.

Passwords are encrypted with a separate private `mail-master.key` outside the document root. PHP OpenSSL and owner-only file permissions are required. Back up that key and the database together; missing or damaged keys are never replaced while encrypted account records remain. Existing storage is not relocated. Passwords and ciphertext are not included in forms, history or audit details. Blank password fields on later edits keep their saved values; after validation errors, reenter any password you intended to replace. Whitespace inside passwords is retained.

**Make this saved account available to assigned staff** controls preparation availability. It does not verify credentials or activate transport. Disable removes availability on the next server authorization check. Clear removes current passwords and disables the account while retaining metadata and assignments. Reenter both passwords before enabling a cleared account. There is no destructive account deletion or automatic credential restore.

## Assignments and permissions

Only owners can manage account settings and assignments in this increment. Each mailbox needs a separate assignment for every person, including owners. Assign read and send independently, with optional expiry from 1–365 days or no expiry. Saving an assignment begins the selected duration again. Disable the assignment to revoke it immediately; prior revisions remain recorded.

Administrators additionally need **Read assigned email accounts** and/or **Send from assigned email accounts** under Administrators. An account assignment cannot override a missing global permission. Template permission does not grant mailbox access. Owners have global administrative authority but still need explicit per-mailbox read/send assignments. A saved profile's creator has no automatic mailbox capability.

**My email accounts** shows only currently enabled, unexpired assignments with matching current staff permissions. It shows basic labels/addresses and the preparation state, never credentials, server usernames or another account's settings. Removed permissions, disabled users, revoked sessions, disabled profiles, revoked/expired/future assignments and client/support identities deny access on fresh server checks. No inbox or sending control is presented as working.

## Implementation

`site/app/mail/accounts.php` adapts the corrected canonical `MailboxProfile` and `MailboxAssignment` to the existing `AccessContext`, `MailboxGrant` and `PermissionPolicy`; it adds no competing role/session system. Canonical message identities include UIDVALIDITY and use unambiguous length-prefixed tuples, but this preparation increment does not consume message summaries or build inboxes.

`site/app/mail/credentials.php` stores only current AES-256-GCM ciphertext, bound to the database prefix, account ID and password purpose. Keys use verified external private storage and mode 0600; symlinks and key replacement over existing ciphertext are refused. Decryption remains an internal primitive for an already authorized caller. Future transport code must independently enforce fresh authorization, endpoint/DNS/egress/TLS validation and approved mailbox testing before connection.

Four additive InnoDB tables hold current profiles, profile metadata revisions, current per-user assignments and assignment revisions. Unique keys and row locks protect versioned writes. Account changes, assignment changes and existing staff revocations share the staff mutex. Strict audit/history failures roll back the corresponding current row. Stale account forms retain the submitted settings and stale revision, requiring a genuine reload. Owner setup is idempotent and rejects incompatible columns, keys and identifier collations. File recovery preserves added database rows and the private key; it does not pretend to reverse schema or restore a different key/database pair.

## Acceptance gates

Required evidence: native schema/role/credential/authentication boundaries, key loss/tampering/permissions, strict audit rollback, concurrency and revocation; HTTP and Chromium setup/save/error preservation/private listing/clear/mobile checks; actual published 1.12.0 upgrade and recovery preserving earlier templates, new account records, assignments and the original private key. Retain all earlier application, archive, private-file and Apache checks. Named results and screenshots must be reported before release. Syntax and model tests alone are insufficient.

Real mail login/TLS/provider compatibility, fetching/rendering messages, attachments, synchronization, compose/template insertion, sending and email notifications remain later increments with their own acceptance. No live customer mail, provider operation or host deployment is authorized by this candidate.
