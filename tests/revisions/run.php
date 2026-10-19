<?php
declare(strict_types=1);

/**
 * Synthetic A-02 revision-history checks. Fixture documents only —
 * no persistence, UI or application integration.
 */

use CajunWebPro\Foundation\Revisions\ApprovalContext;
use CajunWebPro\Foundation\Revisions\ContentDocument;
use CajunWebPro\Foundation\Revisions\ContentRevision;
use CajunWebPro\Foundation\Revisions\RevisionDiff;

require __DIR__ . '/../../foundation/revisions/ContentRevision.php';
require __DIR__ . '/../../foundation/revisions/ApprovalContext.php';
require __DIR__ . '/../../foundation/revisions/RevisionDiff.php';
require __DIR__ . '/../../foundation/revisions/ContentDocument.php';

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

function doc(): ContentDocument
{
    return new ContentDocument('page:fixture-about');
}

// --- ContentRevision validation ------------------------------------------

check('revision computes hash', (new ContentRevision('r1', 's', 1, 'body', 'a1', 't'))->contentHash() === hash('sha256', 'body'));
rejects('empty subject rejected', fn () => new ContentRevision('r', '', 1, 'c', 'a', 't'), InvalidArgumentException::class);
rejects('zero number rejected', fn () => new ContentRevision('r', 's', 0, 'c', 'a', 't'), InvalidArgumentException::class);
rejects('empty author rejected', fn () => new ContentRevision('r', 's', 1, 'c', '', 't'), InvalidArgumentException::class);

// --- Save / publish -------------------------------------------------------

$d = doc();
check('nothing published initially', $d->published() === null && $d->draft() === null);
rejects('publish without draft rejected', fn () => $d->publish('staff-9', 't0'));
rejects('approve without publication rejected', fn () => $d->approve(new ApprovalContext('ap0', 'r1', 'owner-1', 't0')));

$r1 = $d->save('r1', "line one\nline two", 'staff-9', 't1', 'first draft');
check('save creates draft v1', $d->draft()->id === 'r1' && $r1->number === 1 && $d->published() === null);

$d->publish('staff-9', 't2');
check('publish promotes draft', $d->published()->id === 'r1' && $d->draft() === null);

rejects('no-op save against published rejected', fn () => $d->save('r2', "line one\nline two", 'staff-9', 't3'));

$r2 = $d->save('r2', "line one\nline two\nline three", 'staff-8', 't4');
check('second save is v2 draft', $r2->number === 2 && $d->draft()->id === 'r2' && $d->published()->id === 'r1');

rejects('no-op save against draft rejected', fn () => $d->save('r3', "line one\nline two\nline three", 'staff-8', 't5'));

// A newer draft makes the restore check protect it
rejects('restore over pending draft rejected', fn () => $d->restoreAsDraft('r3', 'r1', 'staff-9', 't5'));

$d->discardDraft('staff-8', 't6');
check('draft discarded explicitly', $d->draft() === null);
rejects('discard without draft rejected', fn () => $d->discardDraft('staff-8', 't7'));

// --- Restore-as-draft -----------------------------------------------------

$r3 = $d->restoreAsDraft('r3', 'r1', 'staff-9', 't8');
check('restore creates new draft revision', $r3->number === 3 && $r3->restoredFrom === 'r1' && $d->draft()->id === 'r3');
check('published untouched by restore', $d->published()->id === 'r1');
check('restore note defaults', $r3->note === 'Restored from r1');

$d->publish('staff-9', 't9');
check('restored draft publishes', $d->published()->id === 'r3' && $d->published()->content === "line one\nline two");

rejects('restore unknown revision rejected', fn () => $d->restoreAsDraft('rx', 'rX', 'staff-9', 't10'));

// --- Compare --------------------------------------------------------------

$diff = $d->compare('r1', 'r2');
check('diff reports added line', $diff->added === ['line three'] && $diff->removed === []);
check('diff counts changes', $diff->changedLineCount() === 1 && !$diff->identical());
$empty = $d->compare('r1', 'r3');
check('identical contents diff clean', $empty->identical() && $empty->changedLineCount() === 0);
rejects('compare unknown revision rejected', fn () => $d->compare('rX', 'r1'));

// --- Approval context -----------------------------------------------------

$d->approve(new ApprovalContext('ap1', 'r3', 'owner-1', 't11', 'Approved for publish'));
check('approval pinned to published', count($d->approvals()) === 1 && $d->approvals()[0]->revisionId === 'r3');
rejects('approval of non-published revision rejected', fn () => $d->approve(new ApprovalContext('ap2', 'r1', 'owner-1', 't12')));

// Approval survives subsequent drafts — context retained separately
$d->save('r4', "line one\nline two\nline four", 'staff-9', 't13');
check('approval retained across later draft', count($d->approvals()) === 1 && $d->draft()->id === 'r4');

// --- History attribution --------------------------------------------------

$hist = $d->history();
$events = array_column($hist, 'event');
check('history ordered and attributed', in_array('saved-v1', $events, true) && in_array('published-r1', $events, true) && in_array('restored-r1-as-v3', $events, true) && in_array('approved-r3', $events, true));
check('every event has actor', count(array_filter($hist, fn ($h) => trim($h['actor']) !== '')) === count($hist));
check('revisions append-only', count($d->revisions()) === 4 && $d->revisions()[0]->id === 'r1');

// --- Result ---------------------------------------------------------------

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "PASS: $checks revision-history checks on PHP " . PHP_VERSION . PHP_EOL;
echo "Scope: isolated revision model only; no persistence, permissions, UI or application integration tested." . PHP_EOL;
