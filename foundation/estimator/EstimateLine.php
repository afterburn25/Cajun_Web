<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Estimator;

/** One explained contribution to an estimate range. */
final readonly class EstimateLine
{
    public function __construct(
        public string $selectionId,
        public string $label,
        public int $lowCents,
        public int $highCents,
    ) {
    }
}
