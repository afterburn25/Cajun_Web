# Account and Notifications: explicit 20i fixture preparation

## Source and scope

This local correction starts from fetched branch `chatgpt/account-notifications-combined-20261101` at `fc14b84170407a0c30616fd278bab79a20657a24`. Its implementation changes only `tests/account_notification_browser.py`, adding 13 lines. All 42 original `check(...)` call ASTs remain unchanged and in order; removing the single inserted block reconstructs the complete original file byte-for-byte. Application, migration, workflow and shipping files are unchanged.

The reported hosted blocker was [run 41343257699](https://github.com/afterburn25/Cajun_Web/actions/runs/41343257699), where the installed browser did not reach the expected 20i hub. The original fetched browser source reproduces the same named assertion failure in the isolated local runtime described below.

## Observed cause and correction

`tickets_installed_seed.php` creates the physical provider tables through `schema_statements()`, but does not prepare provider settings or set `provider_settings_schema_version`. The actual controller therefore renders **Prepare your 20i connection**. The browser fixture previously clicked the 20i navigation link and immediately required `[data-provider-hub]`.

The correction uses the actual owner browser and the actual setup form. Three new checks verify:

1. The unprepared route exposes its CSRF-bound setup form under the authenticated owner session, with no repeated password field and no hub.
2. A forged CSRF POST preserves the absent preparation marker, provider rows and setup audit history.
3. The real browser submission creates the preparation marker and owner audit, opens the overview, and leaves provider profiles, credentials and connection-check histories empty.

Setup normally redirects to `view=provider-settings&section=overview`. The fixture confirms this result, then clicks the actual **20i workspace** navigation link again. The original strict assertion for the bare navigation URL, hub and named administrator destinations remains unchanged.

## Actual execution evidence

The local runs used an unprivileged worker (UID 65534), PHP **8.3.6**, MariaDB **10.11.14**, Playwright **1.56.0**, Chromium **141.0.7390.37**, and Python **3.12.14**. MariaDB used a separate newly initialized datadir and port **33308**. HTTP **8091** was explicitly reserved for these runs and released afterward.

Docker and PHP 8.4 were unavailable locally. The retained [local launcher adapter](evidence/account-notifications-2026-11-01/local-http-launcher.py.txt) accepts only this fixture's exact server `run`/`stop` command pair and translates it to the real local PHP server. It does not change the test source, database, responses, browser behavior or assertions. These results cover the stated local environment; the hosted PHP/database matrix remains a separate gate.

| Retained execution | Passed rows | Failed rows | Total report rows |
| --- | ---: | ---: | ---: |
| [Original fetched source](evidence/account-notifications-2026-11-01/original/browser-results.json) | 40 | 2 | 42 |
| [First correction, before following the setup redirect](evidence/account-notifications-2026-11-01/setup-redirect-attempt1/browser-results.json) | 43 | 2 | 45 |
| [Final correction](evidence/account-notifications-2026-11-01/corrected/browser-results.json) | 54 | 0 | 54 |

Each failed run contains the failing named assertion and its captured `AssertionError` as two report rows; they are one failure point. The intermediate run passed all three setup checks, then stopped at the original bare-URL assertion after the normal overview redirect. Its stderr also contains Playwright teardown cancellations, retained verbatim. The final run has **empty stderr**, all 54 checks pass, and the original PHP/application diagnostic gate passes.

The final run completes the original customer credential rotation, stale-session notification revocation, support isolation, current-owner navigation, mobile layout, rate limiting and password-audit checks. The added flow uses empty synthetic provider storage and performs no provider connection check.

Additional verification:

- `python3 tests/account_notification_contract.py` passes, preserving both exact predecessor shipping layers.
- Python AST parsing and `git diff --check` pass.
- Source reconstruction verifies the entire original browser file and every existing assertion.
- The [source and execution manifest](evidence/account-notifications-2026-11-01/source-and-execution.json) records source hashes, the launcher hash, environment, report counts and evidence-file hashes.

The database was reset only within the separate port-33308 instance between runs, after retaining each prior synthetic database snapshot. That instance is now stopped. All three fixture PHP process IDs were confirmed absent, and the [HTTP release receipt](evidence/account-notifications-2026-11-01/http-port-release.json) records the connection-refused and rebind checks before returning port 8091 to the primary acceptance runner.

## Captures

All four final captures were opened for local visual self-review. They show the explicit preparation page, the resulting 20i hub with its active navigation, the customer account form, and the mobile password-update result with cleared fields and footer.

- [Owner preparation](evidence/account-notifications-2026-11-01/corrected/Owner-20i-Preparation.png)
- [Owner 20i navigation](evidence/account-notifications-2026-11-01/corrected/Owner-20i-Navigation.png)
- [Customer account desktop](evidence/account-notifications-2026-11-01/corrected/Client-Account-Desktop.png)
- [Customer account mobile](evidence/account-notifications-2026-11-01/corrected/Client-Account-Mobile.png)

## Exact browser source identities and delivery state

| Source | SHA-256 |
| --- | --- |
| Original fetched browser | `2ec0b157e687ef38b1cd66426b6be8f88212299374e4a65cd63584029aefe344` |
| Intermediate correction | `3c5f4cb0fc74bfb8455f6eb604314267c125b0fd290825e07723bf0f09b137f5` |
| Final correction | `aad7737422c97a95b59354987b76843cfe755e472b9169aa742587e6357c7367` |

The reports identify the corrected source as `fc14b84170407a0c30616fd278bab79a20657a24+uncommitted-fixture`; the exact file hash above binds the tested bytes. Implementation, the stated local acceptance and root team review are complete. Root reviewed the final 13-line correction, source/evidence manifest and narrative, and inspected the actual 20i hub and customer-account mobile captures. This records team review, not independent human approval or hosted certification. The enclosing local commit identifies the reviewed correction; hosted acceptance, integration into main and release delivery remain pending. This record does not certify the separate PR317 full-application acceptance.
