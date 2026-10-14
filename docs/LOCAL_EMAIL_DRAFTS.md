# Private local email drafts

Development branch implementation in PR #75; not yet published or accepted through packaged browser/upgrade tests.

Open **My email accounts**, then **My email drafts** or **New draft** for a sending account. On an upgraded site, an owner first chooses **Prepare local drafts** and confirms their current password. Fresh installation prepares an empty draft library. Setup preserves existing account settings and messages.

Each staff member sees only drafts they authored, including their saved subjects in the list. An owner cannot browse another author's draft. Both the global Send permission and a current Send assignment for the selected mailbox are required. Disabling an account or revoking access hides its drafts without deleting their content.

Choose the sending account, enter bare email addresses separated by commas in To, Cc and Bcc, and write plain-text subject and message fields. Drafts may be unfinished. Up to 20 recipients are supported across all three lists; duplicate addresses are rejected. The subject supports 200 UTF-8 bytes and the body 20,000 bytes after newline normalization. Validation keeps ordinary entered text available for correction, including Unicode text that exceeds a byte limit.

**Save draft** stores a private record. **Preview draft** shows escaped text without saving. Neither sends a message. A repeated initial save with the same form and content reopens the existing draft. Concurrent or stale edits keep the entered text and earlier version; compare with the current saved version in a separate tab before editing again.

Changing From accounts, or accepting changed settings for the same account, requires an explicit review confirmation. The From address comes from the selected saved mailbox. There is no arbitrary From, Reply-To or envelope identity.

Staff with template-library access can expand **Insert an email template**, select an active permitted template, and enter the values its fields need. Confirm replacement when the subject or message already contains text. Insertion produces an editable copy; save it explicitly. Later source-template changes do not rewrite the saved draft. Client/project fields are entered manually; no client records are fetched by this workflow.

**Discard saved draft** retains the last saved text and metadata history in the author's discarded view. Unsaved edits are not stored. Discarded drafts are read-only. This does not delete any server message.

Draft content is stored as private business data in the configured database, like project messages. Audit and revision history contain identifiers, actions and versions, without subjects, recipients or bodies. Back up the database and all configured private storage and original encryption keys together.

This increment does not synchronize a server Drafts folder, send SMTP mail, upload attachments, produce HTML messages, reply/forward, manage signatures or authorize recurring mail. Outgoing delivery and uncertain-send handling require their own transport and application acceptance. Existing system-email configuration is separate.

Implementation: [contract](MAIL_DRAFTS_CONTRACT.md), [account access](ADMIN_EMAIL_ACCOUNTS.md), [template library](ADMIN_EMAIL_TEMPLATES.md), [update/recovery](STRUCTURE_MIGRATION.md).
