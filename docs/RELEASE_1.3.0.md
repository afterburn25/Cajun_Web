# 1.3.0 release scope and acceptance

| Roadmap | Delivered behavior | Evidence |
|---|---|---|
| A-09 / current project portal | One-click permitted-client support, separate real actor/client, persistent banner/return, fixed/idle expiry | Native lifecycle and real Chromium entry/navigation/return |
| A-09 access boundaries | Explicit client policy and support view/write/download; scoped URLs/forms, stale-tab isolation, blocked client decisions/security/payments | Real HTTP forged/cross-client/copied-context tests and revocation |
| A-09 attribution and audit | Staff messages/uploads/downloads; actor/client/session/object/outcome log without bodies/credentials | PDO/HTTP audit failure rollback, file cleanup and no-byte streaming |
| A-04 / incremental update | Additive three-table support setup; owner confirmation, customer restrictions default denied | Actual published 1.2.0 package overlay preserving credentials, grants, invitations, records and private bytes |
| F-04 packaging | 52 complete / 51 update files; exact installer and canonical component inventory | Exact-main CI, byte-identical rebuild and fresh release download/tag checks |
| Earlier website/account/customer behavior | Existing public site, requests, projects, documents, manual invoices and staff lifecycle | Original native/HTTP/browser suite and 1.0.8/1.1.0 upgrade regressions |

See [test report](TEST_REPORT_1.3.0.md), [release register](RELEASES.md), [support guide](CLIENT_SUPPORT.md). A-09 is released for the currently implemented portal. Future provider controls need their own support permissions/audit integration; provider SSO and A-10 SSH remain incomplete. No live deployment occurred. Staff support grants cover allowed clients agency-wide; finer per-staff client assignments are deferred. Online payments, multi-account mail, 20i, website ZIP handover and a full updater are not delivered by this version.
