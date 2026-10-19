<?php
declare(strict_types=1);

/**
 * Synthetic P-07 notification-model checks. Fixture notifications only —
 * no persistence, no real delivery, no UI.
 */

use CajunWebPro\Foundation\Notifications\DeliveryAttempt;
use CajunWebPro\Foundation\Notifications\MetricSample;
use CajunWebPro\Foundation\Notifications\Notification;
use CajunWebPro\Foundation\Notifications\NotificationFeed;
use CajunWebPro\Foundation\Notifications\NotificationPreferences;

require __DIR__ . '/../../foundation/notifications/Notification.php';
require __DIR__ . '/../../foundation/notifications/NotificationFeed.php';
require __DIR__ . '/../../foundation/notifications/NotificationPreferences.php';
require __DIR__ . '/../../foundation/notifications/DeliveryAttempt.php';
require __DIR__ . '/../../foundation/notifications/MetricSample.php';

$checks = 0;
$failures = [];

function check(string $name, bool $actual): void
{
    global $checks, $failures;
    $checks++;
    if (!$actual) {
        $failures[] = $name;
    }
}

function throws(string $name, callable $action): void
{
    global $checks, $failures;
    $checks++;
    try {
        $action();
        $failures[] = $name . ': invalid action was accepted';
    } catch (InvalidArgumentException) {
    }
}

function notif(array $over = []): Notification
{
    return new Notification(...array_replace([
        'id' => 'n-1',
        'audience' => Notification::AUDIENCE_CLIENT,
        'recipientId' => 'client-7',
        'kind' => 'project-update',
        'title' => 'Milestone reached',
        'body' => 'Design draft posted.',
        'link' => 'project:7',
        'createdAt' => 1000,
    ], $over));
}

// ── Notification construction ─────────────────────────────────────

$n = notif();
check('valid notification constructs', $n->id === 'n-1');
check('client audience asserts visible', (static function () use ($n) {
    $n->assertClientVisible();
    return true;
})());
throws('staff audience refused to client', fn () => notif(['audience' => 'staff'])->assertClientVisible());
throws('unknown audience rejected', fn () => notif(['audience' => 'public']));
throws('unknown kind rejected', fn () => notif(['kind' => 'marketing']));
check('all roadmap kinds accepted', count(Notification::KINDS) === 6);
throws('empty title rejected', fn () => notif(['title' => '  ']));
throws('empty recipient rejected', fn () => notif(['recipientId' => '']));
throws('untyped link rejected', fn () => notif(['link' => 'just-a-slug']));
throws('uppercase link kind rejected', fn () => notif(['link' => 'Project:7']));
check('typed link accepted', notif(['link' => 'invoice:inv_42'])->link === 'invoice:inv_42');

// ── Feed privacy and read state ───────────────────────────────────

$feed = new NotificationFeed('client-7', 'client', [
    notif(['id' => 'n-1', 'createdAt' => 1000]),
    notif(['id' => 'n-2', 'createdAt' => 2000, 'link' => 'project:7']),
    notif(['id' => 'n-3', 'createdAt' => 3000, 'link' => 'project:7']),
], ['n-2' => true]);

check('unread count excludes pre-read', $feed->unreadCount() === 2);
check('newest first ordering', $feed->page()[0]->id === 'n-3');
check('page slices', count($feed->page(1, 1)) === 1 && $feed->page(1, 1)[0]->id === 'n-2');
throws('page requires limit >= 1', fn () => $feed->page(0, 0));

$feed->markRead('n-1', 'client-7');
check('recipient can mark read', $feed->unreadCount() === 1);
check('mark read idempotent', (static function () use ($feed) {
    $feed->markRead('n-1', 'client-7');
    return $feed->unreadCount() === 1;
})());
$feed->markUnread('n-2', 'client-7');
check('recipient can mark unread', $feed->unreadCount() === 2);

throws('foreign actor cannot mark read', fn () => $feed->markRead('n-3', 'staff-9'));
throws('foreign actor cannot mark unread', fn () => $feed->markUnread('n-3', 'staff-9'));
throws('foreign actor cannot mark all', fn () => $feed->markAllRead('staff-9'));
$feed->markAllRead('client-7');
check('mark all read', $feed->unreadCount() === 0);

throws('feed rejects foreign-recipient notification', fn () => new NotificationFeed('client-7', 'client', [
    notif(['recipientId' => 'client-8']),
]));
throws('feed rejects wrong-audience notification', fn () => new NotificationFeed('client-7', 'client', [
    notif(['audience' => 'staff', 'recipientId' => 'client-7']),
]));
throws('feed rejects duplicate ids', fn () => new NotificationFeed('client-7', 'client', [
    notif(), notif(),
]));
throws('read marker for unknown id rejected', fn () => new NotificationFeed('client-7', 'client', [], ['ghost' => true]));
throws('markRead on unknown id rejected', fn () => (new NotificationFeed('c', 'client'))->markRead('nope', 'c'));

