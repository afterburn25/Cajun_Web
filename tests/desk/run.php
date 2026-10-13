<?php
declare(strict_types=1);

/**
 * Synthetic HC-12 service-desk contract checks. Fixture jobs, approvals
 * and tickets only — no queues, persistence, mail or UI.
 */

use CajunWebPro\Foundation\Desk\ApprovalRequest;
use CajunWebPro\Foundation\Desk\FulfillmentJob;
use CajunWebPro\Foundation\Desk\SupportTicket;
use CajunWebPro\Foundation\Desk\TicketMessage;
use CajunWebPro\Foundation\Desk\TraceLink;

require __DIR__ . '/../../foundation/desk/TraceLink.php';
require __DIR__ . '/../../foundation/desk/FulfillmentJob.php';
require __DIR__ . '/../../foundation/desk/ApprovalRequest.php';
require __DIR__ . '/../../foundation/desk/TicketMessage.php';
require __DIR__ . '/../../foundation/desk/SupportTicket.php';

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
    } catch (InvalidArgumentException|LogicException) {
    }
}

function job(string $kind = 'provision', int $max = 3): FulfillmentJob
{
    return new FulfillmentJob($kind . '-1', $kind, 'idem-' . $kind . '-1', [new TraceLink('order', 'ord-9')], $max, 60);
}

// ── TraceLink ────────────────────────────────────────────────────

$l = new TraceLink('order', 'ord-9');
check('link key format', $l->key() === 'order:ord-9');
check('link round-trips', TraceLink::parse('order:ord-9')->kind === 'order' && TraceLink::parse('order:ord-9')->id === 'ord-9');
throws('unknown kind rejected', fn () => new TraceLink('ftp_credential', 'x'));
throws('empty id rejected', fn () => new TraceLink('order', ' '));
throws('malformed parse rejected', fn () => TraceLink::parse('nocolon'));

// ── FulfillmentJob ───────────────────────────────────────────────

$j = job();
check('new job queued', $j->status() === 'queued' && $j->attempts() === 0);
check('reversible job runs without approval', (function () use ($j) { $j->run('staff-amy', 10, 60); return $j->status() === 'running'; })());
$j->succeed('staff-amy', 20);
check('succeeded is terminal-ish', $j->status() === 'succeeded');
throws('succeeded job cannot rerun', fn () => $j->run('staff-amy', 30, 60));

$j2 = job();
$j2->run('s', 0, 60);
$j2->fail('provider 500', 's', 10);
check('failed retries to queued', $j2->status() === 'queued' && $j2->attempts() === 1);
check('backoff recorded', $j2->nextAttemptAt() === 70);
throws('retry refused during backoff', fn () => $j2->run('s', 50, 60));
$j2->run('s', 70, 60); // due now
$j2->fail('provider 500 again', 's', 80);
check('second backoff grows', $j2->nextAttemptAt() === 200);
$j2->run('s', 200, 60);
$j2->fail('third failure', 's', 210);
check('exhausted job dead-letters', $j2->status() === 'dead_letter');
check('last error kept', $j2->lastError() === 'third failure');
throws('dead-letter cannot rerun', fn () => $j2->run('s', 300, 60));

$j3 = job('provision', 2);
$j3->run('s', 0, 30);
check('running lease held', !$j3->expireLease(20));
check('lease expiry stalls', $j3->expireLease(40) && $j3->status() === 'stalled');
$j3->requeue('staff-amy', 50);
check('stalled requeues', $j3->status() === 'queued');
throws('non-stalled requeue refused', fn () => job()->requeue('s', 0));

$j4 = job('deprovision');
throws('irreversible needs approval', fn () => $j4->run('s', 0, 60));
$j4->run('s', 0, 60, 'apr-1');
check('approved irreversible runs', $j4->status() === 'running');

throws('job needs trace links', fn () => new FulfillmentJob('x', 'provision', 'k', [], 3, 60));
throws('empty idempotency key rejected', fn () => new FulfillmentJob('x', 'provision', ' ', [new TraceLink('order', 'o')], 3, 60));
throws('fail needs error', fn () => (function () { $j = job(); $j->run('s', 0, 60); $j->fail('', 's', 1); })());

// ── ApprovalRequest ──────────────────────────────────────────────

