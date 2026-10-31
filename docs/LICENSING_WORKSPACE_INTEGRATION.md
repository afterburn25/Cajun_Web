# Licensing workspace integration — development, not a release

User reported software licensing appears absent. Inspection of b2825c7/abcf9b7 found two concrete integration omissions: studio-licenses had no administrator navigation entry, and the client router never dispatched to the existing software or financing renderers. The underlying grant, lifecycle, private asset, product-policy and payment modules already exist.

This increment adds Software & Licensing > Licenses & software plus an owner-permission-filtered master-portal shortcut. The existing owner page gains read-only readiness and task navigation, and software setup links to the catalog prerequisite instead of presenting a guaranteed failing action. Existing setup, issuance, asset, lifecycle, financial and security checks are retained. Licenses are not created merely by visiting the page.

Client software=1 and finance=1/agreement-id routes now dispatch AFTER real client authentication and BEFORE project fallback. Existing renderer ownership and support restrictions remain. Malformed/mixed selectors fail closed and sensitive licensing responses use private no-store headers. Login/new-password fields and actual provider credentials are not removed; no repeat-current-admin-password controls are added.

The exact eight old-file changes are input/output git-blob-bound in tools/integration/licensing_workspace_apply.py, locally replayed and compared. Assembly records ordinary expanded source and regenerated installer inventory on the separate validation branch, not main or the in-progress PR315 integration branch.

Local PHP syntax and24 navigation/readiness/routing contract assertions passed. Added real disposable MariaDB/MySQL and installed Chromium tests exercise setup through real forms, software catalog creation, four license types, validation by product/domain/expiry, suspension/resumption, private release publication/download, unrelated-account denial, current-session refusal and mobile rendering. These tests are pending until actual exact-source run reports pass; no successful live import, live checkout, production deployment or approved UPDATE is claimed.

The existing finance native run41140107025 had106 passing assertions but failed its strict stderr gate on two intentionally injected missing-infrastructure diagnostics. Test correction captures ONLY those two explicitly scoped callbacks, requires exactly the expected diagnostic from each, and retains those facts in evidence. Unexpected stderr still fails. Original state/financial/audit assertions remain unchanged; the corrected suite must rerun.

Live card processing remains separately gated. No real charge, provider call, customer message, record change or production enablement is authorized by these tests. Full release integration, historical regression, financial upgrade, independent review and standalone package verification still apply.
