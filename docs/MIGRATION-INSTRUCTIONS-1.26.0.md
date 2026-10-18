# Updating to CajunWebPro 1.26.0

These are candidate instructions. Version 1.26.0 is not published; use them only with its final verified release assets once available. [Candidate scope and acceptance](RELEASE_SCOPE_1.26.0.md).

For an existing site, use the UPDATE archive. Keep a recoverable backup of website files, database and configured private storage together, including the original encryption keys. Extract the update into the existing website directory. It creates `cwp-update-1.26.0/`; do not copy its internal `site/` payload over the live installation or run the fresh installer.

Open `cwp-update-1.26.0/owner-update.php` over HTTPS. Sign in as the owner, confirm your backup, inspect the proposed update and apply it. Keep the staging directory available if the update needs to resume or recover. Customized conflicting files require review; the update preserves configuration, database records, private uploads and existing keys. Recovery material stays in private storage under `structure-1.26.0/`.

After the file update, check the public site, admin area, client portal and private downloads. Existing imported products, local prices and images must still be present. Remove the exact update staging directory through your hosting panel only after verification, and retain the backup and recovery material.

To use direct hosting imports, open the service catalog as the owner and choose **20i hosting catalog**. The existing provider connection, inventory storage and catalog import storage must be configured. Enable the new catalog observation storage with your current password, then explicitly refresh the hosting catalog. The file update does not call 20i automatically. Review each hosting type before creating a draft; re-importing preserves the original product and its local edits.

If established storage or its history is missing, restore the matching database/private-storage backup rather than reinstalling or creating replacement keys. This release does not add cloud catalog imports, automatic provider images, provider pricing or hosting publication. [Detailed import behavior](TWENTYI_HOSTING_CATALOG.md).

For a completely new site, use COMPLETE-UPLOAD and its one-page installer. FULL-PROJECT is development source and must not be extracted wholesale into the public website directory.
