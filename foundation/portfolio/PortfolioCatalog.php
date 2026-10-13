<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Portfolio;

use InvalidArgumentException;

/**
 * Pure public-portfolio decisions over adapter-supplied records.
 * Only explicitly published items are ever returned; ordering is a total,
 * deterministic order that does not depend on input sequence.
 */
final class PortfolioCatalog
{
    /**
     * @param list<PortfolioItem> $items Adapter-supplied records in any order.
     * @return list<PortfolioItem> Published items matching the query, deterministically ordered.
     */
    public function publishedItems(array $items, ?PortfolioQuery $query = null): array
    {
        $visible = [];
        foreach ($this->validated($items) as $item) {
            if ($item->status !== PortfolioItem::STATUS_PUBLISHED) {
                continue;
            }
            if ($query !== null && !$query->matches($item)) {
                continue;
            }
            $visible[] = $item;
        }
        usort($visible, self::order(...));
        return $visible;
    }

    /**
     * Distinct categories carried by published items, sorted for a stable filter UI.
     *
     * @param list<PortfolioItem> $items
     * @return list<string>
     */
    public function publishedCategories(array $items): array
    {
        $found = [];
        foreach ($this->validated($items) as $item) {
            if ($item->status !== PortfolioItem::STATUS_PUBLISHED) {
                continue;
            }
            foreach ($item->categories as $category) {
                $found[$category] = true;
            }
        }
        $categories = array_keys($found);
        usort($categories, 'strcmp');
        return $categories;
    }

    /**
     * Exact-slug detail lookup. Unpublished or absent records return null;
     * callers render the shared not-found state instead of an unpublished page.
     */
    public function publishedBySlug(array $items, string $slug): ?PortfolioItem
    {
        if (trim($slug) === '') {
            return null;
        }
        foreach ($this->validated($items) as $item) {
            if ($item->slug === $slug && $item->status === PortfolioItem::STATUS_PUBLISHED) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Reject malformed or ambiguous adapter records rather than resolving
     * them by input order.
     *
     * @return list<PortfolioItem>
     */
    private function validated(array $items): array
    {
        $seen = [];
        foreach ($items as $item) {
            if (!$item instanceof PortfolioItem) {
                throw new InvalidArgumentException('Portfolio records must be PortfolioItem objects.');
            }
            if (in_array($item->id, $seen, true)) {
                throw new InvalidArgumentException('Conflicting duplicate portfolio identifiers are not allowed.');
            }
            $seen[] = $item->id;
        }
        return array_values($items);
    }

    /** Total order: explicit position, then slug, then id; byte-exact comparisons. */
    private static function order(PortfolioItem $a, PortfolioItem $b): int
    {
        return $a->position <=> $b->position
            ?: strcmp($a->slug, $b->slug)
            ?: strcmp($a->id, $b->id);
    }
}
