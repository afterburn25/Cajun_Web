<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Scheduling\Consultation;
use CajunWebPro\Foundation\Scheduling\ConsultationCalendar;
use CajunWebPro\Foundation\Scheduling\SchedulePolicy;
use CajunWebPro\Foundation\Scheduling\TimeWindow;
use CajunWebPro\Foundation\Scheduling\WeeklyRule;

require __DIR__ . '/../../foundation/scheduling/TimeWindow.php';
require __DIR__ . '/../../foundation/scheduling/WeeklyRule.php';
require __DIR__ . '/../../foundation/scheduling/SchedulePolicy.php';
require __DIR__ . '/../../foundation/scheduling/Consultation.php';
require __DIR__ . '/../../foundation/scheduling/ConsultationCalendar.php';

$checks = 0;
$failures = [];

function check(string $label, bool $ok): void
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $label;
    }
}

function throws(string $label, callable $fn): void
{
    try {
        $fn();
        check($label, false);
    } catch (InvalidArgumentException) {
        check($label, true);
    }
}

function fixtureConsultation(string $id, int $start, array $changes = []): Consultation
{
    return new Consultation(...array_replace([
        'id' => $id,
        'name' => 'Fixture Visitor',
        'email' => 'visitor@fx.example.com',
        'slot' => new TimeWindow($start, $start + 1800),
        'timeZone' => 'America/Chicago',
        'status' => Consultation::REQUESTED,
        'requestedAt' => 1700000000,
        'notes' => null,
    ], $changes));
}

// --- TimeWindow ----------------------------------------------------------------------

$w = new TimeWindow(1000, 2000);
check('window contains inner', $w->contains(new TimeWindow(1200, 1800)));
check('window rejects outside', !$w->contains(new TimeWindow(1200, 2100)));
check('overlap detects intersection', $w->overlaps(new TimeWindow(1500, 2500)));
check('touching ends do not overlap', !$w->overlaps(new TimeWindow(2000, 3000)));
check('margin extends both ends', $w->withMargin(60)->startsAt === 940 && $w->withMargin(60)->endsAt === 2060);
throws('inverted window rejected', fn () => new TimeWindow(2000, 1000));
throws('negative start rejected', fn () => new TimeWindow(-5, 100));

// --- WeeklyRule ----------------------------------------------------------------------

$rule = new WeeklyRule(2, 540, 720, 'America/Chicago'); // Tuesdays 09:00–12:00 local
$range = $rule->expand(strtotime('2025-01-06T00:00:00Z'), strtotime('2025-01-20T00:00:00Z'));
check('weekly rule yields two Tuesdays', count($range) === 2);
check('first window is 09:00 local on Jan 7', $range[0]->startsAt === strtotime('2025-01-07T15:00:00Z'));
check('window length is three hours', $range[0]->endsAt - $range[0]->startsAt === 10800);

// DST: America/New_York springs forward 2025-03-09; a Sunday 09:00 rule keeps
// local 09:00 while the UTC instant shifts 14:00 → 13:00.
$dst = new WeeklyRule(0, 540, 600, 'America/New_York');
$around = $dst->expand(strtotime('2025-03-01T00:00:00Z'), strtotime('2025-03-31T00:00:00Z'));
check('DST keeps local time constant', array_map(
    fn (TimeWindow $x) => (new DateTimeImmutable('@' . $x->startsAt))->setTimezone(new DateTimeZone('America/New_York'))->format('H:i'),
    $around,
) === ['09:00', '09:00', '09:00', '09:00', '09:00']);
check('UTC shifts across spring forward', $around[0]->startsAt === strtotime('2025-03-02T14:00:00Z')
    && $around[1]->startsAt === strtotime('2025-03-09T13:00:00Z'));

// A rule in the spring-forward gap (Sunday 02:30) is skipped, never silently moved.
$gap = new WeeklyRule(0, 150, 180, 'America/New_York');
check('gap occurrence is skipped', $gap->expand(strtotime('2025-03-08T00:00:00Z'), strtotime('2025-03-10T00:00:00Z')) === []);
check('gap rule still produces normal weeks', count($gap->expand(strtotime('2025-03-10T00:00:00Z'), strtotime('2025-03-24T00:00:00Z'))) === 2);

