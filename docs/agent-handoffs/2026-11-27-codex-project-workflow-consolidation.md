# Held project-workflow consolidation

PR #407 and its exact resulting-main cohort are qualified. This work has been reconstructed locally from verified main but remains unpushed and is not release evidence. It still requires an exact source/proof successor, fresh independent review, first-attempt hosted acceptance, normal merge, and resulting-main verification.

## Exact local ancestry

- Prepared branch: `codex/project-workflow-consolidated-main788-20261128`
- Verified base: `7889783b6bc9f58c2ac0a8c8a4c7a334155a390e`, tree `b099603c62d852269a9f7ba4ef357960eb2d82bb`
- Base parents: prior main `30b40c2ab1f48ac78c9e5b1d981b687e515dbb23`, accepted PR #407 head `fec49967235f6491de9ce2423caf9c1637dce1e9`
- Rebased inert storage/runtime commit: `770e7bb076366eadf540546219295dcd9d51ad4e`, sole parent verified base
- The reviewed routing, activity, browser, upgrade, package, and workflow group was restored byte-for-byte after the rebase: tracked diff fingerprint `91411af3c6eead83cbacaf23390186618866176f`; untracked manifest SHA-256 `0a0e69da9b3b643e081fd4c72b39bd1a1ec2b79d979662267b9e83875ec5b1e8`.

## Retained and added behavior

- Retains current client/contact identity, support, private notes, billing, hosting, delivery, review, conversation, file, and unified database-update behavior.
- Adds optional owner-reviewed project-history storage through the existing Version & database plan/apply workflow. No password field or legacy direct setup action is reintroduced.
- Routes prepared writes through immutable journaled progress records. Legacy writes take the shared staff mutex and recheck activation inside the transaction, so activation and a direct legacy save cannot cross.
- Shows bounded latest pending design/proposal actions only after current project/contact/support authorization.
- Adds client-visible and staff history with exact event focus, stale-draft retention, personal client/contact read state, and privacy-minimal activity metadata.
- Adds no payment, provider, message, deployment, publication, or other external operation.

## Local evidence

- PHP syntax: all changed and new PHP files pass on local PHP 8.3.
- Pure record suite: 155 checks pass with default and `php -n`.
- Form/render suite: 57 checks pass with default and `php -n`.
- Review-action suite: 45 checks pass with default and `php -n`.
- Unified registry policy: 410 executions pass; view policy: 12 executions pass.
- Schema, runtime, and integration static policies pass.
- Canonical/deployed `ProjectActionItems.php` bytes match.
- Installer generation is current for 3,136 shipping files; 94 build rejection checks pass.
- Browser and upgrade Python sources compile; the published v1.25 harness unit suite passes all 17 tests and 12 release-gate checks.
- The same focused qualification was repeated after reconstruction onto verified main. Registry and view policies pass under default and `php -n`; Python and workflow YAML parse; 94 build rejection checks pass; locked dependency, receiving, PHPMailer and submission mirrors remain exact.

The Windows preparation host has no local MySQL, MariaDB, Docker, or WSL runtime. Native project-history database tests are therefore intentionally not claimed locally. The new workflow retains first-attempt PHP 8.2/8.4 × MariaDB 10.11/MySQL 8.4 lanes.

## Preserved failure and remaining acceptance

The unchanged full composition proof correctly refused the pre-rebase held successor because its exact proof history was still bound to #407. The preserved refusal is `Component fast-path proof is not the exact direct successor`. Do not edit constants or weaken that proof to hide the refusal. Commit the application/workflow source first, then construct a new outer project-workflow projection and proof-only successor from the clean exact implementation tree.

Remaining sequence:

1. Freeze the integrated implementation commits, including the bounded release-status documentation correction, without changing the published v1.27.0 tag, assets, or application version.
2. Create the current composition/provenance proof only after that implementation source is clean and frozen; do not reuse pre-#407 proof hashes.
3. Obtain fresh exact-head implementation and security/privacy/provenance reviews.
4. Publish once and open the integration PR naturally so the four native project-history lanes, full Application HTTP/Chromium, Apache published-source upgrade/recovery, package/inventory, repository, security/privacy, source-composition and review gates execute on the exact successor.
5. Preserve any first-attempt failure. Correct a real defect only on a new reviewed exact successor.
6. Merge normally only after every mandatory gate passes, then verify the complete resulting-main cohort before advancing the next administration slice.
