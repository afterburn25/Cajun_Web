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
foreach (['Unlimited support', 'Infinite edits', 'Unmetered care', 'Forever support', '24/7 guaranteed help', 'Never goes down', 'Guaranteed uptime'] as $name) {
    rejects('unsupported service promise in plan name rejected: ' . $name, fn () => plan(['name' => $name]));
}
rejects('unmetered support rejected', fn () => plan(['supportExpectation' => 'Unmetered support']));
rejects('guaranteed uptime rejected', fn () => plan(['inclusions' => ['Updates'], 'supportExpectation' => 'Guaranteed uptime']));
rejects('non-integer limit rejected', fn () => plan(['limits' => ['edits' => 'many']]));
rejects('negative limit rejected', fn () => plan(['limits' => ['edits' => -5]]));
rejects('empty support expectation rejected', fn () => plan(['supportExpectation' => '']));
rejects('whitespace-only support expectation rejected', fn () => plan(['supportExpectation' => "  \n "]));
rejects('currency with trailing newline rejected', fn () => plan(['currency' => "USD\n"]));
rejects('non-string inclusion rejected', fn () => plan(['inclusions' => [true, 25, null]]));
rejects('stringable inclusion rejected', fn () => plan(['inclusions' => [new class { public string $s = 'x'; public function __toString(): string { return $this->s; } }]]));
rejects('whitespace-only inclusion rejected', fn () => plan(['inclusions' => ['Updates', '   ']]));
rejects('invalid UTF-8 inclusion rejected', fn () => plan(['inclusions' => ["bad\xC3\x28"]]));
rejects('oversized inclusion rejected', fn () => plan(['inclusions' => [str_repeat('a', 1001)]]));
rejects('non-list inclusions rejected', fn () => plan(['inclusions' => ['one' => 'Updates']]));
rejects('malformed limit name rejected', fn () => plan(['limits' => ['Edits Per Month' => 4]]));
rejects('empty limit name rejected', fn () => plan(['limits' => ['' => 4]]));
rejects('list-style limits rejected', fn () => plan(['limits' => [4]]));
rejects('trailing-newline limit name rejected', fn () => plan(['limits' => ["updates\n" => 1]]));

// caller-held references must never mutate retained caps or drift termsHash
$cap = 1;
$refLimits = ['updates' => &$cap];
$refPlan = plan(['limits' => $refLimits]);
$refPlan->activate();
$refHash = $refPlan->termsHash();
$refSub = PlanSubscription::start('sub-ref', $refPlan, 'client-7', 'owner-1', '2026-10-14T12:00:00Z');
$cap = -99;
check('stored limits detached from caller references', $refPlan->limits['updates'] === 1);
check('terms hash stable after external mutation', $refPlan->termsHash() === $refHash);
check('subscription snapshot matches stable terms', $refSub->plan->limits['updates'] === 1);
$refSub->recordConsent('client-7', $refPlan->termsHash(), '2026-10-14T13:00:00Z');
check('consent still valid against unchanged terms', $refSub->consent() !== null);

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

check('subscription starts pending-consent', PlanSubscription::start('sub-1', $active, 'client-7', 'owner-1', '2026-10-13T00:00:00Z')->state() === PlanSubscription::PENDING_CONSENT);
check('price pinned at start', PlanSubscription::start('sub-1', $active, 'client-7', 'owner-1', '2026-10-13T00:00:00Z')->agreedPrice === 9900);
check('start recorded in history', count(PlanSubscription::start('sub-1', $active, 'client-7', 'owner-1', '2026-10-13T00:00:00Z')->history()) === 1);

rejects('start on draft plan rejected', fn () => PlanSubscription::start('sub-x', plan(), 'client-7', 'owner-1', '2026-10-13T00:00:00Z'));
rejects('start on retired plan rejected', function () {
    $retired = plan(['id' => 'plan-old']);
    $retired->activate();
    $retired->retire();
    PlanSubscription::start('sub-x', $retired, 'client-7', 'owner-1', '2026-10-13T00:00:00Z');
});
rejects('empty client rejected', fn () => PlanSubscription::start('sub-x', $active, '', 'owner-1', '2026-10-13T00:00:00Z'));
rejects('whitespace actor at start rejected', fn () => PlanSubscription::start('sub-x', $active, 'client-7', '  ', '2026-10-13T00:00:00Z'));
rejects('relative start time rejected', fn () => PlanSubscription::start('sub-x', $active, 'client-7', 'owner-1', 'tomorrow'));
rejects('impossible date rejected', fn () => PlanSubscription::start('sub-x', $active, 'client-7', 'owner-1', '2026-02-30T00:00:00Z'));