$staffFeed = new NotificationFeed('staff-9', 'staff', [
    notif(['audience' => 'staff', 'recipientId' => 'staff-9', 'kind' => 'service-event']),
]);
check('staff feed holds staff-audience items', $staffFeed->unreadCount() === 1);

// ── Preferences ───────────────────────────────────────────────────

$prefs = new NotificationPreferences('client-7', 'client');
check('email defaults enabled', $prefs->emailEnabled('billing') === true);
check('portal always enabled', $prefs->portalEnabled('billing') === true);
$prefs->setEmail('billing', false, 'client-7');
check('opt-out sticks', $prefs->emailEnabled('billing') === false);
check('other kinds unaffected', $prefs->emailEnabled('support-reply') === true);
check('overrides list only explicit', $prefs->overrides() === ['billing' => false]);
throws('foreign actor cannot set preferences', fn () => $prefs->setEmail('billing', true, 'staff-9'));
throws('unknown kind rejected in set', fn () => $prefs->setEmail('marketing', true, 'client-7'));
throws('unknown kind rejected in get', fn () => $prefs->emailEnabled('marketing'));
throws('unknown kind rejected at construction', fn () => new NotificationPreferences('c', 'client', ['marketing' => false]));

// ── Delivery attempts ─────────────────────────────────────────────

$d = new DeliveryAttempt('n-1', DeliveryAttempt::CHANNEL_EMAIL, maxAttempts: 3, backoffSeconds: 60);
check('new attempt pending', $d->state() === 'pending');
check('pending attempt due', $d->due(0) === true);
$d->recordSent(10);
check('sent recorded', $d->state() === 'sent' && $d->sentAt() === 10 && $d->attempts() === 1);
check('sent not due again', $d->due(100) === false);
throws('sent is terminal for recordSent', fn () => $d->recordSent(20));
throws('sent is terminal for recordFailure', fn () => $d->recordFailure(20, 'bounce'));

$r = new DeliveryAttempt('n-2', DeliveryAttempt::CHANNEL_EMAIL, maxAttempts: 3, backoffSeconds: 60);
$r->recordFailure(0, 'smtp refused');
check('first failure retries', $r->state() === 'retrying' && $r->attempts() === 1);
check('backoff schedules retry at +60', $r->nextAttemptAt() === 60);
check('not due before backoff', $r->due(30) === false);
check('due after backoff', $r->due(60) === true);
$r->recordFailure(100, 'smtp refused again');
check('second failure doubles backoff', $r->nextAttemptAt() === 220);
$r->recordFailure(300, 'mailbox full');
check('third failure is terminal', $r->state() === 'failed' && $r->lastError() === 'mailbox full');
check('failed is not due', $r->due(10000) === false);
throws('failed is terminal', fn () => $r->recordFailure(400, 'again'));
throws('failure needs an error string', fn () => (new DeliveryAttempt('x', 'portal'))->recordFailure(0, ' '));
throws('unknown channel rejected', fn () => new DeliveryAttempt('x', 'sms'));
throws('maxAttempts below 1 rejected', fn () => new DeliveryAttempt('x', 'portal', 0));

$portal = new DeliveryAttempt('n-3', DeliveryAttempt::CHANNEL_PORTAL, maxAttempts: 1);
$portal->recordFailure(0, 'write failed');
check('single-attempt channels fail immediately', $portal->state() === 'failed');

// ── Metric freshness ──────────────────────────────────────────────

$fresh = new MetricSample('disk-usage', '82%', 900);
check('fresh metric reports fresh', $fresh->status(1000, 300) === 'fresh');
check('fresh metric yields value', $fresh->currentValue(1000, 300) === '82%');
check('boundary age still fresh', $fresh->status(1200, 300) === 'fresh');

$stale = new MetricSample('disk-usage', '82%', 500);
check('old sample is stale', $stale->status(1000, 300) === 'stale');
check('stale sample hides the number', $stale->currentValue(1000, 300) === null);

$absent = new MetricSample('uptime', null, null);
check('missing sample is absent', $absent->status(1000, 300) === 'absent');
check('absent sample hides the number', $absent->currentValue(1000, 300) === null);
throws('value without timestamp rejected', fn () => new MetricSample('uptime', '99%', null));
throws('empty kind rejected', fn () => new MetricSample(' ', 'v', 1));
throws('maxAge below 1 rejected', fn () => $fresh->status(1000, 0));

// ── Summary ───────────────────────────────────────────────────────

if ($failures) {
    fwrite(STDERR, "FAIL: " . count($failures) . " of {$checks} checks\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
echo "PASS: {$checks} notification-model checks on PHP " . PHP_VERSION . "\n";
echo "Scope: isolated model only; no persistence, delivery transport or UI tested.\n";
