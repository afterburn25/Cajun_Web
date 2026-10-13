<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Portfolio;

use InvalidArgumentException;

/**
 * An immutable portfolio record snapshot built by a trusted application adapter.
 * Fields are adapter inputs, not assumed database column names; the adapter maps
 * the real project/media storage to this contract after the current source is
 * inspected. This object does not read a database or authenticate a request.
 */
final readonly class PortfolioItem
{
    /** Real delivered work shown under its own truthful label. */
    public const KIND_CLIENT_WORK = 'client-work';
    /** Demonstration material that must be labeled as a concept, not client work. */
    public const KIND_CONCEPT = 'concept';

    /** Explicitly published; the only status that may appear in public output. */
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * @param list<string> $categories Exact category slugs; empty means uncategorized.
     * @param int $position Explicit display weight supplied by the adapter; lower sorts first.
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public string $summary,
        public string $kind,
        public string $status,
        public array $categories = [],
        public int $position = 0,
        public ?string $liveUrl = null,
        public ?PortfolioMedia $cover = null,
        public ?CaseStudy $caseStudy = null,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('A portfolio item needs an identifier.');
        }
        if (trim($slug) === '') {
            throw new InvalidArgumentException('A portfolio item needs a slug.');
        }
        if (trim($title) === '') {
            throw new InvalidArgumentException('A portfolio item needs a title.');
        }
        if (!in_array($kind, [self::KIND_CLIENT_WORK, self::KIND_CONCEPT], true)) {
            throw new InvalidArgumentException('A portfolio item needs an honest kind label.');
        }
        if (!in_array($status, [self::STATUS_PUBLISHED, self::STATUS_DRAFT, self::STATUS_ARCHIVED], true)) {
            throw new InvalidArgumentException('A portfolio item needs a known status.');
        }
        if ($liveUrl !== null && trim($liveUrl) === '') {
            throw new InvalidArgumentException('A live URL must be a non-empty string or null.');
        }

        $seen = [];
        foreach ($categories as $category) {
            if (!is_string($category) || trim($category) === '') {
                throw new InvalidArgumentException('Portfolio categories must be non-empty strings.');
            }
            if (in_array($category, $seen, true)) {
                throw new InvalidArgumentException('Duplicate categories on one item are not allowed.');
            }
            $seen[] = $category;
        }
    }
}
