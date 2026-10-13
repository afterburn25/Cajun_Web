# Administrator email-template library — bounded A-07

Published and download-verified [1.12.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.12.0) at `c526e960db13246c217f2b9d6f421c17b8d48261`, from published 1.11.2. [Actual combined acceptance](TEST_REPORT_1.12.0.md). This library stores and previews reusable text. Account setup, inboxes, sending, signatures, compose insertion and transactional receipts/reset messages remain separate work.

## Use

Open **Email templates** in administration. On an existing installation, an owner enables additive storage with their current password; existing content/accounts/settings remain intact. Fresh installations prepare the empty library automatically. Owners can grant **Personal and shared email templates** under Administrators. This grant provides no mailbox, sending, account-management or client-record access.

Create a personal or shared draft, enter a name/subject/message, and save. Use exact `{{client_name}}`, `{{project_name}}`, `{{studio_name}}` and `{{portal_url}}` fields; the last resolves to this site's client sign-in page. Sample names are entered manually for previews. No client records are loaded. Missing sample values are explicitly listed. Output is plain text and HTML is escaped; preview never sends or saves a message. Double braces are reserved for valid fields; ordinary single braces are literal text. Subject/name are single-line, bounded UTF-8 text.

Activate a saved draft when ready. Editing an active template returns it to draft; activate again after reviewing changes. Archive removes it from the active list and retains history. Duplicate any visible template, including an archived one, into a new personal draft. Visibility is selected at creation and stays fixed; duplication always starts personal. Each saved operation retains a complete immutable snapshot and actor/time; history is paginated and earlier text can be copied into a new draft.

Personal templates remain visible only to their creator, including when another owner is signed in. Shared templates are visible to all staff with template permission; their creator or an owner can edit/activate/archive them. Other staff may preview or duplicate shared records. Support sessions and client identities cannot access this staff library. Concurrent changes require reloading; an old form never silently overwrites a newer version. Invalid submitted text is preserved in the form, escaped for display.

## Implementation and acceptance

Canonical model: `foundation/email-templates/`, mirrored into protected `site/includes/foundation/email-templates/`. Reuses Devin #56 through `f3522f6` with reviewed single-pass literal substitution, strict grammar and string merge values. Fifty isolated PHP 8.4 checks pass; this is component evidence only. The application adapter is `site/app/mail/templates.php`; administration and schema/migration files are organized in their corresponding directories. No new root business file, runtime dependency, daemon or customer CLI step.

Additive InnoDB tables `mail_templates` and `mail_template_revisions` retain current state and immutable versions. Per-record revisions plus row locks reject stale writes; the existing staff mutex serializes authorization changes with template mutations. A strict audit insert is part of every mutation transaction, so audit/history failures roll back current state too. Setup rejects incompatible or populated unrecognized tables rather than adopting or replacing records. No hard deletion is provided. Owner file recovery preserves added database tables/records; it does not pretend to reverse database changes.

Required evidence: native migration/idempotence, role/privacy/support boundaries, revoked sessions, malformed fields, literal sample handling, audit rollback, stale and simultaneous edits; HTTP/Chromium create/preview/activate/archive/duplicate, invalid draft preservation, HTML escaping, personal isolation, mobile rendering and real 1.11.2 upgrade/recovery. Existing application/package/Apache/historical-update checks remain required. Actual named results and their limits are recorded in the linked release report.
