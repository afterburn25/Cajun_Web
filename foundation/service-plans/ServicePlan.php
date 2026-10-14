<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\ServicePlans;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * An owner-defined maintenance/service plan. P-04 requires agreed prices
 * and terms with no invented "unlimited support" promise — so wording
 * that implies unbounded service is rejected at construction, limits
 * are typed numeric caps, and a plan becomes immutable once active:
 * changing terms means drafting a new plan, never editing under live
 * subscriptions.
 */
final class ServicePlan
{
    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
    public const RETIRED = 'retired';

    private const CADENCES = ['one-time', 'monthly', 'quarterly', 'annually'];
    /** Phrasing that would promise unbounded service or guaranteed outcomes. */
    private const BANNED = '#\b(unlimited|infinite|unmetered|forever|24/7 guaranteed|never goes down|guaranteed uptime)\b#i';
    /** Customer-facing term strings are bounded so terms stay reviewable. */
    private const TERM_MAX_BYTES = 1000;
    /** Canonical meter names: lowercase snake_case, bounded. */
    private const LIMIT_NAME = '/^[a-z][a-z0-9_]{0,63}$/';

    private string $status = self::DRAFT;

    /**
     * @param int $priceAmount integer minor units (cents) per cadence
     * @param array<string,int> $limits named caps, e.g. ['support_requests_per_month' => 4]
     * @param list<string> $inclusions concrete deliverables
     * @param list<string> $exclusions what is explicitly not covered
     */
    private function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $cadence,
        public readonly int $priceAmount,
        public readonly string $currency,
        public readonly array $inclusions,
        public readonly array $exclusions,
        public readonly array $limits,
        public readonly string $supportExpectation,
        public readonly string $createdBy,
    ) {
    }

    public static function draft(
        string $id,
        string $name,
        string $cadence,
        int $priceAmount,
        string $currency,
        array $inclusions,
        array $exclusions,
        array $limits,
        string $supportExpectation,
        string $createdBy,
    ): self {
        foreach (['id' => $id, 'name' => $name, 'createdBy' => $createdBy] as $field => $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException('A plan needs ' . $field . '.');
            }
            self::checkText($value, $field);
        }
        if (!in_array($cadence, self::CADENCES, true)) {
            throw new InvalidArgumentException('Unknown cadence: ' . $cadence);
        }
        if ($priceAmount < 0) {
            throw new InvalidArgumentException('Price cannot be negative.');
        }
        $currency = strtoupper($currency);
        if (!preg_match('/^[A-Z]{3}\z/', $currency)) {
            throw new InvalidArgumentException('Currency must be an ISO-style code (e.g. USD).');
        }
        $inclusions = self::checkTermList($inclusions, 'inclusion');
        $exclusions = self::checkTermList($exclusions, 'exclusion');
        if ($inclusions === []) {
            throw new InvalidArgumentException('A plan needs at least one concrete inclusion.');
        }
        self::checkText($supportExpectation, 'support expectation');
        // No unbounded promises anywhere in the customer-facing terms.
        foreach (array_merge($inclusions, $exclusions, [$supportExpectation]) as $text) {
            if (preg_match(self::BANNED, $text)) {
                throw new InvalidArgumentException('Terms cannot promise unbounded or guaranteed service: ' . $text);
            }
        }
        foreach ($limits as $limit => $cap) {
            if (!is_string($limit) || !preg_match(self::LIMIT_NAME, $limit)) {
                throw new InvalidArgumentException('Limit names must be canonical lowercase identifiers: ' . $limit);
            }
            if (!is_int($cap) || $cap < 0) {
                throw new InvalidArgumentException('Limits must be non-negative integer caps: ' . $limit);
            }
        }
        return new self($id, $name, $cadence, $priceAmount, $currency, $inclusions, $exclusions, $limits, $supportExpectation, $createdBy);
    }

    /** Publish the plan — it becomes subscribable and immutable. */
    public function activate(): self
    {
        if ($this->status !== self::DRAFT) {
            throw new LogicException('Only a draft plan can be activated.');
        }
        $this->status = self::ACTIVE;
        return $this;
    }

    /** Retire: existing subscriptions continue, new ones are refused. */
    public function retire(): self
    {
        if ($this->status !== self::ACTIVE) {
            throw new LogicException('Only an active plan can be retired.');
        }
        $this->status = self::RETIRED;
        return $this;
    }

    public function isRecurring(): bool
    {
        return $this->cadence !== 'one-time';
    }

    public function subscribable(): bool
    {
        return $this->status === self::ACTIVE;
    }

    /**
     * sha256 over the canonical customer-facing terms. Consent records
     * pin this so a client always consents to the exact terms offered.
     * Every term field was validated as bounded UTF-8 at construction,
     * so serialization can only fail on an impossible state — which is
     * thrown explicitly rather than hashing a false coerced payload.
     */
    public function termsHash(): string
    {
        $encoded = json_encode([
            'name' => $this->name,
            'cadence' => $this->cadence,
            'price' => $this->priceAmount,
            'currency' => $this->currency,
            'inclusions' => $this->inclusions,
            'exclusions' => $this->exclusions,
            'limits' => $this->limits,
            'support' => $this->supportExpectation,
        ], JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new LogicException('Plan terms could not be serialized canonically.');
        }
        return hash('sha256', $encoded);
    }

    /** Dense list of bounded, non-blank, valid-UTF-8 plain strings. */
    private static function checkTermList(array $terms, string $what): array
    {
        if (!array_is_list($terms)) {
            throw new InvalidArgumentException("Plan {$what}s must be a dense list.");
        }
        $clean = [];
        foreach ($terms as $text) {
            if (!is_string($text)) {
                throw new InvalidArgumentException("Plan {$what}s must be plain strings.");
            }
            self::checkText($text, $what);
            $clean[] = $text;
        }
        return $clean;
    }

    /** Non-blank, bounded, valid-UTF-8 term text — objects never coerce. */
    private static function checkText(string $text, string $what): void
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException("A plan {$what} must not be blank.");
        }
        if (strlen($text) > self::TERM_MAX_BYTES) {
            throw new InvalidArgumentException("A plan {$what} exceeds " . self::TERM_MAX_BYTES . ' bytes.');
        }
        if (!preg_match('//u', $text)) {
            throw new InvalidArgumentException("A plan {$what} must be valid UTF-8.");
        }
    }

    public function status(): string
    {
        return $this->status;
    }
}
