<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Portfolio\BeforeAfterPair;
use CajunWebPro\Foundation\Portfolio\CaseStudy;
use CajunWebPro\Foundation\Portfolio\PortfolioCatalog;
use CajunWebPro\Foundation\Portfolio\PortfolioItem;
use CajunWebPro\Foundation\Portfolio\PortfolioMedia;
use CajunWebPro\Foundation\Portfolio\PortfolioQuery;

require __DIR__ . '/../../foundation/portfolio/PortfolioMedia.php';
require __DIR__ . '/../../foundation/portfolio/BeforeAfterPair.php';
require __DIR__ . '/../../foundation/portfolio/CaseStudy.php';
require __DIR__ . '/../../foundation/portfolio/PortfolioItem.php';
require __DIR__ . '/../../foundation/portfolio/PortfolioQuery.php';
require __DIR__ . '/../../foundation/portfolio/PortfolioCatalog.php';

$checks = 0;
$failures = [];
$catalog = new PortfolioCatalog();

function item(array $changes = []): PortfolioItem
{
    return new PortfolioItem(...array_replace([
        'id' => 'fx-item-1',
        'slug' => 'fx-item-one',
        'title' => 'Fixture Item One',
        'summary' => 'Synthetic fixture summary.',
        'kind' => PortfolioItem::KIND_CONCEPT,
        'status' => PortfolioItem::STATUS_PUBLISHED,
        'categories' => ['fx-cat-a'],
        'position' => 0,
        'liveUrl' => null,
        'cover' => null,
        'caseStudy' => null,
    ], $changes));
}

function expect(string $name, mixed $expected, mixed $actual): void
{
    global $checks, $failures;
    $checks++;
    if ($actual !== $expected) {
        $failures[] = $name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
    }
}

function rejects(string $name, callable $action): void
{
    global $checks, $failures;
    $checks++;
    try {
        $action();
        $failures[] = $name . ': invalid input was accepted';
    } catch (InvalidArgumentException) {
        // Expected failure; other exceptions must fail the runner.
    }
}

function ids(array $items): array
{
    return array_map(fn(PortfolioItem $item) => $item->id, $items);
}

// Synthetic fixture set; identifiers and titles are deliberately artificial.
$fixtures = [
    item(['id' => 'fx-delta', 'slug' => 'fx-delta', 'position' => 10, 'categories' => ['fx-cat-b']]),
    item(['id' => 'fx-beta', 'slug' => 'fx-beta', 'position' => 5, 'categories' => ['fx-cat-a', 'fx-cat-b']]),
    item(['id' => 'fx-alpha', 'slug' => 'fx-alpha', 'position' => 5, 'categories' => ['fx-cat-a']]),
    item(['id' => 'fx-draft', 'slug' => 'fx-draft', 'status' => PortfolioItem::STATUS_DRAFT, 'categories' => ['fx-cat-a']]),
    item(['id' => 'fx-archived', 'slug' => 'fx-archived', 'status' => PortfolioItem::STATUS_ARCHIVED, 'position' => 1]),
    item(['id' => 'fx-gamma', 'slug' => 'fx-gamma', 'position' => 5, 'categories' => []]),
];

// Visibility: only published records ever appear in public output.
expect('unfiltered hides drafts and archived', ['fx-alpha', 'fx-beta', 'fx-gamma', 'fx-delta'], ids($catalog->publishedItems($fixtures)));
expect('unpublished with matching category stays hidden', ['fx-alpha', 'fx-beta'], ids($catalog->publishedItems($fixtures, new PortfolioQuery(['fx-cat-a']))));

// Ordering: position ascending, slug then id as deterministic tie-breakers.
expect('position then slug ordering', ['fx-alpha', 'fx-beta', 'fx-gamma', 'fx-delta'], ids($catalog->publishedItems([
    item(['id' => 'fx-delta', 'slug' => 'fx-delta', 'position' => 10]),
    item(['id' => 'fx-alpha', 'slug' => 'fx-alpha', 'position' => 5]),
    item(['id' => 'fx-beta', 'slug' => 'fx-beta', 'position' => 5]),
    item(['id' => 'fx-gamma', 'slug' => 'fx-gamma', 'position' => 5]),
])));
expect('input order does not change output', ids($catalog->publishedItems($fixtures)), ids($catalog->publishedItems(array_reverse($fixtures))));
expect('id breaks identical position and slug ties', ['fx-001', 'fx-002'], ids($catalog->publishedItems([
    item(['id' => 'fx-002', 'slug' => 'fx-same', 'position' => 0]),
    item(['id' => 'fx-001', 'slug' => 'fx-same', 'position' => 0]),
])));

// Category filtering: empty selection is unfiltered; unknown categories match nothing.
expect('single category filter', ['fx-beta', 'fx-delta'], ids($catalog->publishedItems($fixtures, PortfolioQuery::forCategory('fx-cat-b'))));
expect('multi-category filter unions matches', ['fx-alpha', 'fx-beta', 'fx-delta'], ids($catalog->publishedItems($fixtures, new PortfolioQuery(['fx-cat-a', 'fx-cat-b']))));
expect('unknown category yields empty result', [], $catalog->publishedItems($fixtures, PortfolioQuery::forCategory('fx-unknown')));
expect('blank category means unfiltered', true, PortfolioQuery::forCategory('   ')->isUnfiltered());
expect('null category means unfiltered', true, PortfolioQuery::forCategory(null)->isUnfiltered());
expect('empty selection means unfiltered', true, (new PortfolioQuery([]))->isUnfiltered());
expect('blank and duplicate selections normalize', ['fx-cat-a'], (new PortfolioQuery([' fx-cat-a ', 'fx-cat-a', '']))->categories);
expect('non-string selections are dropped', ['fx-cat-a'], (new PortfolioQuery(['fx-cat-a', 7, null]))->categories);
expect('category match is exact not case-folded', [], $catalog->publishedItems($fixtures, PortfolioQuery::forCategory('FX-CAT-A')));
expect('uncategorized published items still appear unfiltered', true, in_array('fx-gamma', ids($catalog->publishedItems($fixtures)), true));

