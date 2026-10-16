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
