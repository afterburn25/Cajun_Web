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

require_once __DIR__ . '/../../foundation/desk/TraceLink.php';
require_once __DIR__ . '/../../foundation/desk/FulfillmentJob.php';
require_once __DIR__ . '/../../foundation/desk/ApprovalRequest.php';
require_once __DIR__ . '/../../foundation/desk/TicketMessage.php';
require_once __DIR__ . '/../../foundation/desk/SupportTicket.php';

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
check('run returns the attempt token', $j->attemptToken() === 'provision-1#1');
$j->succeed('staff-amy', 20, 'provision-1#1');
check('succeeded is terminal-ish', $j->status() === 'succeeded');
throws('succeeded job cannot rerun', fn () => $j->run('staff-amy', 30, 60));

$j2 = job();
$tok = $j2->run('s', 0, 60);
$j2->fail('provider 500', 's', 10, $tok);
check('failed retries to queued', $j2->status() === 'queued' && $j2->attempts() === 1);
check('backoff recorded', $j2->nextAttemptAt() === 70);
throws('retry refused during backoff', fn () => $j2->run('s', 50, 60));
$tok = $j2->run('s', 70, 60); // due now
check('second claim mints a new token', $tok === 'provision-1#2');
$j2->fail('provider 500 again', 's', 80, $tok);
check('second backoff grows', $j2->nextAttemptAt() === 200);
$tok = $j2->run('s', 200, 60);
$j2->fail('third failure', 's', 210, $tok);
check('exhausted job dead-letters', $j2->status() === 'dead_letter');
check('last error kept', $j2->lastError() === 'third failure');
throws('dead-letter cannot rerun', fn () => $j2->run('s', 300, 60));

$j3 = job('provision', 2);
$j3->run('s', 0, 30);
check('running lease held', !$j3->expireLease(20));
check('lease expiry stalls', $j3->expireLease(40) && $j3->status() === 'stalled');
$j3->resolveStalled('provision-1#1', 'not_executed', 'provider shows no request', 'staff-amy', 45);
$j3->requeue('staff-amy', 50);
check('stalled requeues', $j3->status() === 'queued');
check('requeue clears stale backoff', $j3->nextAttemptAt() === null);
throws('non-stalled requeue refused', fn () => job()->requeue('s', 0));

$j4 = job('deprovision');
throws('irreversible needs approval', fn () => $j4->run('s', 0, 60));
$ord9 = new TraceLink('order', 'ord-9');
$pend = new ApprovalRequest('apr-p', 'deprovision', $ord9, 'staff-amy', 0, 3600);
throws('unapproved request cannot release job', fn () => $j4->run('s', 0, 60, $pend));
$pend->approve('staff-ben', 'owner', 5);
$wrong = new ApprovalRequest('apr-w', 'refund', $ord9, 'staff-amy', 0, 3600);
$wrong->approve('staff-ben', 'owner', 5);
throws('wrong-kind approval refused', fn () => $j4->run('s', 0, 60, $wrong));
$other = new ApprovalRequest('apr-o', 'deprovision', new TraceLink('order', 'ord-1'), 'staff-amy', 0, 3600);
$other->approve('staff-ben', 'owner', 5);
throws('untraced subject refused', fn () => $j4->run('s', 0, 60, $other));
$j4->run('s', 10, 60, $pend);
check('approved matching request runs', $j4->status() === 'running');

throws('job needs trace links', fn () => new FulfillmentJob('x', 'provision', 'k', [], 3, 60));
throws('empty idempotency key rejected', fn () => new FulfillmentJob('x', 'provision', ' ', [new TraceLink('order', 'o')], 3, 60));
throws('fail needs error', fn () => (function () { $j = job(); $tok = $j->run('s', 0, 60); $j->fail('', 's', 1, $tok); })());
throws('completion needs exact attempt token', fn () => (function () { $j = job(); $j->run('s', 0, 60); $j->succeed('s', 1, 'provision-1#99'); })());

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

// Approved decisions expire like live requests — a stale approval cannot
// release a later destructive run.
$ap4 = new ApprovalRequest('apr-4', 'deprovision', $subj, 'staff-amy', 0, 200);
$ap4->approve('staff-ben', 'owner', 110);
check('approval valid after decision', $ap4->approved(110) && $ap4->approved(199));
check('approval expires at boundary', $ap4->status(200) === 'expired' && !$ap4->approved(200));
check('no authority before decision instant', !$ap4->approved(109));
$jx = job('deprovision');
throws('expired approval cannot run job', fn () => $jx->run('s', 300, 60, $ap4));

$ap5 = new ApprovalRequest('apr-5', 'refund', $subj, 'staff-amy', 100, 3600);
throws('cannot decide before request exists', fn () => $ap5->approve('staff-ben', 'owner', 50));

