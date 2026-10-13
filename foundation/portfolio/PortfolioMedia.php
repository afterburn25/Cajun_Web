<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Portfolio;

use InvalidArgumentException;

/** A single media reference supplied by the trusted adapter; never built from request input. */
final readonly class PortfolioMedia
{
    public function __construct(
        public string $asset,
        public string $alt,
    ) {
        if (trim($asset) === '') {
            throw new InvalidArgumentException('A portfolio media item needs an asset reference.');
        }
        if (trim($alt) === '') {
            throw new InvalidArgumentException('A portfolio media item needs alternative text.');
        }
    }
}
