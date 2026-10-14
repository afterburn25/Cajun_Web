<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\ServicePlans;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * A client's subscription to a service plan. P-04 requires explicit
 * recurring-payment consent — so a recurring plan cannot reach `active`
 * without a consent recorded **by the subscribing client** against the
 * exact terms hash that was presented. The subscription pins both the
 * agreed price and the plan's terms hash at subscribe time into an
 * immutable snapshot; later plan edits (which must happen on a new
 * plan, since active plans are immutable) can never silently reprice
 * or re-term this subscription. All transitions require a non-empty
 * actor and an explicit UTC instant (`YYYY-MM-DDTHH:MM:SSZ`), and no
 * event may be recorded before the previous one — history is
 * monotonic, attributed and canonical.
 */
final class PlanSubscription
{
    public const PENDING_CONSENT = 'pending-consent';
    public const ACTIVE = 'active';
    public const PAUSED = 'paused';
    public const CANCELLED = 'cancelled';
    public const ABANDONED = 'abandoned';

    private const TERMINAL = [self::CANCELLED, self::ABANDONED];

    /** Explicit UTC instant — no relative or ambiguous strings. */
    private const INSTANT = '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/';

    private string $state = self::PENDING_CONSENT;
    private ?array $consent = null;
    private string $termsHash;
    private int $lastAt;

    /** @var list<array{at:string,actor:string,action:string,state:string}> */
    private array $history = [];

    private function __construct(
        public readonly string $id,
        public readonly ServicePlan $plan,
        public readonly string $clientId,
        public readonly int $agreedPrice,
        public readonly string $createdAt,
    ) {
    }

