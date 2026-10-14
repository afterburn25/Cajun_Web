<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Comparisons;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One sourced comparison statement. C-01's acceptance requires that
 * every competitor claim carry primary-source evidence — a fact is
 * structurally invalid without a source URL, observation date and plan
 * scope, and absolutist wording is rejected outright.
 */
final readonly class ComparisonFact
{
    public const SUBJECT_COMPETITOR = 'competitor';
    public const SUBJECT_SELF = 'self';

    public const EVIDENCE_PROVIDER_DOC = 'provider-doc';
    public const EVIDENCE_PROVIDER_TERMS = 'provider-terms';
    public const EVIDENCE_OBSERVED = 'observed';
    public const EVIDENCE_INTERNAL_OFFER = 'internal-offer';

    /** Phrasing the roadmap explicitly forbids publishing. */
    private const BANNED_PATTERN = '/guarantee|#1|number one|always beats|universal(ly)? superior|best in class|outperform(s|s all)?|rank(s|ing)? first|faster than all/i';

    /**
     * @param string $subjectType SUBJECT_COMPETITOR or SUBJECT_SELF
     * @param string $provider  e.g. 'wix', 'squarespace', 'cajunwebpro'
     * @param string $aspect    e.g. 'pricing', 'portability', 'support'
     * @param string $planScope e.g. 'Business Basic', 'all plans'
     * @param string $reviewBy  ISO date after which the fact is stale
     */
    public function __construct(
        public string $id,
        public string $subjectType,
        public string $provider,
        public string $aspect,
        public string $claim,
        public string $sourceUrl,
        public string $observedAt,
        public string $planScope,
        public string $evidenceKind,
        public string $reviewBy,
    ) {
        if (!in_array($subjectType, [self::SUBJECT_COMPETITOR, self::SUBJECT_SELF], true)) {
            throw new InvalidArgumentException('Subject must be competitor or self.');
        }
        foreach (['id', 'provider', 'aspect', 'claim', 'sourceUrl', 'observedAt', 'planScope', 'evidenceKind', 'reviewBy'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('A fact needs ' . $field . '.');
            }
        }
        if (strtotime($observedAt) === false || strtotime($reviewBy) === false) {
            throw new InvalidArgumentException('observedAt and reviewBy must be parseable dates.');
        }
        if (strtotime($reviewBy) <= strtotime($observedAt)) {
            throw new InvalidArgumentException('reviewBy must be after observedAt.');
        }
        if (!in_array($evidenceKind, [self::EVIDENCE_PROVIDER_DOC, self::EVIDENCE_PROVIDER_TERMS, self::EVIDENCE_OBSERVED, self::EVIDENCE_INTERNAL_OFFER], true)) {
            throw new InvalidArgumentException('Unknown evidence kind.');
        }
        if (!preg_match('/^https:\/\//', $sourceUrl)) {
            throw new InvalidArgumentException('Fact sources must be https URLs.');
        }
        if ($subjectType === self::SUBJECT_COMPETITOR && $evidenceKind === self::EVIDENCE_INTERNAL_OFFER) {
            throw new InvalidArgumentException('Competitor claims cannot cite internal offers as evidence.');
        }
        if ($subjectType === self::SUBJECT_SELF && $evidenceKind !== self::EVIDENCE_INTERNAL_OFFER) {
            throw new InvalidArgumentException('CajunWebPro deliverables must cite internal-offer evidence — they describe what we sell, not a competitor source.');
        }
        if (preg_match(self::BANNED_PATTERN, $claim)) {
            throw new InvalidArgumentException('Absolutist or guaranteed-outcome phrasing is not publishable.');
        }
    }

    /** Facts go stale — a comparison may only render unexpired facts. */
    public function stale(int $now): bool
    {
        return $now >= strtotime($this->reviewBy);
    }
}
