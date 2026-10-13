# 1.11.0 directory correction — acceptance record

Candidate `9e8dedff3faad111ecfb6fd19a168b468f9d43d0` passes [run 38420905892](https://github.com/afterburn25/Cajun_Web/actions/runs/38420905892). This record precedes final merge/publication; the published TEST_REPORT.md must name the exact tested main commit and downloaded package verification.

| Suite | Passing named checks |
| --- | ---: |
| Native MariaDB authorization and business workflows | 618 |
| Actual HTTP and Chromium | 782 |
| Filesystem, package layout, migration interruption and recovery | 41 |
| Apache mod_php, rewrite on/off, root/subfolder | 203 |
| Negative package-builder checks | 16 |
| Total application/structure checks | 1660 |

All existing native suites remain: original application (65), staff (31), support (35), catalog (49), payment preparation (62), attempts (69), signed notifications (62), ledger (69), delivery archives (55), delivery (57), activity (64). Independent access, invitation, portfolio and estimator suites also pass on PHP 8.2/8.4. Full application tests use PHP 8.3/MariaDB 10.11 and Chromium.

Actual immutable COMPLETE packages 1.0.8 and 1.1.0–1.10.0 are checksum-verified and upgraded through the authenticated staged owner page. Existing configuration, private files, credentials, guards, invoices, messages/reviews, delivery ZIPs and activity retain existing acceptance coverage. Wrong owner password/CSRF, legacy authenticated POST, exact-body signed legacy callback and duplicate/tampered signatures are exercised. No live gateway/provider calls occur.

Unprivileged filesystem tests interrupt after journaled operations, resume, recover exact original files, retry after recovery, preserve unknown/custom files, reject customized privileged entries, reject incomplete payload, retain the mutex inode, reject overlapping runs and handle denied writes. Apache blocks the old application during maintenance while owner recovery stays reachable; it actually caches the old entry with timestamp validation disabled and then verifies canonical behavior after activation.

Actual COMPLETE one-page setup runs under Apache with and without rewriting, at root and in a subfolder. Missing setup support is useful rather than fatal. Setup verifies create/read/write/rename/delete, selects usable external storage despite a readable root-owned unused scaffold, removes every setup-only PHP file, and leaves internal denials. Exact assets, session scope, no directory listings, non-executable uploads and private-file denial pass.

Builder checks enforce the 6-file fresh root allowlist, meaningful organized directories, 218 production files, 205 staged UPDATE files, sanitized full project, exact image bytes, canonical foundation/bootstrap, generated dependency hashes, no stale mappings and no unsafe/duplicate/symlink/runtime/secret members. Windows UTF-8 build work from Devin PR #42 is integrated. A clean local Windows build passes; exact-main release preparation must compare it byte-for-byte to Linux CI.

Desktop/mobile homepage screenshots from the reorganized candidate are pixel-identical to published 1.10.0. Admin/client workspace dimensions remain identical, with content/version differences. Final exact-main screenshots must be inspected and retained with the release evidence before publication.

No live host or LiteSpeed instance was inspected or deployed. Apache-compatible rules do not certify another server or account. Controlled interruptions after durable journal writes do not claim arbitrary hardware/power-loss certification. Backup confirmation and bounded code recovery do not replace a full matched website/database/private-storage backup. Provider/payment capabilities remain at their prior disabled/sandbox gates.
