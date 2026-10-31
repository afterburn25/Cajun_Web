# Backup runtime integration with current owner sessions

Development candidate, not a replacement release or live operation. The owner requested all remaining accepted work be integrated. This group preserves the original PR176/196/197 history for file backups, isolated restore drills and private database exports. It is reconciled against main1f3e6dc7, after the two prerequisite groups. The delivered1.26.1 archives are unchanged.

## Intentional integration corrections

Backup setup, restore and pruning now re-read an active enabled owner through the canonical administrator-session and permission services instead of requesting the current password again. Expired/revoked/anonymous/nonowner/support contexts remain refused; the account mutex and transactional checks remain. The actual admin login and customer password changes are not changed. The older main does not define the later admin_session_matches helper, so this group uses its existing admin_user session revalidation rather than importing a second authentication implementation.

Setup and pruning now enforce their displayed confirmation checkboxes on the server. Creation and restoration retain their previous explicit confirmations and restoration retains its exact typed phrase. Private backup UI helpers are self-contained. Wording makes clear that configuration/key files are excluded but the database export contains sensitive account/business data and must be protected; original private keys need separate retention.

All160 original native commands remain byte-for-byte and ordered, with one additional backup-native command. The browser addition is an exact two-line hook; its inverse is checked before the older template/source contracts, retaining every historical check. The existing HTTP backup suite replaces only obsolete second-password assertions with no-password-field and server-confirmation assertions. A new focused installed fixture runs that suite plus revoked/support contexts, private download denials and actual desktop/mobile Chromium rendering. SQL lifecycle tests also cover expired/revoked/anonymous/support authority, audit rollback, preserved records/keys, malformed/corrupt archives, source-only restore and database export metadata.

## Evidence boundaries

Local canonical refresh,37 pure backup-policy cases and284 acceptance-harness assertions pass. Archive tests require ZipArchive and SQL/browser tests require disposable Linux fixtures; those are not claimed as local passes. Hosted PHP/database and browser acceptance must complete before this candidate is considered for main. Original failed local invocations and the template predecessor mismatch are retained outside the repository as diagnostic logs; the corrected historical inverse retains the predecessor rather than weakening its hash.

A file restore does not reimport the SQL export or restore private keys/uploads. No automatic provider, billing, mail or production deployment is introduced. This is owner-authorized ChatGPT self-review, not an independent audit.