// A refused denial must not mutate a retained decision: state and reason
// are identical before and after the rejected call.
$ap6 = new ApprovalRequest('apr-6', 'refund', $subj, 'staff-amy', 0, 3600);
$ap6->deny('staff-ben', 'owner', 10, 'original reason');
throws('second denial refused', fn () => $ap6->deny('staff-ben', 'owner', 20, 'injected reason'));
check('refused denial keeps reason', $ap6->reason() === 'original reason' && $ap6->decidedBy() === 'staff-ben');
$ap7 = new ApprovalRequest('apr-7', 'refund', $subj, 'staff-amy', 0, 3600);
$ap7->approve('staff-ben', 'owner', 10);
throws('denial on approved refused', fn () => $ap7->deny('staff-ben', 'owner', 20, 'injected'));
check('approved record keeps null reason', $ap7->reason() === null && $ap7->approved(30));

// Stale worker results cannot settle a replacement attempt.
$j5 = job('provision');
$tokA = $j5->run('workerA', 100, 10);
check('first claim token minted', $tokA === 'provision-1#1' && $j5->attemptToken() === $tokA);
check('lease expiry stalls first worker', $j5->expireLease(110));
check('expiry releases the old token', $j5->attemptToken() === null);
$j5->resolveStalled($tokA, 'not_executed', 'provider shows no request', 'staff-amy', 111);
$j5->requeue('staff-amy', 111);
$tokB = $j5->run('workerB', 112, 60);
check('replacement claim mints new token', $tokB === 'provision-1#2' && $j5->attemptToken() === $tokB);
throws('old worker success refused', fn () => $j5->succeed('workerA', 113, $tokA));
throws('old worker failure refused', fn () => $j5->fail('gone', 'workerA', 113, $tokA));
check('attempt two still runs under new worker', $j5->status() === 'running' && $j5->attempts() === 2);
$j5->succeed('workerB', 120, $tokB);
check('new worker settles its own attempt', $j5->status() === 'succeeded');

// One worker holding sequential claims cannot reuse an earlier token.
$j6 = job('provision');
$tok1 = $j6->run('workerA', 0, 10);
$j6->expireLease(15);
$j6->resolveStalled($tok1, 'not_executed', 'provider shows no request', 'staff-amy', 15);
$j6->requeue('staff-amy', 16);
$tok2 = $j6->run('workerA', 17, 60);
throws('same worker cannot reuse old token', fn () => $j6->succeed('workerA', 20, $tok1));
$j6->succeed('workerA', 25, $tok2);
check('same worker settles with current token', $j6->status() === 'succeeded');

// A stalled attempt erases certainty about the external operation, so
// requeue is fenced behind an explicit attempt-bound verified
// resolution — an operator click and the idempotency key are not
// evidence about what the provider did.
$jr = job('provision');
$tokR = $jr->run('workerA', 100, 10);
$jr->expireLease(110);
check('stalled attempt token retained', $jr->stalledToken() === $tokR);
throws('requeue refused without resolution', fn () => $jr->requeue('staff-amy', 111));
throws('replacement claim refused while stalled', fn () => $jr->run('workerB', 112, 60));
throws('resolution needs the stalled token', fn () => $jr->resolveStalled('provision-1#99', 'not_executed', 'e', 'staff-amy', 111));
throws('resolution needs evidence', fn () => $jr->resolveStalled($tokR, 'not_executed', ' ', 'staff-amy', 111));
throws('resolution needs known outcome', fn () => $jr->resolveStalled($tokR, 'maybe', 'e', 'staff-amy', 111));
check('still stalled after refused evidence', $jr->status() === 'stalled' && $jr->resolution($tokR) === null);
$jr->resolveStalled($tokR, 'unknown', 'provider unreachable', 'staff-amy', 112);
check('unknown observation recorded', $jr->observations($tokR) === [['evidence' => 'provider unreachable', 'actor' => 'staff-amy', 'at' => 112]]);
throws('unknown keeps retry blocked', fn () => $jr->requeue('staff-amy', 113));
check('unknown stays stalled', $jr->status() === 'stalled');
$jr->resolveStalled($tokR, 'unknown', 'provider unreachable', 'staff-amy', 114);
check('identical unknown replays without duplicating', count($jr->observations($tokR)) === 1);
$jr->resolveStalled($tokR, 'unknown', 'second probe still unclear', 'staff-ben', 115);
check('distinct unknown observations retained', count($jr->observations($tokR)) === 2);
check('no conclusive resolution yet', $jr->resolution($tokR) === null);
// A later conclusive verdict for the same stalled token completes the
// stall; the unknown evidence stays recorded and never released retry.
$jr->resolveStalled($tokR, 'completed', 'provider log confirms request pr-9', 'staff-amy', 120);
check('unknown can conclude completed', $jr->status() === 'succeeded' && $jr->resolution($tokR)['outcome'] === 'completed');
check('unknown observations retained after completion', count($jr->observations($tokR)) === 2);
$histBefore = $jr->history();
$jr->resolveStalled($tokR, 'completed', 'provider log confirms request pr-9', 'staff-amy', 125);
check('completed verdict replays after success', $jr->status() === 'succeeded' && $jr->history() === $histBefore);
throws('conflicting conclusive refused after completion', fn () => $jr->resolveStalled($tokR, 'not_executed', 'different evidence', 'staff-amy', 126));

