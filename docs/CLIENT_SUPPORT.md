# Client portal support access

Candidate v1.3.0 implements the current website-project portal portion of A-09. Use the [release register](RELEASES.md) to distinguish candidates from published packages. VPS SSH, provider SSO, payments and email remain separate roadmap work.

## Enable and open a portal

Back up the installed website and database before merging the verified UPDATE package. Existing configuration, installed guards and private files stay in place. Sign in as an owner, enable **Administrators** if coming from 1.1.0, then open **Client support**. Confirm the backup and your support-access agreement/notice, and enter your current owner password. This adds three support tables without modifying customer passwords or staff roles. Fresh installation creates the tables but still requires this policy confirmation.

Every client starts restricted. The owner must allow each client covered by the service's support agreement and notice. The customer's ordinary portal displays whether staff support access is allowed. This configuration is an operational policy confirmation, not a legal agreement generator or proof of customer consent.

Click **Open client portal** on the client's record or Client support card. The persistent banner identifies the client and real staff member, shows the maximum expiry and provides **Return to admin**. Project links keep that same session. End it before opening another client. A session expires after 15 minutes total or five minutes without a request; expired links never become a different customer's portal.

## Permissions and limits

Owners can access permitted clients. Other administrators need **Open permitted client portals for support**. Separately grant **Send Studio messages and upload files during support** and/or **Download private files during support** when required. Website/client-management access does not implicitly grant these new permissions. A support-only member lands in Client support and cannot open the administrative project editor.

Messages retain the real staff ID and appear as Studio; uploads retain the staff author type and a session audit that identifies the actual actor. Client approvals, legal acceptance, payment/enrollment actions, client login/logout, invitation activation and other unrecognized mutations are blocked in a support context. Support permissions do not grant provider access or SSH.

Support retains a separate customer identity from the staff login. If an actual customer is also signed in in that browser, their ordinary portal and login keys remain unchanged. Each support context has its own form token; removing or changing its URL cannot turn a staff form into a customer operation. Context URLs alone cannot grant access in another browser. Do not treat URL IDs as authentication tokens.

## Audit and revocation

Client support shows the most recent 100 events. Owners see all records; other staff see only their own. Records retain actor, client, support session, action, object, outcome and UTC time. They exclude message bodies, passwords and the private browser-binding secret. Starts, ends, portal views, writes, uploads, downloads and relevant denials are recorded. Invalid unbound links are rejected without adopting a claimed actor. There is no automatic audit purge in this version.

Support writes fail closed if their audit insert fails: database changes roll back, new uploaded files are removed, and downloads are not streamed. Staff credential/permission changes, deactivation, customer credential changes/deactivation, or client policy restrictions invalidate access. Policy restriction ends active client sessions immediately; other changes and idle expiry are recorded on the next request. Administrator logout ends that browser's sessions.

The tables are additive InnoDB structures, validated before adoption. Incompatible columns, identifier collations, primary keys or unknown unmarked records require review; setup never drops/replaces them. Failed or interrupted merges should be recovered with the complete validated host backup. Do not use a code-only downgrade as recovery.

## Verification scope

`tests/support_native.php` exercises real PDO/InnoDB authorization, policy, identity separation, revocation and transaction rollback. `tests/support_cases.py` covers the checksum-pinned published 1.2.0 update, HTTP isolation and failure injection, and actual Chromium navigation/responsive screenshots. Existing 1.0.8/1.1.0 upgrades, staff lifecycle and ordinary customer flows remain in the application suite. Results are recorded in the version's release report. These isolated tests do not certify the owner's live hosting environment.
