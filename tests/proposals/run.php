<?php
declare(strict_types=1);

/**
 * Synthetic P-02 versioned-approval checks. Fixture proposals only —
 * no persistence, mail, signatures or application integration.
 */

use CajunWebPro\Foundation\Proposals\ApprovalDecision;
use CajunWebPro\Foundation\Proposals\Proposal;
use CajunWebPro\Foundation\Proposals\ProposalVersion;

require __DIR__ . '/../../foundation/proposals/ProposalVersion.php';
require __DIR__ . '/../../foundation/proposals/ApprovalDecision.php';
require __DIR__ . '/../../foundation/proposals/Proposal.php';

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

function rejects(string $name, callable $action, string $class = LogicException::class): void
{
    global $checks, $failures;
    $checks++;
    try {
        $action();
        $failures[] = $name . ': invalid action was accepted';
    } catch (Throwable $e) {
        if (!($e instanceof $class)) {
            throw $e;
        }
    }
}

function proposal(): Proposal
{
    return new Proposal('prop-1', 'client-4', 'Website redesign', 'staff-9', '2026-10-13T10:00:00Z');
}

function version(int $n = 1, array $overrides = []): ProposalVersion
{
    return ProposalVersion::create(...array_replace([
        'id' => 'ver-' . $n,
        'number' => $n,
        'deliverables' => ['Fixture homepage design', 'Fixture contact form'],
        'exclusions' => ['Hosting setup'],
        'scheduleNotes' => 'Fixture: two review rounds',
        'milestones' => [['label' => 'Deposit', 'amountCents' => 50000], ['label' => 'Balance', 'amountCents' => 50000]],
        'authorId' => 'staff-9',
        'createdAt' => '2026-10-13T10:00:0' . $n . 'Z',
    ], $overrides));
}

// --- ProposalVersion ------------------------------------------------------

check('version computes content hash', preg_match('/^[0-9a-f]{64}$/', version()->contentHash) === 1);
check('hash covers content', version()->contentHash !== version(1, ['deliverables' => ['Different scope']])->contentHash);
check('totalCents sums milestones', version()->totalCents() === 100000);
rejects('empty deliverables rejected', fn () => version(1, ['deliverables' => []]), InvalidArgumentException::class);
rejects('non-positive number rejected', fn () => version(0), InvalidArgumentException::class);
rejects('negative milestone rejected', fn () => version(1, ['milestones' => [['label' => 'x', 'amountCents' => -1]]]), InvalidArgumentException::class);
rejects('blank milestone label rejected', fn () => version(1, ['milestones' => [['label' => ' ', 'amountCents' => 5]]]), InvalidArgumentException::class);
rejects('bad hash rejected', fn () => new ProposalVersion('v', 1, ['x'], [], '', [], 'staff-9', 'now', 'nothex'), InvalidArgumentException::class);

// --- ApprovalDecision -----------------------------------------------------

check('approve decision constructs', (new ApprovalDecision('d1', 'ver-1', 'client-4', 'approve', '', 'now'))->kind === 'approve');
rejects('change request needs a note', fn () => new ApprovalDecision('d2', 'ver-1', 'client-4', 'request-changes', '', 'now'), InvalidArgumentException::class);
rejects('unknown kind rejected', fn () => new ApprovalDecision('d3', 'ver-1', 'client-4', 'maybe', '', 'now'), InvalidArgumentException::class);

// --- Proposal lifecycle ---------------------------------------------------

$p = proposal();
check('proposal starts draft', $p->state() === Proposal::DRAFT);
rejects('send without a version rejected', fn () => $p->send('staff-9', 't'));
rejects('decide while drafting rejected', fn () => $p->decide(new ApprovalDecision('d', 'ver-1', 'client-4', 'approve', '', 't'), 't'));

$p->addVersion(version(), 'staff-9', 't1');
check('version recorded', $p->current()->id === 'ver-1');
rejects('non-sequential version rejected', fn () => $p->addVersion(version(3), 'staff-9', 't2'));

$p->send('staff-9', 't3');
check('sent state', $p->state() === Proposal::SENT);
rejects('add version while sent rejected', fn () => $p->addVersion(version(2), 'staff-9', 't4'));
rejects('double-send rejected', fn () => $p->send('staff-9', 't5'));

// Stale-version decisions rejected — the pinned-version guarantee
rejects('stale version decision rejected', fn () => $p->decide(new ApprovalDecision('d', 'ver-OLD', 'client-4', 'approve', '', 't6'), 't6'));

// Changes requested → revise → new version → resend
$p->decide(new ApprovalDecision('d7', 'ver-1', 'client-4', 'request-changes', 'Fixture: tighten scope', 't7'), 't7');
check('changes-requested state', $p->state() === Proposal::CHANGES_REQUESTED);
check('v1 now superseded', $p->supersededVersions() === [] && $p->current()->id === 'ver-1');
rejects('resend without revision rejected', fn () => $p->send('staff-9', 't8'));

$p->revise(version(2), 'staff-9', 't9');
check('revise returns to draft', $p->state() === Proposal::DRAFT);
check('v1 superseded after revision', count($p->supersededVersions()) === 1 && $p->supersededVersions()[0]->id === 'ver-1');
check('current is v2', $p->current()->id === 'ver-2');

$p->send('staff-9', 't10');
rejects('approving superseded v1 rejected', fn () => $p->decide(new ApprovalDecision('d11', 'ver-1', 'client-4', 'approve', '', 't11'), 't11'));

$p->decide(new ApprovalDecision('d12', 'ver-2', 'client-4', 'approve', '', 't12'), 't12');
check('approved is terminal', $p->state() === Proposal::APPROVED);
rejects('withdraw after approval rejected', fn () => $p->withdraw('staff-9', 't13'));
rejects('decide after approval rejected', fn () => $p->decide(new ApprovalDecision('d14', 'ver-2', 'client-4', 'approve', '', 't14'), 't14'));
rejects('revise after approval rejected', fn () => $p->revise(version(3), 'staff-9', 't15'));

// Withdrawal from review is allowed; it is terminal
$w = proposal();
$w->addVersion(version(), 'staff-9', 't1')->send('staff-9', 't2');
$w->withdraw('staff-9', 't3');
check('withdrawn state', $w->state() === Proposal::WITHDRAWN);
rejects('send after withdrawal rejected', fn () => $w->send('staff-9', 't4'));

// History is actor-attributed and ordered
$hist = $p->history();
check('history records lifecycle', count($hist) >= 5 && $hist[0]['event'] === 'created' && end($hist)['event'] === 'approved-v2');
check('decision retained', $p->decisions()[0]->id === 'd7' && count($p->decisions()) === 2);

// --- Result ---------------------------------------------------------------

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "PASS: $checks proposal checks on PHP " . PHP_VERSION . PHP_EOL;
echo "Scope: isolated version/approval state machine only; no persistence, mail, signature or application integration tested." . PHP_EOL;
