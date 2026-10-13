<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Domains;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * Computes quotes from configured TLD offerings. Honest-availability
 * rules: unknown TLDs, unconfigured prices, out-of-range year counts
 * and privacy on unsupported TLDs all produce null rather than a
 * guessed price.
 */
final class DomainPricing
{
    /** @var array<string,TldOffering> */
    private array $offerings = [];

    /**
     * @param TldOffering[] $offerings Duplicate TLDs are rejected rather
     *        than resolved by input order.
     */
    public function __construct(array $offerings)
    {
        foreach ($offerings as $offering) {
            if (isset($this->offerings[$offering->tld])) {
                throw new InvalidArgumentException('Duplicate TLD offering: ' . $offering->tld);
            }
            $this->offerings[$offering->tld] = $offering;
        }
    }

    public function offered(string $tld): bool
    {
        return isset($this->offerings[$tld]);
    }

    /**
     * Quote one domain operation, or null when it cannot be honestly
     * priced. Privacy is included only when requested AND supported AND
     * priced (a null privacy price means privacy is included free).
     */
    public function quote(DomainName $domain, string $operation, int $years, bool $privacy = false): ?DomainQuote
    {
        $offering = $this->offerings[$domain->tld] ?? null;
        if ($offering === null || !TldOffering::validOperation($operation)) {
            return null;
        }
        if ($years < $offering->minYears || $years > $offering->maxYears) {
            return null;
        }
        $unit = $offering->priceFor($operation);
        if ($unit === null) {
            return null;
        }
        $privacyCents = 0;
        if ($privacy) {
            if (!$offering->privacySupported) {
                return null;
            }
            $privacyCents = ($offering->privacyCents ?? 0) * $years;
        }
        return new DomainQuote(
            domain: $domain,
            operation: $operation,
            years: $years,
            unitCents: $unit,
            privacy: $privacy,
            privacyCents: $privacyCents,
            totalCents: $unit * $years + $privacyCents,
        );
    }
}
