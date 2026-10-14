# Hosting lifecycle journal and persistence boundary

Unreleased HC-08 preparation on `codex/hosting-journal`, starting from hosting integrationb6d4d24. The source is outside `site/`; it does not write a database, authorize a user, contact20i or provision hosting. Codex owns this journal and the later application adapter. Preserve the parent hosting integration PR79 and Devin's original PR60. Mail1.22 PR77 remains a separate candidate.

`foundation/hosting/HostingJournal.php` stores a bounded, versioned JSON command history and reconstructs the existing `HostingAccount` model by invoking a fixed list of its methods. It does not deserialize PHP objects, use reflection, accept stored class/method names or assign private model fields. The format captures the package terms, account/client identity, creation time and ordered events. Do not edit or truncate old events to force a new state.

Creation snapshots the validated package and detaches caller references. `fromJson()` requires the expected account and client identity from the caller's trusted storage selection. Only canonical JSON with exact version1 fields, sorted quotas, exact scalar types and supported command arguments is accepted. Duplicate keys, unknown fields/commands, noncanonical encoding, malformed UTF-8, control characters, foreign identities, duplicate events, decreasing times and inconsistent observation fields are refused. Observations cannot precede their dispatched attempt or follow their recording event. Newer unknown formats must be preserved and refused, never downgraded.

`append()` returns a new immutable journal after reconstructing the entire proposed result. A failed transition leaves the original bytes and revision intact, even if a mutable model method began an internal change before throwing. Each event has a unique32-hex identity. Exact replay returns the existing journal; changed reuse is refused. A model returned by `account()` is independent, so mutating it cannot rewrite the saved journal.

Round trips retain the canonical lifecycle's unresolved outcomes, exact attempt sequence, provider resource identity, domain, grace deadlines, disputes, cancellation requester, retry eligibility, history and observations. In particular, an unknown create cannot become a retryable order by process restart, and a failed destructive operation is not automatically rearmed. Two different actor identifiers still do not establish authorization: the application must check actual users and grants.

The first format caps512 events,1 MiB JSON,64 named quotas,128-byte account/client/actor identifiers,256-byte single-line text values and64-byte quota names. Timestamps and quotas are nonnegative integers, with null quota retaining the canonical "not metered by this plan" meaning. A full journal fails explicitly; future compaction/versioning must preserve all attempt and audit invariants before extending this format. It must not silently discard history.

The fingerprint detects byte differences for comparison. **It is not a signature, permission check, authentic provider receipt or evidence that a remote action occurred.** A structurally valid journal can contain invented observations if supplied by an unauthorized writer. Never accept it from HTTP as an account import, state update or proof of ownership. Provider observations must come from the separately verified adapter and use sanitized values rather than credentials or raw response bodies.

## Required durable application adapter

Before application activation, add native MariaDB storage and tests covering:

- Current staff/client ownership and action-specific authorization, separate from model actor strings. Bind each loaded journal to its selected account/client and approved package terms.
- Transactional row locking or compare-and-swap revisions, stable command ids and strict audit with no lost updates. Validate retained bytes before adopting or mutating them. Never rebuild damaged history as a fresh pending account.
- An immutable committed dispatch fence before any credential or network use. Loading a pending attempt or replaying an event cannot authorize another provider call. Only the invocation that commits a new authorized claim may continue.
- Exact operation/account/attempt-bound results, conservative unknown outcomes and deliberate reconciliation. A confirmed operation whose result cannot be saved must remain unresolved; restoring files cannot erase its fence.
- Native competing-process, stale revision, strict audit failure, interruption, update/recovery and current-authority tests; private staff/client screens and the independently verified provider contract.

The journal is one prerequisite, not completion of these items. Public ordering, merchant settlement,20i provisioning, suspension, deletion, StackCP handoff, VPS and SSH stay behind their own acceptance and owner activation gates. [Hosting foundation](HOSTING_FOUNDATION.md), [dependency gates](DEPENDENCY_GATES.md), [hosting roadmap](HOSTING_AND_BILLING_ROADMAP.md).
