<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Portfolio;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * A genuine paired redesign capture. Both sides must exist and reference
 * different assets; a single capture is PortfolioMedia, not a pair.
 */
final readonly class BeforeAfterPair
{
    public function __construct(
        public PortfolioMedia $before,
        public PortfolioMedia $after,
    ) {
        if ($before->asset === $after->asset) {
            throw new InvalidArgumentException('A before/after pair needs two different asset references.');
        }
    }
}
