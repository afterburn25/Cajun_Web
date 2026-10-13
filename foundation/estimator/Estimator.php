<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Estimator;

/**
 * Pure deterministic evaluation: the same request and rule set always
 * produce the same result, and every shown number traces to a supplied rule.
 * Uncertain or unpriced scope resolves to consultation, never a guessed total.
 */
final class Estimator
{
    public function evaluate(EstimateRequest $request, EstimateRuleSet $rules): EstimateResult
    {
        $lines = [];
        $unpriced = [];
        foreach ($request->selections() as $selectionId) {
            $rule = $rules->ruleFor($selectionId);
            if ($rule === null) {
                $unpriced[] = $selectionId;
                continue;
            }
            $lines[] = new EstimateLine($rule->selectionId, $rule->label, $rule->lowCents, $rule->highCents);
        }

        $notes = [];
        if ($request->wantsConsultation()) {
            $notes[] = 'Custom scope text was provided, so a consultation replaces a computed range.';
        }
        if ($unpriced !== []) {
            $notes[] = count($unpriced) . ' selection(s) have no current price rule and need manual review.';
        }

        if ($request->wantsConsultation() || $unpriced !== [] || $lines === []) {
            return new EstimateResult(
                EstimateResult::KIND_CONSULTATION,
                $lines,
                $unpriced,
                null,
                null,
                $notes,
            );
        }

        $low = 0;
        $high = 0;
        foreach ($lines as $line) {
            $low += $line->lowCents;
            $high += $line->highCents;
        }
        $notes[] = 'Range computed from ' . count($lines) . ' admin-supplied rule(s).';
        return new EstimateResult(
            EstimateResult::KIND_ESTIMATE,
            $lines,
            [],
            $low,
            $high,
            $notes,
        );
    }
}
