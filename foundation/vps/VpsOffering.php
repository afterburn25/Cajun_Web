<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Vps;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One sellable VPS configuration with an admin-configured monthly
 * price. A null price means the offering exists for comparison but is
 * not orderable — the component cannot invent provider costs.
 */
final readonly class VpsOffering
{
    public function __construct(
        public string $slug,
        public string $label,
        public VpsSpec $spec,
        public ?int $monthlyCents,
    ) {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $slug)) {
            throw new InvalidArgumentException('Offering slug must be lowercase letters, digits, hyphens.');
        }
        if (trim($label) === '') {
            throw new InvalidArgumentException('Offering needs a display label.');
        }
        if ($monthlyCents !== null && $monthlyCents < 0) {
            throw new InvalidArgumentException('Offering price cannot be negative.');
        }
    }

    /** Whether this offering can be ordered (a configured price exists). */
    public function orderable(): bool
    {
        return $this->monthlyCents !== null;
    }
}
