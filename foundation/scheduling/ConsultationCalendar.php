<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Scheduling;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * Deterministic view of bookable time: concrete availability windows plus
 * the policy and existing consultations. `slots()` enumerates bookable
 * starts; `isBookable()` validates a chosen slot against availability,
 * overlap (with buffer) and minimum notice. The calendar never confirms —
 * it only proves a slot is legitimately requestable.
 */
final class ConsultationCalendar
{
    /** @var list<TimeWindow> */
    private array $availability;

    /** @var array<string, Consultation> */
    private array $consultations = [];

    /**
     * @param list<TimeWindow> $availability Concrete UTC availability windows.
     * @param iterable<mixed> $consultations Existing consultations for conflict checks.
     */
    public function __construct(
        private SchedulePolicy $policy,
        array $availability,
        iterable $consultations = [],
    ) {
        $windows = [];
        foreach ($availability as $window) {
            if (!$window instanceof TimeWindow) {
                throw new InvalidArgumentException('Availability must be TimeWindow objects.');
            }
            $windows[] = $window;
        }
        usort($windows, static fn (TimeWindow $a, TimeWindow $b): int => $a->startsAt <=> $b->startsAt);
        $this->availability = $windows;
        foreach ($consultations as $consultation) {
            if (!$consultation instanceof Consultation) {
                throw new InvalidArgumentException('The calendar only accepts Consultation objects.');
            }
            if (isset($this->consultations[$consultation->id])) {
                throw new InvalidArgumentException("Duplicate consultation id '{$consultation->id}'.");
            }
            $this->consultations[$consultation->id] = $consultation;
        }
    }

    /**
     * Bookable slot starts in [from, to), on policy-sized boundaries inside
     * availability windows, honoring minimum notice against $now.
     *
     * @return list<TimeWindow>
     */
    public function slots(int $from, int $to, int $now): array
    {
        if ($to <= $from || $from < 0) {
            throw new InvalidArgumentException('Slot search needs a nonnegative range start before its end.');
        }
        $earliest = max($from, $now + $this->policy->minNoticeSeconds);
        $slots = [];
        $step = $this->policy->slotSeconds();
        foreach ($this->availability as $window) {
            // First absolute slot boundary at or after the earliest allowed start.
            $lower = max($earliest, $window->startsAt);
            $start = intdiv($lower + $step - 1, $step) * $step;
            for (; $start + $step <= $window->endsAt && $start < $to; $start += $step) {
                $slot = new TimeWindow($start, $start + $step);
                if ($this->isBookable($slot, null, $now)) {
                    $slots[] = $slot;
                }
            }
        }
        return $slots;
    }

    /**
     * Whether a slot may legitimately be requested: fully inside an
     * availability window, at least the policy's notice ahead of $now, and
     * clear of every active consultation including its buffer.
     */
    public function isBookable(TimeWindow $slot, ?string $ignoreId, int $now): bool
    {
        if ($slot->startsAt < $now + $this->policy->minNoticeSeconds) {
            return false;
        }
        if ($slot->startsAt % $this->policy->slotSeconds() !== 0) {
            return false;
        }
        if ($slot->endsAt - $slot->startsAt !== $this->policy->slotSeconds()) {
            return false;
        }
        $inside = false;
        foreach ($this->availability as $window) {
            if ($window->contains($slot)) {
                $inside = true;
                break;
            }
        }
        if (!$inside) {
            return false;
        }
        foreach ($this->consultations as $existing) {
            if ($existing->id === $ignoreId || !$existing->isActive()) {
                continue;
            }
            if ($slot->overlaps($existing->slot->withMargin($this->policy->bufferSeconds))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Record a consultation whose slot was verified bookable. Returns it
     * still `requested` — confirming is a separate, explicit step.
     */
    public function request(Consultation $consultation, int $now): Consultation
    {
        if (isset($this->consultations[$consultation->id])) {
            throw new InvalidArgumentException("Duplicate consultation id '{$consultation->id}'.");
        }
        if (!$this->isBookable($consultation->slot, null, $now)) {
            throw new InvalidArgumentException('The requested slot is not inside availability or conflicts with an existing consultation.');
        }
        $consultation = $consultation->withStatus(Consultation::REQUESTED);
        $this->consultations[$consultation->id] = $consultation;
        return $consultation;
    }

    /**
     * Apply a legal lifecycle move. Only the transition rules change state;
     * `confirmed` is therefore never reachable without an explicit call, and
     * terminal statuses cannot reopen.
     */
    public function transition(Consultation $consultation, string $to): Consultation
    {
        if (!Consultation::canTransition($consultation->status, $to)) {
            throw new InvalidArgumentException("Cannot move a consultation from '{$consultation->status}' to '{$to}'.");
        }
        return $consultation->withStatus($to);
    }

    /**
     * Move a consultation to a new slot. The new slot must be bookable while
     * ignoring this consultation's current slot, and the result returns to
     * `requested` — a new time needs a fresh confirmation.
     */
    public function reschedule(Consultation $consultation, TimeWindow $slot, int $now): Consultation
    {
        if (!$consultation->isActive()) {
            throw new InvalidArgumentException('Only a requested or confirmed consultation can be rescheduled.');
        }
        if (!$this->isBookable($slot, $consultation->id, $now)) {
            throw new InvalidArgumentException('The new slot is not inside availability or conflicts with an existing consultation.');
        }
        return $consultation->withSlot($slot);
    }
}
