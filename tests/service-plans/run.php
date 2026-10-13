<?php
declare(strict_types=1);

/**
 * Synthetic P-04 service-plan contract checks. Fixture plans and
 * subscriptions only — no billing, persistence or UI.
 */

use CajunWebPro\Foundation\ServicePlans\PlanSubscription;
use CajunWebPro\Foundation\ServicePlans\ServicePlan;

require __DIR__ . '/../../foundation/service-plans/ServicePlan.php';
require __DIR__ . '/../../foundation/service-plans/PlanSubscription.php';

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

function plan(array $changes = []): ServicePlan
{
    return ServicePlan::draft(...array_replace([
        'id' => 'plan-care',
        'name' => 'Care Plan',
        'cadence' => 'monthly',
        'priceAmount' => 9900,
        'currency' => 'usd',
        'inclusions' => ['Core updates monthly', 'Up to 4 content edits per month'],
        'exclusions' => ['New page design', 'Custom development'],
        'limits' => ['content_edits_per_month' => 4],
        'supportExpectation' => 'Replies within two business days',
        'createdBy' => 'owner-1',
    ], $changes));
}

// --- ServicePlan -----------------------------------------------------------

check('plan starts draft', plan()->status() === ServicePlan::DRAFT);
check('currency normalized', plan()->currency === 'USD');
check('monthly is recurring', plan()->isRecurring());
check('one-time not recurring', plan(['cadence' => 'one-time'])->isRecurring() === false);
check('draft not subscribable', !plan()->subscribable());
check('terms hash deterministic', plan()->termsHash() === plan()->termsHash());
check('terms hash changes with price', plan()->termsHash() !== plan(['priceAmount' => 12900])->termsHash());

rejects('empty inclusions rejected', fn () => plan(['inclusions' => []]));
rejects('negative price rejected', fn () => plan(['priceAmount' => -1]));
rejects('bad currency rejected', fn () => plan(['currency' => 'US$']));
rejects('unknown cadence rejected', fn () => plan(['cadence' => 'weekly']));
rejects('unlimited inclusion rejected', fn () => plan(['inclusions' => ['Unlimited content edits']]));
rejects('unmetered support rejected', fn () => plan(['supportExpectation' => 'Unmetered support']));
rejects('guaranteed uptime rejected', fn () => plan(['inclusions' => ['Updates'], 'supportExpectation' => 'Guaranteed uptime']));
rejects('non-integer limit rejected', fn () => plan(['limits' => ['edits' => 'many']]));
rejects('negative limit rejected', fn () => plan(['limits' => ['edits' => -5]]));
rejects('empty support expectation rejected', fn () => plan(['supportExpectation' => '']));

// --- Lifecycle: draft → active → retired -----------------------------------

$plan = plan();
rejects('retire from draft rejected', fn () => $plan->retire());
$plan->activate();
check('active subscribable', $plan->subscribable());
rejects('double activate rejected', fn () => $plan->activate());
$plan->retire();
check('retired status', $plan->status() === ServicePlan::RETIRED);
check('retired not subscribable', !$plan->subscribable());
rejects('double retire rejected', fn () => $plan->retire());

// --- PlanSubscription ------------------------------------------------------

$active = plan();
$active->activate();

check('subscription starts pending-consent', PlanSubscription::start('sub-1', $active, 'client-7', 'owner-1', '2026-10-13')->state() === PlanSubscription::PENDING_CONSENT);
check('price pinned at start', PlanSubscription::start('sub-1', $active, 'client-7', 'owner-1', '2026-10-13')->agreedPrice === 9900);
check('start recorded in history', count(PlanSubscription::start('sub-1', $active, 'client-7', 'owner-1', '2026-10-13')->history()) === 1);

rejects('start on draft plan rejected', fn () => PlanSubscription::start('sub-x', plan(), 'client-7', 'owner-1', '2026-10-13'));
rejects('start on retired plan rejected', function () {
    $retired = plan(['id' => 'plan-old']);
    $retired->activate();
    $retired->retire();
    PlanSubscription::start('sub-x', $retired, 'client-7', 'owner-1', '2026-10-13');
});
rejects('empty client rejected', fn () => PlanSubscription::start('sub-x', $active, '', 'owner-1', '2026-10-13'));

// Recurring requires consent
$sub = PlanSubscription::start('sub-1', $active, 'client-7', 'owner-1', '2026-10-13');
rejects('recurring activate without consent rejected', fn () => $sub->activate('owner-1', '2026-10-13'));

$sub->recordConsent('client-7', '2026-10-14');
check('consent pins terms hash', $sub->consent()['termsHash'] === $active->termsHash());
rejects('double consent rejected', fn () => $sub->recordConsent('client-7', '2026-10-14'));

$sub->activate('owner-1', '2026-10-14');
check('activated after consent', $sub->state() === PlanSubscription::ACTIVE);
rejects('double activate rejected', fn () => $sub->activate('owner-1', '2026-10-14'));

$sub->pause('owner-1', '2026-10-20');
check('paused', $sub->state() === PlanSubscription::PAUSED);
rejects('pause from paused rejected', fn () => $sub->pause('owner-1', '2026-10-21'));
$sub->resume('owner-1', '2026-10-22');
check('resumed', $sub->state() === PlanSubscription::ACTIVE);

$sub->cancel('client-7', '2026-11-01', 'ending service');
check('cancelled is terminal', $sub->state() === PlanSubscription::CANCELLED);
rejects('resume after cancel rejected', fn () => $sub->resume('owner-1', '2026-11-02'));
rejects('consent after cancel rejected', fn () => $sub->recordConsent('client-7', '2026-11-02'));
check('history fully attributed', count($sub->history()) === 6 && $sub->history()[5]['actor'] === 'client-7' && str_contains($sub->history()[5]['action'], 'ending service'));

// One-time plan activates without consent
$oneTime = plan(['id' => 'plan-fix', 'cadence' => 'one-time']);
$oneTime->activate();
$otSub = PlanSubscription::start('sub-ot', $oneTime, 'client-9', 'owner-1', '2026-10-13');
$otSub->activate('owner-1', '2026-10-13');
check('one-time activates without consent', $otSub->state() === PlanSubscription::ACTIVE);

// Abandon pending
$pending = PlanSubscription::start('sub-p', $active, 'client-8', 'owner-1', '2026-10-13');
rejects('cancel pending rejected', fn () => $pending->cancel('owner-1', '2026-10-13'));
$pending->abandon('owner-1', '2026-10-13');
check('abandoned is terminal', $pending->state() === PlanSubscription::ABANDONED);
rejects('activate abandoned rejected', fn () => $pending->activate('owner-1', '2026-10-14'));

rejects('empty actor transition rejected', function () use ($active) {
    PlanSubscription::start('s', $active, 'c', 'a', '2026-10-13')->activate('', '2026-10-13');
});

// --- Result ---------------------------------------------------------------

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "PASS: $checks service-plan checks on PHP " . PHP_VERSION . PHP_EOL;
echo "Scope: isolated plan/subscription contract only; no billing, persistence or application integration tested." . PHP_EOL;