// An end inside the spring-forward gap is also skipped — Sunday 01:30 exists
// but 02:30 does not, so the whole occurrence drops rather than silently
// stretching to 03:30 and offering unconfigured availability.
$endGap = new WeeklyRule(0, 90, 150, 'America/New_York');
check('end-in-gap occurrence is skipped', $endGap->expand(strtotime('2025-03-08T00:00:00Z'), strtotime('2025-03-10T00:00:00Z')) === []);
check('end-in-gap rule still produces normal weeks', count($endGap->expand(strtotime('2025-03-10T00:00:00Z'), strtotime('2025-03-24T00:00:00Z'))) === 2);

// Fall-back policy is explicit: ambiguous local times resolve to the first
// occurrence — Sunday 01:00–01:30 America/New_York on 2025-11-02 is
// 05:00–05:30 UTC (the EDT occurrence), not the repeated hour.
$fallback = new WeeklyRule(0, 60, 90, 'America/New_York');
$fbWindows = $fallback->expand(strtotime('2025-11-01T00:00:00Z'), strtotime('2025-11-04T00:00:00Z'));
check('fall-back resolves to first occurrence', count($fbWindows) === 1
    && $fbWindows[0]->startsAt === strtotime('2025-11-02T05:00:00Z')
    && $fbWindows[0]->endsAt === strtotime('2025-11-02T05:30:00Z'));

throws('unknown timezone rejected', fn () => new WeeklyRule(1, 0, 60, 'Not/AZone'));
throws('blank timezone rejected', fn () => new WeeklyRule(1, 0, 60, ' '));
throws('bad day rejected', fn () => new WeeklyRule(7, 0, 60, 'America/Chicago'));
throws('inverted minutes rejected', fn () => new WeeklyRule(1, 600, 600, 'America/Chicago'));
throws('past-midnight end rejected', fn () => new WeeklyRule(1, 0, 1441, 'America/Chicago'));
check('end-of-day 1440 allowed', (new WeeklyRule(1, 1380, 1440, 'America/Chicago'))->endMinute === 1440);

// --- SchedulePolicy --------------------------------------------------------------------

$policy = new SchedulePolicy(30, 300, 3600); // 30-min slots, 5-min buffer, 1-hour notice
check('slot seconds derive from minutes', $policy->slotSeconds() === 1800);
throws('zero-length slot rejected', fn () => new SchedulePolicy(0));
throws('overlong slot rejected', fn () => new SchedulePolicy(481));
throws('negative buffer rejected', fn () => new SchedulePolicy(30, -1));

// --- Consultation ------------------------------------------------------------------------

check('request defaults to requested', fixtureConsultation('fx-a', 1737000000)->status === Consultation::REQUESTED);
throws('blank id rejected', fn () => fixtureConsultation(' ', 1737000000));
throws('bad email rejected', fn () => fixtureConsultation('fx-x', 1737000000, ['email' => 'nope']));
throws('bad timezone rejected', fn () => fixtureConsultation('fx-x', 1737000000, ['timeZone' => 'Mars/Olympus']));
throws('unknown status rejected', fn () => fixtureConsultation('fx-x', 1737000000, ['status' => 'booked']));
check('confirmed is never a constructor shortcut status name', !in_array('booked', [Consultation::REQUESTED, Consultation::CONFIRMED, Consultation::DECLINED, Consultation::CANCELLED], true));

check('requested can confirm', Consultation::canTransition(Consultation::REQUESTED, Consultation::CONFIRMED));
check('requested can decline', Consultation::canTransition(Consultation::REQUESTED, Consultation::DECLINED));
check('confirmed can cancel', Consultation::canTransition(Consultation::CONFIRMED, Consultation::CANCELLED));
check('confirmed cannot revert to requested', !Consultation::canTransition(Consultation::CONFIRMED, Consultation::REQUESTED));
check('declined is terminal', !Consultation::canTransition(Consultation::DECLINED, Consultation::CANCELLED));
check('cancelled is terminal', !Consultation::canTransition(Consultation::CANCELLED, Consultation::REQUESTED));
check('requested and confirmed are active', fixtureConsultation('fx-a', 1)->isActive() && fixtureConsultation('fx-b', 1, ['status' => Consultation::CONFIRMED])->isActive());
check('declined is inactive', !fixtureConsultation('fx-c', 1, ['status' => Consultation::DECLINED])->isActive());

