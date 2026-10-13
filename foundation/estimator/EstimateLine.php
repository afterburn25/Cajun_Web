<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Estimator;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/** One explained contribution to an estimate range. */
final readonly class EstimateLine
{
    public function __construct(
        public string $selectionId,
        public string $label,
        public int $lowCents,
        public int $highCents,
    ) {
        if (trim($selectionId) === '' || trim($label) === '' || $lowCents < 0 || $lowCents > $highCents) {
            throw new InvalidArgumentException('An estimate line needs an identifier, label and nonnegative ordered range.');
        }
    }
}
