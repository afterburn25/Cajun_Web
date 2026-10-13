<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Estimator;

use InvalidArgumentException;

/**
 * The complete set of current pricing rules, loaded by the adapter from
 * authoritative admin configuration. Duplicate selection rules are rejected
 * rather than resolved by input order.
 */
final readonly class EstimateRuleSet
{
    /** @var array<string, PriceRule> */
    private array $rules;

    /** @param list<PriceRule> $rules */
    public function __construct(array $rules)
    {
        $map = [];
        foreach ($rules as $rule) {
            if (!$rule instanceof PriceRule) {
                throw new InvalidArgumentException('Rule sets must contain PriceRule objects.');
            }
            if (isset($map[$rule->selectionId])) {
                throw new InvalidArgumentException('Conflicting duplicate price rules are not allowed.');
            }
            $map[$rule->selectionId] = $rule;
        }
        $this->rules = $map;
    }

    public function ruleFor(string $selectionId): ?PriceRule
    {
        return $this->rules[$selectionId] ?? null;
    }
}
