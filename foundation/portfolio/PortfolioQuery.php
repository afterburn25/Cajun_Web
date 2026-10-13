<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Portfolio;

/**
 * A public portfolio browsing request after normalization.
 * An empty selection means unfiltered; unknown categories simply match nothing.
 */
final readonly class PortfolioQuery
{
    /** @var list<string> */
    public array $categories;

    /**
     * @param list<string> $categories Selected category slugs; blank entries are dropped.
     */
    public function __construct(array $categories = [])
    {
        $selected = [];
        foreach ($categories as $category) {
            if (!is_string($category)) {
                continue;
            }
            $category = trim($category);
            if ($category !== '' && !in_array($category, $selected, true)) {
                $selected[] = $category;
            }
        }
        $this->categories = $selected;
    }

    /** Convenience for a single-category browse; null/blank means unfiltered. */
    public static function forCategory(?string $category): self
    {
        return new self($category === null ? [] : [$category]);
    }

    public function isUnfiltered(): bool
    {
        return $this->categories === [];
    }

    /** Exact match: an item is selected when it carries at least one chosen category. */
    public function matches(PortfolioItem $item): bool
    {
        if ($this->isUnfiltered()) {
            return true;
        }
        foreach ($item->categories as $category) {
            if (in_array($category, $this->categories, true)) {
                return true;
            }
        }
        return false;
    }
}
