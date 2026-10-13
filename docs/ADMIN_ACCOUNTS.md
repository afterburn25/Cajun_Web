# Administrator accounts — 1.2.0

Application integration for the core A-05 account lifecycle in [PR #10](https://github.com/afterburn25/Cajun_Web/pull/10). Publication status and exact evidence belong in the [release register](RELEASES.md). This does not connect multi-account webmail, mailbox assignments, payment providers, 20i, support impersonation or SSH.

## Owner workflow

Open **Administrators**. A fresh installation already has an owner. On an existing installation, back up the database, application, configuration and private storage, then select the initial owner and confirm with your current administrator password. Existing identities, emails, password hashes and authorization versions are retained. Other existing administrators receive website and client-workspace access; owner-only settings become restricted after this explicit migration.

Create a name/email, choose Owner or Administrator, and select the required access. A new account has no password and cannot sign in. Create a private setup link on that account's card and share it using your chosen private channel. The link is shown once, expires after 24 hours and works for one completed password setup. The interface does not send email or claim delivery. The recipient chooses a passphrase and then signs in separately. Without JavaScript, the recipient can paste the 64-character code after `#token=` into the setup form.

Creating or replacing a setup link is also owner-assisted recovery: it immediately retires the target's previous password, active sessions and older setup/reset links. The target cannot sign in again until completing the new setup. You cannot issue this recovery link for your own account. Your own password changes use **Your account**; the existing email-reset workflow still requires configured application email delivery. Do not disable email delivery and lose the sole owner's password without a host backup/recovery plan.

Edit an account to change its name, login email, role, selected access or enabled state. Saving revokes its existing sessions and pending setup/reset links. Changing your own record in Administrators signs you out; **Your account** preserves the current session while revoking other sessions. Accounts are deactivated instead of deleted, preserving audit identities. A pending owner whose password setup is unfinished cannot substitute for the last activated owner.

## Access matrix

| Role/access | Available functions |
|---|---|
| Owner | All current administration, including staff management, email delivery configuration, maintenance and database upgrades |
| Administrator + Website | Website/portfolio/services/testimonials/settings; inquiries, pipeline, estimator pricing, sales content and revisions |
| Administrator + Client workspace | All agency client projects, client invitations, messages, review versions, private documents and manual invoices |
| Administrator with both | Both sets above; owner-only settings remain unavailable |
| Administrator with no selections | Own account and sign-out only |
| Client portal identity | Its authorized customer workspace; no administrator access |

These are agency-wide capabilities, not per-client staff assignments. Administrators have no implicit mailbox or financial/provider configuration capability. Owners have known global capabilities, but future mailbox read/send still requires an explicit mailbox grant. Mailbox controls do not exist in this release. Navigation, direct page requests, private project downloads and form actions enforce the same current assignment. Unrecognized actions/permissions do not confer access.

## Implementation and migration contract

- Existing `users` remains the only administrator authentication identity. No competing staff password table or integer authorization version is introduced. Client identities remain in `studio_clients`.
- `staff_profiles` adds `user_id`, `role`, `enabled` and an explicit JSON list of supported capability names. `staff_invitations` uses the canonical store's hashed, version-bound token schema. The successful migration records `staff_schema_version=1`.
- Fresh setup creates both tables and initializes the owner in its installation transaction. Existing installations initialize only through the password-confirmed owner-selection action; browsing does not silently create tables or promote accounts. Before migration, existing administrator access remains compatible.
- Migration validates existing column/type/nullability, required unique keys, InnoDB and invitation collation. It can finish interrupted creation of empty compatible tables; it rejects incompatible tables or unknown existing staff data without adopting or replacing it. DDL and data initialization are separated because MySQL DDL commits implicitly. Initialization uses a transaction.
- `app/staff.php` is the lifecycle/authorization adapter; `app/staff-admin.php` supplies forms. `admin.php` checks action permissions independently of the requested view. `core.php` reloads the existing authorization version and enabled profile on each request. Malformed profiles fail closed after migration. New/recovery accounts with empty password hashes are explicitly denied login.
- All account, invitation, reset and activation mutations share an installation-scoped MariaDB/MySQL advisory lock and transactional updates. Fresh actor authorization and current-password confirmation happen inside that lock. This serializes concurrent owner changes and protects the last enabled owner with a completed password. Setup redemption also uses the invitation store's row lock and expiry checks. No external email or provider work runs inside these transactions.
- Password/account/access changes rotate the existing opaque `auth_version`. Revocation applies to the next authenticated request; it does not undo a request already authorized and in flight. Reset and setup records are revoked together. Audits record actor/target/action without passwords or raw tokens.
- Private setup tokens use a URL fragment, so browsers do not send the token in the initial URL request or referrer. Local JavaScript copies it into a password field and removes the fragment. Activation requires a CSRF-protected POST, password validation and request limits. Setup responses use no-store and no-referrer. Do not add analytics or third-party resources to these pages. Historical email-reset links retain their existing query-string format; no broader logging claim is made for those links.
- `foundation/access` and `foundation/invitations` remain canonical. The builder copies them verbatim into `site/app/foundation` during `--refresh` and rejects divergent copies during verification. The same builder verifies the installer's embedded core against `app/core.php`, regenerates dependency hashes and packages only the flat shipping tree. Developer tooling is not a customer hosting requirement.

## Acceptance and limits

`tests/staff_native.php` uses disposable MariaDB tables for interrupted/incompatible migration, credential preservation, duplicate-email rollback, expiry/replacement/single-use setup, assigned access, denied escalation, reset, session revocation, pending-owner protection and simultaneous owner deactivations. `tests/staff_worker.php` participates in the two-process test. The independent invitation store also tests simultaneous redemption and transactional callback failures.

`tests/staff_cases.py` exercises actual PHP HTTP requests and Chromium: the checksum-pinned published 1.1.0 package with populated original data, current UPDATE ZIP, explicit migration, fresh-install role initialization, CSRF/current-password enforcement, owner creation, JS and no-JS activation, permitted mutations, direct/forged request denials, mobile layouts and real session invalidation/recovery. Original 1.0.8/fresh-install and website/client acceptance remain in the application suite. Captures use synthetic accounts only.

Full application acceptance is PHP 8.3 / MariaDB 10.11 on isolated GitHub Linux runners and loopback HTTP. Independent component checks run PHP 8.2/8.4 with MariaDB 11.4 where applicable. Shared-host HTTPS/SMTP and live deployment remain unverified. MFA, finer-grained or per-client staff roles, mailbox grants/setup, webmail/templates/bells and provider operations remain future work. A code-only downgrade after account migration is unsupported because old versions do not enforce these roles; recover a complete validated host backup instead.
