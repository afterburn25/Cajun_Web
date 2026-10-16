<?php
declare(strict_types=1);

/**
 * Synthetic A-01 lead-pipeline checks. Fixture boards/leads only —
 * no persistence, drag UI or application integration.
 */

use CajunWebPro\Foundation\Pipeline\LeadRecord;
use CajunWebPro\Foundation\Pipeline\PipelineBoard;
use CajunWebPro\Foundation\Pipeline\PipelineStage;

require __DIR__ . '/../../foundation/pipeline/PipelineStage.php';
require __DIR__ . '/../../foundation/pipeline/PipelineBoard.php';
require __DIR__ . '/../../foundation/pipeline/LeadRecord.php';

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

function rejects(string $name, callable $action): void
{
    global $checks, $failures;
    $checks++;
    try {
        $action();
        $failures[] = $name . ': invalid action was accepted';
    } catch (InvalidArgumentException|LogicException) {
        // Expected; other exceptions must fail the runner.
    }
}

function board(): PipelineBoard
{
    return new PipelineBoard([
        new PipelineStage('new', 'New', 0, ['consultation', 'not-proceeding']),
        new PipelineStage('consultation', 'Consultation', 1, ['proposal', 'not-proceeding']),
        new PipelineStage('proposal', 'Proposal Sent', 2, ['won', 'not-proceeding']),
        new PipelineStage('won', 'Won', 3, [], isWon: true),
        new PipelineStage('not-proceeding', 'Not Proceeding', 4, ['new'], isLost: true),
    ], 'new');
}

// --- Stage/board validation ----------------------------------------------

check('board constructs', board()->initial()->id === 'new');
rejects('duplicate stage rejected', fn () => new PipelineBoard([
    new PipelineStage('a', 'A', 0, []),
    new PipelineStage('a', 'Again', 1, []),
], 'a'));
rejects('dangling next reference rejected', fn () => new PipelineBoard([
    new PipelineStage('a', 'A', 0, ['missing']),
], 'a'));
rejects('missing initial stage rejected', fn () => new PipelineBoard([
    new PipelineStage('a', 'A', 0, []),
], 'nope'));
rejects('empty board rejected', fn () => new PipelineBoard([], 'a'));
rejects('won and lost together rejected', fn () => new PipelineStage('x', 'X', 0, [], true, true));

check('allowed transitions computed', board()->canTransition('new', 'consultation'));
check('unlisted transition denied', !board()->canTransition('new', 'won'));
check('terminal stage has no moves', board()->allowedMoves('won') === []);
check('re-open path allowed', board()->canTransition('not-proceeding', 'new'));
check('stages ordered by position', board()->stages()[0]->id === 'new' && board()->stages()[3]->id === 'won');
check('allowedMoves returns stages', board()->allowedMoves('new')[0]->id === 'consultation');
check('terminal() reflects next', board()->stage('won')->terminal() && !board()->stage('new')->terminal());

// --- LeadRecord -----------------------------------------------------------

$lead = new LeadRecord('lead-1', board(), 'Fixture Client', 'staff-9', 't0');
check('lead starts at initial stage', $lead->stageId() === 'new');
check('stage object resolved', $lead->stage()->label === 'New');

$lead->moveTo('consultation', 'staff-9', 't1');
check('valid move applied', $lead->stageId() === 'consultation');
rejects('invalid move rejected', fn () => $lead->moveTo('won', 'staff-9', 't2'));
check('stage unchanged after rejected move', $lead->stageId() === 'consultation');

$lead->moveTo('proposal', 'staff-8', 't3');
$lead->moveTo('not-proceeding', 'staff-9', 't4', 'budget fixture reason');
check('not-proceeding recorded', $lead->stageId() === 'not-proceeding');
$hist = $lead->history();
check('loss event named', end($hist)['event'] === 'not-proceeding — budget fixture reason');
check('actor attributed', end($hist)['actor'] === 'staff-9');

// Re-opening a lost lead is a configured transition
$lead->moveTo('new', 'owner-1', 't5');
check('lost lead re-opened', $lead->stageId() === 'new');

// Won is terminal
$lead->moveTo('consultation', 'staff-9', 't6');
$lead->moveTo('proposal', 'staff-9', 't7');
$lead->moveTo('won', 'staff-9', 't8');
check('won reached', $lead->stageId() === 'won' && $lead->stage()->isWon);
rejects('move out of won rejected', fn () => $lead->moveTo('new', 'staff-9', 't9'));

// Links and follow-ups
$lead->link('brief:42', 'staff-9', 't10')->link('project:7', 'staff-9', 't11');
check('links recorded', $lead->links() === ['brief:42', 'project:7']);
$lead->link('brief:42', 'staff-9', 't12');
check('duplicate link deduped', count($lead->links()) === 2);
rejects('malformed link rejected', fn () => $lead->link('no-colon-here', 'staff-9', 't13'));

$lead->setFollowUp('2026-10-20', 'staff-9', 't14');
check('follow-up set', $lead->followUpAt() === '2026-10-20');
rejects('bad follow-up date rejected', fn () => $lead->setFollowUp('next weekish', 'staff-9', 't15'), InvalidArgumentException::class);
$lead->setNote('Fixture note', 'staff-8', 't16');
check('note stored', $lead->note() === 'Fixture note');

check('history is append-only timeline', count($lead->history()) >= 10 && $lead->history()[0]['event'] === 'created');

// --- Result ---------------------------------------------------------------

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "PASS: $checks pipeline checks on PHP " . PHP_VERSION . PHP_EOL;
echo "Scope: isolated board/lead model only; no persistence, permissions, UI or application integration tested." . PHP_EOL;
