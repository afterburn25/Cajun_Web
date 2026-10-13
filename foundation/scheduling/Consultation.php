<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Scheduling;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One consultation request and its lifecycle. `requested` is the only state
 * a visitor-facing path can reach; `confirmed` requires an explicit decision
 * after persistence, so nothing reads as booked prematurely. Declined and
 * cancelled are terminal. Rescheduling produces a new `requested` instance —
 * a moved meeting is not confirmed until it is confirmed again.
 */
final readonly class Consultation
{
    public const REQUESTED = 'requested';
    public const CONFIRMED = 'confirmed';
    public const DECLINED = 'declined';
    public const CANCELLED = 'cancelled';

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        self::REQUESTED => [self::CONFIRMED, self::DECLINED, self::CANCELLED],
        self::CONFIRMED => [self::CANCELLED],
        self::DECLINED => [],
        self::CANCELLED => [],
    ];

    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public TimeWindow $slot,
        public string $timeZone,
        public string $status = self::REQUESTED,
        public int $requestedAt = 0,
        public ?string $notes = null,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('A consultation needs an id.');
        }
        if (trim($name) === '') {
            throw new InvalidArgumentException('A consultation needs a contact name.');
        }
        if (trim($email) === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A consultation needs a valid contact email.');
        }
        WeeklyRule::zone($timeZone); // validates; stored verbatim for display/preference
        if (!in_array($status, [self::REQUESTED, self::CONFIRMED, self::DECLINED, self::CANCELLED], true)) {
            throw new InvalidArgumentException("Unknown consultation status '{$status}'.");
        }
        if ($requestedAt < 0) {
            throw new InvalidArgumentException('A consultation timestamp cannot be negative.');
        }
        if ($notes !== null && trim($notes) === '') {
            throw new InvalidArgumentException('Consultation notes must be non-empty or null.');
        }
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function isActive(): bool
    {
        return $this->status === self::REQUESTED || $this->status === self::CONFIRMED;
    }

    public function withStatus(string $status): self
    {
        return new self($this->id, $this->name, $this->email, $this->slot, $this->timeZone, $status, $this->requestedAt, $this->notes);
    }

    public function withSlot(TimeWindow $slot): self
    {
        return new self($this->id, $this->name, $this->email, $slot, $this->timeZone, self::REQUESTED, $this->requestedAt, $this->notes);
    }
}
