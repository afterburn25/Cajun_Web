# Scheduled read-only hosting inventory

## Scope and authority

Isolated branch `codex/twentyi-scheduled-refresh`, base `82302571dfbcda649f4cebfce79c86faf63662f8`, implements owner takeover requirement 7/HC-13. Source inspection found no application job runner. The new runner performs only the verified `packages.list` / `GET /package` through existing `InventoryReader`, `FixedHttpTransport`, protected credentials and inventory persistence. No account schema, prices, provider writes or client assignment are inferred.

An owner prepares additive history and explicitly approves repeated reads using their current password. Neither action contacts 20i. The durable approval binds the approving owner/authentication version, exact profile/revision/ciphertext and canonical installed website/document-root paths. It stores no password, raw key or interactive session. Changed owner access/authentication, provider settings/key or installation paths invalidate dispatch. Disable remains available while a worker is resolving DNS.

## Shared-host setup

Schedule installed `app/jobs/run.php` every five minutes through the hosting panel using its PHP command and the same filesystem account as the private key. The file refuses HTTP, accepts no arguments and prints finite outcome codes. It needs no Node, Composer, daemon, root access or SSH. The stored document root remains strict input to the original vault guard: private storage must be outside both the application and web root, and the original key retains 0600 permissions. The Windows vault guard is unchanged.

One invocation starts at most one due read. A connection-owned job mutex remains held across DNS/network/commit; an expired 120-second durable lease does not permit concurrent takeover. The staff mutex serializes owner and credential changes around dispatch. Existing HTTP timeout is 15 seconds; a 30-second budget is checked at dispatch authorization boundaries. Host resolver timeouts remain a host responsibility because PHP cannot portably impose a hard wall-clock bound on DNS.

## Persistence and recovery

Start intent, journal and audit commit before network. Typed observations, inventory result and scheduler result/head/audit commit atomically through an internal helper requiring the owning staff mutex and transaction. The public inventory wrapper retains its former transaction/error behavior and has no new lock precondition. Incomplete reads back off exponentially to at most daily. Uncertain work does not automatically retry. Owner recovery requires an expired lease and a free worker lock, records refused/interrupted without network and disables scheduling until fresh approval.

An immutable event table records setup/approval/pause/start/finish. An independent settings head, audits, contiguous hash chain and original inventory evidence detect history loss. Worker state validates all retained event bytes, approval/start references, exact intent identity/revision/actor/time and result hashes through fixed database queries, without per-event round trips. Owner pages return at most 21 history records and exclude key/auth fingerprints and vault paths.

## Evidence and remaining work

Local touched PHP syntax and 42 pure document cases in each default/no-INI mode pass. Root reviewed the persistence extraction and the retained-history correction; no further concrete source issue was found in that delta. Real PHP 8.2/8.4 with MariaDB/MySQL native tests are prepared but not yet run. They must verify SQL/PHP hash parity, concurrent workers, revocation, atomic rollback, corrupt retained evidence, malformed storage and explicit recovery. Actual owner HTTP/Chromium setup/run/failure/revocation/recovery/mobile proof remains pending. No live 20i API or customer deployment occurred.

Prior catalog 872 artifacts are preserved and do not certify this increment. Other inventory schedules, billing/provisioning jobs, live account acceptance and host cron activation remain unfinished. Root integrates and runs fresh combined acceptance before release.

## 2026-10-15 - initial scheduler native matrix and recovery corrections

Exact source 60f95ca460b303b09853c0f2dfaa4c30d43dde56 ran in four-way workflow 38831603128. All four artifacts were downloaded and SHA256/CRC/named reports verified. Both MySQL variants passed 65 native cases; both MariaDB variants failed after five passes on a SHA2/ASCII collation comparison. The failed archives remain preserved; this is not full acceptance. Corrected exact-byte binary casts keep every integrity gate. Added an uncached current staff-schema check, old event-header binding checks, vault-permission refusal and original public-wrapper nested-transaction coverage. The initial pure report filename detection overwrote one report despite both 42-case commands passing; INI-based naming now retains both. Browser setup/read/failure/revocation/mobile cases and Apache internal-denial paths are wired. Source/pure syntax checks pass; new native/full-browser execution remains pending. No other checkout or live provider action.

## Exact e597 evidence and packaging repair

Native run38832382087 passed all four PHP/database combinations; all downloaded archives have verified SHA256/CRC and70 native,42 default and42 minimal named results each. Proof is retained at work/schedule-e597-native-verification.json. Artifact12018430434 (8.2/MariaDB) SHA2561e3eee1faed5bcecd22143f3ef6d35378e9c0c4fdef5840e3b2f3d3e240c7d73;12018136203 (8.4/MariaDB)756ef7daa0a27fd1c2b04022b9976995395e28b56db105ddba924c834f723ba3;12017368396 (8.2/MySQL)e2871c25a8ead83f42d3100df00adbbd4a51383e64a82b8455eb201e7c18a7d2;12017333491 (8.4/MySQL)e463b4d50eec871a6fb3d77614d35971921abfa61cd01bc060811d9759b0bb76.

