<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\ServicePlans;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * A client's subscription to a service plan. P-04 requires explicit
 * recurring-payment consent — so a recurring plan cannot reach `active`
 * without a `ConsentRecord` whose terms hash matches the plan the
 * client actually saw. The subscription pins the agreed price at
 * subscribe time; later plan edits (which must happen on a new plan,
 * since active plans are immutable) never silently reprice this
 * subscription. All transitions are actor-attributed in history.
 */
final class PlanSubscription
{
    public const PENDING_CONSENT = 'pending-consent';
    public const ACTIVE = 'active';
    public const PAUSED = 'paused';
    public const CANCELLED = 'cancelled';
    public const ABANDONED = 'abandoned';

    private const TERMINAL = [self::CANCELLED, self::ABANDONED];

    private string $state = self::PENDING_CONSENT;
    private ?array $consent = null;

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
     * price. Client consent is recorded separately via `recordConsent`.
     */
    public static function start(string $id, ServicePlan $plan, string $clientId, string $actor, string $at): self
    {
        if (trim($id) === '' || trim($clientId) === '') {
            throw new InvalidArgumentException('A subscription needs an id and client.');
        }
        if (!$plan->subscribable()) {
            throw new LogicException('Plan is not open to new subscriptions.');
        }
        if (strtotime($at) === false) {
            throw new InvalidArgumentException('Start time must be parseable.');
        }
        $sub = new self($id, $plan, $clientId, $plan->priceAmount, $at);
        $sub->record($actor, 'start', $at);
        return $sub;
    }

    /**
     * Record the client's consent to the exact terms. For recurring
     * plans this is what unlocks activation; one-time plans may activate
     * directly after start since no recurring charge is involved.
     */
    public function recordConsent(string $consentBy, string $at): self
    {
        $this->assertNotTerminal('consent');
        if ($this->state !== self::PENDING_CONSENT || $this->consent !== null) {
            throw new LogicException('Consent is only recorded once, before activation.');
        }
        if (trim($consentBy) === '' || strtotime($at) === false) {
            throw new InvalidArgumentException('Consent needs a client actor and a timestamp.');
        }
        $this->consent = [
            'by' => $consentBy,
            'at' => $at,
            'termsHash' => $this->plan->termsHash(),
        ];
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
        if ($this->plan->isRecurring() && $this->consent === null) {
            throw new LogicException('Recurring plans require recorded client consent before activation.');
        }
        if ($this->consent !== null && $this->consent['termsHash'] !== $this->plan->termsHash()) {
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
        if (trim($actor) === '' || strtotime($at) === false) {
            throw new InvalidArgumentException('Every transition needs an actor and a valid timestamp.');
        }
        $this->state = $state;
        $this->record($actor, $action, $at);
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
