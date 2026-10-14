# Financial agreement notifications

Financial offers and client decisions now appear in the existing **Workspace activity** center and its personal bell. Clients receive new offers, replacement offers and withdrawals for their active projects. Staff need both client-management and payment-management permissions to see client accept/decline decisions and offers or withdrawals made by other staff. Your own actions do not create notifications for you. Issued invoices and recorded payments retain their existing notifications.

Open an entry to see the exact original financial history event. A later offer or invoice does not change that destination. Opening an entry keeps its read status; choose **Mark read**, **Mark unread** or **Mark visible project updates read** explicitly. Each person has separate read state, and a new arrival cannot be included in an earlier captured page action. The count continues to use the existing 99+ limit.

The feed shows only the project name, a fixed description and the recorded UTC time. It does not copy financial terms, client feedback, prices or receipt notes into notifications. Current ownership, account status, permissions and retained financial history are checked again when viewing, opening or marking an entry. Support sessions cannot read or change a client's personal notification state. A history or storage problem reports unavailability while retaining business records.

Both workspace activity and financial agreements require their existing owner preparation. There is no additional setup or schema migration for these notifications. Back up the retained financial records and existing personal read state together. No email is sent, and no payment is recorded by a notification. Online checkout remains separate work.

## Evidence and remaining acceptance

Contract `98d0115` precedes this implementation on `codex/design-financial-activity`, from frozen parent PR 114/`a9a5f40`. Local PHP 8.4/MariaDB under 128 MiB passes 254 named financial checks, preserving the previous 209 and adding 45 notification checks. Actual local HTTP/Edge passes 75 feature assertions plus one empty-runtime-diagnostics assertion. Four complete desktop/320px client/staff activity screenshots are inspected, including escaped hostile project text and exact browser navigation.

The first new native fixture exhausted memory because its test snapshot eagerly copied the retained maximum-size history. The snapshot now streams bounded hashes, without raising the 128 MiB runtime limit; a fresh fixture passes. A local browser seed helper initially loaded an already-bootstrapped module twice; its include was corrected before data creation, then all real HTTP checks passed. Neither failure was hidden or resolved by weakening product authorization.

Focused PHP 8.2/8.4, full current application/package/Apache and populated latest 1.23 notification read-state recovery, independent parent/combined review and exact-main release acceptance remain required. The recovery tests are prepared, syntax-checked and not yet claimed to pass. The original parent114 acceptance remains on a9a5f40: its 9,302 browser/native checkpoint assertions, six real screens, unchanged homepage pixels and exact Windows archives are verified while the original full run 38651962280 continues through Apache. New notification acceptance must use its own committed source. Published 1.23 and Devin's complete 20i ownership remain unchanged.

The canonical installer refresh completed successfully before implementation commit. All 2,633 dependency hashes match the 2,634 shipping files; bundled receiving and submission dependencies match their pinned originals. This is source validation, not yet committed package acceptance.
