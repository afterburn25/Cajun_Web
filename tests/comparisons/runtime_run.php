<?php
declare(strict_types=1);

/**
 * C-01 runtime-adapter checks: the published comparison registry itself.
 * Source URLs here are fixture stand-ins via the growth_sources stub —
 * the checks verify structure, governance and staleness, not live pages.
 */

define('CWP_BOOT', true);
define('CWP_CODE_ROOT', __DIR__.'/../../site');

function growth_sources(): array {
    return [
        ['Wix: hosting and exporting a site', 'https://example.invalid/wix-export'],
        ['Wix Studio: design and development tools', 'https://example.invalid/wix-studio'],
        ['Squarespace: adding custom code', 'https://example.invalid/sq-code'],
        ['Squarespace: exporting site content', 'https://example.invalid/sq-export'],
    ];
}

require __DIR__.'/../../site/app/marketing/comparisons.php';

use CajunWebPro\Foundation\Comparisons\{ComparisonFact,ComparisonRegistry,ComparisonSection};

$checks = 0;
function c_ok(string $name, bool $passed): void {
    global $checks; $checks++;
    if (!$passed) { fwrite(STDERR, 'FAIL: '.$name."\n"); exit(1); }
}
function c_deny(string $name, callable $fn): void {
    try { $fn(); } catch (InvalidArgumentException|LogicException|RuntimeException) { c_ok($name, true); return; }
    c_ok($name, false);
}

$now = time();
$registry = comparison_registry();
c_ok('Registry builds and is memoized', $registry instanceof ComparisonRegistry && comparison_registry() === $registry);
c_ok('Every provider carries its two published facts', count($registry->forProvider('wix')) === 2 && count($registry->forProvider('squarespace')) === 2);
c_ok('Unknown providers hold no facts', $registry->forProvider('cajunwebpro') === [] && $registry->forProvider('other') === []);

$all = array_merge($registry->forProvider('wix'), $registry->forProvider('squarespace'));
foreach ($all as $fact) {
    c_ok('Fact '.$fact->id.' cites the mapped https source', str_starts_with($fact->sourceUrl, 'https://example.invalid/'));
    c_ok('Fact '.$fact->id.' carries documented review dates', $fact->observedAt === '2026-10-13' && strtotime($fact->reviewBy) > strtotime($fact->observedAt));
    c_ok('Fact '.$fact->id.' is currently fresh', !$fact->stale($now));
}

foreach (['wix', 'squarespace'] as $provider) {
    $section = comparison_section($provider);
    c_ok($provider.' section resolves both cited facts', count($section->renderableFacts($registry)) === 2);
    c_ok($provider.' section is publishable today', comparison_publishable($provider, $now) && $section->staleCitations($registry, $now) === []);
    c_ok($provider.' facts render in citation order', $section->renderableFacts($registry)[0]->aspect === ($provider === 'wix' ? 'capabilities' : 'customization'));
}
c_deny('Unknown provider section is refused', fn() => comparison_section('joomla'));

$future = strtotime('2027-06-01');
c_ok('Sections become unpublished past their review dates', !comparison_publishable('wix', $future) && !comparison_publishable('squarespace', $future));
c_ok('Stale citations are enumerated for review', comparison_section('wix')->staleCitations($registry, $future) === ['wix.studio-capabilities', 'wix.hosting-dependence']);

echo 'comparisons runtime: '.$checks.' checks, 0 failures'."\n";
