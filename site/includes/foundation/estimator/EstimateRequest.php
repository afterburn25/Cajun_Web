<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Estimator;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * A normalized public estimator submission. Selection identifiers come from
 * the request after server-side validation against the adapter's known
 * options; they are never treated as prices or persisted data.
 */
final readonly class EstimateRequest
{
    /** @var list<string> */
    public array $options;

    /**
     * @param string $serviceType Required project/service selection id.
     * @param ?string $sizeBand Optional approximate size selection id.
     * @param list<string> $options Additional selected option ids.
     * @param ?string $timing Optional timing-preference selection id.
     * @param ?string $customScope Free-text custom scope; its presence routes the result to consultation.
     */
    public function __construct(
        public string $serviceType,
        public ?string $sizeBand = null,
        array $options = [],
        public ?string $timing = null,
        public ?string $customScope = null,
    ) {
        if (trim($serviceType) === '') {
            throw new InvalidArgumentException('An estimate request needs a service type.');
        }
        foreach (['sizeBand' => $sizeBand, 'timing' => $timing] as $field => $value) {
            if ($value !== null && trim($value) === '') {
                throw new InvalidArgumentException("An estimate request's {$field} must be non-empty or null.");
            }
        }

        $seen = [];
        foreach ($options as $option) {
            if (!is_string($option) || trim($option) === '') {
                throw new InvalidArgumentException('Estimate options must be non-empty strings.');
            }
            if (!in_array($option, $seen, true)) {
                $seen[] = $option;
            }
        }
        $this->options = $seen;
    }

    /**
     * Every priced selection id in request order, deduplicated so a repeated
     * id cannot be charged twice.
     *
     * @return list<string>
     */
    public function selections(): array
    {
        $selections = [$this->serviceType];
        if ($this->sizeBand !== null) {
            $selections[] = $this->sizeBand;
        }
        foreach ($this->options as $option) {
            $selections[] = $option;
        }
        if ($this->timing !== null) {
            $selections[] = $this->timing;
        }
        return array_values(array_unique($selections));
    }

    public function wantsConsultation(): bool
    {
        // Whitespace-only text carries no scope and does not force consultation.
        return $this->customScope !== null && trim($this->customScope) !== '';
    }
}