Full38832382079 is failed evidence: installer hashes had been generated from five local CRLF files while Git stored LF. The bounded packaging correction regenerates inventory from exact committed bytes; it changes no application behavior. Final12018490202 SHA25661d3d1782629ffa59512703988bbc239b70fa391dea34f975215dcf386ce4429 and checkpoint12018235959 SHA25636693a9d39a829f6f679303f31b006a41c4cb02bf666d71951aa32d9201a8d53 contain no completed browser/package acceptance. Independent review then identified retained observed-package counts and original inventory audit witnesses as missing checks; that separate correction requires new native/full evidence.

## Retained inventory witnesses follow-up

Independent source review identified missing original inventory audit/observation-count reconciliation. Scheduler state now requires exact action, actor and detail witnesses for each start/outcome, including pending starts and original actors on another owner's interruption review. The observed package count must equal the immutable result count. Three fixed SQL queries preserve bounded database round trips. Lost rows/audits refuse worker dispatch and owner history; original restoration retains the next state and performs no replay. Native loss/restoration, case-only action and original-actor regressions are added. Exact corrected-source runtime/browser evidence remains required.

## Exact5fb evidence and browser fixture correction

Native38833639848 passes all4 supported combinations with102 native +42 default/+42 minimal named checks each, all actual archives/SHA256/CRC/source verified. Windows33450 builds exact5fb: FULL5481 members SHA2564512c790ebe88f5c6c39f5ab6d10f10c760a68da0b574de9f4e1476b6d5f9f36; COMPLETE2690 dcdc845ecb3327df551bf29c9357559b5526b82b9ccc96fa7eb75f5a8136844a; UPDATE2677 b4ac0e47eb0604355e41f7b3dfb959774615a320c7d0524fc2a8a3fb5ee748a6. All11 checksums/manifests/exact Git members verified; proof work/schedule-5fb-windows-verification.json.

Full38833639837 browser failed after1912 passing recorded checks. Its server/DB checks verify scheduler setup, authenticated GET, unchanged assignments, duplicate prevention, mobile fit and actual failure/backoff; the later browser lookup reused the previous document after same-fragment navigation. Two test navigations now use explicit reload, preserving all assertions/capture diagnostics. No application/inventory change. Failed checkpoint12019415202 SHA256c905704aaafc7f447e9d49e05687bd2f85c3b8c2d6a5694784044fdff2256def and both inspected owner captures are retained. At that checkpoint corrected packaged/browser and final combined acceptance remained required.

## Corrected554 checkpoint and final preserved5fb outcome

Exact554b02d0fa4ef6e042b8576ae0971203ccbea78f checkpoint38836183578 is terminal SUCCESS. Final artifact12019218659 SHA256bc87d891a1f14063e437a697c19ab61d8fc1ccc4a4b82c22d26aa449201ff4bd independently passes CRC, all named reports, all11 distribution checksums, manifests and exact Git source members. Actual HTTP/Chromium passes1,926 named checks including34 scheduler checks; native102 and pure42/42 remain passing. PHP and browser diagnostics are clear. Proofs are work/schedule-554-checkpoint-verification.json and work/schedule-554-screenshot-verification.json. The checkpoint intentionally skips recovery/Apache, so it is not a full run.

All four actual scheduler section captures were inspected at original resolution: owner desktop1100x935, owner mobile288x1370, failure mobile288x1511 and revoked mobile288x1404. Labels, timestamps and actions fit without overlap. The failure capture shows retained authentication failure/backoff; revocation shows Approval Required without a Run-if-due action. Password fields are empty. These are section captures, not a claim that unrelated page chrome or collapsed setup instructions were visually inspected. The browser test separately verifies mobile page width, installed CLI refusal and private-route denials.

Original5fb full38833639837 is terminal FAILURE because its browser navigation failed; preserve that outcome. Its later289 recovery and1,931 Apache named checks passed. Final12019503731 SHA25661e5becc63553c8fc859dfae1d1a6b04aa271cf9b031b051f2875c205edcbed1 passes archive/package verification and all11 Linux distribution files are byte-identical to preserved exact5fb Windows output. Proofs are work/schedule-5fb-full-terminal-verification.json and work/schedule-5fb-crossplatform-verification.json. Shipping runtime and installer inventory remain byte-identical5fb. Root may normally integrate this branch and run fresh combined acceptance; no release, cron activation or real provider operation is claimed.
