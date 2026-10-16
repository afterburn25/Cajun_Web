# Updating CajunWebPro 1.24.0 to 1.25.0

Release-candidate instructions. Publication and download verification are still pending. Use these instructions with the final verified `CajunWebPro-1.25.0-UPDATE.zip`; a development ZIP or successful parent test run does not establish release acceptance.

The update preserves the existing installation and uses the owner update page. It does not run a fresh installer, reset the database, enable paid provider operations or deploy itself to your server. The [frozen scope](RELEASE_SCOPE_1.25.0.md) identifies the included increment and unfinished features.

## Before uploading

1. Confirm the installed version is 1.24.0 and record the website address, including any subfolder. Keep the existing configuration and original private-storage location.
2. Create one recoverable backup set containing the complete database, website files, configured private storage and all original encryption keys. Include existing uploads, project deliveries, mail records and update recovery journals. Keep the backup outside the public website. Check that the backup can be restored.
3. Schedule a maintenance window and pause other administrative changes, scheduled work and uploads while updating. Retain pending or uncertain provider/payment/mail history; an unknown outcome must never be cleared to make an action available again.
4. Download the verified UPDATE and `SHA256SUMS.txt` from the same final 1.25.0 GitHub Release. Check the archive checksum. FULL-PROJECT is for development; COMPLETE-UPLOAD is for a fresh installation.

## Apply the staged update

1. Extract the UPDATE into the existing website directory using your hosting file manager. It must create `cwp-update-1.25.0/` beside the current `index.php`. Preserve the archive's directories. Do not copy the inner `site/` payload over the live files manually, and do not overwrite a different existing staging directory.
2. Open `https://YOUR-SITE/cwp-update-1.25.0/owner-update.php`. For a subfolder installation, include that subfolder before `cwp-update-1.25.0/`.
3. Sign in with the current owner account, confirm the recoverable backup, inspect the proposed update and apply it. The update checks recognized files and stops on incompatible customizations instead of silently replacing them.
4. If interrupted, keep the staging directory and private recovery journal. Reopen the same owner update page and use its recovery or continuation controls. Do not delete configuration, keys, locks, storage or history to restart installation.
5. Wait for the update page to report completion. The file update and optional database preparation are separate steps.

The update retains recovery data in private storage under `structure-1.25.0/`. The shared `structure-1.11.0/mutex.php` lock remains in place so older and newer update tools cannot run concurrently. Do not remove either as a workaround for a failed update.

## Prepare the features you use

Sign in as the owner, leave any client support session, and use the application's explicit preparation forms with your current owner password. Loading a page does not prepare its database tables.

| Area | Owner action and boundary |
|---|---|
| 20i settings and inventory | Open 20i settings. If shown, use **Prepare inventory storage**. Keep the original saved credentials and encryption keys. Review the connection and current package/domain/client assignments before sharing observations. |
| Provider operation history | Open **20i requests** and use **Prepare operation records** if offered. Retain previous approvals, attempts and unknown outcomes. Preparation does not send a provider request. |
| Optional 20i features | Use the relevant preparation forms for domain search, registrar inventory, observations, mailbox history, scheduling, cloud confirmation/assignment and StackCP observations as needed. Follow the visible prerequisites. Preparation does not grant remote authority or enable automatic writes. |
| Commercial hosting and renewals | Prepare the relevant agreements, billing, order and fulfillment history through their owner screens. Review mappings, current client consent, invoice purpose and payment evidence before approving any operation. Existing invoices are not automatically repurposed. |
| Website licensing | Open website licenses and use **Prepare licensing storage** when offered. This preserves recognized older licenses, domains and bundles and records the existing-invoice boundary. An established missing table or incompatible shape requires recovery rather than empty replacement. |

The actual published 1.24.0 package predates recurring-billing and licensing storage. On a stock 1.24 installation, these records cannot be claimed as preexisting. If a later accepted development version already added them, preserve them and follow its recorded migration history. Release acceptance separately exercises populated older licensing storage and post-upgrade recurring/licensing records.

For an older paid website invoice without an exact license link, use **Review an older website invoice** only after checking the original purchase records. Select the exact existing ordinary license or deliberately choose to create one, record the reason and confirm with the owner's password. The application does not guess from a matching client/product. Linking preserves active or revoked status and does not generate a download, send mail, reactivate a license or change payment records. Internal master licenses stay outside client portal and bundle delivery paths.

## Verify before reopening normal activity

- Confirm owner and permitted staff login, then check a real client's login and a separate client's isolation.
- Open existing projects, messages, private files and ZIP deliveries; verify downloads and their expected contents.
- Review invoices, receipts, support history, mail accounts, drafts and submission history. Check that accepted or uncertain sends still cannot be blindly replayed.
- Confirm the original 20i profile, observations, assignments and operation histories remain. Check that saved observations are labeled accurately and private provider diagnostics stay out of client pages.
- Verify the included features you configured, the public homepage and meaningful owner/client screens on desktop and mobile. Check for PHP or migration errors.
- Confirm version 1.25.0 in the application. Keep provider writes and scheduled activity disabled until their own configuration and review requirements are satisfied.

After successful verification, remove only the exact public `cwp-update-1.25.0/` staging directory with the hosting file manager. Keep the private recovery copies with the backup set. If rollback is needed after database or application changes, restore a consistent backup set; restoring only one part can leave records and encrypted files incompatible.

This increment does not establish complete domain purchase/transfer, DNS/database/mail administration, VPS lifecycle, provider-issued StackCP login or interactive SSH. Automated acceptance uses isolated fixtures; it does not verify your live 20i entitlement or authorize production operations.
