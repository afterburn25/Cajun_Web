# Reply and Forward copied-message drafts

This increment adds bounded Reply and Forward draft creation to the existing admin inbox, local-draft, and explicit mail-submission workflows. It does not add Reply All, attachments, HTML composition, automatic sending, retries, mailbox flag changes, or Sent-folder synchronization.

## Authority and source binding

- The actor must retain a current staff session, global `mail.read` and `mail.send` permissions, Read access to the exact source account revision, and Send access to the selected outgoing account revision.
- A copy action performs one new authorized exact-message read. It binds the source account, account revision, INBOX UIDVALIDITY, UID, durable read record, outgoing account, and actor to an opaque draft id.
- A recent display read cannot be reused immediately as the copy read. The established per-account two-second message-read boundary fails closed before another connection or draft write.
- Every open, edit, review, submission preparation, replay, and dispatch authorization rechecks the current source and sender authority. Losing source Read access masks copied subjects and content without deleting retained records.

## Private storage

Copied content is stored only in the protected `mail_reply_drafts` row. The paired ordinary `mail_drafts` row is a content-free recovery placeholder with status `source`; older application code therefore cannot expose, edit, discard, or submit copied bytes through the ordinary draft API.

The immutable origin fingerprint covers the source message identity, durable read record, actor, original outgoing account, mode, omissions, and RFC 5322 thread context. The protected content fingerprint covers the current outgoing account, draft revision/status, immutable origin, and canonical copied content. Integrity or schema mismatches preserve the stored data and refuse use.

Reply copies only the validated reply address, bounded subject, bounded plain-text quote, and validated conversation identifiers. Forward starts with no recipients and no reply-thread headers. Raw MIME, hidden recipients, attachments, remote content, and arbitrary headers are never stored in the draft.

## Explicit review and submission

Creating or saving a copied draft does not prepare or send a submission. The author must open the existing review screen and explicitly confirm sending.

Ordinary submissions remain canonical format 1. Exact regression checks pin their private JSON bytes, fingerprint, MIME checksum, and absence of reply-thread headers.

Copied submissions use format 2, which appends only canonical thread context and the immutable origin fingerprint to the format-1 payload. Reply MIME may add validated `In-Reply-To` and `References` headers. Forward MIME adds neither. Existing immutable submission, dispatch-fence, current-authority, rate-limit, result, and replay rules remain unchanged. There is no new retry or live-operation control.

## Database preparation

`mail_replies_schema_version` is an additive version-1 schema registered in the central Version & database workflow. Its reviewed dependency plan closes over mail accounts, local drafts, and exact message reads. Setup requires the current owner password, validates exact table/index/collation/foreign-key metadata, preserves populated recognized storage on repeat, and refuses unknown or changed storage without adopting it.

## Acceptance required before merge

- Pure compose, origin, thread, protected-storage, admin-policy, ordinary format-1 regression, schema-registry, and no-INI portability checks.
- Native MariaDB and MySQL checks on PHP 8.2 and 8.4 for additive setup, rollback, source/sender authority, protected storage, format-2 preparation, ordinary format-1 retention, tamper refusal, concurrency, revocation, pagination, and absence of SMTP dispatch.
- Full packaged Application HTTP/Chromium acceptance for Reply and Forward creation, explicit review, synthetic loopback SMTP acceptance, exact saved MIME/thread headers, replay, authority revocation, direct-file denial, and desktop/mobile rendering.
- Exact-source composition/provenance, package/inventory, security/privacy, and independent implementation review under the repository release workflow.

Synthetic loopback mail acceptance is not real-mailbox or production-delivery evidence. No customer message, live payment, provider operation, deployment, or release publication is authorized by this increment.
