<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Domains;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * A priced domain order line for cart/checkout display. Produced only
 * by DomainPricing from configured offerings — a missing configuration
 * yields "unavailable" (null from quote()), never a zero or invented
 * price.
 */
final readonly class DomainQuote
{
    public function __construct(
        public DomainName $domain,
        public string $operation,
        public int $years,
        public int $unitCents,
        public bool $privacy,
        public int $privacyCents,
        public int $totalCents,
    ) {
    }
}
