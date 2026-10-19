# P-07 notification foundation (isolated component)

Canonical contract classes for the roadmap's notification work: portal
bell/timeline, per-recipient read state, configured email preferences,
bounded delivery retries, and honestly-reported stale/absent metrics.

This is a **model layer only**. It owns no persistence, no routes, no
mail transport and no UI. Integration (database storage, portal/admin
surfaces, real mail senders, polling) is a separate gate.

## Files

| File | Role |
| --- | --- |
| `foundation/notifications/Notification.php` | One notification scoped to a recipient and audience (`client`/`staff`), with a typed `kind`, required title, and an opaque `kind:id` link reference. `assertClientVisible()` makes staff-only content structurally unshowable to clients. |
| `foundation/notifications/NotificationFeed.php` | One recipient's feed: construction rejects foreign-recipient, wrong-audience and duplicate records, so read state can never cross recipients. `markRead`/`markUnread`/`markAllRead` accept only the recipient as actor — a staff view cannot silently consume a client's unread bell. Newest-first `page()` for paginated history. |
| `foundation/notifications/NotificationPreferences.php` | Per-(audience, recipient) email opt-outs per kind. Portal delivery is unconditional (the bell is the product surface); email defaults enabled with explicit per-kind opt-out recorded under the recipient's own actor identity. |
| `foundation/notifications/DeliveryAttempt.php` | `pending → sent / retrying → sent / failed` per channel (`portal`/`email`), bounded `maxAttempts` with exponential backoff. `failed` is terminal and honest — never retries forever and never reports success without a recorded confirmation. |
| `foundation/notifications/MetricSample.php` | Timestamped usage/health sample reporting `fresh` / `stale` / `absent`. `currentValue()` returns a number only when fresh, so a renderer must show the honest marker rather than an old or invented figure. |

## Invariants

- A notification's audience and recipient are fixed at construction.
- Read state and listing are per-feed; cross-recipient access is a
  construction-time error, not a filter.
- `DeliveryAttempt` requires a named error for every failure and a
  terminal `failed` state once retries exhaust.
- A metric value without its `checkedAt` cannot exist; stale or absent
  data yields `null`, not a guess.

## Not included (separate gates)

- Real mail transport and provider delivery.
- The sources that produce metrics — this component only models the
  honest freshness contract they must satisfy.

## Runtime integration — `devin/non20i-notifications-runtime`

The first persistence/UI slice now exists on the runtime branch:
`ci_notification_feed`, `ci_notification_prefs` and
`ci_notification_deliveries` (separate from the payment-webhook
`notifications` tables), an owner-password setup migration,
`app/notifications/feed.php` adapters, private admin and portal feed
screens, recipient-only read state, per-kind email opt-outs, read-only
support-session viewing, and emit hooks on inquiry, message, decision,
delivery and invoice-paid events. A stacked announcements slice adds
scoped `clients.manage`-gated service announcements to all active
clients with an auditable `notification_announcements` history.
Email copies attempt synchronously
after the domain write commits — no daemon. Polling endpoints and
`MetricSample` producers remain future gates.

## Tests

`tests/notifications/run.php` — 65 synthetic checks covering
construction validation, audience separation, feed privacy, read-state
actor rules, preference opt-outs, bounded retry/backoff/terminal states,
and metric freshness. Fixture notifications only; no `site/` code.

CI: `.github/workflows/notifications.yml` runs the suite on the official
PHP 8.2 and 8.4 images with `--network none`.
