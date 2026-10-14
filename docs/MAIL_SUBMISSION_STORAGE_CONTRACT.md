# Proposed durable submission adapter

This is the next implementation boundary after isolated transport source2edeec1. No database schema, migration, route or Send UI is implemented by this document. Reuse the current staff mutex/transactions, account grants/private key, saved drafts and immutable preparation component. Independent transport review remains requested in PR77.

## One reviewed intent and one attempt

The form carries a server-generated random32-hex submission id plus the exact saved draft/account revisions. GET/preview/template/save actions do not create submission records or send mail. One explicit confirmed POST creates an immutable private snapshot, fixed MIME bytes, snapshot fingerprint, MIME hash, Message-ID and reserved random32-hex attempt id under current author/Send/mailbox authority.

Proposed additive tables are `mail_submissions` (immutable private preparation), `mail_submission_dispatches` (at most one committed dispatch fence per submission) and `mail_submission_results` (at most one immutable terminal observation). Foreign keys restrict deletion of referenced draft/account/author records. Exact metadata/index/collation/foreign-key validation and owner-password setup follow the existing draft migration discipline; unknown/nonempty unrecognized storage is never adopted or replaced.

Use a unique saved draft-id/revision association as well as the submission-id primary key. Repeated clicks with different form ids cannot submit the same saved revision twice. Existing-intent replay returns its existing state after current author/mailbox access checks and never performs DNS, credential decryption or SMTP. Conflicting id reuse fails. The snapshot's whole private fingerprint, including Bcc, is bound to every transition; the MIME checksum alone is insufficient.

Changing a draft/account after review invalidates a fresh dispatch. History remains the immutable old snapshot. Any dispatched submission without a terminal result, or a terminal unknown result, prevents another send from the same draft even after a later edit. Retry/reconciliation is deliberately absent from this first adapter. A later explicit retry experience must address possible duplicate delivery and use a new reviewed intent; never silently recycle an attempt id. A valid rejection/not-submitted result permits a later edited revision to be reviewed as a new intent, subject to current authority and rate limits.

## Commit before exposure

1. Under the staff mutex and one database transaction, enforce current staff author/session, global Send, exact enabled mailbox grant/account revision, saved active draft revision and reviewed snapshot. Validate endpoint policy and bounded content. Insert immutable preparation and strict metadata-only audit atomically. A failed audit or insert leaves no intent and performs no resolver, decryption or SMTP work.
2. Resolve the saved host outside the staff mutex and transaction. Validate the entire bounded public DNS answer set; choose exactly one address without fallback. No caller endpoint or test-driver selection is exposed through HTTP/configuration. Resolution failure can record a finite not-submitted preparation result without a dispatch fence.
3. Reacquire the staff mutex. Reload current actor/session/grant/account/draft revisions and verify immutable saved bytes/hashes. An existing dispatch/result means return existing state without sending. Recheck bounded actor/account limits. Insert the unique dispatch fence plus strict audit in a transaction and commit before decrypting credentials or opening SMTP. Never hold an InnoDB transaction over network I/O.
4. Retain the established staff mutex while using the selected saved account and one bounded channel, as existing connection checks do. The required transport callback rechecks current authority before credentials, envelope, DATA and terminator. Callback expiry/revocation before the terminator stops submission; uncertain post-terminator failures remain unknown. No retry/failover/credential fallback.
5. Finalize with the exact reserved attempt id, submission id, private fingerprint and MIME hash. Require a matching dispatch fence for any observed network result. DNS/predispatch refusal is explicitly distinguished. Insert one terminal result and strict audit atomically. Exact replay is a no-op; any mismatched/conflicting receipt leaves the original state unchanged. Validate allowed status/reason/phase/code combinations at this persistence boundary rather than trusting a caller-constructed object.

Finalization records what the server actually did. Permission/session loss after a verified final250 must not relabel acceptance as failure, suppress its internal result or enable a retry. Use the saved actor identity for the attributed result audit; independently recheck current authorization before disclosing the history to a browser. Internal finalization is never a public callback or user-selectable operation.

If the process dies after the dispatch fence, or strict result/audit persistence fails, retain the unresolved fence and do not repeat network work. A missing result does not prove the mail was unsent, even when local code had observed a final response. If only preparation exists after interruption, show an incomplete preparation and do not resume it automatically. A current-authorized history explains the distinction without recipient/content leakage into public notices, URLs or audit logs.

## Required native evidence before UI

- Fresh/additive setup with exact schema adoption/refusal, failed setup audit, preserved original mail/provider keys and earlier records.
- Current author versus owner isolation; account grant/global Send/session/draft/account revision checks before preparation, after DNS and while waiting for the shared mutex.
- Two concurrent processes with the same or different form ids for one draft revision: one preparation, one dispatch fence, at most one transport invocation. Existing replay never resolves or decrypts again.
- Strict preparation/dispatch audit failures prevent all later effects; result-audit failure preserves a dispatched unresolved intent. No database transaction exists inside resolver/transport callbacks.
- Exact preparation/MIME reconstruction and private Bcc fingerprint binding; damaged stored content refuses dispatch without overwriting records.
- Finite DNS/credential/transport outcomes; all-recipient refusal; accepted/negative/unknown terminal results; incomplete process interruption; post-acceptance authority loss still records acceptance while browser disclosure is denied.
- Wrong attempt/submission/fingerprint/hash, stale duplicate or conflicting terminal receipts cannot mutate state. Unknown/dispatched intents block another revision of the same draft. Known failures and new edited revisions retain separate immutable history.
- Author-only private history has no-store/no-referrer behavior when later connected. Real packaged browser/Apache/latest-release update and recovery remain a subsequent gate.

See the [overall contract](MAIL_SUBMISSION_CONTRACT.md), [verified component scope](MAIL_SUBMISSION_TRANSPORT.md), [draft behavior](LOCAL_EMAIL_DRAFTS.md) and [release gates](RELEASE_WORKFLOW.md). No real mailbox delivery, automatic reconciliation or production activation is authorized by component evidence alone.
