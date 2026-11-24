# Migrate CajunWebPro 1.26.1 to 1.27.0

These instructions apply only to the published 1.26.1 COMPLETE-UPLOAD release. Do not use the UPDATE archive as a fresh installation package.

## Before staging

1. Verify the installed version is 1.26.1.
2. Back up the database, `config.php`, `.installed.php`, the complete external private-storage directory, public media, encryption keys, payment/provider/mail key material, and any retained update journal.
3. Keep the original 1.26.1 package and its hashes available for recovery.
4. Confirm no earlier update or recovery is in progress.
5. Do not enable live payment, provider, mailbox, SMTP, or provisioning operations for the migration.

## Stage and review

1. Extract `CajunWebPro-1.27.0-UPDATE.zip` into the installed web root. Every member must remain below `cwp-update-1.27.0/`.
2. Open `/cwp-update-1.27.0/owner-update.php` over HTTPS while signed in as the active owner.
3. Review the recognized starting release and backup confirmation.
4. Apply once. If interrupted, return to the same authenticated updater and use its supported recovery or resume action; do not copy individual files by hand.
5. After the file update reports Complete, sign in again and open **Version & database**.

## Reviewed database updates

Review the exact plan and apply the required/selected updates in the dependency order displayed by the application. The 1.27.0 additions and upgrades are:

- `software_onboarding_version` → 1
- `client_identity_schema_version` → 1
- `hosting_storefront_version` → 1
- `gateway_receipts_schema_version` → 1
- `hosting_payments_schema_version` → 1
- `project_private_notes_schema_version` → 1
- `mail_copies_schema_version` → 1
- `mail_drafts_html_ready` → 1
- `mail_files_schema_version` → 1
- `mail_flags_schema_version` → 1
- `mail_inbox_schema_version` 1 → 2
- `mail_messages_schema_version` 1 → 2
- `mail_replies_schema_version` → 1

Optional storage stays off until explicitly selected. Schema preparation creates no charge, provisioning request, provider write, mailbox read, or customer message.

## Verify and clean up

Confirm application version 1.27.0; owner and customer sign-in; customer identity flows; Shared checkout in disabled/local-gateway mode; projects and private notes; catalog/licensing; mail account/draft/inbox pages; configuration and private-storage paths; existing customer, invoice, payment, license, provider, project, and mail records; and an empty maintenance state.

Remove only `cwp-update-1.27.0/` after verification. Retain backups and the qualification report. Production deployment and live-operation enablement require separate authorization.

## Fresh installation

Use `CajunWebPro-1.27.0-COMPLETE-UPLOAD.zip`, not UPDATE. Extract it directly into the intended document root and use the one-page keyless installer. FULL-PROJECT is development source and documentation, not the upload package.
