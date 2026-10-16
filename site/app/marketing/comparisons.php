<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/includes/foundation/comparisons/ComparisonFact.php';
require_once CWP_CODE_ROOT.'/includes/foundation/comparisons/ComparisonRegistry.php';
require_once CWP_CODE_ROOT.'/includes/foundation/comparisons/ComparisonSection.php';
use CajunWebPro\Foundation\Comparisons\{ComparisonFact,ComparisonRegistry,ComparisonSection};

/**
 * C-01 — the published competitor claims, registered as sourced facts.
 * Every claim carries its primary-source URL (the same links the pages
 * already cite), the actual documentation-review date and a re-verify
 * date. Claims that go past review must not keep rendering as current —
 * `comparison_publishable` gates each block and the page shows an honest
 * review note instead.
 * Provider sources last reviewed 13 October 2026 (see the page footer).
 */
function comparison_registry(): ComparisonRegistry {
    static $registry=null;if($registry!==null)return $registry;
    $s=array_column(growth_sources(),1,0);$registry=(new ComparisonRegistry())
        ->register(new ComparisonFact('wix.studio-capabilities',ComparisonFact::SUBJECT_COMPETITOR,'wix','capabilities','Wix Studio offers responsive design tools, animation, custom CSS and development capabilities.',$s['Wix Studio: design and development tools'],'2026-10-13','Wix Studio plans',ComparisonFact::EVIDENCE_PROVIDER_DOC,'2027-01-13'))
        ->register(new ComparisonFact('wix.hosting-dependence',ComparisonFact::SUBJECT_COMPETITOR,'wix','portability','A Wix site runtime depends on Wix hosting; moving a site to another host generally means rebuilding that experience.',$s['Wix: hosting and exporting a site'],'2026-10-13','All Wix plans',ComparisonFact::EVIDENCE_PROVIDER_DOC,'2027-01-13'))
        ->register(new ComparisonFact('squarespace.custom-code',ComparisonFact::SUBJECT_COMPETITOR,'squarespace','customization','Squarespace supports CSS and code-based customizations; some scripts and code-injection features depend on the plan, and it does not host arbitrary server-side PHP.',$s['Squarespace: adding custom code'],'2026-10-13','Plan-dependent',ComparisonFact::EVIDENCE_PROVIDER_DOC,'2027-01-13'))
        ->register(new ComparisonFact('squarespace.export-scope',ComparisonFact::SUBJECT_COMPETITOR,'squarespace','portability','The Squarespace XML export covers selected content, not a complete portable copy of every design or feature.',$s['Squarespace: exporting site content'],'2026-10-13','All Squarespace plans',ComparisonFact::EVIDENCE_PROVIDER_DOC,'2027-01-13'));
    return $registry;
}

/** The fair-comparison block for one provider — a section citing its facts. */
function comparison_section(string $provider): ComparisonSection {
    return match($provider){
        'wix'=>new ComparisonSection('compare-wix.fair','Builders can do good work.','A Wix site is not automatically plain or incapable.',['wix.studio-capabilities','wix.hosting-dependence']),
        'squarespace'=>new ComparisonSection('compare-squarespace.fair','Builders can do good work.','A Squarespace site can be well made and capable.',['squarespace.custom-code','squarespace.export-scope']),
        default=>throw new InvalidArgumentException('Unknown comparison provider.'),
    };
}
/** A block is publishable only while every cited fact is registered and unexpired. */
function comparison_publishable(string $provider,int $now): bool {
    return comparison_section($provider)->publishable(comparison_registry(),$now);
}
/** Resolved cited facts for rendering — only call when publishable. */
function comparison_facts(string $provider): array {
    return comparison_section($provider)->renderableFacts(comparison_registry());
}
