# Hosting full acceptance integration

Development branch: `codex/twentyi-hosting-full-acceptance`, starting source `10142c7e97c8ba9986e0c18d9ab0190ccc432727`. This change adds tests and CI integration. It is not a release or live deployment. The original portal-handoff, hosting-commerce and direct-catalog release branches remain intact.

## Existing workflow integration

The existing native/browser job keeps every prior command and adds the joined purchase guards and runner after the hosting recovery checks. All 162 native commands retain their intended order. The existing browser driver runs before the hosting offer browser and the separate owner re-link/client-isolation browser. The existing runtime/service selection supplies PHP8.2/8.3/8.4 and the assigned loopback database port; full-suite mode does not assume the focused job's fixed port.

Only the joined preparation PHP child receives the existing two-day libfaketime offset. The parent, database, due-invoice, portal and HTTP/browser processes keep real time. External payment evidence and provider transport remain synthetic. This proves eventual recurring purchase only when actual execution passes; it does not implement immediate checkout or live settlement. [Purchase and handoff scope](TWENTYI_HOSTING_CLIENT_ACCESS.md#joined-purchase-and-browser-continuation).

Existing hosting-operation, offer and joined-purchase database prefixes remain separate. Native and browser reports use the established flat QA partition. Private configuration, credentials, sessions and native child output remain excluded. The generic artifact transfer helper and private-file policy are unchanged.

## Published1.25 upgrade continuation

The baseline remains the actual published1.25 COMPLETE archive: source `b79c67110829126916e3a65e616191542448bccc`, SHA256 `bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39`, 2,895 members. Existing original assertions, retention files and sixteen evidence members remain required.

Each existing root/subfolder and rewrite/no-rewrite Apache site continues with:

- Read-only Hosting visits before setup; no hidden schema activation.
- Explicit owner setup, invalid confirmation/CSRF/password and delegated/support rejection, then idempotent retry.
- Actual original-file recovery and reapplication while hosting history is empty.
- Refusal of the original setup-priced product, retaining its terms, import provenance and image.
- Real owner pricing/image/publication forms on the separately imported type901 draft, followed by client request, owner conversion and client consent with replay and other-client isolation checks.
- Recovery refusal once published or withdrawn history exists, with exact database, installed files, journal and backup comparisons.

The new agreement stays pending activation. No invoice, payment, provider operation or automatic activation is added. All original business rows, keys, provenance and images remain checked. Normal authentication bookkeeping stays subject to the existing snapshot exclusions. Six historical plus four supplemental updater submissions use the original ten-attempt rate budget without resetting it.

A future upgrade from a genuinely published1.26 archive is still a separate gate. This supplement does not invent that baseline or substitute a candidate archive for a published release.

## Evidence and actual verification

The full suite requires181 browser reports and232 final reports. Planned captures total368:344 browser captures,16 historical upgrade captures and8 new hosting-upgrade captures. The supplement adds two named reports and four hash-only retention diagnostics; diagnostics and progress cannot become passing reports. These inventories describe required outputs, not observed results.

Local checks actually run:385 artifact-helper outcomes, preserving all342 preceding names/outcomes in order;43 new upgrade source/output guards;28 Python AST and normal/minimal PHP syntax checks; exact source inverses, contract/report preservation, clean patch application and independent source review. The earlier joined16-method and handoff89-outcome portable checks remain from unchanged reviewed source. The existing comparator/form guards also pass42 outcomes, and the actual immutable published1.25 input checks pass19 outcomes against the unchanged transfer helper. No application database, Apache, Chromium or screenshot acceptance is inferred.

Two pre-freeze fixture defects were corrected: the candidate version had shared a variable with the installer URL, and the setup-fee refusal test had matched unconditional help text. The original drafts are retained locally. The corrected assertion requires the actual error alert and no enabled publish form.

Original focused run [39100808137](https://github.com/afterburn25/Cajun_Web/actions/runs/39100808137) at28351f71 passed all five jobs:18 reports,2,043 named outcomes and ten reviewed captures. This does not accept later source. The original512 [recovery run](https://github.com/afterburn25/Cajun_Web/actions/runs/39104617939) and10142 [joined run](https://github.com/afterburn25/Cajun_Web/actions/runs/39108671173) were still queued at08:22UTC; neither was cancelled, replaced or repeated. New full-suite execution is pending those outcomes and final source integration.

## Remaining product and release work

Finish actual focused and complete supported-runtime acceptance, review every required capture, resolve the separate release candidate/main integration and verify final packages and downloads. Published1.25 remains immutable. Complete cloud product imports, automatic provider image import, immediate first-payment checkout, remote access and other documented roadmap gaps are not claimed complete.

The owner-supplied [full API reference](reference/20i-owner-api-34e713-redacted.txt) is already retained. The public documentation points to the authenticated My20i console; no authenticated documentation session is available here. Public HostShop image-upload behavior does not establish a reseller API image field. Do not ask the owner to resend the reference or guess those fields.

## Recovery startup correction and original outcomes

The original [512 recovery run 39104617939](https://github.com/afterburn25/Cajun_Web/actions/runs/39104617939), attempt 1, is now terminal failed. Its packaged UI job 117607690830 failed during the native-history HTTP continuation. The actual absent phase passed 66 checks including rollback and restoration. The next empty phase retained an OSError after preparation/state and before a successful server-log open; cleanup passed. Its original report contains neither errno nor an exact startup stage. Socket preflight and log-open errors remain possible; TIME_WAIT is not a confirmed diagnosis.

The four original native lanes passed PHP 8.2.34/8.4.26 with MariaDB 10.11.19/MySQL 8.4.11. Independent archive digest/size/CRC and exact named-report review verifies 28 reports and 3,168 outcomes: 1,080 native and 2,088 pure/modeled executions. These are historical 512 results, not acceptance of later checkout source. Separate recovery-file archives were not independently validated, so no additional filesystem-byte proof is claimed. External receipt `work/hosting-512-native-outcome-review/review.json` has SHA256 `4d4b19a31fbfb286bc8376d2c81af5c2025847039ea2a68e5fb3c3453a621308`.

The correction on parent c38 sets SO_REUSEADDR before the existing fixed loopback bind, matching the PHP CLI server's reuse behavior. It adds no alternate port, retry or process termination. Only two fixed startup stage labels and an integer errno from 1 through 4095 (otherwise null) may enter failure diagnostics; exception text and paths remain excluded. All original application calls/checks and finally cleanup are exact. [Python documents closed-connection reuse](https://docs.python.org/3.14/library/socket.html); [PHP 8.3.6 sets the same option](https://github.com/php/php-src/blob/php-8.3.6/sapi/cli/php_cli_server.c#L1240-L1247). These support the correction, not a retrospective diagnosis.

`tests/hosting_catalog_recovery_socket_test.py` executes the exact preflight/diagnostic blocks in isolation. Six portable methods pass on Windows Python 3.14. Two Linux methods use newly owned ephemeral loopback sockets to reproduce TIME_WAIT and refuse active listeners with and without their own reuse option. They have not run locally; `--require-linux` deliberately refuses this platform. The focused workflow runs `python -B tests/hosting_catalog_recovery_socket_test.py --require-linux -v` before packaging. Original fixed port 8092 and existing local servers are not touched by the isolated guard.

All 466 artifact-helper outcomes pass after updating exact source pins and appending one reversible focused-workflow insertion. The full application workflow, 171-command recipe, 192/243 report sets, transfer helper, browser driver and shipping files remain unchanged. Independent source review verifies 38 correction predicates; proposal and review receipts are retained under `work/hosting-recovery-512-original-failure-correction/` and `work/hosting-recovery-512-correction-vps-review/`. Corrected Linux/application/SQL/HTTP/capture acceptance is pending. Preserve the original failed and queued runs; inspect the first changed-source run without duplicating it.