    /**
     * Start a subscription against an active plan, pinning the current
     * price and terms hash into an immutable snapshot. Client consent is
     * recorded separately via `recordConsent`.
     */
    public static function start(string $id, ServicePlan $plan, string $clientId, string $actor, string $at): self
    {
        foreach (['id' => $id, 'client' => $clientId, 'actor' => $actor] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('A subscription needs a non-empty ' . $field . '.');
            }
        }
        if (!$plan->subscribable()) {
            throw new LogicException('Plan is not open to new subscriptions.');
        }
        $ts = self::instant($at);
        $sub = new self($id, $plan, $clientId, $plan->priceAmount, $at);
        $sub->termsHash = $plan->termsHash();
        $sub->lastAt = $ts;
        $sub->record($actor, 'start', $at);
        return $sub;
    }

    /**
     * Record the subscribing client's consent to the exact terms that
     * were presented. `consentBy` must be this subscription's client —
     * the model never converts an arbitrary actor string into that
     * client's consent — and `termsHash` must equal the pinned snapshot,
     * so consent cannot be recorded against terms the client never saw.
     * For recurring plans this is what unlocks activation; one-time
     * plans may activate directly after start since no recurring charge
     * is involved.
     */
    public function recordConsent(string $consentBy, string $termsHash, string $at): self
    {
        $this->assertNotTerminal('consent');
        if ($this->state !== self::PENDING_CONSENT || $this->consent !== null) {
            throw new LogicException('Consent is only recorded once, before activation.');
        }
        if (trim($consentBy) === '') {
            throw new InvalidArgumentException('Consent needs a named client actor.');
        }
        if ($consentBy !== $this->clientId) {
            throw new LogicException('Consent must come from the subscribing client.');
        }
        if (!hash_equals($this->termsHash, $termsHash)) {
            throw new InvalidArgumentException('Consent was given for different terms than this subscription pinned.');
        }
        $ts = self::instant($at);
        $this->requireMonotonic($ts);
        $this->consent = [
            'by' => $consentBy,
            'at' => $at,
            'termsHash' => $termsHash,
        ];
        $this->lastAt = $ts;
        $this->record($consentBy, 'consent', $at);
        return $this;
    }

    /** Activate — recurring plans require a matching consent record. */
    public function activate(string $actor, string $at): self
    {
        $this->assertNotTerminal('activate');
        if ($this->state === self::ACTIVE) {
            throw new LogicException('Subscription is already active.');
        }
        if ($this->state !== self::PENDING_CONSENT) {
            throw new LogicException('Only a pending-consent subscription can activate.');
        }
        if ($this->plan->isRecurring() && $this->consent === null) {
            throw new LogicException('Recurring plans require recorded client consent before activation.');
        }
        if ($this->consent !== null && !hash_equals($this->termsHash, $this->consent['termsHash'])) {
            throw new LogicException('Consent was recorded against different terms.');
        }
        $this->transition(self::ACTIVE, $actor, 'activate', $at);
        return $this;
    }

    /** Pause an active subscription (e.g. disputed payment). */
    public function pause(string $actor, string $at): self
    {
        $this->assertNotTerminal('pause');
        if ($this->state !== self::ACTIVE) {
            throw new LogicException('Only an active subscription can be paused.');
        }
        $this->transition(self::PAUSED, $actor, 'pause', $at);
        return $this;
    }

    /** Resume a paused subscription. */
    public function resume(string $actor, string $at): self
    {
        $this->assertNotTerminal('resume');
        if ($this->state !== self::PAUSED) {
            throw new LogicException('Only a paused subscription can resume.');
        }
        $this->transition(self::ACTIVE, $actor, 'resume', $at);
        return $this;
    }

    /** Cancel — terminal. Either party may end it; the actor is recorded. */
    public function cancel(string $actor, string $at, string $reason = ''): self
    {
        $this->assertNotTerminal('cancel');
        if ($this->state === self::PENDING_CONSENT) {
            throw new LogicException('Abandon a pending subscription instead of cancelling.');
        }
        $this->transition(self::CANCELLED, $actor, 'cancel' . ($reason !== '' ? ': ' . $reason : ''), $at);
        return $this;
    }

    /** Abandon a subscription that never activated — terminal. */
    public function abandon(string $actor, string $at): self
    {
        $this->assertNotTerminal('abandon');
        if ($this->state !== self::PENDING_CONSENT) {
            throw new LogicException('Only a pending-consent subscription can be abandoned.');
        }
        $this->transition(self::ABANDONED, $actor, 'abandon', $at);
        return $this;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function consent(): ?array
    {
        return $this->consent;
    }

    /** @return list<array{at:string,actor:string,action:string,state:string}> */
    public function history(): array
    {
        return $this->history;
    }

    private function transition(string $state, string $actor, string $action, string $at): void
    {
        if (trim($actor) === '') {
            throw new InvalidArgumentException('Every transition needs a named actor.');
        }
        $ts = self::instant($at);
        $this->requireMonotonic($ts);
        $this->state = $state;
        $this->lastAt = $ts;
        $this->record($actor, $action, $at);
    }

    /** Explicit UTC instants only — relative or ambiguous strings refuse. */
    private static function instant(string $at): int
    {
        if (!preg_match(self::INSTANT, $at, $m)
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            || (int) $m[4] > 23 || (int) $m[5] > 59 || (int) $m[6] > 59) {
            throw new InvalidArgumentException('Time must be an explicit UTC instant (YYYY-MM-DDTHH:MM:SSZ).');
        }
        return gmmktime((int) $m[4], (int) $m[5], (int) $m[6], (int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /** History is monotonic: no event may precede the last recorded one. */
    private function requireMonotonic(int $ts): void
    {
        if ($ts < $this->lastAt) {
            throw new InvalidArgumentException('An event cannot be recorded before the previous one.');
        }
    }

    private function record(string $actor, string $action, string $at): void
    {
        $this->history[] = ['at' => $at, 'actor' => $actor, 'action' => $action, 'state' => $this->state];
    }

    private function assertNotTerminal(string $action): void
    {
        if (in_array($this->state, self::TERMINAL, true)) {
            throw new LogicException('Cannot ' . $action . ': subscription is ' . $this->state . '.');
        }
    }
}