// Filter-source list: distinct published categories, sorted, excluding hidden items.
expect('published categories are distinct and sorted', ['fx-cat-a', 'fx-cat-b'], $catalog->publishedCategories([
    item(['id' => 'fx-1', 'categories' => ['fx-cat-b', 'fx-cat-a']]),
    item(['id' => 'fx-2', 'categories' => ['fx-cat-a']]),
    item(['id' => 'fx-3', 'status' => PortfolioItem::STATUS_DRAFT, 'categories' => ['fx-hidden']]),
]));

// Detail lookup by exact slug; hidden and absent slugs resolve to null.
expect('published slug resolves', 'fx-beta', $catalog->publishedBySlug($fixtures, 'fx-beta')?->id);
expect('draft slug resolves to null', null, $catalog->publishedBySlug($fixtures, 'fx-draft'));
expect('unknown slug resolves to null', null, $catalog->publishedBySlug($fixtures, 'fx-missing'));
expect('blank slug resolves to null', null, $catalog->publishedBySlug($fixtures, '  '));
expect('slug match is exact not case-folded', null, $catalog->publishedBySlug($fixtures, 'FX-BETA'));

// Truthful labels survive filtering: kind is part of the record, never inferred.
$labeled = [
    item(['id' => 'fx-real', 'kind' => PortfolioItem::KIND_CLIENT_WORK, 'categories' => ['fx-cat-a']]),
    item(['id' => 'fx-concept', 'kind' => PortfolioItem::KIND_CONCEPT, 'categories' => ['fx-cat-a']]),
];
$returned = $catalog->publishedItems($labeled, PortfolioQuery::forCategory('fx-cat-a'));
expect('concept label preserved', PortfolioItem::KIND_CONCEPT, $returned[0]->kind);
expect('client-work label preserved', PortfolioItem::KIND_CLIENT_WORK, $returned[1]->kind);

// Contract validation: malformed records and ambiguous identifiers are rejected.
rejects('empty identifier', fn() => item(['id' => '  ']));
rejects('empty slug', fn() => item(['slug' => '']));
rejects('empty title', fn() => item(['title' => '']));
rejects('unknown kind label', fn() => item(['kind' => 'real-client']));
rejects('inflated kind label', fn() => item(['kind' => 'featured-client']));
rejects('unknown status', fn() => item(['status' => 'live']));
rejects('non-string category', fn() => item(['categories' => [42]]));
rejects('empty category', fn() => item(['categories' => ['']] ));
rejects('duplicate categories on one item', fn() => item(['categories' => ['fx-cat-a', 'fx-cat-a']]));
rejects('empty live URL string', fn() => item(['liveUrl' => ' ']));
rejects('media without asset', fn() => new PortfolioMedia('', 'alt'));
rejects('media without alt text', fn() => new PortfolioMedia('fx.png', ''));
rejects('same-asset before/after pair', fn() => new BeforeAfterPair(
    new PortfolioMedia('fx.png', 'before'), new PortfolioMedia('fx.png', 'after'),
));
rejects('non-item in catalog', fn() => $catalog->publishedItems([item(), 'fx-string']));
rejects('duplicate identifiers in catalog', fn() => $catalog->publishedItems([item(), item()]));
rejects('non-string deliverable', fn() => new CaseStudy('c', 's', [true], 'r'));
rejects('empty deliverable', fn() => new CaseStudy('c', 's', ['  '], 'r'));
rejects('bad gallery entry', fn() => new CaseStudy('c', 's', [], 'r', ['fx.png']));
rejects('bad pair entry', fn() => new CaseStudy('c', 's', [], 'r', [], [new PortfolioMedia('a.png', 'a')]));

// Paired media: genuine pairs carry both sides; a lone capture is single media.
$pair = new BeforeAfterPair(new PortfolioMedia('fx-before.webp', 'Before fixture'), new PortfolioMedia('fx-after.webp', 'After fixture'));
$study = new CaseStudy('Challenge fixture.', 'Solution fixture.', ['Deliverable fixture'], 'Results fixture.', [], [$pair]);
$detailed = item(['id' => 'fx-study', 'caseStudy' => $study]);
expect('case study is carried on the record', $study, $catalog->publishedBySlug([$detailed], 'fx-item-one')?->caseStudy);
expect('pair exposes both captures', 'fx-after.webp', $pair->after->asset);
expect('nullable live URL accepted', null, item()->liveUrl);
expect('live URL kept verbatim', 'https://fixture.invalid/', item(['liveUrl' => 'https://fixture.invalid/'])->liveUrl);

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "PASS: $checks portfolio catalog checks on PHP " . PHP_VERSION . PHP_EOL;
echo "Scope: isolated contract/filtering/ordering checks only; no database, routes, UI, installer or 1.1.0 application integration tested." . PHP_EOL;
