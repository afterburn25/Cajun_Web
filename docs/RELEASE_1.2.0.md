# Release 1.2.0 — administrator-account acceptance

Published [v1.2.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.2.0) at `f85627e05b171e4ee8508231e2ed6ee7b9374274` through PR #10. [Exact-main evidence](https://github.com/afterburn25/Cajun_Web/actions/runs/38362411560); [test report](TEST_REPORT_1.2.0.md); [publication verification](RELEASE_VERIFICATION_1.2.0.json).

| Roadmap area | Delivered behavior | Evidence |
|---|---|---|
| A-05 core accounts | Owner creates/edits/deactivates staff, chooses explicit website/client grants and issues private setup/recovery links | Native adapter and HTTP/Chromium creation, assignment, activation and recovery |
| A-05 enforcement | Current-password/CSRF enforcement, denied escalation, role-specific navigation/pages/actions, revoked sessions/setup/reset links | Direct and forged requests, client/admin separation, disabled and removed-grant sessions |
| A-05 owner preservation | At least one enabled owner with completed password setup; pending owner cannot substitute | Native single/concurrent deactivation/demotion; HTTP last-owner rejection |
| A-05 migration | Existing users/passwords remain; explicit initial owner and other legacy grants; two additive tables | Actual published 1.1.0 package upgrade, preserved data/private bytes, interrupted/incompatible schema checks |
| F-04 / A-04 packaging | Complete/update packages, bundled canonical foundations, embedded core/hash verification, fresh/upgrade paths | Exact-commit CI and byte-identical local rebuild; downloaded-back assets and exact tag |
| Existing 1.1.0 flows | Public website/requests, admin content/pricing/pipeline, client projects/files/decisions/manual invoices | Original native/browser regressions retained |

Core A-05 is verified/released, not deployed. Mailbox grants and MFA remain deferred; A-06/A-07/A-08, online payments, 20i, A-09/A-10 support sessions/SSH, P-06 client website ZIP delivery and full updater/backup capabilities are not completed by this release. Existing clients are not administrator identities, and staff client-workspace permission is agency-wide. The 1.1.0 [feature matrix](RELEASE_1.1.0.md) remains the record for earlier delivered scope.
