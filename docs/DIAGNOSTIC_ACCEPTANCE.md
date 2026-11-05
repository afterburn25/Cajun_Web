# Diagnostic acceptance — multi-failure collection

`tests/diagnostic_acceptance.py` is an explicit **diagnostic** runner for the
installed HTTP/browser suite. It exists because the strict runner,
`tests/browser_acceptance.py`, stops at the first failed assertion: one long
cycle revealed only one problem. The diagnostic runner applies a per-unit
exception boundary so unrelated modules still execute and report.

**This is not release acceptance.** `tests/browser_acceptance.py` is
contract-pinned in `tools/acceptance-contract.json`
(`unchanged_test_sha256`, `release_acceptance_corrections`) and remains the
authoritative release gate, byte-identical. A green diagnostic run is never
release evidence; a red one is never reportable as success.

## What it runs

- **5 bootstrap stages** ported verbatim from the strict runner:
  `public` routes/guards/estimator, `admin-flow` HTTP flows/uploads/invoices,
  `installer` (native fresh install), `release` (upgrade + fixture seeding via
  `release_cases.run_http`), `browser` (Chromium session, captures, motion).
- **58 feature modules** — every `*_cases.run_browser` call in the strict
  runner's actual non-focused branch, in the same order. The registry parses
  that branch with Python's AST and attaches finite metadata; a missing,
  duplicated, stale, or newly unclassified module fails the contract test.
- A final `server-log` unit asserting the PHP server log has no warnings.

Each module keeps its own assertions unchanged. A failure aborts only that
module; later modules still run because they self-seed dedicated fixtures.
Every result records the units that failed before it (`after_failures`) so a
suspect downstream failure can be discounted instead of trusted blindly.

## Status vocabulary

`passed`, `failed`, `blocked` (a declared prerequisite did not pass — the unit
never ran against possibly contaminated state), `skipped` (deselected by the
run filter), `absent` (the module file is not in this source tree). The
combined report additionally lists `not_reached` modules and synthesizes a
`group:<name>` blocked unit in both JSON and Markdown when an expected group
job produced no results at all (infrastructure failure), so a crashed worker
can never look green. A selected `absent` module also fails its lane and the
combined report.

## Groups and parallelism

Ten groups, each a separate GitHub Actions job with its own MariaDB/MySQL
service, extracted `test-runtime` tree and fixture servers — no shared
mutable database or browser session:

| group | modules | contents |
|---|---|---|
| website | 2 | portfolio, reviews (+ public/browser stages) |
| installer | 1 | release-browser (+ installer/release stages) |
| portal | 3 | staff, appointments, contacts (+ admin-flow stage) |
| billing | 9 | catalog, payments, attempts, ledger, delivery, recurring-billing, license-release, software-commerce, license-financing |
| mail | 9 | notifications, mail-templates, mail-accounts, mail-connections, mail-inbox, mail-message, mail-notices, mail-drafts, mail-submissions |
| twentyi-inventory | 9 | catalog-import, provider-settings/operations, service-observations, service-plan, cloud-providers, reset-template, cloud-assignment, provider-schedule |
| twentyi-domains | 9 | domain-search, registrar, domain-renewals, domain-contracts, registration-periods, domain-expiry, renewal-agreements, renewal-dispatch, registration-drafts |
| twentyi-packages | 10 | provider-mailbox, provider-forwarding, cloud-sites, package-php, document-roots, package-force-ssl, directory-listing, force-ssl-controls, wordpress-installed, directory-controls |
| stackcp | 2 | stackcp-prerequisites, stackcp-access (in-group dependency) |
| security | 4 | support, backups, activity, client-authority (+ server-log) |

All 58 modules can execute behind the shared bootstrap. Verified module→module
edges (from controllers and `*_ready()` gates): `staff` roots the
admin-readiness chain (`catalog`, `payments`, `support`, `portfolio`);
`payments` also creates the shared `request-staff` account consumed by 34
modules (billing, mail, portal, security, provider and StackCP modules);
`provider-settings` applies the inventory storage that every
`?view=provider-settings` feature module needs, and `provider-operations`
gates `cloud-sites`; the mail chain runs
`mail-accounts` → `mail-inbox`/`mail-connections`/`mail-drafts` →
`mail-message` → `mail-notices`/`mail-submissions`; `stackcp-access` →
`stackcp-prerequisites`; `backups` requires `staff`; `license-financing`
requires `software-commerce`; and `contacts` requires the current recurring,
registration-draft, software-commerce, and financing preparation chains. Each
group job replays its transitive closure as
`preparation` units so it is self-sufficient on a fresh `ci_` schema.
**Supported workers: up to 10 concurrent group jobs** (one per group), plus
stage- and module-level blocking keeps dependents honest inside each job.
The matrix runs each selected group on PHP 8.3/MariaDB 10.11. When billing is
selected it also runs one separately named target lane on PHP 8.4/MySQL 8.4;
the target artifact is not substituted into the ten-group combined report.

## Workflow

`.github/workflows/diagnostic-tests.yml` runs on the bounded
`codex/diagnostic-acceptance-*` successor branch, on `main`, and by manual
dispatch. Manual group text is passed only through an environment variable to
the shared allowlisting registry CLI; it is never interpolated into Python or
shell source. Development runs use PR/ref-scoped newest-wins concurrency,
while every main run is retained. The workflow reuses the acceptance build
job's immutable inputs (`tools/acceptance_artifacts.py
pack-inputs` / `receive`), seeds the isolated `ci_` fixture with
`tests/native.php`, then runs one job per group. `qa/` (results JSON, report
MD, screenshots, `http-server.log`, `php-diagnostics.json`) uploads per
group; the `combine` job merges all results via `tests/diagnostic_report.py`
and fails the workflow when anything failed, was blocked, was selected but
absent, or a group never reported. Every lane binds source commit, tree, run,
attempt, ref, event, PHP and database; an always-run mutation guard verifies
the received shipping source even when the diagnostic runner fails.

## Running locally / on a branch

```
php tests/native.php                      # seed ci_ fixture + config-fixture
php tests/provider_catalog_import_native.php   # qa artifact for catalog-import
python tests/diagnostic_acceptance.py --list
python tests/diagnostic_acceptance.py --group mail --tag mail
python tests/diagnostic_acceptance.py --modules mail-inbox,mail-message
python tests/diagnostic_report.py --expect=mail <results-dir> qa/diagnostic-combined
```

`--group` and `--modules` automatically replay the registry's verified
prerequisite closure (e.g. `staff` → `payments` → the shared `request-staff`
account → mail/provider feature chains) as `preparation` units inside the
same job so the selection is self-sufficient on a fresh `ci_` schema. A
failed preparation unit records a real failure and dependency-blocks its
dependents. `--no-deps` runs exactly the listed keys for a targeted
reproduction when the state is known to be pre-seeded.

## Repair and retest protocol

The combined report's `assignment` column suggests a repair lane per group.
Each repair happens on an isolated branch/worktree; retest with
`--group <g>` (or `--modules …`) plus a neighboring group for
regression, integrate reviewed fixes normally, then re-run **strict**
`tests/browser_acceptance.py` on the combined candidate. Diagnostic green is
a triage signal, never a bypass.
