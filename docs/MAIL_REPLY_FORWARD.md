# Reply and Forward in admin webmail

Status: in development on `codex/mail-replies-forwards`, starting at frozen ticket-capture source `e29c1c1fb81fd221980fd383d358635b8ebd8ec0`. Codex owns this non-20i increment. Devin retains the complete 20i integration. No Reply or Forward control is available from this preparation yet.

## Intended workflow

An administrator opens an exact currently authorized message and selects Reply or Forward. The server must revalidate the actor, incoming mailbox, outgoing mailbox, account revisions, INBOX UIDVALIDITY and UID before obtaining content or creating a draft. Do not trust posted original-message content or recipient headers. Preserve current CSRF/session/support restrictions, bounded reads, explicit actions and private responses.

Create an ordinary editable private draft. Reply derives recipients from parsed Reply-To, or From only when Reply-To is absent. An invalid or unsupported reply address must remain visible as unavailable; do not guess another recipient. Forward starts with no recipients. Show quoted content and any omission explicitly. Neither action may send automatically; the existing saved-draft review and explicit Send process remains required. Existing submission identity, replay prevention, unknown-outcome handling and server-acceptance-versus-delivery wording must remain intact.

Reply context must survive draft edits, exact submission snapshots and maintained MIME preparation. Preserve existing saved drafts, submission fingerprints and dispatch history when adding storage. Incoming copied content remains subject to current mailbox access. Forwarded content must not expose hidden recipients, raw message headers, attached files or another mailbox's data.

## Implemented component

`foundation/mail-compose/MessageThread.php` represents parsed conversation identifiers without I/O, authorization, persistence or MIME generation. It is outside the deployment tree and has no application call site. `none()` supplies empty context for new messages/forwards. `reply()` carries the exact parent Message-ID into In-Reply-To, uses parent References before appending the parent ID, or falls back to a single parent In-Reply-To when References is absent. Missing identifiers stay missing. These construction rules follow [RFC 5322 section 3.6.4](https://www.rfc-editor.org/rfc/rfc5322#section-3.6.4).

The component accepts modern dot-atom identifiers and no-fold literals as parsed tokens, preserving case and exact bytes. It refuses controls, injected headers, external whitespace, obsolete quoted identifiers and malformed stored shapes. Its application bounds are 512 bytes per identifier, 32 references and 4,096 bytes for the joined References value; these are implementation limits, not RFC-wide limits. It refuses overflow instead of silently cutting history. A later receiving adapter must distinguish unavailable/unsupported context from an invented successful parse and keep the message-reading path honest.

Arrays are copied element by element so external PHP references cannot mutate accepted context. Strict saved-shape reconstruction revalidates identifiers. This establishes data validity only; the application still has to prove authority and provenance.

## Evidence and remaining acceptance

The new isolated suite has 45 named checks passing locally in each PHP 8.4 default and no-ini mode. Cases include standard reply construction, missing/multiple parent references, injection and storage damage, independent size/count limits, array aliases and readonly state. The mail-compose GitHub matrix now runs these cases alongside all existing draft/template cases on PHP 8.2/8.4. Remote results are pending until downloaded and inspected.

Next implement bounded maintained-header parsing and recipient selection, authorized draft creation/provenance, compatible storage and MIME threading, and Reply/Forward screens. Then verify native permissions/revocation/stale/replay/audit cases, actual isolated IMAP-to-draft-to-SMTP behavior, fresh/update/recovery preservation and desktop/mobile browser flows. Do not transfer the component result to those unimplemented layers. Attachments, HTML composition, server-folder synchronization and the wider A-06 roadmap remain separate outstanding requirements. No real customer mail, deployment or new release occurs here.
