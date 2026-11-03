# Software download source and lifecycle acceptance corrections

28 October 2026. Focused correction prepared from integration source `0495e2e78a5a66b2c3c98aefbdb196444e01d12e` and incorporated into the designated integration lane after review. PR #266 and main remain unmodified by this correction.

## Failure and correction

Feature source `4b17cd33c5b077f88ec55e35986315fe9e6f68fd` fails installed software run [40670153980](https://github.com/afterburn25/Cajun_Web/actions/runs/40670153980) at `My Software shows one verified release link with exact purchase unit source`. The preceding notice retry/reset assertions now pass. The software portal and this assertion are identical on the integration source above.

The browser fixture creates an active trial before a second trial is upgraded in place through synthetic paid fulfillment. Both licenses authorize the same release. The portal orders equivalent release candidates by license ID, then displays only the first authorized license for each asset. The earlier unpurchased trial therefore hides the paid invoice/unit source.

The correction orders purchase-linked entitlements before owner-issued entitlements within each release, retaining the existing deterministic license-ID tie break. Every candidate still passes the same entitlement authorization before deduplication. Invalid purchase units remain denied, and an eligible owner-issued trial remains a fallback. No license, payment, download-authorization, or audit rule changes. The existing installed browser assertion remains unchanged.

## Independent verification

The exact original renderer reproduces the problem on PHP 8.2/8.4 with MariaDB 10.11/MySQL 8.4: nine reproduction/control checks per combination (36 total). The corrected renderer passes 13 checks per combination (52 total):

- Real customer password login, session and CSRF through the existing portal router.
- Exactly one download link bound to the purchased invoice/unit despite the earlier valid trial.
- Actual download endpoint returns the exact synthetic ZIP hash and records its selected asset/license/invoice/unit in the audit.
- Another authenticated customer cannot see or download the release.
- An invalid purchase unit is refused and the valid trial is selected instead.
- An expired trial plus an invalid purchase exposes no link; restoring valid purchase rights restores its source link.
- External mail and payments remain disabled; PHP emits no runtime diagnostics.

The fixture uses isolated local synthetic SQL records and ledger entries; no payment gateway is contacted. The server is PHP's local HTTP server, not Apache or a browser. Initial corrected-copy runs failed a fixture ZIP hash check because independently recreating the ZIP changed its timestamp. The original failures are retained; rerunning with the exact same ZIP bytes passes without modifying production checks.

Both PHP versions lint the changed renderer. The canonical build refresh regenerates its installer inventory. Exact combined hosted software/browser acceptance, full installed runtime coverage, sandbox transactions, populated published-version upgrade, independent review and main/release acceptance remain required. Owner confirmation that sandbox credentials are currently unavailable does not certify gateway behavior.

## Exercise the explicit software lifecycle forms

The later installed software acceptance tail still selects `studio_license_deactivate` and `studio_license_reactivate` forms for the paid software license. Those legacy website controls are deliberately absent for software entitlements. Its actual owner register exposes `software_license_lifecycle` with `suspend` and `resume` transitions and mandatory reasons. The legacy handlers also reject a forged software-license request.

The corrected test selects the exact license and transition in the real form, supplies the required reason, and retains the existing suspended/active portal, download denial/recovery, SQL state and original-key assertions. It adds an actual owner POST asserting that the legacy action remains refused. Shipping lifecycle behavior is unchanged.

Independent execution extracts the exact original and corrected tail with Python AST and uses the canonical BeautifulSoup, CSRF and form-data helpers against real owner/customer HTTP sessions and SQL. All four PHP/database combinations pass 12 checks each (48 total), including reproduction of the original `StopIteration` without mutation, the corrected tail, exact suspension/resumption reason audits, preserved key/domain/entitlement history and the restored ZIP checksum. This is a focused tail execution against synthetic local fixtures; it does not claim the preceding full browser suite or Chromium/Apache execution passed. The executed shipping source is `220cab22ca299f114e486983a098554072d271ce`; the follow-up changes tests and this review note only.