// --- Calendar ----------------------------------------------------------------------------

// Availability: 2025-01-07 14:00–18:00 UTC (aligned to the hour).
$avail = new TimeWindow(strtotime('2025-01-07T14:00:00Z'), strtotime('2025-01-07T18:00:00Z'));
$now = strtotime('2025-01-07T12:00:00Z');
$calendar = new ConsultationCalendar($policy, [$avail]);

$slots = $calendar->slots(strtotime('2025-01-07T00:00:00Z'), strtotime('2025-01-08T00:00:00Z'), $now);
// 14:00–18:00 minus 1h notice from 12:00 → starts at 14:00; 30-min slots: 14:00…17:30 = 8.
check('slots enumerate policy-aligned windows', count($slots) === 8 && $slots[0]->startsAt === strtotime('2025-01-07T14:00:00Z'));

$earlyNow = strtotime('2025-01-07T13:30:00Z');
$noticeSlots = $calendar->slots(strtotime('2025-01-07T00:00:00Z'), strtotime('2025-01-08T00:00:00Z'), $earlyNow);
check('minimum notice drops near slots', $noticeSlots[0]->startsAt === strtotime('2025-01-07T14:30:00Z'));

check('unaligned slot not bookable', !$calendar->isBookable(new TimeWindow(strtotime('2025-01-07T14:10:00Z'), strtotime('2025-01-07T14:40:00Z')), null, $now));
check('slot outside availability not bookable', !$calendar->isBookable(new TimeWindow(strtotime('2025-01-07T18:00:00Z'), strtotime('2025-01-07T18:30:00Z')), null, $now));
check('wrong-length slot not bookable', !$calendar->isBookable(new TimeWindow(strtotime('2025-01-07T14:00:00Z'), strtotime('2025-01-07T15:00:00Z')), null, $now));
check('aligned inside slot bookable', $calendar->isBookable(new TimeWindow(strtotime('2025-01-07T14:00:00Z'), strtotime('2025-01-07T14:30:00Z')), null, $now));

// Conflict and buffer.
$taken = fixtureConsultation('fx-1', strtotime('2025-01-07T15:00:00Z'), ['status' => Consultation::CONFIRMED]);
$busy = new ConsultationCalendar($policy, [$avail], [$taken]);
check('overlapping confirmed slot rejected', !$busy->isBookable(new TimeWindow(strtotime('2025-01-07T15:00:00Z'), strtotime('2025-01-07T15:30:00Z')), null, $now));
check('buffer blocks adjacent slot', !$busy->isBookable(new TimeWindow(strtotime('2025-01-07T15:30:00Z'), strtotime('2025-01-07T16:00:00Z')), null, $now));
check('slot beyond buffer is fine', $busy->isBookable(new TimeWindow(strtotime('2025-01-07T15:35:00Z') - strtotime('2025-01-07T15:35:00Z') % 1800 + 1800, strtotime('2025-01-07T15:35:00Z') - strtotime('2025-01-07T15:35:00Z') % 1800 + 3600), null, $now));

$cancelled = fixtureConsultation('fx-2', strtotime('2025-01-07T15:00:00Z'), ['status' => Consultation::CANCELLED]);
$free = new ConsultationCalendar($policy, [$avail], [$cancelled]);
check('cancelled consultation frees the slot', $free->isBookable(new TimeWindow(strtotime('2025-01-07T15:00:00Z'), strtotime('2025-01-07T15:30:00Z')), null, $now));

throws('duplicate booking ids rejected', fn () => new ConsultationCalendar($policy, [$avail], [$taken, fixtureConsultation('fx-1', strtotime('2025-01-07T16:00:00Z'))]));
throws('non-window availability rejected', fn () => new ConsultationCalendar($policy, ['x']));
throws('non-consultation rejected', fn () => new ConsultationCalendar($policy, [$avail], ['x']));

