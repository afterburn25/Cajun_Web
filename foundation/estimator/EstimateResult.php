<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Estimator;

use InvalidArgumentException;

/**
 * The outcome of evaluating a request against current rules.
 * `estimate` carries an explained low/high range; `consultation` means the
 * scope needs human review and carries no total. Nothing here is a quote,
 * invoice or final price.
 */
final readonly class EstimateResult
{
    public const KIND_ESTIMATE = 'estimate';
    public const KIND_CONSULTATION = 'consultation';

    /** Required truthful labeling; render this with any shown range. */
    public const DISCLAIMER = 'Estimate only — not a final quote.';

    /**
     * @param list<EstimateLine> $lineItems
     * @param list<string> $unpriced Selection ids with no current rule.
     * @param list<string> $notes Factual explanations of how the result was derived.
     */
    public function __construct(
        public string $kind,
        public array $lineItems,
        public array $unpriced,
        public ?int $lowCents,
        public ?int $highCents,
        public array $notes = [],
    ) {
        if (!in_array($kind, [self::KIND_ESTIMATE, self::KIND_CONSULTATION], true)) {
            throw new InvalidArgumentException('An estimate result needs a known kind.');
        }
        if ($kind === self::KIND_ESTIMATE) {
            if ($lineItems === [] || $lowCents === null || $highCents === null || $lowCents > $highCents) {
                throw new InvalidArgumentException('An estimate needs priced lines and a consistent range.');
            }
        } elseif ($lowCents !== null || $highCents !== null) {
            throw new InvalidArgumentException('A consultation result cannot carry a total range.');
        }
        $ids = [];
        $low = $high = 0;
        $overflow = false;
        foreach ($lineItems as $line) {
            if (!$line instanceof EstimateLine) {
                throw new InvalidArgumentException('Result lines must be EstimateLine objects.');
            }
            if (in_array($line->selectionId, $ids, true)) {
                throw new InvalidArgumentException('Result lines must have unique selection identifiers.');
            }
            $ids[] = $line->selectionId;
            if ($overflow || $line->highCents > PHP_INT_MAX - $high) {
                $overflow = true;
            } else {
                $low += $line->lowCents;
                $high += $line->highCents;
            }
        }
        $missing = [];
        foreach ($unpriced as $id) {
            if (!is_string($id) || trim($id) === '' || in_array($id, $ids, true) || in_array($id, $missing, true)) {
                throw new InvalidArgumentException('Unpriced selections must be distinct nonempty identifiers without priced lines.');
            }
            $missing[] = $id;
        }
        foreach ($notes as $note) {
            if (!is_string($note) || trim($note) === '') {
                throw new InvalidArgumentException('Result notes must be nonempty strings.');
            }
        }
        if ($kind === self::KIND_ESTIMATE && ($unpriced !== [] || $overflow || $low !== $lowCents || $high !== $highCents)) {
            throw new InvalidArgumentException('An estimate requires all selections priced and exact line-sum totals.');
        }
    }
}
