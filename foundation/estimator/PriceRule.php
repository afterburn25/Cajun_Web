<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Estimator;

use InvalidArgumentException;

/**
 * One admin-editable pricing rule supplied by the trusted adapter.
 * Amounts are integer minor units (e.g. cents); rules are inputs, never
 * embedded in code, so the component cannot invent prices.
 */
final readonly class PriceRule
{
    public function __construct(
        public string $selectionId,
        public string $label,
        public int $lowCents,
        public int $highCents,
    ) {
        if (trim($selectionId) === '') {
            throw new InvalidArgumentException('A price rule needs a selection identifier.');
        }
        if (trim($label) === '') {
            throw new InvalidArgumentException('A price rule needs a display label.');
        }
        if ($lowCents < 0 || $highCents < 0) {
            throw new InvalidArgumentException('Price rules cannot carry negative amounts.');
        }
        if ($lowCents > $highCents) {
            throw new InvalidArgumentException('A price rule range must be low then high.');
        }
    }
}