// Request → confirm → reschedule → cancel lifecycle.
$cal = new ConsultationCalendar($policy, [$avail]);
$req = $cal->request(fixtureConsultation('fx-9', strtotime('2025-01-07T15:00:00Z')), $now);
check('request stays requested', $req->status === Consultation::REQUESTED);
check('new request blocks the slot', !$cal->isBookable(new TimeWindow(strtotime('2025-01-07T15:00:00Z'), strtotime('2025-01-07T15:30:00Z')), null, $now));
throws('requesting a taken slot rejected', fn () => $cal->request(fixtureConsultation('fx-10', strtotime('2025-01-07T15:15:00Z'), ['slot' => new TimeWindow(strtotime('2025-01-07T15:30:00Z'), strtotime('2025-01-07T16:00:00Z'))]), $now));
throws('requesting outside availability rejected', fn () => $cal->request(fixtureConsultation('fx-11', strtotime('2025-01-07T18:00:00Z')), $now));

$confirmed = $cal->transition($req, Consultation::CONFIRMED);
check('explicit confirm reaches confirmed', $confirmed->status === Consultation::CONFIRMED);
throws('confirming again rejected', fn () => $cal->transition($confirmed, Consultation::CONFIRMED));

$moved = $cal->reschedule($confirmed, new TimeWindow(strtotime('2025-01-07T16:30:00Z'), strtotime('2025-01-07T17:00:00Z')), $now);
check('reschedule returns to requested', $moved->status === Consultation::REQUESTED);
check('reschedule adopts the new slot', $moved->slot->startsAt === strtotime('2025-01-07T16:30:00Z'));
check('reschedule onto its own vacated slot is allowed', $cal->isBookable(new TimeWindow(strtotime('2025-01-07T15:00:00Z'), strtotime('2025-01-07T15:30:00Z')), 'fx-9', $now));
$other = $cal->request(fixtureConsultation('fx-12', strtotime('2025-01-07T17:30:00Z')), $now);
throws('reschedule onto another booking rejected', fn () => $cal->reschedule($moved, new TimeWindow(strtotime('2025-01-07T17:30:00Z'), strtotime('2025-01-07T18:00:00Z')), $now));
throws('rescheduling a cancelled consult rejected', fn () => $cal->reschedule(fixtureConsultation('fx-z', 1737000000, ['status' => Consultation::CANCELLED]), new TimeWindow(strtotime('2025-01-07T17:00:00Z'), strtotime('2025-01-07T17:30:00Z')), $now));

$cancelledFinal = $cal->transition($moved, Consultation::CANCELLED);
throws('cancelled cannot be confirmed', fn () => $cal->transition($cancelledFinal, Consultation::CONFIRMED));
throws('declined cannot reopen', fn () => $cal->transition(fixtureConsultation('fx-d', 1737000000, ['status' => Consultation::DECLINED]), Consultation::REQUESTED));

// Lifecycle operations must update the stored record on the same calendar:
// the cancellation above frees the rescheduled slot for real, and callers
// holding superseded instances are refused rather than forking state.
check('cancellation frees the rescheduled slot', $cal->isBookable(new TimeWindow(strtotime('2025-01-07T16:30:00Z'), strtotime('2025-01-07T17:00:00Z')), null, $now));
throws('stale instance rejected', fn () => $cal->transition($req, Consultation::CANCELLED));
throws('untracked consultation rejected', fn () => $cal->transition(fixtureConsultation('fx-new', strtotime('2025-01-07T16:00:00Z')), Consultation::CONFIRMED));
throws('untracked reschedule rejected', fn () => $cal->reschedule(fixtureConsultation('fx-new2', strtotime('2025-01-07T16:00:00Z')), new TimeWindow(strtotime('2025-01-07T17:00:00Z'), strtotime('2025-01-07T17:30:00Z')), $now));

printf("%d checks, %d failures\n", $checks, count($failures));
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: {$failure}\n");
}
exit($failures === [] ? 0 : 1);
