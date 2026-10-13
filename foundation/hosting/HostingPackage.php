<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Hosting;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * HC-08 — a sellable shared-hosting package: our SKU mapped to a concrete
 * provider package type with declared limits.
 *
 * Limits are either a non-negative integer quota or `null` meaning "not
 * metered by this plan" — the model refuses the word "unlimited" and any
 * negative/empty limit, so an unbounded promise can never be stored and
 * later surfaced to a client. The provider package type is required: a SKU
 * that cannot name what it provisions is not sellable.
 *
 * @internal Roadmap HC-08; additively inserted.
 */
final readonly class HostingPackage
{
    /**
     * @param array<string,?int> $limits named quotas; null = not metered,
     *        integer = units. Negative values and empty names rejected.
     */
    public function __construct(
        public string $sku,
        public string $label,
        public string $providerPackageType,
        public string $region,
        public array $limits,
    ) {
        foreach (['sku' => $sku, 'label' => $label, 'providerPackageType' => $providerPackageType, 'region' => $region] as $f => $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException("Package {$f} must not be empty.");
            }
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $sku)) {
            throw new InvalidArgumentException("Invalid package SKU '{$sku}'.");
        }
        if ($limits === []) {
            throw new InvalidArgumentException('A package must declare at least one limit.');
        }
        foreach ($limits as $name => $value) {
            if (trim((string) $name) === '' || preg_match('/unlimited|unmetered/i', (string) $name)) {
                throw new InvalidArgumentException("Invalid limit name '{$name}'.");
            }
            if ($value !== null && (!is_int($value) || $value < 0)) {
                throw new InvalidArgumentException("Limit '{$name}' must be a non-negative integer or null (not metered).");
            }
        }
    }

    /** Declared quota for one limit; null = the plan does not meter it. */
    public function limit(string $name): ?int
    {
        return array_key_exists($name, $this->limits) ? $this->limits[$name] : null;
    }

    /** Whether an observed usage level fits inside this package. */
    public function fits(array $usage): bool
    {
        foreach ($usage as $name => $used) {
            $cap = $this->limits[$name] ?? null;
            if ($cap !== null && is_int($used) && $used > $cap) {
                return false;
            }
        }
        return true;
    }
}