// Verified not_executed releases a separately fenced retry — the only
// path back to queued.
$jr2 = job('provision');
$tokR2 = $jr2->run('workerA', 100, 10);
$jr2->expireLease(110);
$jr2->resolveStalled($tokR2, 'not_executed', 'provider log: no such request', 'staff-amy', 115);
check('not_executed recorded with metadata', $jr2->resolution($tokR2) === ['outcome' => 'not_executed', 'evidence' => 'provider log: no such request', 'actor' => 'staff-amy', 'at' => 115]);
check('not_executed resolution stays stalled', $jr2->status() === 'stalled');
$jr2->requeue('staff-amy', 120);
check('verified retry releases to queued', $jr2->status() === 'queued');
$tokR3 = $jr2->run('workerB', 121, 60);
check('fenced retry mints fresh token', $tokR3 === 'provision-1#2');
throws('old resolution does not settle new attempt', fn () => $jr2->succeed('workerA', 122, $tokR2));

// Verified completed is terminal — no resend of an already-run operation.
$jr3 = job('provision');
$tokR4 = $jr3->run('workerA', 100, 10);
$jr3->expireLease(110);
$jr3->resolveStalled($tokR4, 'completed', 'provider id pr-77 confirmed', 'staff-amy', 120);
check('completed resolution settles job', $jr3->status() === 'succeeded');
throws('completed job cannot requeue', fn () => $jr3->requeue('staff-amy', 121));
throws('completed job cannot rerun', fn () => $jr3->run('workerB', 122, 60));

// Unknown is recoverable the other way too: a later verified
// not_executed for the same stalled token releases the fenced retry.
$jrU = job('provision');
$tokU = $jrU->run('workerA', 100, 10);
$jrU->expireLease(110);
$jrU->resolveStalled($tokU, 'unknown', 'provider timeout', 'staff-amy', 112);
throws('still no requeue while unknown', fn () => $jrU->requeue('staff-amy', 113));
$jrU->resolveStalled($tokU, 'not_executed', 'provider confirms no such request', 'staff-ben', 120);
check('unknown can conclude not_executed', $jrU->resolution($tokU)['outcome'] === 'not_executed' && count($jrU->observations($tokU)) === 1);
$jrU->requeue('staff-amy', 125);
check('retry released after conclusive resolution', $jrU->status() === 'queued');

// The second stall needs its own evidence — the first attempt's
// resolution never releases a later stalled attempt.
$jr4 = job('provision');
$tokS1 = $jr4->run('workerA', 0, 10);
$jr4->expireLease(15);
$jr4->resolveStalled($tokS1, 'not_executed', 'no request seen', 'staff-amy', 16);
$jr4->requeue('staff-amy', 16);
$tokS2 = $jr4->run('workerB', 17, 10);
$jr4->expireLease(30);
throws('prior resolution does not release new stall', fn () => $jr4->requeue('staff-amy', 31));
throws('prior token cannot resolve new stall', fn () => $jr4->resolveStalled($tokS1, 'not_executed', 'e2', 'staff-amy', 31));
$jr4->resolveStalled($tokS2, 'not_executed', 'verified for attempt 2', 'staff-amy', 32);
$jr4->requeue('staff-amy', 33);
check('new evidence releases new stall', $jr4->status() === 'queued');
// Identical not_executed replay after requeue+reclaim is a no-op and
// does not touch the newer running attempt.
$tokS3 = $jr4->run('workerC', 40, 60);
check('third claim mints its own token', $tokS3 === 'provision-1#3');
$hist4 = $jr4->history();
$jr4->resolveStalled($tokS2, 'not_executed', 'verified for attempt 2', 'staff-amy', 45);
check('retained not_executed replays after reclaim', $jr4->status() === 'running' && $jr4->attemptToken() === $tokS3 && $jr4->history() === $hist4);
throws('changed-evidence replay on old token refused', fn () => $jr4->resolveStalled($tokS2, 'not_executed', 'different', 'staff-amy', 46));

// Approval expiry is rechecked at the replacement claim: a verified
// retry still cannot run under an approval that lapsed while stalled.
$subjR = new TraceLink('order', 'ord-9');
$apr = new ApprovalRequest('apr-r', 'refund', $subjR, 'staff-amy', 0, 130);
$apr->approve('staff-ben', 'owner', 100);
$jr5 = job('refund');
$jr5->run('workerA', 100, 10, $apr);
$jr5->expireLease(110);
$jr5->resolveStalled('refund-1#1', 'not_executed', 'ledger shows no refund', 'staff-amy', 120);
$jr5->requeue('staff-amy', 125);
throws('expired approval blocks verified retry', fn () => $jr5->run('workerB', 131, 60, $apr));
$apr2 = new ApprovalRequest('apr-r2', 'refund', $subjR, 'staff-amy', 0, 3600);
$apr2->approve('staff-ben', 'owner', 126);
$jr5->run('workerB', 132, 60, $apr2);
check('live approval runs verified retry', $jr5->status() === 'running' && $jr5->attempts() === 2);

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
