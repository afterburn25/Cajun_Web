<?php
declare(strict_types=1);

/**
 * Synthetic C-01 comparison-facts checks. All URLs/dates/providers are
 * conspicuously-marked fixtures — no web fetching, no real claims.
 */

use CajunWebPro\Foundation\Comparisons\ComparisonFact;
use CajunWebPro\Foundation\Comparisons\ComparisonRegistry;
use CajunWebPro\Foundation\Comparisons\ComparisonSection;

require __DIR__ . '/../../foundation/comparisons/ComparisonFact.php';
require __DIR__ . '/../../foundation/comparisons/ComparisonRegistry.php';
require __DIR__ . '/../../foundation/comparisons/ComparisonSection.php';

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
        $failures[] = $name . ': invalid input was accepted';
    } catch (InvalidArgumentException|LogicException) {
        // Expected; other exceptions must fail the runner.
    }
}

function fact(array $changes = []): ComparisonFact
{
    return new ComparisonFact(...array_replace([
        'id' => 'fact-1',
        'subjectType' => 'competitor',
        'provider' => 'fixture-builder-x',
        'aspect' => 'portability',
        'claim' => 'Fixture claim: content export requires manual steps.',
        'sourceUrl' => 'https://docs.fixture.invalid/export',
        'observedAt' => '2026-10-01',
        'planScope' => 'all plans',
        'evidenceKind' => 'provider-doc',
        'reviewBy' => '2027-01-01',
    ], $changes));
}

// --- Fact validation ------------------------------------------------------

check('valid fact constructs', fact()->provider === 'fixture-builder-x');
check('fresh fact not stale', !fact()->stale(strtotime('2026-10-15')));
check('expired fact stale', fact()->stale(strtotime('2027-01-02')));
rejects('competitor claim without https source rejected', fn () => fact(['sourceUrl' => 'http://insecure.invalid/x']));
rejects('missing claim rejected', fn () => fact(['claim' => ' ']));
rejects('unparsable observedAt rejected', fn () => fact(['observedAt' => 'someday']));
rejects('reviewBy before observedAt rejected', fn () => fact(['reviewBy' => '2026-09-01']));
rejects('unknown evidence kind rejected', fn () => fact(['evidenceKind' => 'vibes']));
rejects('competitor citing internal offer rejected', fn () => fact(['evidenceKind' => 'internal-offer']));
rejects('self claim citing provider doc rejected', fn () => fact(['subjectType' => 'self', 'provider' => 'cajunwebpro']));
rejects('guarantee phrasing rejected', fn () => fact(['claim' => 'We guarantee first-page rankings.']));
rejects('superiority phrasing rejected', fn () => fact(['claim' => 'Universally superior to all builders.']));
check('self-deliverable fact valid', fact([
    'id' => 'self-1', 'subjectType' => 'self', 'provider' => 'cajunwebpro',
    'claim' => 'Fixture: CajunWebPro includes a design consultation.',
    'evidenceKind' => 'internal-offer', 'sourceUrl' => 'https://cajunwebpro.invalid/services',
])->subjectType === 'self');

// --- Registry -------------------------------------------------------------

$reg = new ComparisonRegistry();
$reg->register(fact());
$reg->register(fact(['id' => 'fact-2', 'aspect' => 'pricing']));
$reg->register(fact(['id' => 'self-1', 'subjectType' => 'self', 'provider' => 'cajunwebpro', 'aspect' => 'deliverables', 'evidenceKind' => 'internal-offer', 'sourceUrl' => 'https://cajunwebpro.invalid/services']));
check('registry stores facts', count($reg->all()) === 3);
check('lookup by id', $reg->fact('fact-2')->aspect === 'pricing');
check('unknown id is null', $reg->fact('nope') === null);
check('forProvider filters', count($reg->forProvider('cajunwebpro')) === 1);
check('forAspect filters', count($reg->forAspect('portability')) === 1);
rejects('duplicate id rejected', fn () => $reg->register(fact()));

$now = strtotime('2026-10-15');
$later = strtotime('2027-02-01');
check('current() excludes stale', count($reg->current($now)) === 3 && count($reg->current($later)) === 0);
check('staleFacts() lists expired', count($reg->staleFacts($later)) === 3);

// --- Section --------------------------------------------------------------

$section = new ComparisonSection('portability', 'Moving your content', 'Fixture intro.', ['fact-1']);
check('section resolves facts', $section->renderableFacts($reg)[0]->id === 'fact-1');
check('section publishable when fresh', $section->publishable($reg, $now));
check('section unpublishable when stale', !$section->publishable($reg, $later));
check('staleCitations lists expired ids', $section->staleCitations($reg, $later) === ['fact-1']);

$missing = new ComparisonSection('gap', 'Gaps', 'x', ['fact-1', 'fact-missing']);
check('missing fact blocks publish', !$missing->publishable($reg, $now));
check('staleCitations lists missing ids', $missing->staleCitations($reg, $now) === ['fact-missing']);
rejects('renderableFacts throws on missing citation', fn () => $missing->renderableFacts($reg));

rejects('section without facts rejected', fn () => new ComparisonSection('e', 'h', 'i', []));
rejects('duplicate fact citations rejected', fn () => new ComparisonSection('e', 'h', 'i', ['fact-1', 'fact-1']));

// --- Result ---------------------------------------------------------------

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "PASS: $checks comparison-facts checks on PHP " . PHP_VERSION . PHP_EOL;
echo "Scope: isolated claim-registry contract only; no rendering, fetching, CMS or application integration tested." . PHP_EOL;