$subj = new TraceLink('order', 'ord-9');
$ap = new ApprovalRequest('apr-1', 'refund', $subj, 'staff-amy', 0, 3600);
check('request starts requested', $ap->status(100) === 'requested');
check('expiry surfaces honestly', $ap->status(3600) === 'expired');
throws('expired cannot approve', fn () => $ap->approve('staff-ben', 'owner', 3601));

$ap2 = new ApprovalRequest('apr-2', 'refund', $subj, 'staff-amy', 0, 3600);
throws('requester cannot self-approve', fn () => $ap2->approve('staff-amy', 'owner', 10));
throws('wrong role cannot approve', fn () => $ap2->approve('staff-ben', 'administrator', 10));
$ap2->approve('staff-ben', 'owner', 10);
check('different-role owner approves', $ap2->approved(20) && $ap2->decidedBy() === 'staff-ben');
throws('decided request cannot re-decide', fn () => $ap2->deny('staff-cam', 'owner', 20, 'x'));

$ap3 = new ApprovalRequest('apr-3', 'deprovision', $subj, 'staff-amy', 0, 3600);
throws('denial needs reason', fn () => $ap3->deny('staff-ben', 'owner', 10, ''));
$ap3->deny('staff-ben', 'owner', 10, 'client changed mind');
check('denied with reason', $ap3->status(20) === 'denied' && $ap3->reason() === 'client changed mind');

// ── TicketMessage + SupportTicket ────────────────────────────────

$t = new SupportTicket('tk-1', 'client-42', 'Site is down', [new TraceLink('hosting_service', 'svc-1')]);
check('ticket opens open', $t->status() === 'open' && $t->assigneeRef() === null);
$t->assign('staff-amy', 'staff-amy', 10);
check('assignment recorded', $t->assigneeRef() === 'staff-amy');

$clientMsg = new TicketMessage('m-1', 'tk-1', 'client', 'client-42', 'My site returns 500', 100);
$note = new TicketMessage('m-2', 'tk-1', 'staff', 'staff-amy', 'Internal: suspect quota breach', 110, true);
$reply = new TicketMessage('m-3', 'tk-1', 'staff', 'staff-amy', 'We are looking into it', 120);
$t->post($clientMsg, 'client-42', 100);
$t->post($note, 'staff-amy', 110);
$t->post($reply, 'staff-amy', 120);
check('client sees only public messages', count($t->clientMessages()) === 2);
check('staff sees all', count($t->allMessages()) === 3);
check('internal flag blocks client view', !$note->clientVisible());
check('unread excludes own + internal for client', $t->unreadFor('client-42', 0) === 1);
check('staff unread includes internal notes', $t->unreadFor('staff-ben', 0, true) === 3);
throws('non-staff internal note rejected', fn () => new TicketMessage('m', 'tk-1', 'client', 'c', 'x', 0, true));
throws('empty body rejected', fn () => new TicketMessage('m', 'tk-1', 'staff', 's', ' ', 0));
throws('foreign-ticket message rejected', fn () => $t->post(new TicketMessage('m-9', 'tk-2', 'staff', 's', 'x', 0), 's', 0));

$t->transition('pending', 'staff-amy', 130);
check('pending transition', $t->status() === 'pending');
$t->transition('resolved', 'staff-amy', 200);
check('resolved', $t->status() === 'resolved');
$t->transition('open', 'client-42', 210); // reopen — the fix didn't take
check('reopen honestly recorded', $t->status() === 'open');
$t->transition('closed', 'staff-amy', 300);
check('closed terminal', $t->status() === 'closed');
throws('closed ticket cannot transition', fn () => $t->transition('open', 's', 310));
throws('closed ticket cannot post', fn () => $t->post(new TicketMessage('m-8', 'tk-1', 'staff', 's', 'x', 310), 's', 310));
throws('resolved→pending invalid', fn () => (function () { $x = new SupportTicket('tk-8', 'c', 's'); $x->transition('resolved', 'a', 1); $x->transition('pending', 'a', 2); })());
check('history is attributed', $t->history()[1]['note'] === 'assigned to staff-amy');

// ── Summary ──────────────────────────────────────────────────────

if ($failures) {
    fwrite(STDERR, "FAIL: " . count($failures) . " of {$checks} checks\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
echo "PASS: {$checks} service-desk contract checks on PHP " . PHP_VERSION . "\n";
echo "Scope: isolated model only; no queues, persistence, mail or UI tested.\n";
