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
