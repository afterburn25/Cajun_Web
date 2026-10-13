<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Estimator\EstimateLine;
use CajunWebPro\Foundation\Estimator\EstimateRequest;
use CajunWebPro\Foundation\Estimator\EstimateResult;
use CajunWebPro\Foundation\Estimator\EstimateRuleSet;
use CajunWebPro\Foundation\Estimator\Estimator;
use CajunWebPro\Foundation\Estimator\PriceRule;

require __DIR__ . '/../../foundation/estimator/PriceRule.php';
require __DIR__ . '/../../foundation/estimator/EstimateRuleSet.php';
require __DIR__ . '/../../foundation/estimator/EstimateRequest.php';
require __DIR__ . '/../../foundation/estimator/EstimateLine.php';
require __DIR__ . '/../../foundation/estimator/EstimateResult.php';
require __DIR__ . '/../../foundation/estimator/Estimator.php';

$checks = 0;
$failures = [];
$estimator = new Estimator();

function request(array $changes = []): EstimateRequest
{
    return new EstimateRequest(...array_replace([
        'serviceType' => 'fx-service-site',
        'sizeBand' => 'fx-size-small',
        'options' => ['fx-opt-gallery'],
        'timing' => 'fx-time-standard',
        'customScope' => null,
    ], $changes));
}

// Synthetic rules; amounts are fixture minor units, not real prices.
function ruleset(array $rules = []): EstimateRuleSet
{
    return new EstimateRuleSet($rules ?: [
        new PriceRule('fx-service-site', 'Fixture service site', 100_00, 200_00),
        new PriceRule('fx-size-small', 'Fixture small size', 50_00, 80_00),
        new PriceRule('fx-opt-gallery', 'Fixture gallery option', 30_00, 40_00),
        new PriceRule('fx-time-standard', 'Fixture standard timing', 0, 0),
    ]);
}

function expect(string $name, mixed $expected, mixed $actual): void
{
    global $checks, $failures;
    $checks++;
    if ($actual !== $expected) {
        $failures[] = $name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
    }
}

function rejects(string $name, callable $action): void
{
    global $checks, $failures;
    $checks++;
    try {
        $action();
        $failures[] = $name . ': invalid input was accepted';
    } catch (InvalidArgumentException) {
        // Expected failure; other exceptions must fail the runner.
    }
}

// Fully priced request: an explained estimate range, lines in selection order.
$result = $estimator->evaluate(request(), ruleset());
expect('priced request yields an estimate', EstimateResult::KIND_ESTIMATE, $result->kind);
expect('low total is the rule sum', 180_00, $result->lowCents);
expect('high total is the rule sum', 320_00, $result->highCents);
expect('line order follows selection order', ['fx-service-site', 'fx-size-small', 'fx-opt-gallery', 'fx-time-standard'], array_map(fn(EstimateLine $l) => $l->selectionId, $result->lineItems));
expect('no unpriced selections when fully priced', [], $result->unpriced);
expect('estimate carries a computation note', true, (bool) array_filter($result->notes, fn($n) => str_contains($n, 'admin-supplied rule')));
expect('evaluation is deterministic', serialize($result), serialize($estimator->evaluate(request(), ruleset())));

// Missing rules or custom scope resolve to consultation with no total.
$missing = $estimator->evaluate(request(), ruleset([
    new PriceRule('fx-service-site', 'Fixture service site', 100_00, 200_00),
]));
expect('missing rules yield consultation', EstimateResult::KIND_CONSULTATION, $missing->kind);
expect('unpriced selections are named', ['fx-size-small', 'fx-opt-gallery', 'fx-time-standard'], $missing->unpriced);
expect('consultation carries no total', null, $missing->lowCents);
expect('priced context lines are still kept', 1, count($missing->lineItems));
expect('consultation explains why', true, (bool) array_filter($missing->notes, fn($n) => str_contains($n, 'no current price rule')));