// Recurring requires consent
$sub = PlanSubscription::start('sub-1', $active, 'client-7', 'owner-1', '2026-10-13T00:00:00Z');
rejects('recurring activate without consent rejected', fn () => $sub->activate('owner-1', '2026-10-13T00:00:00Z'));
rejects('consent from another client rejected', fn () => $sub->recordConsent('client-8', $active->termsHash(), '2026-10-14T00:00:00Z'));
rejects('consent with foreign terms rejected', fn () => $sub->recordConsent('client-7', hash('sha256', 'other-terms'), '2026-10-14T00:00:00Z'));
check('refused consent left state and history unchanged', $sub->consent() === null && count($sub->history()) === 1);
rejects('consent before start instant rejected', fn () => $sub->recordConsent('client-7', $active->termsHash(), '2020-01-01T00:00:00Z'));
rejects('relative consent time rejected', fn () => $sub->recordConsent('client-7', $active->termsHash(), 'tomorrow'));
rejects('date-only consent time rejected', fn () => $sub->recordConsent('client-7', $active->termsHash(), '2026-10-14'));
rejects('trailing-newline instant rejected', fn () => $sub->recordConsent('client-7', $active->termsHash(), "2026-10-14T00:00:00Z\n"));
rejects('trailing-newline start instant rejected', fn () => PlanSubscription::start('sub-lf', $active, 'client-7', 'owner-1', "2026-10-14T12:00:00Z\n"));
check('refused newline instant left state unchanged', $sub->consent() === null && count($sub->history()) === 1);

$sub->recordConsent('client-7', $active->termsHash(), '2026-10-14T00:00:00Z');
check('consent pins terms hash', $sub->consent()['termsHash'] === $active->termsHash());
rejects('double consent rejected', fn () => $sub->recordConsent('client-7', $active->termsHash(), '2026-10-14T00:00:00Z'));

$sub->activate('owner-1', '2026-10-14T00:00:00Z');
check('activated after consent', $sub->state() === PlanSubscription::ACTIVE);
rejects('double activate rejected', fn () => $sub->activate('owner-1', '2026-10-14T00:00:00Z'));

// an activation cannot be dated before the consent it depends on
$late = PlanSubscription::start('sub-late', $active, 'client-7', 'owner-1', '2026-10-13T00:00:00Z');
$late->recordConsent('client-7', $active->termsHash(), '2026-10-14T00:00:00Z');
rejects('out-of-order activation rejected', fn () => $late->activate('owner-1', '2020-01-01T00:00:00Z'));
check('out-of-order refusal left state unchanged', $late->state() === PlanSubscription::PENDING_CONSENT && count($late->history()) === 2);

$sub->pause('owner-1', '2026-10-20T00:00:00Z');
check('paused', $sub->state() === PlanSubscription::PAUSED);
rejects('pause from paused rejected', fn () => $sub->pause('owner-1', '2026-10-21T00:00:00Z'));
$sub->resume('owner-1', '2026-10-22T00:00:00Z');
check('resumed', $sub->state() === PlanSubscription::ACTIVE);

$sub->cancel('client-7', '2026-11-01T00:00:00Z', 'ending service');
check('cancelled is terminal', $sub->state() === PlanSubscription::CANCELLED);
rejects('resume after cancel rejected', fn () => $sub->resume('owner-1', '2026-11-02T00:00:00Z'));
rejects('consent after cancel rejected', fn () => $sub->recordConsent('client-7', $active->termsHash(), '2026-11-02T00:00:00Z'));
check('history fully attributed', count($sub->history()) === 6 && $sub->history()[5]['actor'] === 'client-7' && str_contains($sub->history()[5]['action'], 'ending service'));

// One-time plan activates without consent
$oneTime = plan(['id' => 'plan-fix', 'cadence' => 'one-time']);
$oneTime->activate();
$otSub = PlanSubscription::start('sub-ot', $oneTime, 'client-9', 'owner-1', '2026-10-13T00:00:00Z');
$otSub->activate('owner-1', '2026-10-13T00:00:00Z');
check('one-time activates without consent', $otSub->state() === PlanSubscription::ACTIVE);

// Abandon pending
$pending = PlanSubscription::start('sub-p', $active, 'client-8', 'owner-1', '2026-10-13T00:00:00Z');
rejects('cancel pending rejected', fn () => $pending->cancel('owner-1', '2026-10-13T00:00:00Z'));
$pending->abandon('owner-1', '2026-10-13T00:00:00Z');
check('abandoned is terminal', $pending->state() === PlanSubscription::ABANDONED);
rejects('activate abandoned rejected', fn () => $pending->activate('owner-1', '2026-10-14T00:00:00Z'));

rejects('empty actor transition rejected', function () use ($active) {
    PlanSubscription::start('s', $active, 'c', 'a', '2026-10-13T00:00:00Z')->activate('', '2026-10-13T00:00:00Z');
});

// --- Result ---------------------------------------------------------------

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "PASS: $checks service-plan checks on PHP " . PHP_VERSION . PHP_EOL;
echo "Scope: isolated plan/subscription contract only; no billing, persistence or application integration tested." . PHP_EOL;
