<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Portfolio;

use InvalidArgumentException;

/**
 * Adapter-supplied long-form presentation for one portfolio record.
 * Text fields are truthful copy inputs; this object does not generate
 * claims, metrics or endorsements.
 */
final readonly class CaseStudy
{
    /** @param list<string> $deliverables @param list<PortfolioMedia> $gallery @param list<BeforeAfterPair> $pairs */
    public function __construct(
        public string $challenge,
        public string $solution,
        public array $deliverables,
        public string $results,
        public array $gallery = [],
        public array $pairs = [],
    ) {
        foreach ($deliverables as $deliverable) {
            if (!is_string($deliverable) || trim($deliverable) === '') {
                throw new InvalidArgumentException('Case-study deliverables must be non-empty strings.');
            }
        }
        foreach ($gallery as $media) {
            if (!$media instanceof PortfolioMedia) {
                throw new InvalidArgumentException('Case-study gallery entries must be PortfolioMedia objects.');
            }
        }
        foreach ($pairs as $pair) {
            if (!$pair instanceof BeforeAfterPair) {
                throw new InvalidArgumentException('Case-study pairs must be BeforeAfterPair objects.');
            }
        }
    }
}