$custom = $estimator->evaluate(request(['customScope' => 'something unusual']), ruleset());
expect('custom scope forces consultation', EstimateResult::KIND_CONSULTATION, $custom->kind);
expect('custom scope is explained', true, (bool) array_filter($custom->notes, fn($n) => str_contains($n, 'Custom scope')));

$empty = $estimator->evaluate(request(['sizeBand' => null, 'options' => [], 'timing' => null]), new EstimateRuleSet([]));
expect('no priced lines yields consultation', EstimateResult::KIND_CONSULTATION, $empty->kind);
expect('the lone unpriced selection is named', ['fx-service-site'], $empty->unpriced);

// Truthful labeling: the result can only ever be estimate or consultation.
expect('disclaimer is estimate-not-quote', 'Estimate only — not a final quote.', EstimateResult::DISCLAIMER);
expect('rule lookup is exact not case-folded', EstimateResult::KIND_CONSULTATION, $estimator->evaluate(
    request(['options' => ['FX-OPT-GALLERY']]), ruleset(),
)->kind);

// Normalization: duplicate and blank selections cannot distort the total.
expect('duplicate options are priced once', 180_00, $estimator->evaluate(
    request(['options' => ['fx-opt-gallery', 'fx-opt-gallery']]), ruleset(),
)->lowCents);
expect('option duplicating the service type is priced once', 180_00, $estimator->evaluate(
    request(['options' => ['fx-service-site', 'fx-opt-gallery']]), ruleset(),
)->lowCents);
expect('blank custom scope means no consultation', EstimateResult::KIND_ESTIMATE, $estimator->evaluate(
    request(['customScope' => '   ']), ruleset(),
)->kind);
expect('zero-priced timing contributes zero', [0, 0], [
    $estimator->evaluate(request(), ruleset())->lineItems[3]->lowCents,
    $estimator->evaluate(request(), ruleset())->lineItems[3]->highCents,
]);

// Contract validation: malformed inputs and ambiguous rules are rejected.
rejects('empty service type', fn() => request(['serviceType' => '  ']));
rejects('blank size band string', fn() => request(['sizeBand' => '']));
rejects('blank timing string', fn() => request(['timing' => ' ']));
rejects('non-string option', fn() => request(['options' => [7]]));
rejects('empty option', fn() => request(['options' => ['']]));
rejects('rule without selection id', fn() => new PriceRule('', 'x', 1, 2));
rejects('rule without label', fn() => new PriceRule('fx', ' ', 1, 2));
rejects('negative low amount', fn() => new PriceRule('fx', 'x', -1, 2));
rejects('negative high amount', fn() => new PriceRule('fx', 'x', 1, -2));
rejects('inverted range', fn() => new PriceRule('fx', 'x', 5, 4));
rejects('duplicate rule ids', fn() => new EstimateRuleSet([
    new PriceRule('fx', 'one', 1, 2), new PriceRule('fx', 'two', 3, 4),
]));
rejects('non-rule in rule set', fn() => new EstimateRuleSet(['fx']));
rejects('unknown result kind', fn() => new EstimateResult('final-quote', [], [], null, null));
rejects('estimate without totals', fn() => new EstimateResult(
    EstimateResult::KIND_ESTIMATE, [new EstimateLine('fx', 'x', 1, 2)], [], null, null));
rejects('estimate with inverted total', fn() => new EstimateResult(
    EstimateResult::KIND_ESTIMATE, [new EstimateLine('fx', 'x', 1, 2)], [], 5, 4));
rejects('estimate without lines', fn() => new EstimateResult(EstimateResult::KIND_ESTIMATE, [], [], 0, 0));
rejects('consultation carrying a total', fn() => new EstimateResult(
    EstimateResult::KIND_CONSULTATION, [], [], 0, 0));
rejects('non-line result entries', fn() => new EstimateResult(
    EstimateResult::KIND_ESTIMATE, ['fx'], [], 1, 2));

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "PASS: $checks estimator checks on PHP " . PHP_VERSION . PHP_EOL;
echo "Scope: isolated contract/evaluation checks only; no form, persistence, lead capture, notification, database or 1.1.0 application integration tested." . PHP_EOL;
